-- ============================================================================
-- Migration : Configuration du club en base de données
-- ============================================================================
-- Créer une table pour stocker la configuration du club
-- au lieu d'utiliser un fichier PHP statique
-- ============================================================================

CREATE TABLE IF NOT EXISTS club_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'float', 'boolean', 'json') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insérer les valeurs par défaut depuis club_config.php
INSERT INTO club_settings (setting_key, setting_value, setting_type, category, description) VALUES

-- INFORMATIONS DU CLUB
('club_name', 'Club ULM Evasion', 'string', 'info', 'Nom complet du club'),
('club_short_name', 'ULM Evasion', 'string', 'info', 'Nom court du club'),
('club_city', 'Maubeuge', 'string', 'info', 'Ville du club'),
('club_department', 'Nord (59)', 'string', 'info', 'Département'),
('club_region', 'Hauts-de-France', 'string', 'info', 'Région'),
('club_home_base', 'LFQJ', 'string', 'info', 'Code OACI de la base (Maubeuge-Élesmes)'),

-- CONTACT
('club_email_from', 'info@clubulmevasion.fr', 'string', 'contact', 'Email de contact principal'),
('club_email_reply_to', 'info@clubulmevasion.fr', 'string', 'contact', 'Email de réponse'),
('club_phone', '+33 3 21 96 70 00', 'string', 'contact', 'Téléphone'),
('club_website', 'https://clubulmevasion.fr', 'string', 'contact', 'Site web'),
('club_facebook', 'https://www.facebook.com/clubulmevasion', 'string', 'contact', 'Page Facebook'),

-- ADRESSE
('club_address_line1', 'Aérodrome de Maubeuge-Élesmes', 'string', 'address', 'Adresse ligne 1'),
('club_address_line2', '', 'string', 'address', 'Adresse ligne 2'),
('club_address_postal', '59330 ÉLESMES', 'string', 'address', 'Code postal et ville'),

-- BRANDING
('club_logo_path', 'assets/img/logo.png', 'string', 'branding', 'Chemin du logo'),
('club_logo_alt', 'Logo Club ULM Evasion', 'string', 'branding', 'Texte alternatif du logo'),
('club_logo_height', '50', 'integer', 'branding', 'Hauteur du logo en pixels'),
('club_cover_image', 'assets/img/cover.jpg', 'string', 'branding', 'Image de couverture'),
('club_color_primary', '#004b8d', 'string', 'branding', 'Couleur primaire'),
('club_color_secondary', '#00a0c6', 'string', 'branding', 'Couleur secondaire'),
('club_color_accent', '#0078b8', 'string', 'branding', 'Couleur d''accent'),

-- MODULES
('module_events', '1', 'boolean', 'modules', 'Activer le module événements'),
('module_polls', '1', 'boolean', 'modules', 'Activer le module sondages'),
('module_proposals', '1', 'boolean', 'modules', 'Activer le module propositions'),
('module_changelog', '1', 'boolean', 'modules', 'Activer le changelog'),
('module_stats', '1', 'boolean', 'modules', 'Activer les statistiques'),
('module_basulm_import', '1', 'boolean', 'modules', 'Activer l''import BasULM'),
('module_weather', '1', 'boolean', 'modules', 'Activer la météo'),

-- RÈGLES DE GESTION
('sorties_per_month', '2', 'integer', 'rules', 'Nombre de sorties par mois par membre'),
('inscription_min_days', '3', 'integer', 'rules', 'Délai minimum d''inscription (jours)'),
('notification_days_before', '7', 'integer', 'rules', 'Jours avant notification'),
('priority_double_inscription', '1', 'boolean', 'rules', 'Priorité aux doubles inscriptions'),

-- UPLOADS
('max_photo_size', '5242880', 'integer', 'uploads', 'Taille max photo (5 MB)'),
('max_attachment_size', '10485760', 'integer', 'uploads', 'Taille max pièce jointe (10 MB)'),
('max_event_cover_size', '3145728', 'integer', 'uploads', 'Taille max couverture événement (3 MB)'),

-- INTÉGRATIONS EXTERNES
('weather_api_key', '', 'string', 'integrations', 'Clé API météo (OpenWeatherMap)'),
('weather_api_provider', 'openweathermap', 'string', 'integrations', 'Fournisseur API météo'),
('map_default_center_lat', '50.3053', 'float', 'integrations', 'Latitude par défaut de la carte'),
('map_default_center_lng', '4.0331', 'float', 'integrations', 'Longitude par défaut de la carte'),
('map_default_zoom', '8', 'integer', 'integrations', 'Zoom par défaut de la carte')

ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description);
