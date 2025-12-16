<?php
/**
 * Migration complète : Activation du choix multiple pour les sondages
 * 
 * Ce script exécute toutes les migrations nécessaires :
 * 1. Ajoute la colonne allow_multiple_choices à la table polls
 * 2. Supprime la contrainte UNIQUE sur poll_votes
 * 3. Configure les sondages de type "date" pour le choix multiple
 * 
 * Usage: php setup/migrate_multiple_choice.php
 * Ou visiter: https://gestnav.clubulmevasion.fr/setup/migrate_multiple_choice.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Si accès via navigateur, vérifier les droits admin
if (php_sapi_name() !== 'cli') {
    require_login();
    if (!is_admin()) {
        die("❌ Accès refusé. Vous devez être administrateur.");
    }
    echo "<pre>";
}

echo "🚀 Migration complète : Choix Multiple pour les sondages\n";
echo "=========================================================\n\n";

$errors = 0;
$warnings = 0;

// ====================================================================
// ÉTAPE 1 : Ajouter la colonne allow_multiple_choices
// ====================================================================
echo "📋 ÉTAPE 1/3 : Ajout de la colonne 'allow_multiple_choices'\n";
echo "------------------------------------------------------------\n";

try {
    $check = $pdo->query("SHOW COLUMNS FROM polls LIKE 'allow_multiple_choices'");
    
    if ($check->rowCount() > 0) {
        echo "⚠️  La colonne 'allow_multiple_choices' existe déjà\n";
        $warnings++;
    } else {
        $sql = "ALTER TABLE polls ADD COLUMN allow_multiple_choices TINYINT(1) DEFAULT 0 AFTER type";
        $pdo->exec($sql);
        echo "✅ Colonne 'allow_multiple_choices' ajoutée\n";
    }
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    $errors++;
}

echo "\n";

// ====================================================================
// ÉTAPE 2 : Modifier la contrainte UNIQUE sur poll_votes
// ====================================================================
echo "📋 ÉTAPE 2/3 : Modification de la contrainte UNIQUE\n";
echo "------------------------------------------------------------\n";

try {
    $check = $pdo->query("SHOW KEYS FROM poll_votes WHERE Key_name = 'uk_user_poll'");
    
    if ($check->rowCount() > 0) {
        $pdo->exec("ALTER TABLE poll_votes DROP INDEX uk_user_poll");
        echo "✅ Contrainte UNIQUE supprimée\n";
        
        $pdo->exec("CREATE INDEX idx_poll_user ON poll_votes(poll_id, user_id)");
        echo "✅ Index de performance ajouté\n";
    } else {
        echo "⚠️  La contrainte UNIQUE n'existe pas (déjà supprimée ?)\n";
        $warnings++;
        
        // Vérifier si l'index existe
        $check_index = $pdo->query("SHOW KEYS FROM poll_votes WHERE Key_name = 'idx_poll_user'");
        if ($check_index->rowCount() === 0) {
            $pdo->exec("CREATE INDEX idx_poll_user ON poll_votes(poll_id, user_id)");
            echo "✅ Index de performance ajouté\n";
        }
    }
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    $errors++;
}

echo "\n";

// ====================================================================
// ÉTAPE 3 : Activer le choix multiple pour les sondages de type "date"
// ====================================================================
echo "📋 ÉTAPE 3/3 : Configuration des sondages existants\n";
echo "------------------------------------------------------------\n";

try {
    // Compter les sondages de type "date" ouverts
    $stmt = $pdo->query("SELECT COUNT(*) FROM polls WHERE type = 'date' AND status = 'ouvert' AND allow_multiple_choices = 0");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $update = $pdo->exec("UPDATE polls SET allow_multiple_choices = 1 WHERE type = 'date' AND status = 'ouvert'");
        echo "✅ $update sondage(s) de type 'date' configuré(s) en choix multiple\n";
    } else {
        echo "ℹ️  Aucun sondage de type 'date' à mettre à jour\n";
    }
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    $errors++;
}

echo "\n";

// ====================================================================
// RÉSUMÉ
// ====================================================================
echo "📊 RÉSUMÉ DE LA MIGRATION\n";
echo "=========================================================\n\n";

if ($errors > 0) {
    echo "❌ Migration terminée avec $errors erreur(s)\n";
    if ($warnings > 0) {
        echo "⚠️  $warnings avertissement(s)\n";
    }
    echo "\n⚠️  Veuillez corriger les erreurs et relancer la migration.\n\n";
} else {
    echo "✅ Migration réussie !\n";
    if ($warnings > 0) {
        echo "⚠️  $warnings avertissement(s) (déjà configuré)\n";
    }
    echo "\n";
    
    // Afficher les statistiques
    echo "📈 Statistiques :\n";
    echo "----------------\n";
    
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(allow_multiple_choices) as with_multiple,
            SUM(CASE WHEN type = 'date' THEN 1 ELSE 0 END) as dates,
            SUM(CASE WHEN status = 'ouvert' THEN 1 ELSE 0 END) as ouverts
        FROM polls
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "   Total de sondages : " . $stats['total'] . "\n";
    echo "   Avec choix multiple : " . $stats['with_multiple'] . "\n";
    echo "   Sondages de dates : " . $stats['dates'] . "\n";
    echo "   Sondages ouverts : " . $stats['ouverts'] . "\n\n";
    
    echo "🎯 Prochaines étapes :\n";
    echo "--------------------\n";
    echo "1. Accédez à sondages_admin.php\n";
    echo "2. Éditez un sondage avec le bouton '✏️ Éditer'\n";
    echo "3. Activez le choix multiple si nécessaire\n";
    echo "4. Testez le vote avec plusieurs options\n\n";
    
    echo "📚 Documentation :\n";
    echo "----------------\n";
    echo "   Voir GUIDE_CHOIX_MULTIPLE.md pour plus d'informations\n\n";
}

// ====================================================================
// STRUCTURE DES TABLES
// ====================================================================
echo "📋 Structure des tables après migration :\n";
echo "=========================================================\n\n";

echo "Table 'polls' :\n";
echo "---------------\n";
$columns = $pdo->query("SHOW COLUMNS FROM polls");
while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
    $highlight = $col['Field'] === 'allow_multiple_choices' ? ' ← NOUVEAU' : '';
    echo sprintf("   %-30s %-20s%s\n", 
        $col['Field'], 
        $col['Type'],
        $highlight
    );
}

echo "\nTable 'poll_votes' - Index :\n";
echo "----------------------------\n";
$keys = $pdo->query("SHOW KEYS FROM poll_votes");
$displayed = [];
while ($key = $keys->fetch(PDO::FETCH_ASSOC)) {
    $key_name = $key['Key_name'];
    if (!in_array($key_name, $displayed)) {
        $displayed[] = $key_name;
        $unique = $key['Non_unique'] == 0 ? 'UNIQUE' : 'INDEX';
        $highlight = $key_name === 'idx_poll_user' ? ' ← NOUVEAU' : '';
        echo sprintf("   %-30s %-10s%s\n", 
            $key_name, 
            $unique,
            $highlight
        );
    }
}

echo "\n";

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
    echo "<br><br><a href='../sondages_admin.php' style='padding: 10px 20px; background: #004b8d; color: white; text-decoration: none; border-radius: 5px;'>← Retour aux sondages</a>";
}
