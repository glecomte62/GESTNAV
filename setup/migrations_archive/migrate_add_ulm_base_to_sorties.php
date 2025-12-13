<?php
/**
 * Migration: Ajouter colonne ulm_base_id à sorties
 * Pour permettre la sélection de bases ULM comme destination
 */

require_once 'config.php';

$pdo = $pdo ?? null;
if (!$pdo instanceof PDO) {
    die('Connexion base de données indisponible.');
}

try {
    // Vérifier si la colonne existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM sorties LIKE 'ulm_base_id'");
    if ($stmt->rowCount() > 0) {
        echo "✅ La colonne ulm_base_id existe déjà dans sorties.\n";
        exit(0);
    }
    
    // Ajouter la colonne ulm_base_id
    $pdo->exec("
        ALTER TABLE sorties 
        ADD COLUMN ulm_base_id INT NULL AFTER destination_id,
        ADD FOREIGN KEY (ulm_base_id) REFERENCES ulm_bases_fr(id) ON DELETE SET NULL
    ");
    
    echo "✅ Colonne ulm_base_id ajoutée avec succès à sorties!\n";
    echo "ℹ️  Les sorties peuvent maintenant avoir des bases ULM comme destination.\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de l'ajout de la colonne: " . $e->getMessage() . "\n";
    exit(1);
}
?>
