<?php
require 'config.php';
require 'auth.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

$message = '';
$error = false;
$show_form = false;
$inscription = null;

if (!$token) {
    $error = true;
    $message = "Lien invalide ou expiré.";
} else {
    // Vérifier que le token existe
    $stmt = $pdo->prepare("
        SELECT ei.id, ei.evenement_id, ei.user_id, ei.nb_accompagnants, e.titre, e.date_limite_inscription, u.email, u.prenom, u.nom
        FROM evenement_inscriptions ei
        JOIN evenements e ON e.id = ei.evenement_id
        JOIN users u ON u.id = ei.user_id
        WHERE ei.action_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $inscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inscription) {
        $error = true;
        $message = "Lien invalide ou expiré.";
    } elseif ($action === 'inscrire') {
        // Vérifier la date limite
        if ($inscription['date_limite_inscription']) {
            $date_limite = new DateTime($inscription['date_limite_inscription']);
            $now = new DateTime();
            
            if ($now > $date_limite) {
                $error = true;
                $message = "La date limite d'inscription est dépassée.";
            } else {
                $show_form = true;
            }
        } else {
            $show_form = true;
        }
    } elseif ($action === 'annuler') {
        // Traiter l'annulation immédiate
        require_once 'mail_helper.php';
        try {
            $upd = $pdo->prepare("UPDATE evenement_inscriptions SET statut = 'annulée' WHERE action_token = ?");
            $upd->execute([$token]);
            
            $message = "Votre participation a été annulée.";
            
            // Notification admin
            $html_notif = "
            <html>
            <head><meta charset='UTF-8'></head>
            <body>
                <h3>Annulation d'inscription à un événement</h3>
                <p><strong>" . htmlspecialchars($inscription['prenom'] . ' ' . $inscription['nom']) . "</strong> 
                a annulé sa participation à <strong>" . htmlspecialchars($inscription['titre']) . "</strong></p>
                <p>Email: " . htmlspecialchars($inscription['email']) . "</p>
            </body>
            </html>";
            
            gestnav_send_mail(
                $pdo,
                'info@clubulmevasion.fr',
                "Annulation d'inscription - " . $inscription['titre'],
                $html_notif
            );
        } catch (Exception $e) {
            $error = true;
            $message = "Erreur : " . $e->getMessage();
        }
    }
}

// Traiter la soumission du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nb_accompagnants']) && !$error) {
    require_once 'mail_helper.php';
    
    $nb_accompagnants = (int)($_POST['nb_accompagnants'] ?? 0);
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    if ($nb_accompagnants < 0 || $nb_accompagnants > 10) {
        $error = true;
        $message = "Le nombre d'accompagnants doit être entre 0 et 10.";
    } else {
        try {
            // Mettre à jour l'inscription
            $upd = $pdo->prepare("
                UPDATE evenement_inscriptions
                SET statut = 'confirmée', nb_accompagnants = ?, notes = ?
                WHERE action_token = ?
            ");
            $upd->execute([$nb_accompagnants, $notes, $token]);
            
            $message = "Vous êtes inscrit(e) à l'événement !";
            $show_form = false;
            
            // Notification admin
            $html_notif = "
            <html>
            <head><meta charset='UTF-8'></head>
            <body>
                <h3>Nouvelle inscription confirmée</h3>
                <p><strong>" . htmlspecialchars($inscription['prenom'] . ' ' . $inscription['nom']) . "</strong> 
                s'est inscrit(e) à <strong>" . htmlspecialchars($inscription['titre']) . "</strong></p>
                <p><strong>Accompagnants :</strong> " . $nb_accompagnants . "</p>
                " . ($notes ? "<p><strong>Notes :</strong> " . nl2br(htmlspecialchars($notes)) . "</p>" : "") . "
                <p>Email: " . htmlspecialchars($inscription['email']) . "</p>
            </body>
            </html>";
            
            gestnav_send_mail(
                $pdo,
                'info@clubulmevasion.fr',
                "Nouvelle inscription - " . $inscription['titre'],
                $html_notif
            );
        } catch (Exception $e) {
            $error = true;
            $message = "Erreur : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $error ? 'Erreur' : 'Réponse à l\'invitation' ?> - GESTNAV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <?php if ($show_form): ?>
                <!-- Formulaire d'inscription -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">
                            <i class="bi bi-check-circle"></i> Confirmation d'inscription
                        </h4>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title mb-3"><?= htmlspecialchars($inscription['titre']) ?></h5>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="nb_accompagnants" class="form-label">Nombre de personnes vous accompagnant *</label>
                                <input type="number" class="form-control" id="nb_accompagnants" name="nb_accompagnants" 
                                       min="0" max="10" value="0" required>
                                <small class="form-text text-muted">Cela nous aide pour la catering et l'organisation (0 = vous seul)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes ou remarques (optionnel)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Par exemple : régime alimentaire, assistance nécessaire..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-check2-circle"></i> Confirmer mon inscription
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Message de succès/erreur -->
                <div class="alert alert-<?= $error ? 'danger' : 'success' ?>" role="alert">
                    <h4 class="alert-heading"><?= $error ? 'Erreur' : 'Succès' ?></h4>
                    <p><?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 2rem; text-align: center;">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="bi bi-house"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
