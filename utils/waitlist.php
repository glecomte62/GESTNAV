<?php
// Utils: waitlist handling for sorties
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../mail_helper.php';

/**
 * Get waitlist users for a sortie: inscriptions not assigned on any machine for this sortie.
 * Ordered by inscription id ascending (proxy for arrival time).
 * @return array each row: [user_id, prenom, nom, email, inscription_id]
 */
function gestnav_get_waitlist(PDO $pdo, int $sortie_id): array {
    $sql = "
        SELECT si.id AS inscription_id, u.id AS user_id, u.prenom, u.nom, u.email
        FROM sortie_inscriptions si
        JOIN users u ON u.id = si.user_id
        LEFT JOIN (
            SELECT DISTINCT sa.user_id
            FROM sortie_assignations sa
            JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id
            WHERE sm.sortie_id = :sid1
        ) assigned ON assigned.user_id = si.user_id
        WHERE si.sortie_id = :sid2
          AND assigned.user_id IS NULL
        ORDER BY si.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':sid1' => $sortie_id, ':sid2' => $sortie_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Promote users from waitlist to free seats. Seats per machine = 2.
 * Returns array of notifications for promoted users with machine info.
 * @return array list of ['user'=>[id,prenom,nom,email],'machine'=>[nom,immat]]
 */
function gestnav_auto_promote_waitlist(PDO $pdo, int $sortie_id): array {
    // Build seat availability by machine
    $stmt = $pdo->prepare("SELECT sm.id AS sm_id, m.nom, m.immatriculation FROM sortie_machines sm JOIN machines m ON m.id = sm.machine_id WHERE sm.sortie_id = ?");
    $stmt->execute([$sortie_id]);
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$machines) return [];

    // Count current assignments per machine
    $stmt = $pdo->prepare("SELECT sa.sortie_machine_id, COUNT(*) AS c FROM sortie_assignations sa JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id WHERE sm.sortie_id = ? GROUP BY sa.sortie_machine_id");
    $stmt->execute([$sortie_id]);
    $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // sm_id => count

    $waitlist = gestnav_get_waitlist($pdo, $sortie_id);
    if (!$waitlist) return [];

    $notifications = [];

    try {
        $pdo->beginTransaction();

        foreach ($machines as $m) {
            $sm_id = (int)$m['sm_id'];
            $current = isset($counts[$sm_id]) ? (int)$counts[$sm_id] : 0;
            $capacity = 2;
            $free = $capacity - $current;
            while ($free > 0 && !empty($waitlist)) {
                $next = array_shift($waitlist);
                $user_id = (int)$next['user_id'];
                // Double-check not already assigned (race condition guard)
                $chk = $pdo->prepare("SELECT 1 FROM sortie_assignations WHERE sortie_machine_id=? AND user_id=?");
                $chk->execute([$sm_id, $user_id]);
                if ($chk->fetch()) {
                    continue; // pick next
                }
                $ins = $pdo->prepare("INSERT INTO sortie_assignations (sortie_machine_id, user_id, role_onboard) VALUES (?,?,?)");
                $ins->execute([$sm_id, $user_id, 'passager']);
                $free--;
                $notifications[] = [
                    'user' => [
                        'id' => $user_id,
                        'prenom' => $next['prenom'],
                        'nom' => $next['nom'],
                        'email' => $next['email'],
                    ],
                    'machine' => [
                        'nom' => $m['nom'],
                        'immat' => $m['immatriculation']
                    ]
                ];
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [];
    }

    return $notifications;
}

/**
 * Notify promoted users by email. Call after gestnav_auto_promote_waitlist.
 */
function gestnav_notify_promoted(PDO $pdo, int $sortie_id, array $promoted): void {
    if (!$promoted) return;

    // Fetch sortie info
    $s = $pdo->prepare("SELECT titre, date_sortie FROM sorties WHERE id=?");
    $s->execute([$sortie_id]);
    $sortie = $s->fetch(PDO::FETCH_ASSOC);

    foreach ($promoted as $p) {
        if (empty($p['user']['email'])) continue;
        $to = [$p['user']['email'] => trim(($p['user']['prenom'] ?? '') . ' ' . ($p['user']['nom'] ?? ''))];
        $subject = "Sortie ULM – Place confirmée: " . ($sortie['titre'] ?? '');
        $machine_str = trim(($p['machine']['nom'] ?? '') . ' ' . ($p['machine']['immat'] ? '(' . $p['machine']['immat'] . ')' : ''));

        $html = '<p>Bonne nouvelle, une place s\'est libérée sur la sortie <strong>' . htmlspecialchars($sortie['titre'] ?? '') . '</strong> (\n        ' . htmlspecialchars($sortie['date_sortie'] ?? '') . ').</p>' .
                '<p>Vous avez été automatiquement affecté(e) sur la machine <strong>' . htmlspecialchars($machine_str) . '</strong>.</p>' .
                '<p>Rendez-vous sur GestNav pour consulter les détails.</p>';
        $text = "Une place s'est libérée sur la sortie " . ($sortie['titre'] ?? '') . " (" . ($sortie['date_sortie'] ?? '') . ").\n" .
                "Vous êtes affecté(e) automatiquement sur la machine " . $machine_str . ".";

        gestnav_send_mail($pdo, $to, $subject, $html, $text);
    }
}

/**
 * Notify co-occupants when a user cancels.
 */
function gestnav_notify_copilots_on_cancel(PDO $pdo, int $sortie_id, int $canceled_user_id): void {
    // Find machines where canceled user was assigned, then the other person on that machine
    $sql = "
        SELECT u.email, u.prenom, u.nom, m.nom AS machine_nom, m.immatriculation, s.titre, s.date_sortie
        FROM sortie_assignations sa_cancel
        JOIN sortie_machines sm ON sm.id = sa_cancel.sortie_machine_id
        JOIN machines m ON m.id = sm.machine_id
        JOIN sorties s ON s.id = sm.sortie_id
        JOIN sortie_assignations sa_other ON sa_other.sortie_machine_id = sa_cancel.sortie_machine_id AND sa_other.user_id <> sa_cancel.user_id
        JOIN users u ON u.id = sa_other.user_id
        WHERE sm.sortie_id = ? AND sa_cancel.user_id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sortie_id, $canceled_user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        if (empty($r['email'])) continue;
        $to = [$r['email'] => trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? ''))];
        $subject = "Sortie ULM – Désistement sur votre équipage";
        $machine_str = trim(($r['machine_nom'] ?? '') . ' ' . ($r['immatriculation'] ? '(' . $r['immatriculation'] . ')' : ''));
        $html = '<p>Information: un membre de votre équipage s\'est désisté pour la sortie <strong>' . htmlspecialchars($r['titre'] ?? '') . '</strong> (' . htmlspecialchars($r['date_sortie'] ?? '') . ').</p>' .
                '<p>Machine: <strong>' . htmlspecialchars($machine_str) . '</strong>.</p>' .
                '<p>Nous cherchons un remplaçant automatiquement à partir de la liste d\'attente.</p>';
        $text = "Un membre de votre équipage s'est désisté pour la sortie " . ($r['titre'] ?? '') . ", machine: " . $machine_str . ".";
        gestnav_send_mail($pdo, $to, $subject, $html, $text);
    }
}
