#!/usr/bin/env php
<?php
/**
 * Script de migration : Importe les valeurs de club_config.php vers la base de données
 * Usage: php setup/import_config_to_db.php
 */

require_once __DIR__ . '/../config.php';

echo "==========================================\n";
echo " Migration de club_config.php vers BDD\n";
echo "==========================================\n\n";

// Vérifier si la table existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'club_settings'");
    if ($stmt->rowCount() === 0) {
        echo "❌ Erreur : La table 'club_settings' n'existe pas.\n";
        echo "   Exécutez d'abord : mysql < setup/migration_config_to_db.sql\n";
        exit(1);
    }
} catch (PDOException $e) {
    echo "❌ Erreur de connexion : " . $e->getMessage() . "\n";
    exit(1);
}

// Lire le fichier club_config.php actuel ou backup
$configFile = __DIR__ . '/../club_config.php.backup';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/../club_config.php';
}

if (!file_exists($configFile)) {
    echo "❌ Aucun fichier de configuration trouvé.\n";
    exit(1);
}

echo "📄 Lecture de : $configFile\n\n";
$content = file_get_contents($configFile);

// Fonction pour extraire une valeur
function extractValue($content, $constName, $default = '') {
    // String entre quotes simples
    if (preg_match("/define\('$constName',\s*'([^']*)'\);/", $content, $matches)) {
        return stripslashes($matches[1]);
    }
    // String entre quotes doubles
    if (preg_match("/define\('$constName',\s*\"([^\"]*)\"\);/", $content, $matches)) {
        return stripslashes($matches[1]);
    }
    // Boolean ou nombre
    if (preg_match("/define\('$constName',\s*([^)]+)\);/", $content, $matches)) {
        return trim($matches[1]);
    }
    return $default;
}

// Extraire toutes les valeurs
$settings = [
    'club_name' => extractValue($content, 'CLUB_NAME'),
    'club_short_name' => extractValue($content, 'CLUB_SHORT_NAME'),
    'club_city' => extractValue($content, 'CLUB_CITY'),
    'club_department' => extractValue($content, 'CLUB_DEPARTMENT'),
    'club_region' => extractValue($content, 'CLUB_REGION'),
    'club_home_base' => extractValue($content, 'CLUB_HOME_BASE'),
    'club_email_from' => extractValue($content, 'CLUB_EMAIL_FROM'),
    'club_email_reply_to' => extractValue($content, 'CLUB_EMAIL_REPLY_TO'),
    'club_phone' => extractValue($content, 'CLUB_PHONE'),
    'club_website' => extractValue($content, 'CLUB_WEBSITE'),
    'club_facebook' => extractValue($content, 'CLUB_FACEBOOK'),
    'club_address_line1' => extractValue($content, 'CLUB_ADDRESS_LINE1'),
    'club_address_line2' => extractValue($content, 'CLUB_ADDRESS_LINE2'),
    'club_address_postal' => extractValue($content, 'CLUB_ADDRESS_POSTAL'),
    'club_logo_path' => extractValue($content, 'CLUB_LOGO_PATH', 'assets/img/logo.png'),
    'club_logo_alt' => extractValue($content, 'CLUB_LOGO_ALT', 'Logo Club'),
    'club_logo_height' => (int)extractValue($content, 'CLUB_LOGO_HEIGHT', '50'),
    'club_cover_image' => extractValue($content, 'CLUB_COVER_IMAGE', 'assets/img/cover.jpg'),
    'club_color_primary' => extractValue($content, 'CLUB_COLOR_PRIMARY', '#004b8d'),
    'club_color_secondary' => extractValue($content, 'CLUB_COLOR_SECONDARY', '#00a0c6'),
    'club_color_accent' => extractValue($content, 'CLUB_COLOR_ACCENT', '#0078b8'),
    'sorties_per_month' => (int)extractValue($content, 'CLUB_SORTIES_PER_MONTH', '2'),
    'inscription_min_days' => (int)extractValue($content, 'CLUB_INSCRIPTION_MIN_DAYS', '3'),
    'notification_days_before' => (int)extractValue($content, 'CLUB_NOTIFICATION_DAYS_BEFORE', '7'),
    'priority_double_inscription' => extractValue($content, 'CLUB_PRIORITY_DOUBLE_INSCRIPTION', 'true') === 'true' ? 1 : 0,
    'weather_api_key' => extractValue($content, 'CLUB_WEATHER_API_KEY', ''),
    'weather_api_provider' => extractValue($content, 'CLUB_WEATHER_API_PROVIDER', 'openweathermap'),
    'map_default_center_lat' => (float)extractValue($content, 'CLUB_MAP_DEFAULT_CENTER_LAT', '46.603354'),
    'map_default_center_lng' => (float)extractValue($content, 'CLUB_MAP_DEFAULT_CENTER_LNG', '1.888334'),
    'map_default_zoom' => (int)extractValue($content, 'CLUB_MAP_DEFAULT_ZOOM', '6'),
    'module_events' => extractValue($content, 'CLUB_MODULE_EVENTS', 'true') === 'true' ? 1 : 0,
    'module_polls' => extractValue($content, 'CLUB_MODULE_POLLS', 'true') === 'true' ? 1 : 0,
    'module_proposals' => extractValue($content, 'CLUB_MODULE_PROPOSALS', 'true') === 'true' ? 1 : 0,
    'module_changelog' => extractValue($content, 'CLUB_MODULE_CHANGELOG', 'true') === 'true' ? 1 : 0,
    'module_stats' => extractValue($content, 'CLUB_MODULE_STATS', 'true') === 'true' ? 1 : 0,
    'module_basulm_import' => extractValue($content, 'CLUB_MODULE_BASULM_IMPORT', 'true') === 'true' ? 1 : 0,
    'module_weather' => extractValue($content, 'CLUB_MODULE_WEATHER', 'true') === 'true' ? 1 : 0,
];

echo "📊 Valeurs extraites :\n";
echo "  - Club : " . $settings['club_name'] . "\n";
echo "  - Ville : " . $settings['club_city'] . "\n";
echo "  - Email : " . $settings['club_email_from'] . "\n";
echo "  - Couleur primaire : " . $settings['club_color_primary'] . "\n";
echo "\n";

// Demander confirmation
echo "⚠️  Voulez-vous importer ces valeurs dans la base de données ?\n";
echo "   Cela écrasera les valeurs existantes.\n";
echo "   Taper 'oui' pour continuer : ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim($line) !== 'oui') {
    echo "❌ Annulé.\n";
    exit(0);
}

echo "\n🔄 Import en cours...\n\n";

// Préparer la requête d'insertion
$sql = "INSERT INTO club_settings (setting_key, setting_value, setting_type, category) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";

$stmt = $pdo->prepare($sql);

$count = 0;
$errors = 0;

foreach ($settings as $key => $value) {
    // Déterminer le type
    $type = 'string';
    $category = 'general';
    
    if (is_int($value)) {
        $type = 'integer';
    } elseif (is_float($value)) {
        $type = 'float';
    } elseif (is_bool($value) || $value === 0 || $value === 1) {
        $type = 'boolean';
        $value = $value ? '1' : '0';
    }
    
    // Déterminer la catégorie
    if (strpos($key, 'club_name') !== false || strpos($key, 'club_city') !== false || strpos($key, 'club_home') !== false) {
        $category = 'info';
    } elseif (strpos($key, 'email') !== false || strpos($key, 'phone') !== false || strpos($key, 'website') !== false) {
        $category = 'contact';
    } elseif (strpos($key, 'address') !== false) {
        $category = 'address';
    } elseif (strpos($key, 'color') !== false || strpos($key, 'logo') !== false || strpos($key, 'cover') !== false) {
        $category = 'branding';
    } elseif (strpos($key, 'module_') !== false) {
        $category = 'modules';
    } elseif (strpos($key, 'sorties') !== false || strpos($key, 'inscription') !== false || strpos($key, 'notification') !== false || strpos($key, 'priority') !== false) {
        $category = 'rules';
    } elseif (strpos($key, 'max_') !== false) {
        $category = 'uploads';
    } elseif (strpos($key, 'weather') !== false || strpos($key, 'map') !== false) {
        $category = 'integrations';
    }
    
    try {
        $stmt->execute([$key, $value, $type, $category]);
        echo "  ✅ $key = " . (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) . "\n";
        $count++;
    } catch (PDOException $e) {
        echo "  ❌ Erreur pour $key : " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n==========================================\n";
echo "✅ Import terminé !\n";
echo "   $count paramètre(s) importé(s)\n";
if ($errors > 0) {
    echo "   ⚠️  $errors erreur(s)\n";
}
echo "==========================================\n\n";

echo "🎉 Vous pouvez maintenant :\n";
echo "   1. Aller sur /config_generale.php pour vérifier\n";
echo "   2. Tester votre site pour voir si tout fonctionne\n";
echo "   3. Sauvegarder club_config.php.backup si besoin de rollback\n\n";
