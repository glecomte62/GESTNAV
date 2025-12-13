<?php
require 'header.php';
require_once 'utils/activity_log.php';
require_login();

$sortie_id = isset($_GET['sortie_id']) ? (int)$_GET['sortie_id'] : 0;
$user_id   = $_SESSION['user_id'];

$message = '';
$error   = false;

if ($sortie_id > 0) {
    // Vérifier que la sortie existe et est "prévue"
    $stmt = $pdo->prepare("SELECT * FROM sorties WHERE id = ?");
    $stmt->execute([$sortie_id]);
    $sortie = $stmt->fetch(PDO::FETCH_ASSOC);

    // Destination (optionnelle selon schéma)
    $destination_label = '';
    $hasDestinationId = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM sorties LIKE 'destination_id'");
        if ($colCheck && $colCheck->fetch()) {
            $hasDestinationId = true;
        }
    } catch (Throwable $e) {
        $hasDestinationId = false; // silencieux
    }
    if ($hasDestinationId && $sortie && !empty($sortie['destination_id'])) {
        try {
            $stmtDest = $pdo->prepare("SELECT oaci, nom FROM aerodromes_fr WHERE id = ? LIMIT 1");
            $stmtDest->execute([(int)$sortie['destination_id']]);
            if ($rowDest = $stmtDest->fetch(PDO::FETCH_ASSOC)) {
                $destination_label = trim(($rowDest['oaci'] ?? '') . ' – ' . ($rowDest['nom'] ?? ''));
            }
        } catch (Throwable $e) {
            $destination_label = '';
        }
    }

    if ($sortie) {
        // Interdire l'inscription si la sortie n'est pas publiée (statut différent de "prévue")
        if (isset($sortie['statut']) && $sortie['statut'] !== 'prévue') {
            $error = true;
            $message = "Inscription non disponible pour cette sortie (statut : " . htmlspecialchars($sortie['statut']) . ").";
        }
        // Essayer d'insérer l'inscription
        try {
            // Générer un token unique pour les actions (sera activé à l'approbation admin)
            $action_token = bin2hex(random_bytes(32));
            
            $stmtIns = $pdo->prepare("\n                INSERT INTO sortie_inscriptions (sortie_id, user_id, action_token) \n                VALUES (?, ?, ?)\n            ");
            $stmtIns->execute([$sortie_id, $user_id, $action_token]);
            // Log opération (inscription à une sortie)
            gn_log_current_user_operation($pdo, 'sortie_inscription', 'Inscription effectuée');

            $message = "Votre inscription à la sortie « " . htmlspecialchars($sortie['titre']) . " » a bien été enregistrée. L'administrateur la validera et vous enverrera les détails par mail.";
            
        } catch (PDOException $e) {
            // Si doublon (déjà inscrit)
            if ($e->getCode() === '23000') {
                $message = "Vous êtes déjà inscrit(e) à cette sortie.";
                // Optionnel: tentative de double inscription
                gn_log_current_user_operation($pdo, 'sortie_inscription_duplicate', 'Déjà inscrit(e)');
            } else {
                $error = true;
                $message = "Une erreur est survenue lors de votre inscription.";
                error_log("Erreur inscription: " . $e->getMessage());
            }
        }
    } else {
        $error = true;
        $message = "Sortie introuvable.";
    }
} else {
    $error = true;
    $message = "Aucune sortie spécifiée.";
}
?>

<div style="max-width:700px;margin:2rem auto;padding:1.5rem;background:#fff;border-radius:1rem;box-shadow:0 8px 24px rgba(0,0,0,0.08);border:1px solid rgba(0,0,0,0.03);">
    <h2 style="margin-top:0;">Inscription à une sortie</h2>
    <p style="padding:0.75rem 1rem;border-radius:0.75rem;
        background:<?= $error ? '#fde8e8' : '#e7f7ec' ?>;
        color:<?= $error ? '#b02525' : '#0a8a0a' ?>;">
        <?= htmlspecialchars($message) ?>
    </p>

    <?php if (!$error && !empty($destination_label)): ?>
        <div style="margin:0.75rem 0 0;padding:0.55rem 0.9rem;border:1px solid #d0d7e2;border-radius:0.75rem;background:#f5f9fc;font-size:0.85rem;display:inline-flex;align-items:center;gap:0.5rem;">
            <span style="background:#004b8d;color:#fff;padding:0.25rem 0.55rem;border-radius:999px;font-size:0.65rem;letter-spacing:0.05em;font-weight:600;">DESTINATION</span>
            <span style="font-weight:600;color:#004b8d;">
                <?= htmlspecialchars($destination_label) ?>
            </span>
        </div>
    <?php endif; ?>

    <p style="margin-top:1rem;">
        <?php
        // Afficher badge PRIORITAIRE pour l'utilisateur courant si actif
        $isPriorityUser = false;
        try {
            if (!empty($_SESSION['user_id'])) {
                $stP = $pdo->prepare('SELECT active FROM sortie_priorites WHERE user_id = ?');
                $stP->execute([ (int)$_SESSION['user_id'] ]);
                $isPriorityUser = (bool)($stP->fetchColumn() ?? 0);
            }
        } catch (Throwable $e) { $isPriorityUser = false; }
        if ($isPriorityUser): ?>
            <span style="display:inline-flex;align-items:center;padding:0.22rem 0.6rem;border:1px solid #f5b5b5;border-radius:999px;font-size:0.75rem;background:#fde8e8;color:#b00020;margin-right:.5rem;" title="Vous êtes prioritaire sur la prochaine sortie">
                PRIORITAIRE
            </span>
        <?php endif; ?>
        <a href="sorties.php" style="text-decoration:none;border-radius:999px;padding:0.55rem 1.3rem;
           background:linear-gradient(135deg,#004b8d,#00a0c6);color:#fff;font-weight:600;">
            Retour aux sorties
        </a>
    </p>
</div>

<?php require 'footer.php'; ?>
