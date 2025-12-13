<?php
/**
 * Migration: Ajouter colonne type_membre à la table users
 * Permet de catégoriser les utilisateurs comme CLUB ou INVITE
 */

require_once 'config.php';

try {
    // Vérifier si la colonne existe déjà
    $checkStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'type_membre'");
    $columnExists = $checkStmt->rowCount() > 0;
    
    if (!$columnExists) {
        echo "Ajout de la colonne type_membre...\n";
        
        // Ajouter la colonne
        $pdo->exec("ALTER TABLE users ADD COLUMN type_membre VARCHAR(50) DEFAULT 'club' AFTER actif");
        
        echo "✓ Colonne type_membre ajoutée avec succès!\n";
        echo "Tous les utilisateurs existants sont définis comme 'club'.\n";
    } else {
        echo "✓ La colonne type_membre existe déjà.\n";
    }
    
} catch (Exception $e) {
    echo "✕ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration terminée avec succès!\n";
?>
