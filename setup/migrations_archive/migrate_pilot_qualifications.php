<?php
/**
 * Migration: Ajouter les qualifications pilote
 * - emport_passager: booléen, indique si le pilote peut emporter des passagers
 * - qualification_radio_ifr: booléen, indique si le pilote a la qualification radio pour IFR
 */

require_once 'config.php';

try {
    echo "🔄 Vérification de la structure de la table users...\n";
    
    // Vérifier si les colonnes existent déjà
    $colsStmt = $pdo->query('SHOW COLUMNS FROM users');
    $existingCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
    
    // Ajouter les colonnes manquantes de base d'abord
    $requiredCols = [
        'telephone' => "ALTER TABLE users ADD COLUMN telephone VARCHAR(20) NULL AFTER email",
        'qualification' => "ALTER TABLE users ADD COLUMN qualification VARCHAR(50) NULL AFTER telephone",
        'photo_path' => "ALTER TABLE users ADD COLUMN photo_path VARCHAR(255) NULL AFTER qualification",
        'photo_metadata' => "ALTER TABLE users ADD COLUMN photo_metadata JSON NULL AFTER photo_path",
        'actif' => "ALTER TABLE users ADD COLUMN actif TINYINT(1) DEFAULT 1 AFTER photo_metadata",
        'password_hash' => "ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER actif",
    ];
    
    foreach ($requiredCols as $col => $alterStmt) {
        if (!in_array($col, $existingCols)) {
            echo "➕ Ajout de la colonne '$col'...\n";
            $pdo->exec($alterStmt);
            echo "✅ Colonne '$col' ajoutée\n";
        } else {
            echo "ℹ️  Colonne '$col' existe déjà\n";
        }
    }
    
    // Maintenant ajouter les nouvelles colonnes qualifications
    $hasEmportPassager = in_array('emport_passager', $existingCols);
    $hasQualifRadioIfr = in_array('qualification_radio_ifr', $existingCols);
    
    if (!$hasEmportPassager) {
        echo "➕ Ajout de la colonne 'emport_passager'...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN emport_passager TINYINT(1) DEFAULT 0 AFTER qualification");
        echo "✅ Colonne 'emport_passager' ajoutée\n";
    } else {
        echo "ℹ️  Colonne 'emport_passager' existe déjà\n";
    }
    
    if (!$hasQualifRadioIfr) {
        echo "➕ Ajout de la colonne 'qualification_radio_ifr'...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN qualification_radio_ifr TINYINT(1) DEFAULT 0 AFTER emport_passager");
        echo "✅ Colonne 'qualification_radio_ifr' ajoutée\n";
    } else {
        echo "ℹ️  Colonne 'qualification_radio_ifr' existe déjà\n";
    }
    
    echo "\n✅ Migration réussie!\n";
    echo "Les colonnes suivantes sont maintenant disponibles:\n";
    echo "  - emport_passager (TINYINT)\n";
    echo "  - qualification_radio_ifr (TINYINT)\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>
