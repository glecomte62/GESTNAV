<?php
/**
 * Migration: Ajouter colonne message à email_logs
 */

require_once 'config.php';

try {
    // Vérifier si la colonne existe déjà
    $checkStmt = $pdo->query("SHOW COLUMNS FROM email_logs LIKE 'message'");
    $columnExists = $checkStmt->rowCount() > 0;
    
    if (!$columnExists) {
        echo "Ajout de la colonne 'message' à email_logs...\n";
        
        $pdo->exec("
            ALTER TABLE email_logs 
            ADD COLUMN message TEXT NULL AFTER recipient_count
        ");
        
        echo "✓ Colonne 'message' ajoutée avec succès!\n";
    } else {
        echo "✓ La colonne 'message' existe déjà.\n";
    }
    
} catch (Exception $e) {
    echo "✕ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration terminée avec succès!\n";
?>
