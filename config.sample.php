<?php
/**
 * GESTNAV - Fichier de configuration
 * 
 * IMPORTANT : Renommer ce fichier en config.php après installation
 * et modifier les valeurs selon votre environnement
 */

// ============================================================================
// BASE DE DONNÉES
// ============================================================================

define('DB_HOST', 'localhost');          // Hôte MySQL (généralement 'localhost')
define('DB_NAME', 'gestnav');            // Nom de la base de données
define('DB_USER', 'gestnav_user');       // Utilisateur MySQL
define('DB_PASS', 'CHANGEZ_MOI');        // Mot de passe MySQL

// ============================================================================
// APPLICATION
// ============================================================================

define('BASE_URL', 'https://votre-domaine.fr/gestnav');  // URL de base (sans slash final)
define('GESTNAV_VERSION', '2.4.5');                       // Version de l'application

// ============================================================================
// ENVIRONNEMENT
// ============================================================================

define('ENVIRONMENT', 'production');  // 'development' ou 'production'

// Mode debug (afficher les erreurs PHP)
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// ============================================================================
// SÉCURITÉ
// ============================================================================

// Clé de chiffrement pour les sessions (générer avec bin2hex(random_bytes(32)))
define('ENCRYPTION_KEY', 'CHANGEZ_MOI_GENERER_ALEATOIREMENT');

// Durée de session (en secondes) - 2 heures par défaut
define('SESSION_LIFETIME', 7200);

// ============================================================================
// CONNEXION À LA BASE DE DONNÉES
// ============================================================================

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    if (ENVIRONMENT === 'development') {
        die("Erreur de connexion à la base de données : " . $e->getMessage());
    } else {
        error_log("Database connection error: " . $e->getMessage());
        die("Une erreur est survenue. Veuillez contacter l'administrateur.");
    }
}

// ============================================================================
// SESSION
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    // Forcer HTTPS en production
    if (ENVIRONMENT === 'production') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

// ============================================================================
// CONFIGURATION DU CLUB (chargée depuis club_config.php)
// ============================================================================

$clubConfigFile = __DIR__ . '/club_config.php';
if (file_exists($clubConfigFile)) {
    require_once $clubConfigFile;
} else {
    // Valeurs par défaut si club_config.php n'existe pas encore
    if (!defined('CLUB_NAME')) define('CLUB_NAME', 'Mon Club ULM');
    if (!defined('CLUB_SHORT_NAME')) define('CLUB_SHORT_NAME', 'CLUB ULM');
    if (!defined('CLUB_EMAIL_FROM')) define('CLUB_EMAIL_FROM', 'noreply@monclub.fr');
    if (!defined('CLUB_LOGO_PATH')) define('CLUB_LOGO_PATH', 'assets/img/logo.png');
    if (!defined('CLUB_COLOR_PRIMARY')) define('CLUB_COLOR_PRIMARY', '#004b8d');
    if (!defined('CLUB_COLOR_SECONDARY')) define('CLUB_COLOR_SECONDARY', '#00a0c6');
}

// ============================================================================
// TIMEZONE
// ============================================================================

date_default_timezone_set('Europe/Paris');

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Rediriger vers une URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Échapper du HTML
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Formater une date
 */
function format_date($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Formater une date et heure
 */
function format_datetime($datetime, $format = 'd/m/Y à H:i') {
    if (empty($datetime)) return '';
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    return date($format, $timestamp);
}
