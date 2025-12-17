<?php
/**
 * Script de diagnostic pour débugger config_generale.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnostic</title></head><body>";
echo "<h1>🔍 Diagnostic GESTNAV</h1>";

// 1. Test connexion BDD
echo "<h2>1. Connexion base de données</h2>";
try {
    require_once __DIR__ . '/config.php';
    echo "✅ <strong>config.php</strong> chargé<br>";
    echo "✅ Connexion PDO OK<br>";
} catch (Exception $e) {
    echo "❌ Erreur : " . htmlspecialchars($e->getMessage()) . "<br>";
    exit;
}

// 2. Test table club_settings
echo "<h2>2. Table club_settings</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'club_settings'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Table <strong>club_settings</strong> existe<br>";
        
        $count = $pdo->query("SELECT COUNT(*) FROM club_settings")->fetchColumn();
        echo "✅ Nombre de paramètres : <strong>$count</strong><br>";
        
        if ($count > 0) {
            $sample = $pdo->query("SELECT setting_key, setting_value FROM club_settings LIMIT 3")->fetchAll();
            echo "<ul>";
            foreach ($sample as $row) {
                echo "<li><code>" . htmlspecialchars($row['setting_key']) . "</code> = " . htmlspecialchars(substr($row['setting_value'], 0, 50)) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "⚠️ Table vide - exécuter migration<br>";
        }
    } else {
        echo "❌ Table <strong>club_settings</strong> n'existe pas<br>";
        echo "👉 Exécuter : <a href='setup/run_migration.php'>setup/run_migration.php</a><br>";
    }
} catch (PDOException $e) {
    echo "❌ Erreur : " . htmlspecialchars($e->getMessage()) . "<br>";
}

// 3. Test club_config_manager.php
echo "<h2>3. Gestionnaire de configuration</h2>";
try {
    require_once __DIR__ . '/utils/club_config_manager.php';
    echo "✅ <strong>club_config_manager.php</strong> chargé<br>";
    
    $clubName = get_club_setting('club_name', 'Défaut');
    echo "✅ get_club_setting() fonctionne<br>";
    echo "→ club_name = <strong>" . htmlspecialchars($clubName) . "</strong><br>";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// 4. Test auth.php
echo "<h2>4. Authentification</h2>";
try {
    if (file_exists(__DIR__ . '/auth.php')) {
        require_once __DIR__ . '/auth.php';
        echo "✅ <strong>auth.php</strong> chargé<br>";
        
        if (isset($_SESSION['user_id'])) {
            echo "✅ Session active : user_id = " . $_SESSION['user_id'] . "<br>";
            echo "→ Nom : " . htmlspecialchars($_SESSION['user_nom'] ?? '') . " " . htmlspecialchars($_SESSION['user_prenom'] ?? '') . "<br>";
            echo "→ Role : " . htmlspecialchars($_SESSION['role'] ?? '') . "<br>";
        } else {
            echo "⚠️ Pas de session active - redirection vers login attendue<br>";
        }
    } else {
        echo "⚠️ auth.php non trouvé<br>";
    }
} catch (Exception $e) {
    echo "❌ Erreur : " . htmlspecialchars($e->getMessage()) . "<br>";
}

// 5. Test club_config.php
echo "<h2>5. Configuration club</h2>";
try {
    if (!defined('CLUB_NAME')) {
        require_once __DIR__ . '/club_config.php';
    }
    echo "✅ <strong>club_config.php</strong> chargé<br>";
    
    if (defined('CLUB_NAME')) {
        echo "✅ CLUB_NAME défini = <strong>" . htmlspecialchars(CLUB_NAME) . "</strong><br>";
    } else {
        echo "⚠️ CLUB_NAME non défini<br>";
    }
    
    if (defined('CLUB_COLOR_PRIMARY')) {
        echo "✅ CLUB_COLOR_PRIMARY = " . htmlspecialchars(CLUB_COLOR_PRIMARY) . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur : " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// 6. Test config_generale.php
echo "<h2>6. Test chargement config_generale.php</h2>";
try {
    ob_start();
    include __DIR__ . '/config_generale.php';
    $output = ob_get_clean();
    
    if (strlen($output) > 0) {
        echo "✅ config_generale.php génère du contenu (" . strlen($output) . " octets)<br>";
        echo "<details><summary>Voir le contenu</summary>";
        echo "<iframe srcdoc='" . htmlspecialchars($output) . "' style='width:100%;height:600px;border:1px solid #ccc;'></iframe>";
        echo "</details>";
    } else {
        echo "⚠️ config_generale.php ne génère aucun contenu<br>";
    }
} catch (Exception $e) {
    echo "❌ Erreur lors du chargement : " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<h2>✅ Diagnostic terminé</h2>";
echo "<p><a href='config_generale.php'>Retourner sur config_generale.php</a></p>";
echo "</body></html>";
