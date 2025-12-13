<?php
/**
 * Configuration générale du club
 * Page d'administration pour modifier les paramètres du club
 * Les paramètres sont stockés en base de données
 */

require_once 'config.php';
require_once 'auth.php';
require_once 'utils/club_config_manager.php';

require_login();
require_admin();

// Handler pour sauvegarder les modifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    try {
        // Préparer les paramètres à sauvegarder
        $settings = [
            // Informations du club
            'club_name' => $_POST['club_name'] ?? '',
            'club_short_name' => $_POST['club_short_name'] ?? '',
            'club_city' => $_POST['club_city'] ?? '',
            'club_department' => $_POST['club_department'] ?? '',
            'club_region' => $_POST['club_region'] ?? '',
            'club_home_base' => $_POST['club_home_base'] ?? '',
            
            // Contact
            'club_email_from' => $_POST['club_email'] ?? '',
            'club_email_reply_to' => $_POST['club_email'] ?? '',
            'club_phone' => $_POST['club_phone'] ?? '',
            'club_website' => $_POST['club_website'] ?? '',
            'club_facebook' => $_POST['club_facebook'] ?? '',
            
            // Adresse
            'club_address_line1' => $_POST['club_address_1'] ?? '',
            'club_address_line2' => $_POST['club_address_2'] ?? '',
            'club_address_postal' => $_POST['club_postal'] ?? '',
            
            // Branding
            'club_logo_path' => $_POST['logo_path'] ?? 'assets/img/logo.png',
            'club_logo_alt' => 'Logo ' . ($_POST['club_name'] ?? 'Club'),
            'club_logo_height' => (int)($_POST['logo_height'] ?? 50),
            'club_cover_image' => 'assets/img/cover.jpg',
            'club_color_primary' => $_POST['color_primary'] ?? '#004b8d',
            'club_color_secondary' => $_POST['color_secondary'] ?? '#00a0c6',
            'club_color_accent' => $_POST['color_accent'] ?? '#0078b8',
            
            // Règles de gestion
            'sorties_per_month' => (int)($_POST['sorties_per_month'] ?? 2),
            'inscription_min_days' => (int)($_POST['inscription_min_days'] ?? 3),
            'notification_days_before' => (int)($_POST['notification_days'] ?? 7),
            'priority_double_inscription' => isset($_POST['priority_double']),
            
            // Intégrations
            'weather_api_key' => $_POST['weather_api_key'] ?? '',
            'weather_api_provider' => 'openweathermap',
            'map_default_center_lat' => (float)($_POST['map_lat'] ?? 46.603354),
            'map_default_center_lng' => (float)($_POST['map_lng'] ?? 1.888334),
            'map_default_zoom' => (int)($_POST['map_zoom'] ?? 6),
            
            // Modules
            'module_events' => isset($_POST['module_events']),
            'module_polls' => isset($_POST['module_polls']),
            'module_proposals' => isset($_POST['module_proposals']),
            'module_changelog' => isset($_POST['module_changelog']),
            'module_stats' => isset($_POST['module_stats']),
            'module_basulm_import' => isset($_POST['module_basulm']),
            'module_weather' => isset($_POST['module_weather']),
            
            // Upload sizes
            'max_photo_size' => 5 * 1024 * 1024,
            'max_attachment_size' => 10 * 1024 * 1024,
            'max_event_cover_size' => 3 * 1024 * 1024,
        ];
        
        // Sauvegarder en base de données
        if (update_club_settings($settings, $_SESSION['user_id'])) {
            // Logger l'opération
            $stmt = $pdo->prepare("INSERT INTO operation_logs (user_id, action, details) VALUES (?, 'config_update', ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                'Modification de la configuration du club via interface web'
            ]);
            
            $success = "Configuration sauvegardée avec succès dans la base de données !";
        } else {
            $error = "Erreur lors de la sauvegarde de la configuration.";
        }
        
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Charger la configuration actuelle depuis la base de données
$currentConfig = [
    'club_name' => get_club_setting('club_name', ''),
    'club_short_name' => get_club_setting('club_short_name', ''),
    'club_city' => get_club_setting('club_city', ''),
    'club_department' => get_club_setting('club_department', ''),
    'club_region' => get_club_setting('club_region', ''),
    'club_home_base' => get_club_setting('club_home_base', ''),
    'club_email' => get_club_setting('club_email_from', ''),
    'club_phone' => get_club_setting('club_phone', ''),
    'club_website' => get_club_setting('club_website', ''),
    'club_facebook' => get_club_setting('club_facebook', ''),
    'club_address_1' => get_club_setting('club_address_line1', ''),
    'club_address_2' => get_club_setting('club_address_line2', ''),
    'club_postal' => get_club_setting('club_address_postal', ''),
    'logo_path' => get_club_setting('club_logo_path', 'assets/img/logo.png'),
    'logo_height' => get_club_setting('club_logo_height', 50),
    'color_primary' => get_club_setting('club_color_primary', '#004b8d'),
    'color_secondary' => get_club_setting('club_color_secondary', '#00a0c6'),
    'color_accent' => get_club_setting('club_color_accent', '#0078b8'),
    'sorties_per_month' => get_club_setting('sorties_per_month', 2),
    'inscription_min_days' => get_club_setting('inscription_min_days', 3),
    'notification_days' => get_club_setting('notification_days_before', 7),
    'priority_double' => get_club_setting('priority_double_inscription', true),
    'weather_api_key' => get_club_setting('weather_api_key', ''),
    'map_lat' => get_club_setting('map_default_center_lat', 46.603354),
    'map_lng' => get_club_setting('map_default_center_lng', 1.888334),
    'map_zoom' => get_club_setting('map_default_zoom', 6),
    'module_events' => get_club_setting('module_events', true),
    'module_polls' => get_club_setting('module_polls', true),
    'module_proposals' => get_club_setting('module_proposals', true),
    'module_changelog' => get_club_setting('module_changelog', true),
    'module_stats' => get_club_setting('module_stats', true),
    'module_basulm' => get_club_setting('module_basulm_import', true),
    'module_weather' => get_club_setting('module_weather', true),
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
