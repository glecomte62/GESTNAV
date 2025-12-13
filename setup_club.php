#!/usr/bin/env php
<?php
/**
 * GESTNAV - Script d'installation interactif pour nouveau club
 * 
 * Ce script guide pas à pas dans la configuration de GESTNAV
 * pour un nouveau club ULM.
 * 
 * Usage: php setup_club.php
 */

// Couleurs pour le terminal
class Colors {
    public static $HEADER = "\033[95m";
    public static $OKBLUE = "\033[94m";
    public static $OKCYAN = "\033[96m";
    public static $OKGREEN = "\033[92m";
    public static $WARNING = "\033[93m";
    public static $FAIL = "\033[91m";
    public static $ENDC = "\033[0m";
    public static $BOLD = "\033[1m";
    public static $UNDERLINE = "\033[4m";
}

function println($text = "", $color = "") {
    echo $color . $text . Colors::$ENDC . PHP_EOL;
}

function print_header($text) {
    println();
    println(str_repeat("=", 70), Colors::$HEADER);
    println($text, Colors::$HEADER . Colors::$BOLD);
    println(str_repeat("=", 70), Colors::$HEADER);
    println();
}

function print_section($text) {
    println();
    println("📋 " . $text, Colors::$OKCYAN . Colors::$BOLD);
    println(str_repeat("-", 70), Colors::$OKCYAN);
}

function print_success($text) {
    println("✅ " . $text, Colors::$OKGREEN);
}

function print_error($text) {
    println("❌ " . $text, Colors::$FAIL);
}

function print_warning($text) {
    println("⚠️  " . $text, Colors::$WARNING);
}

function prompt($question, $default = "") {
    if ($default) {
        echo Colors::$OKBLUE . $question . " [" . $default . "]: " . Colors::$ENDC;
    } else {
        echo Colors::$OKBLUE . $question . ": " . Colors::$ENDC;
    }
    
    $input = trim(fgets(STDIN));
    return $input ?: $default;
}

function prompt_yes_no($question, $default = true) {
    $defaultStr = $default ? "O/n" : "o/N";
    $response = strtolower(prompt($question . " (" . $defaultStr . ")", $default ? "o" : "n"));
    return in_array($response, ['o', 'oui', 'y', 'yes', '1']);
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function validate_oaci($code) {
    return preg_match('/^[A-Z]{4}$/', strtoupper($code));
}

function validate_color($color) {
    return preg_match('/^#?[0-9A-Fa-f]{6}$/', $color);
}

// ============================================================================
// DÉBUT DU SCRIPT
// ============================================================================

clear();
print_header("🛩️  GESTNAV - Installation pour nouveau club ULM");

println("Bienvenue dans l'assistant d'installation de GESTNAV !");
println("Ce script va vous guider dans la configuration de votre club.");
println();
println("Durée estimée : 5-10 minutes", Colors::$WARNING);
println();

if (!prompt_yes_no("Voulez-vous continuer ?")) {
    println("Installation annulée.", Colors::$WARNING);
    exit(0);
}

$config = [];

// ============================================================================
// 1. INFORMATIONS DU CLUB
// ============================================================================

print_section("1. Informations du club");

$config['CLUB_NAME'] = prompt("Nom complet du club", "Club ULM");
$config['CLUB_SHORT_NAME'] = prompt("Nom court / Acronyme", substr($config['CLUB_NAME'], 0, 20));
$config['CLUB_CITY'] = prompt("Ville", "");
$config['CLUB_DEPARTMENT'] = prompt("Département (ex: Pas-de-Calais (62))", "");
$config['CLUB_REGION'] = prompt("Région", "");

do {
    $oaci = strtoupper(prompt("Code OACI de la base principale (ex: LFQJ)", ""));
    if (!validate_oaci($oaci)) {
        print_error("Code OACI invalide. Format attendu : 4 lettres majuscules (ex: LFXX)");
    }
} while (!validate_oaci($oaci));
$config['CLUB_HOME_BASE'] = $oaci;

print_success("Informations du club enregistrées");

// ============================================================================
// 2. CONTACT ET COMMUNICATION
// ============================================================================

print_section("2. Contact et communication");

do {
    $email = prompt("Email de contact principal", "contact@" . strtolower(str_replace(' ', '', $config['CLUB_SHORT_NAME'])) . ".fr");
    if (!validate_email($email)) {
        print_error("Adresse email invalide");
    }
} while (!validate_email($email));
$config['CLUB_EMAIL_FROM'] = $email;
$config['CLUB_EMAIL_REPLY_TO'] = $email;
$config['CLUB_EMAIL_SENDER_NAME'] = strtoupper($config['CLUB_SHORT_NAME']);

$config['CLUB_PHONE'] = prompt("Téléphone (format: +33 X XX XX XX XX)", "");

do {
    $website = prompt("Site web", "https://" . strtolower(str_replace(' ', '', $config['CLUB_SHORT_NAME'])) . ".fr");
    if ($website && !validate_url($website)) {
        print_error("URL invalide");
        $website = "";
    }
} while ($website && !validate_url($website));
$config['CLUB_WEBSITE'] = $website;

$config['CLUB_FACEBOOK'] = prompt("Page Facebook (URL complète, optionnel)", "");

print_section("Adresse postale");
$config['CLUB_ADDRESS_LINE1'] = prompt("Adresse ligne 1", "Aérodrome de " . $config['CLUB_CITY']);
$config['CLUB_ADDRESS_LINE2'] = prompt("Adresse ligne 2 (optionnel)", "");
$config['CLUB_ADDRESS_POSTAL'] = prompt("Code postal + Ville", "");

print_success("Informations de contact enregistrées");

// ============================================================================
// 3. VISUELS ET BRANDING
// ============================================================================

print_section("3. Visuels et branding");

$config['CLUB_LOGO_PATH'] = prompt("Chemin du logo", "assets/img/logo.png");
println("💡 Placez votre logo à cet emplacement après l'installation", Colors::$WARNING);

$config['CLUB_LOGO_HEIGHT'] = (int)prompt("Hauteur du logo en pixels", "50");

println();
println("Couleurs du club (format hexadécimal):", Colors::$BOLD);

do {
    $color = prompt("Couleur principale (ex: #004b8d)", "#004b8d");
    if (!validate_color($color)) {
        print_error("Format invalide. Utilisez le format #RRGGBB (ex: #004b8d)");
    }
} while (!validate_color($color));
$config['CLUB_COLOR_PRIMARY'] = $color;

do {
    $color = prompt("Couleur secondaire (ex: #00a0c6)", "#00a0c6");
    if (!validate_color($color)) {
        print_error("Format invalide. Utilisez le format #RRGGBB (ex: #00a0c6)");
    }
} while (!validate_color($color));
$config['CLUB_COLOR_SECONDARY'] = $color;

do {
    $color = prompt("Couleur d'accentuation (ex: #0078b8)", "#0078b8");
    if (!validate_color($color)) {
        print_error("Format invalide. Utilisez le format #RRGGBB (ex: #0078b8)");
    }
} while (!validate_color($color));
$config['CLUB_COLOR_ACCENT'] = $color;

print_success("Visuels et branding configurés");

// ============================================================================
// 4. MODULES OPTIONNELS
// ============================================================================

print_section("4. Modules optionnels");

println("Activez/désactivez les fonctionnalités selon vos besoins:");
println();

$modules = [
    'CLUB_MODULE_EVENTS' => ['Gestion des événements', true],
    'CLUB_MODULE_POLLS' => ['Sondages', true],
    'CLUB_MODULE_PROPOSALS' => ['Propositions de sorties par membres', true],
    'CLUB_MODULE_CHANGELOG' => ['Historique des versions', true],
    'CLUB_MODULE_STATS' => ['Statistiques et tableaux de bord', true],
    'CLUB_MODULE_BASULM_IMPORT' => ['Import depuis BasULM', true],
    'CLUB_MODULE_WEATHER' => ['Intégration météo', true],
];

foreach ($modules as $key => $info) {
    list($label, $default) = $info;
    $config[$key] = prompt_yes_no("  - " . $label, $default);
}

print_success("Modules configurés");

// ============================================================================
// 5. RÈGLES DE GESTION
// ============================================================================

print_section("5. Règles de gestion des sorties");

$config['CLUB_SORTIES_PER_MONTH'] = (int)prompt("Nombre de sorties visées par mois", "2");
$config['CLUB_INSCRIPTION_MIN_DAYS'] = (int)prompt("Délai minimum d'inscription avant une sortie (jours)", "3");
$config['CLUB_NOTIFICATION_DAYS_BEFORE'] = (int)prompt("Délai de notification avant une sortie (jours)", "7");
$config['CLUB_PRIORITY_DOUBLE_INSCRIPTION'] = prompt_yes_no("Priorité pour membres inscrits aux 2 sorties mensuelles", true);

print_success("Règles de gestion configurées");

// ============================================================================
// 6. UPLOADS ET FICHIERS
// ============================================================================

print_section("6. Uploads et fichiers");

$config['CLUB_MAX_PHOTO_SIZE'] = (int)prompt("Taille max photos (MB)", "5") * 1024 * 1024;
$config['CLUB_MAX_ATTACHMENT_SIZE'] = (int)prompt("Taille max pièces jointes (MB)", "10") * 1024 * 1024;
$config['CLUB_MAX_EVENT_COVER_SIZE'] = (int)prompt("Taille max couvertures événements (MB)", "3") * 1024 * 1024;

print_success("Limites d'upload configurées");

// ============================================================================
// 7. BASE DE DONNÉES
// ============================================================================

print_section("7. Configuration de la base de données");

$config['DB_HOST'] = prompt("Hôte MySQL", "localhost");
$config['DB_NAME'] = prompt("Nom de la base de données", "gestnav");
$config['DB_USER'] = prompt("Utilisateur MySQL", "root");
$config['DB_PASS'] = prompt("Mot de passe MySQL", "");

// Test de connexion
print_warning("Test de connexion à la base de données...");
try {
    $pdo = new PDO(
        "mysql:host={$config['DB_HOST']};charset=utf8mb4",
        $config['DB_USER'],
        $config['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Vérifier si la base existe
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$config['DB_NAME']}'");
    if ($stmt->rowCount() == 0) {
        print_warning("La base de données n'existe pas encore");
        if (prompt_yes_no("Voulez-vous la créer maintenant ?")) {
            $pdo->exec("CREATE DATABASE `{$config['DB_NAME']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            print_success("Base de données créée");
        }
    } else {
        print_success("Connexion réussie - Base de données trouvée");
    }
} catch (PDOException $e) {
    print_error("Erreur de connexion : " . $e->getMessage());
    if (!prompt_yes_no("Continuer malgré l'erreur ?", false)) {
        exit(1);
    }
}

// ============================================================================
// 8. CONFIGURATION SMTP (optionnel)
// ============================================================================

print_section("8. Configuration SMTP (envoi d'emails)");

if (prompt_yes_no("Voulez-vous configurer l'envoi d'emails maintenant ?")) {
    $config['SMTP_HOST'] = prompt("Serveur SMTP", "smtp.gmail.com");
    $config['SMTP_PORT'] = (int)prompt("Port SMTP", "587");
    $config['SMTP_USER'] = prompt("Utilisateur SMTP", $config['CLUB_EMAIL_FROM']);
    $config['SMTP_PASS'] = prompt("Mot de passe SMTP", "");
    $config['SMTP_ENCRYPTION'] = prompt("Chiffrement (tls/ssl)", "tls");
    print_success("Configuration SMTP enregistrée");
} else {
    print_warning("Configuration SMTP ignorée - À configurer plus tard dans config_mail.php");
}

// ============================================================================
// 9. INTÉGRATIONS EXTERNES (optionnel)
// ============================================================================

print_section("9. Intégrations externes (optionnel)");

if (prompt_yes_no("Voulez-vous configurer l'API météo ?", false)) {
    $config['CLUB_WEATHER_API_KEY'] = prompt("Clé API OpenWeatherMap", "");
    $config['CLUB_WEATHER_API_PROVIDER'] = prompt("Fournisseur", "openweathermap");
} else {
    $config['CLUB_WEATHER_API_KEY'] = '';
    $config['CLUB_WEATHER_API_PROVIDER'] = 'openweathermap';
}

println("Coordonnées GPS pour le centre de la carte:");
$config['CLUB_MAP_DEFAULT_CENTER_LAT'] = (float)prompt("Latitude", "48.8566");
$config['CLUB_MAP_DEFAULT_CENTER_LNG'] = (float)prompt("Longitude", "2.3522");
$config['CLUB_MAP_DEFAULT_ZOOM'] = (int)prompt("Zoom par défaut (1-18)", "8");

// ============================================================================
// GÉNÉRATION DES FICHIERS
// ============================================================================

print_header("📝 Génération des fichiers de configuration");

// Générer club_config.php
print_warning("Génération de club_config.php...");

$clubConfigContent = "<?php
/**
 * GESTNAV - Configuration personnalisée
 * Généré automatiquement le " . date('d/m/Y à H:i') . "
 */

// ============================================================================
// INFORMATIONS DU CLUB
// ============================================================================

define('CLUB_NAME', '{$config['CLUB_NAME']}');
define('CLUB_SHORT_NAME', '{$config['CLUB_SHORT_NAME']}');
define('CLUB_CITY', '{$config['CLUB_CITY']}');
define('CLUB_DEPARTMENT', '{$config['CLUB_DEPARTMENT']}');
define('CLUB_REGION', '{$config['CLUB_REGION']}');
define('CLUB_HOME_BASE', '{$config['CLUB_HOME_BASE']}');

// ============================================================================
// CONTACT ET COMMUNICATION
// ============================================================================

define('CLUB_EMAIL_FROM', '{$config['CLUB_EMAIL_FROM']}');
define('CLUB_EMAIL_REPLY_TO', '{$config['CLUB_EMAIL_REPLY_TO']}');
define('CLUB_EMAIL_SENDER_NAME', '{$config['CLUB_EMAIL_SENDER_NAME']}');
define('CLUB_PHONE', '{$config['CLUB_PHONE']}');
define('CLUB_WEBSITE', '{$config['CLUB_WEBSITE']}');
define('CLUB_FACEBOOK', '{$config['CLUB_FACEBOOK']}');

define('CLUB_ADDRESS_LINE1', '{$config['CLUB_ADDRESS_LINE1']}');
define('CLUB_ADDRESS_LINE2', '{$config['CLUB_ADDRESS_LINE2']}');
define('CLUB_ADDRESS_POSTAL', '{$config['CLUB_ADDRESS_POSTAL']}');

// ============================================================================
// VISUELS ET BRANDING
// ============================================================================

define('CLUB_LOGO_PATH', '{$config['CLUB_LOGO_PATH']}');
define('CLUB_LOGO_ALT', 'Logo {$config['CLUB_NAME']}');
define('CLUB_LOGO_HEIGHT', {$config['CLUB_LOGO_HEIGHT']});
define('CLUB_COVER_IMAGE', 'assets/img/cover.jpg');

define('CLUB_COLOR_PRIMARY', '{$config['CLUB_COLOR_PRIMARY']}');
define('CLUB_COLOR_SECONDARY', '{$config['CLUB_COLOR_SECONDARY']}');
define('CLUB_COLOR_ACCENT', '{$config['CLUB_COLOR_ACCENT']}');

// ============================================================================
// MODULES OPTIONNELS
// ============================================================================

define('CLUB_MODULE_EVENTS', " . ($config['CLUB_MODULE_EVENTS'] ? 'true' : 'false') . ");
define('CLUB_MODULE_POLLS', " . ($config['CLUB_MODULE_POLLS'] ? 'true' : 'false') . ");
define('CLUB_MODULE_PROPOSALS', " . ($config['CLUB_MODULE_PROPOSALS'] ? 'true' : 'false') . ");
define('CLUB_MODULE_CHANGELOG', " . ($config['CLUB_MODULE_CHANGELOG'] ? 'true' : 'false') . ");
define('CLUB_MODULE_STATS', " . ($config['CLUB_MODULE_STATS'] ? 'true' : 'false') . ");
define('CLUB_MODULE_BASULM_IMPORT', " . ($config['CLUB_MODULE_BASULM_IMPORT'] ? 'true' : 'false') . ");
define('CLUB_MODULE_WEATHER', " . ($config['CLUB_MODULE_WEATHER'] ? 'true' : 'false') . ");

// ============================================================================
// RÈGLES DE GESTION
// ============================================================================

define('CLUB_SORTIES_PER_MONTH', {$config['CLUB_SORTIES_PER_MONTH']});
define('CLUB_INSCRIPTION_MIN_DAYS', {$config['CLUB_INSCRIPTION_MIN_DAYS']});
define('CLUB_NOTIFICATION_DAYS_BEFORE', {$config['CLUB_NOTIFICATION_DAYS_BEFORE']});
define('CLUB_PRIORITY_DOUBLE_INSCRIPTION', " . ($config['CLUB_PRIORITY_DOUBLE_INSCRIPTION'] ? 'true' : 'false') . ");

// ============================================================================
// UPLOADS ET FICHIERS
// ============================================================================

define('CLUB_MAX_PHOTO_SIZE', {$config['CLUB_MAX_PHOTO_SIZE']});
define('CLUB_MAX_ATTACHMENT_SIZE', {$config['CLUB_MAX_ATTACHMENT_SIZE']});
define('CLUB_MAX_EVENT_COVER_SIZE', {$config['CLUB_MAX_EVENT_COVER_SIZE']});

define('CLUB_ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('CLUB_ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// ============================================================================
// INTÉGRATIONS EXTERNES
// ============================================================================

define('CLUB_WEATHER_API_KEY', '{$config['CLUB_WEATHER_API_KEY']}');
define('CLUB_WEATHER_API_PROVIDER', '{$config['CLUB_WEATHER_API_PROVIDER']}');

define('CLUB_MAP_DEFAULT_CENTER_LAT', {$config['CLUB_MAP_DEFAULT_CENTER_LAT']});
define('CLUB_MAP_DEFAULT_CENTER_LNG', {$config['CLUB_MAP_DEFAULT_CENTER_LNG']});
define('CLUB_MAP_DEFAULT_ZOOM', {$config['CLUB_MAP_DEFAULT_ZOOM']});

// ============================================================================
// FONCTIONS HELPER
// ============================================================================

function get_club_config() {
    return [
        'name' => CLUB_NAME,
        'short_name' => CLUB_SHORT_NAME,
        'city' => CLUB_CITY,
        'email' => CLUB_EMAIL_FROM,
        'website' => CLUB_WEBSITE,
        'colors' => [
            'primary' => CLUB_COLOR_PRIMARY,
            'secondary' => CLUB_COLOR_SECONDARY,
            'accent' => CLUB_COLOR_ACCENT
        ]
    ];
}

function is_module_enabled(\$module_name) {
    \$const_name = 'CLUB_MODULE_' . strtoupper(\$module_name);
    return defined(\$const_name) && constant(\$const_name) === true;
}
";

file_put_contents('club_config.php', $clubConfigContent);
print_success("club_config.php généré");

// Générer config.php (base de données uniquement)
if (file_exists('config.php')) {
    $backup = 'config.php.backup.' . date('YmdHis');
    copy('config.php', $backup);
    print_warning("config.php existant sauvegardé dans $backup");
}

print_warning("Génération de config.php...");
$configContent = "<?php
/**
 * GESTNAV - Configuration technique
 * Généré automatiquement le " . date('d/m/Y à H:i') . "
 */

// Base de données
define('DB_HOST', '{$config['DB_HOST']}');
define('DB_NAME', '{$config['DB_NAME']}');
define('DB_USER', '{$config['DB_USER']}');
define('DB_PASS', '{$config['DB_PASS']}');

// Version de l'application
define('GESTNAV_VERSION', '2.0.0');

// Chemins
define('BASE_PATH', __DIR__);
define('UPLOAD_PATH', BASE_PATH . '/uploads');

// Timezone
date_default_timezone_set('Europe/Paris');

// Connexion à la base de données
try {
    \$pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException \$e) {
    die('Erreur de connexion à la base de données : ' . \$e->getMessage());
}
";

file_put_contents('config.php', $configContent);
print_success("config.php généré");

// Générer config_mail.php si SMTP configuré
if (isset($config['SMTP_HOST'])) {
    print_warning("Génération de config_mail.php...");
    $mailConfigContent = "<?php
/**
 * GESTNAV - Configuration SMTP
 * Généré automatiquement le " . date('d/m/Y à H:i') . "
 */

define('SMTP_HOST', '{$config['SMTP_HOST']}');
define('SMTP_PORT', {$config['SMTP_PORT']});
define('SMTP_USER', '{$config['SMTP_USER']}');
define('SMTP_PASS', '{$config['SMTP_PASS']}');
define('SMTP_ENCRYPTION', '{$config['SMTP_ENCRYPTION']}');
define('SMTP_FROM_EMAIL', '{$config['CLUB_EMAIL_FROM']}');
define('SMTP_FROM_NAME', '{$config['CLUB_EMAIL_SENDER_NAME']}');
";
    file_put_contents('config_mail.php', $mailConfigContent);
    print_success("config_mail.php généré");
}

// ============================================================================
// PROCHAINES ÉTAPES
// ============================================================================

print_header("🎉 Configuration terminée !");

println("Fichiers générés avec succès:", Colors::$OKGREEN . Colors::$BOLD);
println("  ✅ club_config.php", Colors::$OKGREEN);
println("  ✅ config.php", Colors::$OKGREEN);
if (isset($config['SMTP_HOST'])) {
    println("  ✅ config_mail.php", Colors::$OKGREEN);
}

print_section("Prochaines étapes");

println("1️⃣  Placez votre logo dans: " . $config['CLUB_LOGO_PATH'], Colors::$BOLD);
println("2️⃣  Exécutez les scripts de migration de la base de données:");
println("     php setup/install_email_system.php");
println("     php setup/install_events.php");
println("     php setup/install_polls.php");
println("     php setup/migrate_*.php");
println();
println("3️⃣  Créez votre compte administrateur:");
println("     php create_admin.php");
println();
println("4️⃣  Testez l'installation en accédant à votre site");
println();
println("5️⃣  Consultez le guide complet: GUIDE_PERSONNALISATION.md", Colors::$WARNING);

println();
println("📚 Documentation disponible:", Colors::$BOLD);
println("   - GUIDE_PERSONNALISATION.md : Guide complet");
println("   - ARCHITECTURE_*.md : Documentation technique");
println("   - CHANGELOG.md : Historique des versions");

println();
if (prompt_yes_no("Voulez-vous exécuter les migrations maintenant ?", false)) {
    println();
    print_warning("Exécution des migrations...");
    
    $migrations = [
        'setup/install_email_system.php',
        'setup/install_events.php',
        'setup/install_polls.php'
    ];
    
    foreach ($migrations as $migration) {
        if (file_exists($migration)) {
            println("  → $migration", Colors::$OKCYAN);
            passthru("php $migration");
        }
    }
    
    println();
    print_success("Migrations terminées");
}

println();
println("🚀 GESTNAV est prêt pour " . $config['CLUB_NAME'] . " !", Colors::$OKGREEN . Colors::$BOLD);
println();
println("Besoin d'aide ? Consultez la documentation ou ouvrez une issue sur GitHub.", Colors::$WARNING);
println();
