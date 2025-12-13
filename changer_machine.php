<?php
require 'header.php';
require 'mail_helper.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$message = '';
$error = false;
$inscription = null;

if (!$token) {
    $error = true;
    $message = "Lien invalide.";
} else {
    // Vérifier le token
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
        // Traiter la demande de changement de machine
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
            try {
                // Récupérer et supprimer l'assignation existante
                $check = $pdo->prepare("
                    SELECT id, sortie_machine_id FROM sortie_assignations 
                    WHERE user_id = ? 
                    AND sortie_machine_id IN (
                        SELECT id FROM sortie_machines WHERE sortie_id = ?
                    )
                    LIMIT 1
                ");
                $check->execute([$inscription['user_id'], $inscription['sortie_id']]);
                $existing = $check->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    // Supprimer l'assignation existante (le retirer de sa machine actuelle)
                    $del = $pdo->prepare("DELETE FROM sortie_assignations WHERE id = ?");
                    $del->execute([$existing['id']]);
                }
                
                // Envoyer une notification à info@clubulmevasion.fr
                $html_notif = "
                <html>
                <head><meta charset='UTF-8'></head>
                <body>
                    <h3>Demande de changement de machine</h3>
                    <p><strong>" . htmlspecialchars($inscription['prenom'] . ' ' . $inscription['nom']) . "</strong> 
                    demande à changer de machine pour la sortie <strong>" . htmlspecialchars($inscription['titre']) . "</strong></p>
                    <p>Email: " . htmlspecialchars($inscription['email']) . "</p>
                    <p><em>Il a été retiré de sa machine actuelle. Son inscription reste active. Vous pouvez le réassigner à une nouvelle machine.</em></p>
                </body>
                </html>";
                
                gestnav_send_mail(
                    $pdo,
                    'info@clubulmevasion.fr',
                    "Demande de changement de machine - " . $inscription['titre'],
                    $html_notif
                );
                
                $message = "Votre demande de changement de machine a été envoyée à l'administrateur. Vous avez été retiré de votre machine actuelle. Votre inscription reste active.";
                
            } catch (Exception $e) {
                $error = true;
                $message = "Erreur lors de la demande: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="gn-card">
                <div class="gn-card-header">
                    <h2 class="gn-card-title">✈️ Demander un changement de machine</h2>
                </div>
                
                <div style="padding: 1.5rem;">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $error ? 'danger' : 'success' ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$error && $inscription && !empty($_POST)): ?>
                        <!-- Demande confirmée -->
                        <p style="margin-top: 1rem;">
                            <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
                        </p>
                    <?php elseif (!$error && $inscription): ?>
                        <!-- Formulaire de demande -->
                        <div class="info-box" style="padding: 1rem; background: #e8f4f8; border-left: 4px solid #00a0c6; border-radius: 4px; margin-bottom: 1.5rem;">
                            <p><strong>Sortie :</strong> <?= htmlspecialchars($inscription['titre']) ?></p>
                            <p style="margin-bottom: 0;"><em>Vous demandez à changer de machine pour cette sortie.</em></p>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="confirm" value="1">
                            
                            <div class="alert alert-info" role="alert">
                                <p>L'administrateur examinera votre demande et vous contactera pour choisir une nouvelle machine.</p>
                                <p style="margin-bottom: 0;">Votre inscription reste active dans tous les cas.</p>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Confirmer ma demande</button>
                            <a href="index.php" class="btn btn-secondary">Annuler</a>
                        </form>
                    <?php else: ?>
                        <p>
                            <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
