<?php
/**
 * Migration: Ajouter colonne ulm_base_id à sortie_proposals
 * Pour permettre la sélection de bases ULM en plus des aérodromes
 */

require_once 'config.php';

$pdo = $pdo ?? null;
if (!$pdo instanceof PDO) {
    die('Connexion base de données indisponible.');
}

try {
    // Vérifier si la colonne existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM sortie_proposals LIKE 'ulm_base_id'");
    if ($stmt->rowCount() > 0) {
        echo "✅ La colonne ulm_base_id existe déjà.\n";
        exit(0);
    }
    
    // Ajouter la colonne ulm_base_id
    $pdo->exec("
        ALTER TABLE sortie_proposals 
        ADD COLUMN ulm_base_id INT NULL AFTER aerodrome_id,
        ADD FOREIGN KEY (ulm_base_id) REFERENCES ulm_bases_fr(id) ON DELETE SET NULL
    ");
    
    echo "✅ Colonne ulm_base_id ajoutée avec succès à sortie_proposals!\n";
    echo "ℹ️  Les propositions peuvent maintenant inclure des bases ULM.\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de l'ajout de la colonne: " . $e->getMessage() . "\n";
    exit(1);
}
?>
