<?php
/**
 * Migration: Créer table email_logs pour l'historique d'envois
 */

require_once 'config.php';

try {
    // Vérifier si la table existe déjà
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'email_logs'");
    $tableExists = $checkStmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "Création de la table email_logs...\n";
        
        $pdo->exec("
            CREATE TABLE email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject VARCHAR(255) NOT NULL,
                message TEXT NULL,
                recipient_count INT DEFAULT 0,
                sender_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX (created_at),
                INDEX (sender_id)
            )
        ");
        
        echo "✓ Table email_logs créée avec succès!\n";
    } else {
        echo "✓ La table email_logs existe déjà.\n";
        
        // Vérifier si la colonne message existe
        $checkCol = $pdo->query("SHOW COLUMNS FROM email_logs LIKE 'message'");
        if ($checkCol->rowCount() === 0) {
            echo "Ajout de la colonne 'message'...\n";
            $pdo->exec("ALTER TABLE email_logs ADD COLUMN message TEXT NULL AFTER subject");
            echo "✓ Colonne 'message' ajoutée!\n";
        }
    }
    
} catch (Exception $e) {
    echo "✕ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration terminée avec succès!\n";
?>
