<?php
/**
 * GESTNAV - Configuration du Club
 * 
 * Ce fichier contient tous les paramètres personnalisables pour votre club ULM.
 * Copiez ce fichier en club_config.php et modifiez les valeurs selon vos besoins.
 */

return [
    // ============================================
    // INFORMATIONS DU CLUB
    // ============================================
    'club' => [
        'nom' => 'Club ULM Evasion',
        'nom_court' => 'ULM Evasion',
        'adresse' => 'Aérodrome de Cambrai-Niergnies',
        'code_postal' => '59400',
        'ville' => 'Cambrai',
        'code_oaci' => 'LFQI',
        'telephone' => '+33 X XX XX XX XX',
        'site_web' => 'https://www.clubulmevasion.fr',
    ],

    // ============================================
    // EMAILS
    // ============================================
    'email' => [
        'from_address' => 'info@clubulmevasion.fr',
        'from_name' => 'CLUB ULM EVASION',
        'reply_to' => 'contact@clubulmevasion.fr',
        'admin_email' => 'admin@clubulmevasion.fr', // Pour les notifications système
    ],

    // ============================================
    // BRANDING
    // ============================================
    'branding' => [
        'logo_path' => '/assets/img/logo.png',
        'favicon_path' => '/assets/img/favicon.ico',
        'couleur_primaire' => '#004b8d',
        'couleur_secondaire' => '#00a0c6',
        'couleur_accent' => '#ff6b00',
    ],

    // ============================================
    // FONCTIONNALITÉS
    // ============================================
    'features' => [
        'propositions_sorties' => true,    // Activer les propositions de sorties par les membres
        'sondages' => true,                // Activer les sondages
        'evenements' => true,              // Activer les événements
        'preinscriptions_publiques' => true, // Permettre les pré-inscriptions sans compte
        'notifications_email' => true,     // Envoyer des emails de notification
        'limite_sorties_mois' => 2,        // Objectif de sorties par mois
    ],

    // ============================================
    // RÈGLES DE GESTION
    // ============================================
    'regles' => [
        'priorite_double_inscription' => true, // Priorité aux membres inscrits 2x qui n'ont pas pu participer
        'delai_annulation_sortie' => 24,      // Heures avant la sortie pour annuler sans pénalité
        'max_participants_par_sortie' => 20,   // Limite de participants par sortie (0 = illimité)
        'validation_admin_requise' => true,    // Les inscriptions nécessitent validation admin
    ],

    // ============================================
    // TYPES DE MEMBRES
    // ============================================
    'types_membres' => [
        'club' => 'Membre CLUB',
        'invite' => 'Invité',
    ],

    // ============================================
    // CATÉGORIES D'EMAILS
    // ============================================
    'categories_emails' => [
        'custom' => [
            'label' => 'Libre',
            'prefix' => '',
        ],
        'communication' => [
            'label' => '📢 Communication',
            'prefix' => '📢 Communication - ',
        ],
        'nouveau_membre' => [
            'label' => '🎉 Bienvenue',
            'prefix' => '🎉 Bienvenue - ',
        ],
    ],

    // ============================================
    // UPLOADS
    // ============================================
    'uploads' => [
        'max_photo_size' => 5 * 1024 * 1024,      // 5 MB
        'max_attachment_size' => 10 * 1024 * 1024, // 10 MB
        'allowed_image_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'photos_path' => 'uploads/sorties',
        'email_images_path' => 'uploads/email_images',
        'email_attachments_path' => 'uploads/email_attachments',
    ],

    // ============================================
    // APPLICATION
    // ============================================
    'app' => [
        'version' => '2.0.0',
        'nom' => 'GESTNAV',
        'description' => 'Gestion des Sorties et Membres pour clubs ULM',
        'timezone' => 'Europe/Paris',
        'langue' => 'fr',
    ],

    // ============================================
    // SÉCURITÉ
    // ============================================
    'security' => [
        'session_lifetime' => 7200,  // 2 heures en secondes
        'password_min_length' => 8,
        'max_login_attempts' => 5,
        'lockout_duration' => 900,   // 15 minutes en secondes
    ],
];
