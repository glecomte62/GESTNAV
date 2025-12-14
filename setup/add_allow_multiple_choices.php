<?php
/**
 * Migration : Ajouter la colonne allow_multiple_choices à la table polls
 * 
 * Cette migration ajoute la possibilité de créer des sondages avec choix multiples
 * permettant aux utilisateurs de voter pour plusieurs options.
 * 
 * Usage: php setup/add_allow_multiple_choices.php
 * Ou visiter: https://gestnav.clubulmevasion.fr/setup/add_allow_multiple_choices.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Si accès via navigateur, vérifier les droits admin
if (php_sapi_name() !== 'cli') {
    require_login();
    if (!is_admin()) {
        die("❌ Accès refusé. Vous devez être administrateur.");
    }
}

echo "🔧 Migration : Ajout de la colonne allow_multiple_choices\n";
echo "==========================================================\n\n";

try {
    // Vérifier si la colonne existe déjà
    $check = $pdo->query("SHOW COLUMNS FROM polls LIKE 'allow_multiple_choices'");
    
    if ($check->rowCount() > 0) {
        echo "✅ La colonne 'allow_multiple_choices' existe déjà dans la table 'polls'\n";
        echo "ℹ️  Aucune action nécessaire.\n";
    } else {
        // Ajouter la colonne
        $sql = "ALTER TABLE polls ADD COLUMN allow_multiple_choices TINYINT(1) DEFAULT 0 AFTER type";
        $pdo->exec($sql);
        
        echo "✅ Colonne 'allow_multiple_choices' ajoutée avec succès !\n\n";
        echo "📊 Description de la colonne :\n";
        echo "   - Nom: allow_multiple_choices\n";
        echo "   - Type: TINYINT(1)\n";
        echo "   - Défaut: 0 (désactivé)\n";
        echo "   - Usage: Permet aux utilisateurs de voter pour plusieurs options\n\n";
        
        // Mettre à jour les sondages de type 'date' existants pour activer le choix multiple par défaut
        $update = $pdo->exec("UPDATE polls SET allow_multiple_choices = 1 WHERE type = 'date' AND status = 'ouvert'");
        
        if ($update > 0) {
            echo "🔄 $update sondage(s) de type 'date' mis à jour avec choix multiple activé\n\n";
        }
    }
    
    // Afficher la structure de la table
    echo "📋 Structure actuelle de la table 'polls' :\n";
    echo "==========================================\n";
    
    $columns = $pdo->query("SHOW COLUMNS FROM polls");
    while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("   %-30s %-20s %s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
        );
    }
    
    echo "\n✅ Migration terminée avec succès !\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la migration :\n";
    echo "   " . $e->getMessage() . "\n";
    exit(1);
}

if (php_sapi_name() !== 'cli') {
    echo "\n\n<br><br><a href='../sondages_admin.php'>← Retour aux sondages</a>";
}
