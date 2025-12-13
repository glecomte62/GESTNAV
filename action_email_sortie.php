<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_once 'mail_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$sortie_id = isset($_POST['sortie_id']) ? (int)$_POST['sortie_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($sortie_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Sortie invalide.']);
    exit;
}

if ($message === '') {
    echo json_encode(['success' => false, 'error' => 'Le message ne peut pas être vide.']);
    exit;
}

// Récupérer la sortie
$stmt = $pdo->prepare("SELECT * FROM sorties WHERE id = ?");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sortie) {
    echo json_encode(['success' => false, 'error' => 'Sortie introuvable.']);
    exit;
}

// Récupérer les membres inscrits (table sortie_inscriptions)
try {
    $stmtInscrits = $pdo->prepare("
        SELECT DISTINCT u.id, u.nom, u.prenom, u.email
        FROM sortie_inscriptions si
        JOIN users u ON u.id = si.user_id
        WHERE si.sortie_id = ?
          AND u.email IS NOT NULL
          AND u.email <> ''
    ");
    $stmtInscrits->execute([$sortie_id]);
    $inscrits = $stmtInscrits->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la récupération des inscrits: ' . $e->getMessage()]);
    exit;
}

if (empty($inscrits)) {
    echo json_encode(['success' => false, 'error' => 'Aucun membre inscrit à cette sortie.']);
    exit;
}

// Construire le sujet
$titre = $sortie['titre'] ?? ('Sortie #' . $sortie_id);
$subject = 'CONCERNE - ' . $titre . ' - IMPORTANT';

// Construire le corps HTML et texte
$messageHtml = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
$html = '
    <p>Bonjour,</p>
    <p>Message concernant la sortie <strong>' . htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') . '</strong> :</p>
    <div style="background:#f9f9f9;padding:1rem;border-left:4px solid #004b8d;margin:1rem 0;">
        ' . $messageHtml . '
    </div>
    <p>Cordialement,<br>Le comité du Club ULM Evasion</p>
';

$text = "Message concernant la sortie " . $titre . " :\n\n" . $message . "\n\nCordialement,\nLe comité du Club ULM Evasion";

$sent = 0;
$failed = 0;
$errors = [];
$recipients = [];

foreach ($inscrits as $u) {
    $nom_complet = trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''));
    $to = [$u['email'] => $nom_complet];

    $result = gestnav_send_mail($pdo, $to, $subject, $html, $text);

    if ($result['success']) {
        $sent++;
        $recipients[] = [
            'email' => $u['email'],
            'nom' => $nom_complet
        ];
    } else {
        $failed++;
        $errors[] = $nom_complet . ': ' . ($result['error'] ?? 'Erreur inconnue');
    }
}

echo json_encode([
    'success' => true,
    'sent' => $sent,
    'failed' => $failed,
    'errors' => $errors,
    'recipients' => $recipients
]);
exit;
