<?php
require 'header.php';
require 'mail_helper.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$message = '';
$error = false;
$inscription = null;
$users = [];

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
        // Récupérer les autres membres inscrits à cette sortie
        $stmt2 = $pdo->prepare("
            SELECT DISTINCT u.id, u.prenom, u.nom, u.email
            FROM sortie_inscriptions si
            JOIN users u ON u.id = si.user_id
            WHERE si.sortie_id = ? AND u.id != ? AND u.actif = 1
            ORDER BY u.nom, u.prenom
        ");
        $stmt2->execute([$inscription['sortie_id'], $inscription['user_id']]);
        $users = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        // Traiter le formulaire de soumission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coequipier_id'])) {
            $coequipier_id = (int)$_POST['coequipier_id'];
            
            try {
                // Récupérer et supprimer l'assignation existante
                $check = $pdo->prepare("
                    SELECT id FROM sortie_assignations 
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
                
                // Récupérer le coéquipier
                $c = $pdo->prepare("SELECT prenom, nom, email FROM users WHERE id = ?");
                $c->execute([$coequipier_id]);
                $coequipier = $c->fetch(PDO::FETCH_ASSOC);
                
                // Envoyer une notification à info@clubulmevasion.fr
                $html_notif = "
                <html>
                <head><meta charset='UTF-8'></head>
                <body>
                    <h3>Demande de changement de coéquipier</h3>
                    <p><strong>" . htmlspecialchars($inscription['prenom'] . ' ' . $inscription['nom']) . "</strong> 
                    demande à changer de coéquipier pour la sortie <strong>" . htmlspecialchars($inscription['titre']) . "</strong></p>
                    <p><strong>Coéquipier demandé :</strong> " . htmlspecialchars($coequipier['prenom'] . ' ' . $coequipier['nom']) . " (" . htmlspecialchars($coequipier['email']) . ")</p>
                    <p>Email demandeur: " . htmlspecialchars($inscription['email']) . "</p>
                    <p><em>Il a été retiré de sa machine actuelle. Son inscription reste active. Vous pouvez le réassigner.</em></p>
                </body>
                </html>";
                
                gestnav_send_mail(
                    $pdo,
                    'info@clubulmevasion.fr',
                    "Demande de changement de coéquipier - " . $inscription['titre'],
                    $html_notif
                );
                
                $message = "Votre demande de changement de coéquipier a été envoyée à l'administrateur. Vous avez été retiré de votre machine actuelle. Votre inscription reste active.";
                
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
                    <h2 class="gn-card-title">👥 Changer de coéquipier</h2>
                </div>
                
                <div style="padding: 1.5rem;">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $error ? 'danger' : 'success' ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$error && $inscription && !empty($_POST)): ?>
                        <!-- Confirmé -->
                        <p style="margin-top: 1rem;">
                            <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
                        </p>
                    <?php elseif (!$error && $inscription): ?>
                        <!-- Formulaire -->
                        <p>Sortie: <strong><?= htmlspecialchars($inscription['titre']) ?></strong></p>
                        
                        <?php if (empty($users)): ?>
                            <div class="alert alert-info">
                                Aucun autre membre n'est inscrit à cette sortie.
                            </div>
                            <p>
                                <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
                            </p>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Sélectionner votre coéquipier:</label>
                                    <div>
                                        <?php foreach ($users as $u): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="coequipier_id" 
                                                       id="user_<?= $u['id'] ?>" value="<?= $u['id'] ?>">
                                                <label class="form-check-label" for="user_<?= $u['id'] ?>">
                                                    <strong><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></strong>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Confirmer le changement</button>
                                <a href="index.php" class="btn btn-secondary">Annuler</a>
                            </form>
                        <?php endif; ?>
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
