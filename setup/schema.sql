-- =============================================
-- GESTNAV - Schéma de base de données complet
-- Version: 2.0.0
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =============================================
-- Table: users (Membres du club)
-- =============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `qualification` varchar(50) DEFAULT NULL,
  `emport_passager` tinyint(1) DEFAULT 0,
  `qualification_radio_ifr` tinyint(1) DEFAULT 0,
  `photo_path` varchar(255) DEFAULT NULL,
  `photo_metadata` json DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `type_membre` varchar(50) DEFAULT 'club',
  `password_hash` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_actif` (`actif`),
  KEY `idx_type_membre` (`type_membre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: machines (ULMs du club)
-- =============================================
CREATE TABLE IF NOT EXISTS `machines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `immatriculation` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `immatriculation` (`immatriculation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: machine_owners (Propriétaires d'ULM)
-- =============================================
CREATE TABLE IF NOT EXISTS `machine_owners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `machine_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `machine_id` (`machine_id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`machine_id`) REFERENCES `machines`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sorties (Sorties ULM)
-- =============================================
CREATE TABLE IF NOT EXISTS `sorties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_sortie` datetime NOT NULL,
  `destination_id` int(11) DEFAULT NULL,
  `statut` enum('en_etude','prevue','terminee','annulee') DEFAULT 'prevue',
  `photo_filename` varchar(255) DEFAULT NULL,
  `max_participants` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date_sortie`),
  KEY `idx_statut` (`statut`),
  KEY `created_by` (`created_by`),
  KEY `destination_id` (`destination_id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sortie_machines (Machines affectées)
-- =============================================
CREATE TABLE IF NOT EXISTS `sortie_machines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sortie_id` int(11) NOT NULL,
  `machine_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sortie_id` (`sortie_id`),
  KEY `machine_id` (`machine_id`),
  FOREIGN KEY (`sortie_id`) REFERENCES `sorties`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`machine_id`) REFERENCES `machines`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sortie_assignations (Équipages)
-- =============================================
CREATE TABLE IF NOT EXISTS `sortie_assignations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sortie_machine_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('pilote','passager') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sortie_machine_id` (`sortie_machine_id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`sortie_machine_id`) REFERENCES `sortie_machines`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sortie_inscriptions (Inscriptions)
-- =============================================
CREATE TABLE IF NOT EXISTS `sortie_inscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sortie_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `machine_preference_id` int(11) DEFAULT NULL,
  `coequipier_preference_id` int(11) DEFAULT NULL,
  `statut` enum('en_attente','confirmee','annulee','liste_attente') DEFAULT 'en_attente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_inscription` (`sortie_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `machine_preference_id` (`machine_preference_id`),
  KEY `coequipier_preference_id` (`coequipier_preference_id`),
  FOREIGN KEY (`sortie_id`) REFERENCES `sorties`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`machine_preference_id`) REFERENCES `machines`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`coequipier_preference_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sortie_proposals (Propositions)
-- =============================================
CREATE TABLE IF NOT EXISTS `sortie_proposals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `destination` varchar(255) DEFAULT NULL,
  `date_souhaitee` date DEFAULT NULL,
  `proposed_by` int(11) NOT NULL,
  `statut` enum('en_attente','approuvee','rejetee','en_etude') DEFAULT 'en_attente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sortie_id` int(11) DEFAULT NULL COMMENT 'ID de la sortie créée si approuvée',
  PRIMARY KEY (`id`),
  KEY `proposed_by` (`proposed_by`),
  KEY `sortie_id` (`sortie_id`),
  FOREIGN KEY (`proposed_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sortie_id`) REFERENCES `sorties`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sortie_photos (Photos de sorties)
-- =============================================
CREATE TABLE IF NOT EXISTS `sortie_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sortie_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sortie_id` (`sortie_id`),
  KEY `uploaded_by` (`uploaded_by`),
  FOREIGN KEY (`sortie_id`) REFERENCES `sorties`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: evenements (Événements du club)
-- =============================================
CREATE TABLE IF NOT EXISTS `evenements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_evenement` datetime NOT NULL,
  `lieu` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `date_limite_inscription` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date_evenement`),
  KEY `created_by` (`created_by`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: evenement_inscriptions (Inscriptions événements)
-- =============================================
CREATE TABLE IF NOT EXISTS `evenement_inscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evenement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `statut` enum('en_attente','confirmee','annulee') DEFAULT 'en_attente',
  `commentaire` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_inscription_evenement` (`evenement_id`,`user_id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`evenement_id`) REFERENCES `evenements`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sondages (Sondages)
-- =============================================
CREATE TABLE IF NOT EXISTS `sondages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_fin` datetime DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sondage_options (Options de sondage)
-- =============================================
CREATE TABLE IF NOT EXISTS `sondage_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sondage_id` int(11) NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `ordre` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `sondage_id` (`sondage_id`),
  FOREIGN KEY (`sondage_id`) REFERENCES `sondages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sondage_votes (Votes)
-- =============================================
CREATE TABLE IF NOT EXISTS `sondage_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sondage_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`sondage_id`,`user_id`),
  KEY `option_id` (`option_id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`sondage_id`) REFERENCES `sondages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`option_id`) REFERENCES `sondage_options`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: preinscriptions (Pré-inscriptions publiques)
-- =============================================
CREATE TABLE IF NOT EXISTS `preinscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `adresse_ligne1` varchar(255) NOT NULL,
  `adresse_ligne2` varchar(255) DEFAULT NULL,
  `code_postal` varchar(10) NOT NULL,
  `ville` varchar(100) NOT NULL,
  `pays` varchar(100) DEFAULT 'France',
  `telephone` varchar(20) NOT NULL,
  `gsm` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `date_naissance` date NOT NULL,
  `profession` varchar(150) NOT NULL,
  `contact_urgence_nom` varchar(150) NOT NULL,
  `contact_urgence_tel` varchar(20) NOT NULL,
  `contact_urgence_email` varchar(255) NOT NULL,
  `photo_filename` varchar(255) DEFAULT NULL,
  `presentation` text NOT NULL,
  `est_pilote` tinyint(1) DEFAULT 0,
  `numero_licence` varchar(50) DEFAULT NULL,
  `statut` enum('en_attente','validee','refusee') DEFAULT 'en_attente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `validated_at` timestamp NULL DEFAULT NULL,
  `validated_by` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'ID utilisateur créé si validé',
  `notes_admin` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_email` (`email`),
  KEY `idx_created` (`created_at`),
  FOREIGN KEY (`validated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: aerodromes_fr (Aérodromes français)
-- =============================================
CREATE TABLE IF NOT EXISTS `aerodromes_fr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `oaci` varchar(10) DEFAULT NULL,
  `nom` varchar(255) DEFAULT NULL,
  `ville` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,6) DEFAULT NULL,
  `longitude` decimal(10,6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oaci` (`oaci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: ulm_bases_fr (Bases ULM françaises)
-- =============================================
CREATE TABLE IF NOT EXISTS `ulm_bases_fr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `ville` varchar(255) DEFAULT NULL,
  `oaci` varchar(10) DEFAULT NULL,
  `latitude` decimal(10,6) DEFAULT NULL,
  `longitude` decimal(10,6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nom` (`nom`),
  KEY `idx_ville` (`ville`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: email_logs (Historique des emails)
-- =============================================
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `recipient_count` int(11) DEFAULT 0,
  `sender_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`),
  KEY `sender_id` (`sender_id`),
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: connection_logs (Logs de connexion)
-- =============================================
CREATE TABLE IF NOT EXISTS `connection_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_created` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: affectations_logs (Logs d'affectations)
-- =============================================
CREATE TABLE IF NOT EXISTS `affectations_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sortie_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `machine_id` int(11) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sortie_id` (`sortie_id`),
  KEY `user_id` (`user_id`),
  KEY `performed_by` (`performed_by`),
  FOREIGN KEY (`sortie_id`) REFERENCES `sorties`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sortie_priorites (Priorités pour sorties)
-- =============================================
CREATE TABLE IF NOT EXISTS `sortie_priorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `sortie_id` int(11) NOT NULL,
  `raison` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_priorite` (`user_id`,`sortie_id`),
  KEY `sortie_id` (`sortie_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sortie_id`) REFERENCES `sorties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sortie_machines_exclusions (Exclusions machines)
-- =============================================
CREATE TABLE IF NOT EXISTS `sortie_machines_exclusions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sortie_id` int(11) NOT NULL,
  `machine_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_exclusion` (`sortie_id`,`machine_id`,`user_id`),
  KEY `machine_id` (`machine_id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`sortie_id`) REFERENCES `sorties`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`machine_id`) REFERENCES `machines`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sortie_preinscriptions (Préinscriptions sur sorties)
-- =============================================
CREATE TABLE IF NOT EXISTS `sortie_preinscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sortie_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `preferred_machine_id` int(11) DEFAULT NULL,
  `preferred_coequipier_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sortie_user` (`sortie_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `preferred_machine_id` (`preferred_machine_id`),
  KEY `preferred_coequipier_user_id` (`preferred_coequipier_user_id`),
  FOREIGN KEY (`sortie_id`) REFERENCES `sorties`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`preferred_machine_id`) REFERENCES `machines`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`preferred_coequipier_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: email_recipients (Destinataires des emails)
-- =============================================
CREATE TABLE IF NOT EXISTS `email_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email_log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_log_id` (`email_log_id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`email_log_id`) REFERENCES `email_logs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: operation_logs (Logs des opérations système)
-- =============================================
CREATE TABLE IF NOT EXISTS `operation_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_action_created` (`action`,`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: event_alerts (Alertes envoyées pour sorties/événements)
-- =============================================
CREATE TABLE IF NOT EXISTS `event_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` enum('sortie','evenement') NOT NULL,
  `event_id` int(11) NOT NULL,
  `event_title` varchar(255) NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `recipient_count` int(11) DEFAULT 0,
  `success_count` int(11) DEFAULT 0,
  `failed_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_event` (`event_type`,`event_id`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: event_alert_optouts (Désabonnements des alertes)
-- =============================================
CREATE TABLE IF NOT EXISTS `event_alert_optouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `opted_out_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL,
  `opt_in_token` varchar(64) DEFAULT NULL UNIQUE,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_opted_out_at` (`opted_out_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: event_alert_logs (Logs détaillés des envois d'alertes)
-- =============================================
CREATE TABLE IF NOT EXISTS `event_alert_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alert_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` enum('sent','failed','skipped') DEFAULT 'failed',
  `error_message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_alert` (`alert_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`alert_id`) REFERENCES `event_alerts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: club_settings (Configuration du club)
-- =============================================
-- Stockage de la configuration du club en BDD
-- Modifiable via l'interface /config_generale.php
-- =============================================

CREATE TABLE IF NOT EXISTS `club_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','float','boolean','json') DEFAULT 'string',
  `category` varchar(50) DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_category` (`category`),
  KEY `idx_updated` (`updated_at`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insérer les valeurs par défaut
INSERT INTO `club_settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `description`) VALUES
-- Informations du club
('club_name', 'Club ULM Evasion', 'string', 'info', 'Nom complet du club'),
('club_short_name', 'ULM Evasion', 'string', 'info', 'Nom court du club'),
('club_city', 'Maubeuge', 'string', 'info', 'Ville du club'),
('club_department', 'Nord (59)', 'string', 'info', 'Département'),
('club_region', 'Hauts-de-France', 'string', 'info', 'Région'),
('club_home_base', 'LFQJ', 'string', 'info', 'Code OACI de la base (Maubeuge-Élesmes)'),
-- Contact
('club_email_from', 'info@clubulmevasion.fr', 'string', 'contact', 'Email de contact principal'),
('club_email_reply_to', 'info@clubulmevasion.fr', 'string', 'contact', 'Email de réponse'),
('club_phone', '+33 3 21 96 70 00', 'string', 'contact', 'Téléphone'),
('club_website', 'https://clubulmevasion.fr', 'string', 'contact', 'Site web'),
('club_facebook', 'https://www.facebook.com/clubulmevasion', 'string', 'contact', 'Page Facebook'),
-- Adresse
('club_address_line1', 'Aérodrome de Maubeuge-Élesmes', 'string', 'address', 'Adresse ligne 1'),
('club_address_line2', '', 'string', 'address', 'Adresse ligne 2'),
('club_address_postal', '59330 ÉLESMES', 'string', 'address', 'Code postal et ville'),
-- Branding
('club_logo_path', 'assets/img/logo.png', 'string', 'branding', 'Chemin du logo'),
('club_logo_alt', 'Logo Club ULM Evasion', 'string', 'branding', 'Texte alternatif du logo'),
('club_logo_height', '50', 'integer', 'branding', 'Hauteur du logo en pixels'),
('club_cover_image', 'assets/img/cover.jpg', 'string', 'branding', 'Image de couverture'),
('club_color_primary', '#004b8d', 'string', 'branding', 'Couleur primaire'),
('club_color_secondary', '#00a0c6', 'string', 'branding', 'Couleur secondaire'),
('club_color_accent', '#0078b8', 'string', 'branding', 'Couleur d''accent'),
-- Modules
('module_events', '1', 'boolean', 'modules', 'Activer le module événements'),
('module_polls', '1', 'boolean', 'modules', 'Activer le module sondages'),
('module_proposals', '1', 'boolean', 'modules', 'Activer le module propositions'),
('module_changelog', '1', 'boolean', 'modules', 'Activer le changelog'),
('module_stats', '1', 'boolean', 'modules', 'Activer les statistiques'),
('module_basulm_import', '1', 'boolean', 'modules', 'Activer l''import BasULM'),
('module_weather', '1', 'boolean', 'modules', 'Activer la météo'),
-- Règles de gestion
('sorties_per_month', '2', 'integer', 'rules', 'Nombre de sorties par mois par membre'),
('inscription_min_days', '3', 'integer', 'rules', 'Délai minimum d''inscription (jours)'),
('notification_days_before', '7', 'integer', 'rules', 'Jours avant notification'),
('priority_double_inscription', '1', 'boolean', 'rules', 'Priorité aux doubles inscriptions'),
-- Uploads
('max_photo_size', '5242880', 'integer', 'uploads', 'Taille max photo (5 MB)'),
('max_attachment_size', '10485760', 'integer', 'uploads', 'Taille max pièce jointe (10 MB)'),
('max_event_cover_size', '3145728', 'integer', 'uploads', 'Taille max couverture événement (3 MB)'),
-- Intégrations externes
('weather_api_key', '', 'string', 'integrations', 'Clé API météo (OpenWeatherMap)'),
('weather_api_provider', 'openweathermap', 'string', 'integrations', 'Fournisseur API météo'),
('map_default_center_lat', '50.3053', 'float', 'integrations', 'Latitude par défaut de la carte'),
('map_default_center_lng', '4.0331', 'float', 'integrations', 'Longitude par défaut de la carte'),
('map_default_zoom', '8', 'integer', 'integrations', 'Zoom par défaut de la carte')
ON DUPLICATE KEY UPDATE 
  `setting_value` = VALUES(`setting_value`),
  `description` = VALUES(`description`);

COMMIT;

-- =============================================
-- Fin du schéma GESTNAV
-- =============================================
