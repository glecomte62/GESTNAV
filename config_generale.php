<?php
/**
 * Configuration générale du club
 * Page d'administration pour modifier les paramètres du club
 */

require_once 'config.php';
require_once 'auth.php';

require_login();
require_admin();

// Charger la configuration actuelle depuis club_config.php
$configFile = __DIR__ . '/club_config.php';
$configContent = file_get_contents($configFile);

// Handler pour sauvegarder les modifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    try {
        // Récupérer les valeurs du formulaire
        $clubName = $_POST['club_name'] ?? '';
        $clubShortName = $_POST['club_short_name'] ?? '';
        $clubCity = $_POST['club_city'] ?? '';
        $clubDepartment = $_POST['club_department'] ?? '';
        $clubRegion = $_POST['club_region'] ?? '';
        $clubHomeBase = $_POST['club_home_base'] ?? '';
        
        $clubEmail = $_POST['club_email'] ?? '';
        $clubPhone = $_POST['club_phone'] ?? '';
        $clubWebsite = $_POST['club_website'] ?? '';
        $clubFacebook = $_POST['club_facebook'] ?? '';
        
        $clubAddress1 = $_POST['club_address_1'] ?? '';
        $clubAddress2 = $_POST['club_address_2'] ?? '';
        $clubPostal = $_POST['club_postal'] ?? '';
        
        $logoPath = $_POST['logo_path'] ?? 'assets/img/logo.png';
        $logoHeight = (int)($_POST['logo_height'] ?? 50);
        
        $colorPrimary = $_POST['color_primary'] ?? '#004b8d';
        $colorSecondary = $_POST['color_secondary'] ?? '#00a0c6';
        $colorAccent = $_POST['color_accent'] ?? '#0078b8';
        
        $sortiesPerMonth = (int)($_POST['sorties_per_month'] ?? 2);
        $inscriptionMinDays = (int)($_POST['inscription_min_days'] ?? 3);
        $notificationDays = (int)($_POST['notification_days'] ?? 7);
        $priorityDouble = isset($_POST['priority_double']) ? 'true' : 'false';
        
        $weatherApiKey = $_POST['weather_api_key'] ?? '';
        $mapLat = (float)($_POST['map_lat'] ?? 50.9634);
        $mapLng = (float)($_POST['map_lng'] ?? 1.9547);
        $mapZoom = (int)($_POST['map_zoom'] ?? 8);
        
        // Modules
        $moduleEvents = isset($_POST['module_events']) ? 'true' : 'false';
        $modulePolls = isset($_POST['module_polls']) ? 'true' : 'false';
        $moduleProposals = isset($_POST['module_proposals']) ? 'true' : 'false';
        $moduleChangelog = isset($_POST['module_changelog']) ? 'true' : 'false';
        $moduleStats = isset($_POST['module_stats']) ? 'true' : 'false';
        $moduleBasulm = isset($_POST['module_basulm']) ? 'true' : 'false';
        $moduleWeather = isset($_POST['module_weather']) ? 'true' : 'false';
        
        // Générer le nouveau contenu du fichier
        $newConfig = "<?php
/**
 * GESTNAV - Configuration personnalisable par club
 * Dernière modification : " . date('d/m/Y à H:i') . " par " . htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']) . "
 */

// ============================================================================
// INFORMATIONS DU CLUB
// ============================================================================

define('CLUB_NAME', '" . addslashes($clubName) . "');
define('CLUB_SHORT_NAME', '" . addslashes($clubShortName) . "');
define('CLUB_CITY', '" . addslashes($clubCity) . "');
define('CLUB_DEPARTMENT', '" . addslashes($clubDepartment) . "');
define('CLUB_REGION', '" . addslashes($clubRegion) . "');
define('CLUB_HOME_BASE', '" . addslashes($clubHomeBase) . "');

// ============================================================================
// CONTACT ET COMMUNICATION
// ============================================================================

define('CLUB_EMAIL_FROM', '" . addslashes($clubEmail) . "');
define('CLUB_EMAIL_REPLY_TO', '" . addslashes($clubEmail) . "');
define('CLUB_EMAIL_SENDER_NAME', '" . addslashes(strtoupper($clubShortName)) . "');
define('CLUB_PHONE', '" . addslashes($clubPhone) . "');
define('CLUB_WEBSITE', '" . addslashes($clubWebsite) . "');
define('CLUB_FACEBOOK', '" . addslashes($clubFacebook) . "');

define('CLUB_ADDRESS_LINE1', '" . addslashes($clubAddress1) . "');
define('CLUB_ADDRESS_LINE2', '" . addslashes($clubAddress2) . "');
define('CLUB_ADDRESS_POSTAL', '" . addslashes($clubPostal) . "');

// ============================================================================
// VISUELS ET BRANDING
// ============================================================================

define('CLUB_LOGO_PATH', '" . addslashes($logoPath) . "');
define('CLUB_LOGO_ALT', 'Logo " . addslashes($clubName) . "');
define('CLUB_LOGO_HEIGHT', " . $logoHeight . ");
define('CLUB_COVER_IMAGE', 'assets/img/cover.jpg');

define('CLUB_COLOR_PRIMARY', '" . addslashes($colorPrimary) . "');
define('CLUB_COLOR_SECONDARY', '" . addslashes($colorSecondary) . "');
define('CLUB_COLOR_ACCENT', '" . addslashes($colorAccent) . "');

// ============================================================================
// MODULES OPTIONNELS
// ============================================================================

define('CLUB_MODULE_EVENTS', " . $moduleEvents . ");
define('CLUB_MODULE_POLLS', " . $modulePolls . ");
define('CLUB_MODULE_PROPOSALS', " . $moduleProposals . ");
define('CLUB_MODULE_CHANGELOG', " . $moduleChangelog . ");
define('CLUB_MODULE_STATS', " . $moduleStats . ");
define('CLUB_MODULE_BASULM_IMPORT', " . $moduleBasulm . ");
define('CLUB_MODULE_WEATHER', " . $moduleWeather . ");

// ============================================================================
// RÈGLES DE GESTION
// ============================================================================

define('CLUB_SORTIES_PER_MONTH', " . $sortiesPerMonth . ");
define('CLUB_INSCRIPTION_MIN_DAYS', " . $inscriptionMinDays . ");
define('CLUB_NOTIFICATION_DAYS_BEFORE', " . $notificationDays . ");
define('CLUB_PRIORITY_DOUBLE_INSCRIPTION', " . $priorityDouble . ");

// ============================================================================
// UPLOADS ET FICHIERS
// ============================================================================

define('CLUB_MAX_PHOTO_SIZE', 5 * 1024 * 1024);
define('CLUB_MAX_ATTACHMENT_SIZE', 10 * 1024 * 1024);
define('CLUB_MAX_EVENT_COVER_SIZE', 3 * 1024 * 1024);

define('CLUB_ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('CLUB_ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// ============================================================================
// INTÉGRATIONS EXTERNES
// ============================================================================

define('CLUB_WEATHER_API_KEY', '" . addslashes($weatherApiKey) . "');
define('CLUB_WEATHER_API_PROVIDER', 'openweathermap');

define('CLUB_MAP_DEFAULT_CENTER_LAT', " . $mapLat . ");
define('CLUB_MAP_DEFAULT_CENTER_LNG', " . $mapLng . ");
define('CLUB_MAP_DEFAULT_ZOOM', " . $mapZoom . ");

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
        
        // Sauvegarder le fichier
        if (file_put_contents($configFile, $newConfig)) {
            // Logger l'opération
            $stmt = $pdo->prepare("INSERT INTO operation_logs (user_id, action, details) VALUES (?, 'config_update', ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                'Modification de la configuration du club'
            ]);
            
            $success = "Configuration sauvegardée avec succès !";
            // Recharger le contenu
            $configContent = $newConfig;
        } else {
            $error = "Erreur lors de la sauvegarde du fichier de configuration.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Extraire les valeurs actuelles depuis le fichier
function extractConfigValue($content, $constName, $default = '') {
    if (preg_match("/define\('$constName',\s*'([^']*)'\);/", $content, $matches)) {
        return stripslashes($matches[1]);
    } elseif (preg_match("/define\('$constName',\s*([^)]+)\);/", $content, $matches)) {
        return trim($matches[1]);
    }
    return $default;
}

$currentConfig = [
    'club_name' => extractConfigValue($configContent, 'CLUB_NAME'),
    'club_short_name' => extractConfigValue($configContent, 'CLUB_SHORT_NAME'),
    'club_city' => extractConfigValue($configContent, 'CLUB_CITY'),
    'club_department' => extractConfigValue($configContent, 'CLUB_DEPARTMENT'),
    'club_region' => extractConfigValue($configContent, 'CLUB_REGION'),
    'club_home_base' => extractConfigValue($configContent, 'CLUB_HOME_BASE'),
    'club_email' => extractConfigValue($configContent, 'CLUB_EMAIL_FROM'),
    'club_phone' => extractConfigValue($configContent, 'CLUB_PHONE'),
    'club_website' => extractConfigValue($configContent, 'CLUB_WEBSITE'),
    'club_facebook' => extractConfigValue($configContent, 'CLUB_FACEBOOK'),
    'club_address_1' => extractConfigValue($configContent, 'CLUB_ADDRESS_LINE1'),
    'club_address_2' => extractConfigValue($configContent, 'CLUB_ADDRESS_LINE2'),
    'club_postal' => extractConfigValue($configContent, 'CLUB_ADDRESS_POSTAL'),
    'logo_path' => extractConfigValue($configContent, 'CLUB_LOGO_PATH', 'assets/img/logo.png'),
    'logo_height' => extractConfigValue($configContent, 'CLUB_LOGO_HEIGHT', '50'),
    'color_primary' => extractConfigValue($configContent, 'CLUB_COLOR_PRIMARY', '#004b8d'),
    'color_secondary' => extractConfigValue($configContent, 'CLUB_COLOR_SECONDARY', '#00a0c6'),
    'color_accent' => extractConfigValue($configContent, 'CLUB_COLOR_ACCENT', '#0078b8'),
    'sorties_per_month' => extractConfigValue($configContent, 'CLUB_SORTIES_PER_MONTH', '2'),
    'inscription_min_days' => extractConfigValue($configContent, 'CLUB_INSCRIPTION_MIN_DAYS', '3'),
    'notification_days' => extractConfigValue($configContent, 'CLUB_NOTIFICATION_DAYS_BEFORE', '7'),
    'priority_double' => extractConfigValue($configContent, 'CLUB_PRIORITY_DOUBLE_INSCRIPTION', 'true') === 'true',
    'weather_api_key' => extractConfigValue($configContent, 'CLUB_WEATHER_API_KEY'),
    'map_lat' => extractConfigValue($configContent, 'CLUB_MAP_DEFAULT_CENTER_LAT', '50.9634'),
    'map_lng' => extractConfigValue($configContent, 'CLUB_MAP_DEFAULT_CENTER_LNG', '1.9547'),
    'map_zoom' => extractConfigValue($configContent, 'CLUB_MAP_DEFAULT_ZOOM', '8'),
    'module_events' => extractConfigValue($configContent, 'CLUB_MODULE_EVENTS', 'true') === 'true',
    'module_polls' => extractConfigValue($configContent, 'CLUB_MODULE_POLLS', 'true') === 'true',
    'module_proposals' => extractConfigValue($configContent, 'CLUB_MODULE_PROPOSALS', 'true') === 'true',
    'module_changelog' => extractConfigValue($configContent, 'CLUB_MODULE_CHANGELOG', 'true') === 'true',
    'module_stats' => extractConfigValue($configContent, 'CLUB_MODULE_STATS', 'true') === 'true',
    'module_basulm' => extractConfigValue($configContent, 'CLUB_MODULE_BASULM_IMPORT', 'true') === 'true',
    'module_weather' => extractConfigValue($configContent, 'CLUB_MODULE_WEATHER', 'true') === 'true',
];

require_once 'header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-sliders me-2"></i>Configuration générale du club
                    </h1>
                    <p class="text-muted mb-0">Paramètres du club, communication et modules</p>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <!-- Onglets -->
                <ul class="nav nav-tabs mb-4" id="configTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="infos-tab" data-bs-toggle="tab" data-bs-target="#infos" type="button">
                            <i class="bi bi-info-circle me-1"></i> Informations
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button">
                            <i class="bi bi-telephone me-1"></i> Contact
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="branding-tab" data-bs-toggle="tab" data-bs-target="#branding" type="button">
                            <i class="bi bi-palette me-1"></i> Visuels
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rules-tab" data-bs-toggle="tab" data-bs-target="#rules" type="button">
                            <i class="bi bi-list-check me-1"></i> Règles
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="modules-tab" data-bs-toggle="tab" data-bs-target="#modules" type="button">
                            <i class="bi bi-grid me-1"></i> Modules
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="integrations-tab" data-bs-toggle="tab" data-bs-target="#integrations" type="button">
                            <i class="bi bi-plugin me-1"></i> Intégrations
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="configTabsContent">
                    <!-- Onglet Informations -->
                    <div class="tab-pane fade show active" id="infos" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Informations du club</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nom complet du club</label>
                                        <input type="text" class="form-control" name="club_name" 
                                               value="<?= htmlspecialchars($currentConfig['club_name']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nom court / Acronyme</label>
                                        <input type="text" class="form-control" name="club_short_name" 
                                               value="<?= htmlspecialchars($currentConfig['club_short_name']) ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Ville</label>
                                        <input type="text" class="form-control" name="club_city" 
                                               value="<?= htmlspecialchars($currentConfig['club_city']) ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Département</label>
                                        <input type="text" class="form-control" name="club_department" 
                                               value="<?= htmlspecialchars($currentConfig['club_department']) ?>" 
                                               placeholder="Ex: Pas-de-Calais (62)">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Région</label>
                                        <input type="text" class="form-control" name="club_region" 
                                               value="<?= htmlspecialchars($currentConfig['club_region']) ?>"
                                               placeholder="Ex: Hauts-de-France">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Code OACI base principale</label>
                                        <input type="text" class="form-control" name="club_home_base" 
                                               value="<?= htmlspecialchars($currentConfig['club_home_base']) ?>"
                                               pattern="[A-Z]{4}" placeholder="Ex: LFQJ" maxlength="4" style="text-transform: uppercase;">
                                        <small class="text-muted">4 lettres majuscules</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Onglet Contact -->
                    <div class="tab-pane fade" id="contact" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-envelope me-2"></i>Contact et communication</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email de contact</label>
                                        <input type="email" class="form-control" name="club_email" 
                                               value="<?= htmlspecialchars($currentConfig['club_email']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" name="club_phone" 
                                               value="<?= htmlspecialchars($currentConfig['club_phone']) ?>"
                                               placeholder="+33 X XX XX XX XX">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Site web</label>
                                        <input type="url" class="form-control" name="club_website" 
                                               value="<?= htmlspecialchars($currentConfig['club_website']) ?>"
                                               placeholder="https://votre-club.fr">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Page Facebook</label>
                                        <input type="url" class="form-control" name="club_facebook" 
                                               value="<?= htmlspecialchars($currentConfig['club_facebook']) ?>"
                                               placeholder="https://facebook.com/votre-page">
                                    </div>
                                </div>
                                
                                <h6 class="mt-4 mb-3">Adresse postale</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Adresse ligne 1</label>
                                        <input type="text" class="form-control" name="club_address_1" 
                                               value="<?= htmlspecialchars($currentConfig['club_address_1']) ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Adresse ligne 2 (optionnel)</label>
                                        <input type="text" class="form-control" name="club_address_2" 
                                               value="<?= htmlspecialchars($currentConfig['club_address_2']) ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Code postal + Ville</label>
                                        <input type="text" class="form-control" name="club_postal" 
                                               value="<?= htmlspecialchars($currentConfig['club_postal']) ?>"
                                               placeholder="62100 CALAIS">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Onglet Visuels -->
                    <div class="tab-pane fade" id="branding" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-image me-2"></i>Visuels et branding</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Chemin du logo</label>
                                        <input type="text" class="form-control" name="logo_path" 
                                               value="<?= htmlspecialchars($currentConfig['logo_path']) ?>">
                                        <small class="text-muted">Chemin relatif depuis la racine (ex: assets/img/logo.png)</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Hauteur logo (pixels)</label>
                                        <input type="number" class="form-control" name="logo_height" 
                                               value="<?= htmlspecialchars($currentConfig['logo_height']) ?>" min="20" max="200">
                                    </div>
                                </div>
                                
                                <h6 class="mt-4 mb-3">Couleurs du club</h6>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Couleur principale</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color" name="color_primary" 
                                                   value="<?= htmlspecialchars($currentConfig['color_primary']) ?>">
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($currentConfig['color_primary']) ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Couleur secondaire</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color" name="color_secondary" 
                                                   value="<?= htmlspecialchars($currentConfig['color_secondary']) ?>">
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($currentConfig['color_secondary']) ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Couleur d'accentuation</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color" name="color_accent" 
                                                   value="<?= htmlspecialchars($currentConfig['color_accent']) ?>">
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($currentConfig['color_accent']) ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Aperçu des couleurs :</strong> Les modifications seront visibles après sauvegarde et rechargement de la page.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Onglet Règles -->
                    <div class="tab-pane fade" id="rules" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Règles de gestion des sorties</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Nombre de sorties par mois</label>
                                        <input type="number" class="form-control" name="sorties_per_month" 
                                               value="<?= htmlspecialchars($currentConfig['sorties_per_month']) ?>" min="1" max="10">
                                        <small class="text-muted">Objectif visé du club</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Délai minimum d'inscription (jours)</label>
                                        <input type="number" class="form-control" name="inscription_min_days" 
                                               value="<?= htmlspecialchars($currentConfig['inscription_min_days']) ?>" min="0" max="30">
                                        <small class="text-muted">Avant la sortie</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Délai de notification (jours)</label>
                                        <input type="number" class="form-control" name="notification_days" 
                                               value="<?= htmlspecialchars($currentConfig['notification_days']) ?>" min="1" max="30">
                                        <small class="text-muted">Avant la sortie</small>
                                    </div>
                                </div>
                                
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" name="priority_double" id="priority_double"
                                           <?= $currentConfig['priority_double'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="priority_double">
                                        Priorité automatique pour les membres inscrits aux 2 sorties mensuelles
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Onglet Modules -->
                    <div class="tab-pane fade" id="modules" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-grid me-2"></i>Modules optionnels</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-4">Activez ou désactivez les fonctionnalités selon vos besoins</p>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="module_events" id="module_events"
                                                   <?= $currentConfig['module_events'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="module_events">
                                                <strong>Gestion des événements</strong><br>
                                                <small class="text-muted">Créer et gérer des événements du club</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="module_polls" id="module_polls"
                                                   <?= $currentConfig['module_polls'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="module_polls">
                                                <strong>Sondages</strong><br>
                                                <small class="text-muted">Créer des sondages pour les membres</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="module_proposals" id="module_proposals"
                                                   <?= $currentConfig['module_proposals'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="module_proposals">
                                                <strong>Propositions de sorties</strong><br>
                                                <small class="text-muted">Les membres peuvent proposer des sorties</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="module_changelog" id="module_changelog"
                                                   <?= $currentConfig['module_changelog'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="module_changelog">
                                                <strong>Historique des versions</strong><br>
                                                <small class="text-muted">Afficher le changelog de l'application</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="module_stats" id="module_stats"
                                                   <?= $currentConfig['module_stats'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="module_stats">
                                                <strong>Statistiques</strong><br>
                                                <small class="text-muted">Tableaux de bord et statistiques</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="module_basulm" id="module_basulm"
                                                   <?= $currentConfig['module_basulm'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="module_basulm">
                                                <strong>Import BasULM</strong><br>
                                                <small class="text-muted">Importer les bases ULM depuis BasULM</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="module_weather" id="module_weather"
                                                   <?= $currentConfig['module_weather'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="module_weather">
                                                <strong>Intégration météo</strong><br>
                                                <small class="text-muted">Afficher les données météo</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Onglet Intégrations -->
                    <div class="tab-pane fade" id="integrations" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-cloud me-2"></i>Intégrations externes</h5>
                            </div>
                            <div class="card-body">
                                <h6 class="mb-3">API Météo</h6>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Clé API OpenWeatherMap</label>
                                        <input type="text" class="form-control" name="weather_api_key" 
                                               value="<?= htmlspecialchars($currentConfig['weather_api_key']) ?>"
                                               placeholder="Votre clé API (optionnel)">
                                        <small class="text-muted">
                                            Obtenir une clé gratuite : 
                                            <a href="https://openweathermap.org/api" target="_blank">openweathermap.org/api</a>
                                        </small>
                                    </div>
                                </div>
                                
                                <h6 class="mt-4 mb-3">Carte géographique</h6>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Latitude centre</label>
                                        <input type="number" class="form-control" name="map_lat" step="0.0001"
                                               value="<?= htmlspecialchars($currentConfig['map_lat']) ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Longitude centre</label>
                                        <input type="number" class="form-control" name="map_lng" step="0.0001"
                                               value="<?= htmlspecialchars($currentConfig['map_lng']) ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Zoom par défaut</label>
                                        <input type="number" class="form-control" name="map_zoom" min="1" max="18"
                                               value="<?= htmlspecialchars($currentConfig['map_zoom']) ?>">
                                    </div>
                                </div>
                                <small class="text-muted">
                                    Coordonnées GPS de votre aérodrome pour centrer la carte
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="d-flex justify-content-between mt-4">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Annuler
                    </a>
                    <button type="submit" name="save_config" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Sauvegarder la configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Synchroniser les inputs color et text
document.querySelectorAll('input[type="color"]').forEach(colorInput => {
    const textInput = colorInput.nextElementSibling;
    
    colorInput.addEventListener('input', (e) => {
        textInput.value = e.target.value;
    });
    
    textInput.addEventListener('input', (e) => {
        if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
            colorInput.value = e.target.value;
        }
    });
});

// Validation du formulaire
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php require_once 'footer.php'; ?>
