<?php
/**
 * Migration: Créer table email_recipients pour stocker les destinataires
 */

require_once 'config.php';

try {
    // Vérifier si la table existe déjà
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'email_recipients'");
    $tableExists = $checkStmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "Création de la table email_recipients...\n";
        
        $pdo->exec("
            CREATE TABLE email_recipients (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email_log_id INT NOT NULL,
                user_id INT,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255),
                FOREIGN KEY (email_log_id) REFERENCES email_logs(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX (email_log_id),
                INDEX (user_id)
            )
        ");
        
        echo "✓ Table email_recipients créée avec succès!\n";
    } else {
        echo "✓ La table email_recipients existe déjà.\n";
    }
    
} catch (Exception $e) {
    echo "✕ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration terminée avec succès!\n";
?>
