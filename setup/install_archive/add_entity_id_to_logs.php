<?php
/**
 * Migration: Ajouter colonne entity_id à operation_logs pour liens cliquables
 */

require_once __DIR__ . '/../../config.php';

try {
    // Vérifier si la colonne existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM operation_logs LIKE 'entity_id'");
    if ($stmt->rowCount() > 0) {
        echo "✓ La colonne entity_id existe déjà.\n";
        exit(0);
    }
    
    // Ajouter la colonne entity_id
    $pdo->exec("ALTER TABLE operation_logs ADD COLUMN entity_id INT NULL AFTER action");
    echo "✓ Colonne entity_id ajoutée à operation_logs.\n";
    
    // Ajouter un index pour améliorer les performances
    $pdo->exec("ALTER TABLE operation_logs ADD INDEX idx_entity_id (entity_id)");
    echo "✓ Index ajouté sur entity_id.\n";
    
    echo "\n✅ Migration terminée avec succès.\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la migration: " . $e->getMessage() . "\n";
    exit(1);
}
