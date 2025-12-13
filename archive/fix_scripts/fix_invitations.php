<?php
require 'header.php';
require_admin();

$success = false;
$error = false;
$message = '';
$stats = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_invitations'])) {
    try {
        // 1. D'abord, modifier le schéma
        try {
            $pdo->exec("ALTER TABLE evenement_inscriptions MODIFY statut ENUM('en_attente', 'confirmée', 'annulée') NOT NULL DEFAULT 'en_attente'");
        } catch (Exception $e) {
            // Si l'enum existe déjà, continuer
            if (strpos($e->getMessage(), 'Duplicate') === false) {
                throw $e;
            }
        }
        
        // 2. Réinitialiser toutes les inscriptions créées par invitation en masse à "en_attente"
        // (celles qui ont un action_token mais pas de nb_accompagnants ni notes)
        $pdo->exec("UPDATE evenement_inscriptions SET statut = 'en_attente' WHERE action_token IS NOT NULL AND nb_accompagnants = 0 AND notes IS NULL");
        
        $success = true;
        $message = "✓ Migration complète ! Les invitations ont été réinitialisées en 'en attente'.";
    } catch (Exception $e) {
        $error = true;
        $message = "Erreur : " . $e->getMessage();
    }
}

// Vérifier l'état actuel
try {
    // État du schéma
    $result = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='evenement_inscriptions' AND COLUMN_NAME='statut' AND TABLE_SCHEMA=DATABASE()")->fetch();
    $schema_info = $result ? $result['COLUMN_TYPE'] : "Impossible de vérifier";
    
    // Statistiques des inscriptions
    $stats_result = $pdo->query("
        SELECT 
            statut, 
            COUNT(*) as count
        FROM evenement_inscriptions
        GROUP BY statut
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats_result as $row) {
        $stats[$row['statut']] = $row['count'];
    }
} catch (Exception $e) {
    // Erreur silencieuse
}
?>

<div class="container mt-4">
    <h1 class="mb-4">⚙️ Réparation - Système d'invitations</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle"></i> <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="gn-card mb-4">
                <div class="gn-card-header">
                    <h3 class="gn-card-title">État du schéma</h3>
                </div>
                <div style="padding: 1.5rem;">
                    <p><strong>Colonne statut :</strong></p>
                    <code style="background: #f5f5f5; padding: 10px; display: block; border-radius: 4px; font-family: monospace;">
                        <?= htmlspecialchars($schema_info ?? 'Inconnu') ?>
                    </code>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="gn-card mb-4">
                <div class="gn-card-header">
                    <h3 class="gn-card-title">Statistiques des inscriptions</h3>
                </div>
                <div style="padding: 1.5rem;">
                    <?php if (empty($stats)): ?>
                        <p>Aucune inscription</p>
                    <?php else: ?>
                        <ul style="margin: 0; padding-left: 1rem;">
                            <?php foreach ($stats as $statut => $count): ?>
                                <li><strong><?= ucfirst($statut) ?></strong> : <?= $count ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="gn-card mb-4">
        <div class="gn-card-header">
            <h3 class="gn-card-title">Problème identifié</h3>
        </div>
        <div style="padding: 1.5rem;">
            <p>Les invitations créées en masse sont toujours au statut "confirmée" au lieu de "en_attente".</p>
            <p>Cette action va :</p>
            <ol>
                <li>Modifier le schéma pour ajouter le statut "en_attente" s'il n'existe pas</li>
                <li>Réinitialiser les invitations en masse au statut "en_attente"</li>
            </ol>
            
            <form method="POST" class="mt-3">
                <button type="submit" name="fix_invitations" class="btn btn-danger" 
                        onclick="return confirm('Êtes-vous sûr ? Cela réinitialisera les statuts des invitations.')">
                    <i class="bi bi-lightning-charge-fill"></i> Exécuter la réparation
                </button>
            </form>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
