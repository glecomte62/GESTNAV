<?php
/**
 * Migration : Modifier la contrainte UNIQUE de poll_votes pour autoriser le choix multiple
 * 
 * Cette migration supprime la contrainte qui empêche un utilisateur de voter
 * pour plusieurs options dans un même sondage.
 * 
 * Usage: php setup/fix_poll_votes_constraint.php
 * Ou visiter: https://gestnav.clubulmevasion.fr/setup/fix_poll_votes_constraint.php
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

echo "🔧 Migration : Modification de la contrainte UNIQUE sur poll_votes\n";
echo "==================================================================\n\n";

try {
    // Vérifier si la contrainte existe
    $check = $pdo->query("SHOW KEYS FROM poll_votes WHERE Key_name = 'uk_user_poll'");
    
    if ($check->rowCount() > 0) {
        echo "ℹ️  Contrainte UNIQUE 'uk_user_poll' détectée\n";
        echo "   Cette contrainte empêche le choix multiple.\n\n";
        
        // Supprimer la contrainte UNIQUE
        $pdo->exec("ALTER TABLE poll_votes DROP INDEX uk_user_poll");
        echo "✅ Contrainte UNIQUE supprimée avec succès\n\n";
        
        // Ajouter un index pour les performances (sans contrainte UNIQUE)
        $pdo->exec("CREATE INDEX idx_poll_user ON poll_votes(poll_id, user_id)");
        echo "✅ Index de performance ajouté (idx_poll_user)\n\n";
        
        echo "📝 Changements appliqués :\n";
        echo "   - Ancienne contrainte : UNIQUE(poll_id, user_id) → Un seul vote par sondage\n";
        echo "   - Nouvelle configuration : INDEX(poll_id, user_id) → Votes multiples autorisés\n\n";
        
    } else {
        echo "✅ La contrainte UNIQUE 'uk_user_poll' n'existe pas\n";
        echo "ℹ️  Vérification de l'index de performance...\n\n";
        
        $check_index = $pdo->query("SHOW KEYS FROM poll_votes WHERE Key_name = 'idx_poll_user'");
        
        if ($check_index->rowCount() === 0) {
            $pdo->exec("CREATE INDEX idx_poll_user ON poll_votes(poll_id, user_id)");
            echo "✅ Index de performance ajouté\n\n";
        } else {
            echo "✅ Index de performance déjà présent\n\n";
        }
    }
    
    // Afficher la structure actuelle
    echo "📋 Structure actuelle de la table 'poll_votes' :\n";
    echo "=================================================\n";
    
    $columns = $pdo->query("SHOW COLUMNS FROM poll_votes");
    while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("   %-20s %-20s %s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
        );
    }
    
    echo "\n📑 Index et contraintes :\n";
    echo "========================\n";
    
    $keys = $pdo->query("SHOW KEYS FROM poll_votes");
    $displayed = [];
    while ($key = $keys->fetch(PDO::FETCH_ASSOC)) {
        $key_name = $key['Key_name'];
        if (!in_array($key_name, $displayed)) {
            $displayed[] = $key_name;
            $unique = $key['Non_unique'] == 0 ? 'UNIQUE' : 'INDEX';
            echo sprintf("   %-30s %-10s (%s)\n", 
                $key_name, 
                $unique,
                $key['Column_name']
            );
        }
    }
    
    echo "\n✅ Migration terminée avec succès !\n";
    echo "\n🎯 Actions suivantes :\n";
    echo "   1. Testez le vote avec choix multiple\n";
    echo "   2. Vérifiez que plusieurs votes peuvent être enregistrés par utilisateur\n";
    echo "   3. Consultez les résultats dans sondages_admin.php\n\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la migration :\n";
    echo "   " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), "Can't DROP") !== false) {
        echo "\nℹ️  La contrainte n'existe peut-être pas ou a déjà été supprimée.\n";
        echo "   Vérifiez la structure avec : SHOW KEYS FROM poll_votes;\n";
    }
    
    exit(1);
}

if (php_sapi_name() !== 'cli') {
    echo "\n\n<br><br><a href='../sondages_admin.php'>← Retour aux sondages</a>";
}
