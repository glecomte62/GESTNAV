<?php
require 'header.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_sql'])) {
    try {
        // SQL pour créer les tables
        $sql_queries = [
            "CREATE TABLE IF NOT EXISTS evenements (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                titre VARCHAR(150) NOT NULL,
                description TEXT,
                type ENUM('reunion', 'assemblee', 'formation', 'social', 'autre') NOT NULL DEFAULT 'reunion',
                date_evenement DATETIME NOT NULL,
                lieu VARCHAR(255) NOT NULL,
                adresse TEXT,
                statut ENUM('prévu', 'en_cours', 'terminé', 'annulé') NOT NULL DEFAULT 'prévu',
                created_by INT(11) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_date (date_evenement),
                KEY idx_statut (statut),
                FOREIGN KEY (created_by) REFERENCES users(id)
            )",
            
            "CREATE TABLE IF NOT EXISTS evenement_inscriptions (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                evenement_id INT(11) NOT NULL,
                user_id INT(11) NOT NULL,
                nb_accompagnants INT(11) NOT NULL DEFAULT 0,
                notes TEXT,
                statut ENUM('confirmée', 'annulée') NOT NULL DEFAULT 'confirmée',
                action_token VARCHAR(64),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_inscription (evenement_id, user_id),
                KEY idx_evenement (evenement_id),
                KEY idx_user (user_id),
                KEY idx_statut (statut),
                FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            "CREATE INDEX IF NOT EXISTS idx_evenements_date ON evenements(date_evenement)",
            "CREATE INDEX IF NOT EXISTS idx_evenements_statut ON evenements(statut)",
            "CREATE INDEX IF NOT EXISTS idx_inscriptions_user ON evenement_inscriptions(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_inscriptions_token ON evenement_inscriptions(action_token)"
        ];
        
        foreach ($sql_queries as $query) {
            $pdo->exec($query);
        }
        
        $success = "✓ Tables créées ou mises à jour avec succès !";
    } catch (Exception $e) {
        $error = "✗ Erreur lors de la création des tables : " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <h1 class="mb-4">⚙️ Installation - Système d'événements</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="gn-card mb-4">
        <div class="gn-card-header">
            <h3 class="gn-card-title">Création des tables d'événements</h3>
        </div>
        <div style="padding: 1.5rem;">
            <p>Cette page crée les tables nécessaires pour le système d'événements :</p>
            <ul>
                <li><code>evenements</code> - Stocke les événements du club</li>
                <li><code>evenement_inscriptions</code> - Stocke les inscriptions aux événements</li>
            </ul>
            
            <form method="POST" class="mt-3">
                <button type="submit" name="execute_sql" class="btn btn-danger" 
                        onclick="return confirm('Êtes-vous sûr ? Cette action créera les tables.')">
                    <i class="bi bi-lightning-charge-fill"></i> Exécuter l'installation
                </button>
            </form>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
