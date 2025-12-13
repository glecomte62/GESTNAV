<?php
require 'config.php';
require 'auth.php';
require 'mail_helper.php';
require_once __DIR__ . '/utils/waitlist.php';

// Récupérer les paramètres
$action = isset($_GET['action']) ? $_GET['action'] : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

$message = '';
$error = false;

if (!$token) {
    $error = true;
    $message = "Lien invalide ou expiré.";
} else {
    // Vérifier que le token existe et récupérer les infos
    $stmt = $pdo->prepare("
        SELECT si.id, si.sortie_id, si.user_id, s.titre, u.email, u.prenom, u.nom
        FROM sortie_inscriptions si
        JOIN sorties s ON s.id = si.sortie_id
        JOIN users u ON u.id = si.user_id
        WHERE si.action_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $inscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inscription) {
        $error = true;
        $message = "Lien invalide ou expiré.";
    } else {
        // Traiter l'action
        switch ($action) {
            case 'annuler':
                // Annuler l'inscription et libérer l'éventuelle affectation
                try {
                    $pdo->beginTransaction();

                    // Trouver les affectations de cet utilisateur sur cette sortie
                    $q = $pdo->prepare("SELECT sa.id, sa.sortie_machine_id FROM sortie_assignations sa JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id WHERE sm.sortie_id = ? AND sa.user_id = ?");
                    $q->execute([$inscription['sortie_id'], $inscription['user_id']]);
                    $assigns = $q->fetchAll(PDO::FETCH_ASSOC);

                    // Supprimer les affectations de cet utilisateur pour cette sortie
                    if ($assigns) {
                        $delA = $pdo->prepare("DELETE FROM sortie_assignations WHERE id = ?");
                        foreach ($assigns as $a) {
                            $delA->execute([$a['id']]);
                        }
                    }

                    // Supprimer l'inscription
                    $del = $pdo->prepare("DELETE FROM sortie_inscriptions WHERE action_token = ?");
                    $del->execute([$token]);

                    $pdo->commit();

                    $message = "Votre inscription a été annulée.";
                    // Pas d'envoi automatique de mails ici: les emails partent uniquement lors de la validation des affectations par un administrateur.

                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = true;
                    $message = "Erreur lors de l'annulation: " . $e->getMessage();
                }
                break;
            
            case 'changer_machine':
                // Rediriger vers une page de sélection de machine
                // L'inscription reste, on va modifier l'assignation
                header("Location: changer_machine.php?token=" . urlencode($token));
                exit;
            
            case 'changer_coequipier':
                // Rediriger vers une page de sélection de coéquipier
                header("Location: changer_coequipier.php?token=" . urlencode($token));
                exit;
            
            default:
                $error = true;
                $message = "Action non reconnue.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Action d'inscription - GESTNAV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="alert alert-<?= $error ? 'danger' : 'success' ?>" role="alert">
                <h4 class="alert-heading"><?= $error ? 'Erreur' : 'Succès' ?></h4>
                <p><?= htmlspecialchars($message) ?></p>
            </div>
            
            <div style="margin-top: 2rem;">
                <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
