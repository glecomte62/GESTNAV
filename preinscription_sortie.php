<?php
require 'header.php';
require_login();

$sortie_id = isset($_GET['sortie_id']) ? (int)$_GET['sortie_id'] : 0;
$user_id   = (int)($_SESSION['user_id'] ?? 0);

if ($sortie_id <= 0 || $user_id <= 0) {
    http_response_code(400);
    echo '<div class="container mt-4"><div class="alert alert-danger">Paramètres manquants.</div></div>';
    require 'footer.php';
    exit;
}

// S'assurer que la table des préinscriptions existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sortie_preinscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sortie_id INT NOT NULL,
        user_id INT NOT NULL,
        preferred_machine_id INT NULL,
        preferred_coequipier_user_id INT NULL,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sortie_user (sortie_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    // silencieux; on continue, l'INSERT échouera si impossible
}

// Charger la sortie
$stmt = $pdo->prepare('SELECT * FROM sorties WHERE id = ? LIMIT 1');
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sortie) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Sortie introuvable.</div></div>';
    require 'footer.php';
    exit;
}

// Récupérer machines de la sortie
$machines = [];
try {
    $stmtM = $pdo->prepare('SELECT sm.machine_id, m.nom FROM sortie_machines sm JOIN machines m ON m.id=sm.machine_id WHERE sm.sortie_id = ? ORDER BY m.nom');
    $stmtM->execute([$sortie_id]);
    $machines = $stmtM->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $machines = []; }

// Récupérer liste des membres actifs (pour coéquipier)
$users = [];
try {
    $stmtU = $pdo->query('SELECT id, prenom, nom, email FROM users WHERE actif=1 ORDER BY nom, prenom LIMIT 1000');
    $users = $stmtU->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $users = []; }

$message = '';
$error = false;
$done  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_pref') {
    $pref_machine = isset($_POST['preferred_machine_id']) ? (int)$_POST['preferred_machine_id'] : 0;
    $pref_coeq    = isset($_POST['preferred_coequipier_user_id']) ? (int)$_POST['preferred_coequipier_user_id'] : 0;
    $notes        = trim($_POST['notes'] ?? '');

    if ($pref_machine < 0) $pref_machine = 0;
    if ($pref_coeq < 0)    $pref_coeq    = 0;

    try {
        $sql = 'INSERT INTO sortie_preinscriptions (sortie_id, user_id, preferred_machine_id, preferred_coequipier_user_id, notes)
                VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE preferred_machine_id=VALUES(preferred_machine_id), preferred_coequipier_user_id=VALUES(preferred_coequipier_user_id), notes=VALUES(notes)';
        $stmtI = $pdo->prepare($sql);
        $stmtI->execute([$sortie_id, $user_id, ($pref_machine ?: null), ($pref_coeq ?: null), ($notes !== '' ? $notes : null)]);

        // S'assurer que l'utilisateur figure aussi dans la liste des inscrits
        try {
            $chk = $pdo->prepare('SELECT 1 FROM sortie_inscriptions WHERE sortie_id = ? AND user_id = ?');
            $chk->execute([$sortie_id, $user_id]);
            if (!$chk->fetchColumn()) {
                $token = bin2hex(random_bytes(32));
                $ins = $pdo->prepare('INSERT INTO sortie_inscriptions (sortie_id, user_id, action_token) VALUES (?,?,?)');
                $ins->execute([$sortie_id, $user_id, $token]);
            }
        } catch (Throwable $e) { /* silencieux */ }

        $done = true;
        $message = "Votre préférence a été enregistrée en tant que pré-inscription. L'équipe organisatrice effectuera la répartition définitive en fonction de l'expérience de chacun et des élèves pilotes à intégrer.";
    } catch (Throwable $e) {
        $error = true;
        $message = 'Impossible d\'enregistrer votre préférence pour le moment.';
    }
}

?>

<div class="container mt-4" style="max-width: 820px;">
    <a href="sorties.php" class="btn btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Retour aux sorties
    </a>

    <div class="card">
        <div class="card-body">
            <h2 class="h4 mb-2">Pré-inscription à la sortie</h2>
            <p class="text-muted mb-2" style="line-height:1.6;">
                Merci d'indiquer votre préférence pour une machine et/ou un coéquipier. Il s'agit d'une <strong>pré-inscription</strong> :
                la <strong>validation définitive</strong> sera effectuée par un administrateur en tenant compte de l'expérience de chacun et de
                l'intégration éventuelle d'élèves pilotes. Nous ferons au mieux pour satisfaire les préférences.
            </p>
            <p class="text-muted" style="line-height:1.6;">
                <em>Note organisation&nbsp;:</em> le club vise <strong>2 sorties par mois</strong>. Les membres inscrits aux deux sorties qui n'ont pas pu
                participer à la première sont <strong>prioritaires sur la seconde</strong>, sous réserve de s'y être inscrits.
            </p>

            <div class="p-3 mb-3" style="background:#f5f9fc;border:1px solid #d0d7e2;border-radius:0.75rem;">
                <div><strong>Sortie :</strong> <?= htmlspecialchars($sortie['titre'] ?? '') ?></div>
                <div><strong>Date :</strong> <?= htmlspecialchars($sortie['date_sortie'] ?? '') ?></div>
                <?php if (!empty($sortie['description'])): ?>
                    <div class="mt-1"><strong>Description :</strong> <?= htmlspecialchars($sortie['description']) ?></div>
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
                <div class="alert <?= $error ? 'alert-danger' : 'alert-success' ?>">><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!$done): ?>
            <form method="post">
                <input type="hidden" name="action" value="save_pref">

                <div class="mb-3">
                    <label class="form-label">Préférence machine (optionnel)</label>
                    <select class="form-select" name="preferred_machine_id">
                        <option value="0">Aucune préférence</option>
                        <?php foreach ($machines as $m): ?>
                            <option value="<?= (int)$m['machine_id'] ?>"><?= htmlspecialchars($m['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Préférence coéquipier (optionnel)</label>
                    <select class="form-select" name="preferred_coequipier_user_id">
                        <option value="0">Aucun</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?><?= $u['email'] ? (' — ' . htmlspecialchars($u['email'])) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Notes (optionnel)</label>
                    <textarea name="notes" rows="3" class="form-control" placeholder="Précisez éventuellement votre expérience, contraintes, ou toute information utile."></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">Enregistrer ma pré-inscription</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
