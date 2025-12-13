<?php
/**
 * Gestionnaire de configuration du club
 * 
 * Charge la configuration depuis la base de données
 * Fournit des fonctions helper pour accéder aux paramètres
 */

// Cache des paramètres pour éviter les requêtes multiples
$_CLUB_CONFIG_CACHE = null;

/**
 * Charge tous les paramètres du club depuis la BDD
 * @return array Configuration complète du club
 */
function load_club_config() {
    global $_CLUB_CONFIG_CACHE, $pdo;
    
    // Retourner le cache si déjà chargé
    if ($_CLUB_CONFIG_CACHE !== null) {
        return $_CLUB_CONFIG_CACHE;
    }
    
    // Charger depuis la BDD
    try {
        // Vérifier que la table existe
        $tableExists = $pdo->query("SHOW TABLES LIKE 'club_settings'")->rowCount() > 0;
        
        if (!$tableExists) {
            error_log("Table club_settings n'existe pas - utilisation de la config par défaut");
            $_CLUB_CONFIG_CACHE = get_default_club_config();
            return $_CLUB_CONFIG_CACHE;
        }
        
        $stmt = $pdo->query("SELECT setting_key, setting_value, setting_type FROM club_settings");
        $settings = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['setting_key'];
            $value = $row['setting_value'];
            $type = $row['setting_type'];
            
            // Convertir selon le type
            switch ($type) {
                case 'integer':
                    $value = (int)$value;
                    break;
                case 'float':
                    $value = (float)$value;
                    break;
                case 'boolean':
                    $value = (bool)$value || $value === '1' || $value === 'true';
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
                // 'string' par défaut
            }
            
            $settings[$key] = $value;
        }
        
        $_CLUB_CONFIG_CACHE = $settings;
        return $settings;
        
    } catch (PDOException $e) {
        error_log("Erreur chargement config club: " . $e->getMessage());
        // Retourner une config par défaut minimale
        return get_default_club_config();
    }
}

/**
 * Configuration par défaut (fallback)
 */
function get_default_club_config() {
    return [
        'club_name' => 'Mon Club ULM',
        'club_short_name' => 'Club ULM',
        'club_city' => '',
        'club_department' => '',
        'club_region' => '',
        'club_home_base' => '',
        'club_email_from' => 'contact@monclub.fr',
        'club_email_reply_to' => 'contact@monclub.fr',
        'club_phone' => '',
        'club_website' => '',
        'club_facebook' => '',
        'club_address_line1' => '',
        'club_address_line2' => '',
        'club_address_postal' => '',
        'club_logo_path' => 'assets/img/logo.png',
        'club_logo_alt' => 'Logo Club',
        'club_logo_height' => 50,
        'club_cover_image' => 'assets/img/cover.jpg',
        'club_color_primary' => '#004b8d',
        'club_color_secondary' => '#00a0c6',
        'club_color_accent' => '#0078b8',
        'module_events' => true,
        'module_polls' => true,
        'module_proposals' => true,
        'module_changelog' => true,
        'module_stats' => true,
        'module_basulm_import' => true,
        'module_weather' => true,
        'sorties_per_month' => 2,
        'inscription_min_days' => 3,
        'notification_days_before' => 7,
        'priority_double_inscription' => true,
        'max_photo_size' => 5242880,
        'max_attachment_size' => 10485760,
        'max_event_cover_size' => 3145728,
        'weather_api_key' => '',
        'weather_api_provider' => 'openweathermap',
        'map_default_center_lat' => 46.603354,
        'map_default_center_lng' => 1.888334,
        'map_default_zoom' => 6,
    ];
}

/**
 * Récupère une valeur de configuration
 * @param string $key Clé du paramètre
 * @param mixed $default Valeur par défaut si non trouvée
 * @return mixed Valeur du paramètre
 */
function get_club_setting($key, $default = null) {
    $config = load_club_config();
    return $config[$key] ?? $default;
}

/**
 * Met à jour une valeur de configuration
 * @param string $key Clé du paramètre
 * @param mixed $value Nouvelle valeur
 * @param int $userId ID de l'utilisateur effectuant la modification
 * @return bool Succès de la mise à jour
 */
function update_club_setting($key, $value, $userId = null) {
    global $pdo, $_CLUB_CONFIG_CACHE;
    
    try {
        // Déterminer le type
        $type = 'string';
        if (is_int($value)) $type = 'integer';
        elseif (is_float($value)) $type = 'float';
        elseif (is_bool($value)) {
            $type = 'boolean';
            $value = $value ? '1' : '0';
        }
        elseif (is_array($value)) {
            $type = 'json';
            $value = json_encode($value);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO club_settings (setting_key, setting_value, setting_type, updated_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_type = VALUES(setting_type),
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([$key, $value, $type, $userId]);
        
        // Invalider le cache
        $_CLUB_CONFIG_CACHE = null;
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Erreur mise à jour config: " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour plusieurs valeurs en une fois
 * @param array $settings Tableau clé => valeur
 * @param int $userId ID de l'utilisateur effectuant la modification
 * @return bool Succès de la mise à jour
 */
function update_club_settings($settings, $userId = null) {
    global $_CLUB_CONFIG_CACHE;
    
    $success = true;
    foreach ($settings as $key => $value) {
        if (!update_club_setting($key, $value, $userId)) {
            $success = false;
        }
    }
    
    // Invalider le cache
    $_CLUB_CONFIG_CACHE = null;
    
    return $success;
}

/**
 * Vérifie si un module est activé
 * @param string $moduleName Nom du module (events, polls, proposals, etc.)
 * @return bool Module activé ou non
 */
function is_module_enabled($moduleName) {
    $key = 'module_' . strtolower($moduleName);
    return (bool)get_club_setting($key, false);
}

/**
 * Récupère toutes les couleurs du club
 * @return array ['primary' => '#xxx', 'secondary' => '#xxx', 'accent' => '#xxx']
 */
function get_club_colors() {
    return [
        'primary' => get_club_setting('club_color_primary', '#004b8d'),
        'secondary' => get_club_setting('club_color_secondary', '#00a0c6'),
        'accent' => get_club_setting('club_color_accent', '#0078b8'),
    ];
}

/**
 * Récupère les coordonnées de la carte
 * @return array ['lat' => float, 'lng' => float, 'zoom' => int]
 */
function get_club_map_center() {
    return [
        'lat' => (float)get_club_setting('map_default_center_lat', 46.603354),
        'lng' => (float)get_club_setting('map_default_center_lng', 1.888334),
        'zoom' => (int)get_club_setting('map_default_zoom', 6),
    ];
}

/**
 * Récupère les informations complètes du club
 * @return array Toutes les infos du club
 */
function get_club_info() {
    return [
        'name' => get_club_setting('club_name', ''),
        'short_name' => get_club_setting('club_short_name', ''),
        'city' => get_club_setting('club_city', ''),
        'department' => get_club_setting('club_department', ''),
        'region' => get_club_setting('club_region', ''),
        'home_base' => get_club_setting('club_home_base', ''),
        'email' => get_club_setting('club_email_from', ''),
        'phone' => get_club_setting('club_phone', ''),
        'website' => get_club_setting('club_website', ''),
        'facebook' => get_club_setting('club_facebook', ''),
        'address' => [
            'line1' => get_club_setting('club_address_line1', ''),
            'line2' => get_club_setting('club_address_line2', ''),
            'postal' => get_club_setting('club_address_postal', ''),
        ],
        'logo' => [
            'path' => get_club_setting('club_logo_path', 'assets/img/logo.png'),
            'alt' => get_club_setting('club_logo_alt', 'Logo Club'),
            'height' => get_club_setting('club_logo_height', 50),
        ],
        'cover' => get_club_setting('club_cover_image', 'assets/img/cover.jpg'),
        'colors' => get_club_colors(),
    ];
}

// Charger immédiatement la configuration
$CLUB_CONFIG = load_club_config();

// Définir les constantes pour rétrocompatibilité avec l'ancien système
if (!defined('CLUB_NAME')) {
    define('CLUB_NAME', get_club_setting('club_name', 'Mon Club ULM'));
    define('CLUB_SHORT_NAME', get_club_setting('club_short_name', 'Club ULM'));
    define('CLUB_CITY', get_club_setting('club_city', ''));
    define('CLUB_DEPARTMENT', get_club_setting('club_department', ''));
    define('CLUB_REGION', get_club_setting('club_region', ''));
    define('CLUB_HOME_BASE', get_club_setting('club_home_base', ''));
    
    define('CLUB_EMAIL_FROM', get_club_setting('club_email_from', 'contact@monclub.fr'));
    define('CLUB_EMAIL_REPLY_TO', get_club_setting('club_email_reply_to', get_club_setting('club_email_from', 'contact@monclub.fr')));
    define('CLUB_PHONE', get_club_setting('club_phone', ''));
    define('CLUB_WEBSITE', get_club_setting('club_website', ''));
    define('CLUB_FACEBOOK', get_club_setting('club_facebook', ''));
    
    define('CLUB_ADDRESS_LINE1', get_club_setting('club_address_line1', ''));
    define('CLUB_ADDRESS_LINE2', get_club_setting('club_address_line2', ''));
    define('CLUB_ADDRESS_POSTAL', get_club_setting('club_address_postal', ''));
    
    define('CLUB_LOGO_PATH', get_club_setting('club_logo_path', 'assets/img/logo.png'));
    define('CLUB_LOGO_ALT', get_club_setting('club_logo_alt', 'Logo Club'));
    define('CLUB_LOGO_HEIGHT', get_club_setting('club_logo_height', 50));
    define('CLUB_COVER_IMAGE', get_club_setting('club_cover_image', 'assets/img/cover.jpg'));
    
    define('CLUB_COLOR_PRIMARY', get_club_setting('club_color_primary', '#004b8d'));
    define('CLUB_COLOR_SECONDARY', get_club_setting('club_color_secondary', '#00a0c6'));
    define('CLUB_COLOR_ACCENT', get_club_setting('club_color_accent', '#0078b8'));
    
    define('CLUB_MODULE_EVENTS', get_club_setting('module_events', true));
    define('CLUB_MODULE_POLLS', get_club_setting('module_polls', true));
    define('CLUB_MODULE_PROPOSALS', get_club_setting('module_proposals', true));
    define('CLUB_MODULE_CHANGELOG', get_club_setting('module_changelog', true));
    define('CLUB_MODULE_STATS', get_club_setting('module_stats', true));
    define('CLUB_MODULE_BASULM_IMPORT', get_club_setting('module_basulm_import', true));
    define('CLUB_MODULE_WEATHER', get_club_setting('module_weather', true));
    
    define('CLUB_SORTIES_PER_MONTH', get_club_setting('sorties_per_month', 2));
    define('CLUB_INSCRIPTION_MIN_DAYS', get_club_setting('inscription_min_days', 3));
    define('CLUB_NOTIFICATION_DAYS_BEFORE', get_club_setting('notification_days_before', 7));
    define('CLUB_PRIORITY_DOUBLE_INSCRIPTION', get_club_setting('priority_double_inscription', true));
    
    define('CLUB_MAX_PHOTO_SIZE', get_club_setting('max_photo_size', 5 * 1024 * 1024));
    define('CLUB_MAX_ATTACHMENT_SIZE', get_club_setting('max_attachment_size', 10 * 1024 * 1024));
    define('CLUB_MAX_EVENT_COVER_SIZE', get_club_setting('max_event_cover_size', 3 * 1024 * 1024));
    
    define('CLUB_ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    define('CLUB_ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
    
    define('CLUB_WEATHER_API_KEY', get_club_setting('weather_api_key', ''));
    define('CLUB_WEATHER_API_PROVIDER', get_club_setting('weather_api_provider', 'openweathermap'));
    
    define('CLUB_MAP_DEFAULT_CENTER_LAT', get_club_setting('map_default_center_lat', 46.603354));
    define('CLUB_MAP_DEFAULT_CENTER_LNG', get_club_setting('map_default_center_lng', 1.888334));
    define('CLUB_MAP_DEFAULT_ZOOM', get_club_setting('map_default_zoom', 6));
}
