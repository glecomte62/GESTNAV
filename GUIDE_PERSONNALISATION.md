# Guide de Personnalisation GESTNAV

## 🎯 Objectif

Ce guide explique comment adapter l'application GESTNAV pour un nouveau club ULM. Toute la personnalisation se fait via le fichier `club_config.php`.

## 📋 Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur web (Apache/Nginx)
- Accès FTP ou SSH au serveur

## 🚀 Installation pour un nouveau club

### Étape 1 : Copier les fichiers

1. Clonez ou téléchargez le dépôt GESTNAV
2. Copiez tous les fichiers sur votre serveur web

### Étape 2 : Configuration de la base de données

1. Créez une nouvelle base de données MySQL
2. Modifiez `config.php` avec vos identifiants de base de données :

```php
define('DB_HOST', 'votre_serveur');
define('DB_NAME', 'votre_base');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');
```

3. Exécutez les scripts de migration dans l'ordre :
```bash
php setup/install_email_system.php
php setup/install_events.php
php setup/install_polls.php
# ... autres scripts setup/migrate_*.php
```

### Étape 3 : Personnalisation du club

Modifiez uniquement le fichier `club_config.php` :

#### 1. Informations du club

```php
define('CLUB_NAME', 'Nom de votre club');
define('CLUB_SHORT_NAME', 'Acronyme');
define('CLUB_CITY', 'Votre ville');
define('CLUB_DEPARTMENT', 'Votre département');
define('CLUB_HOME_BASE', 'CODE_OACI'); // Ex: LFXX
```

#### 2. Contact et communication

```php
define('CLUB_EMAIL_FROM', 'contact@votre-club.fr');
define('CLUB_EMAIL_SENDER_NAME', 'NOM DE VOTRE CLUB');
define('CLUB_PHONE', '+33 X XX XX XX XX');
define('CLUB_WEBSITE', 'https://votre-club.fr');
```

#### 3. Visuels et branding

**Logo du club :**
- Placez votre logo dans `assets/img/logo.png`
- Ou modifiez le chemin : `define('CLUB_LOGO_PATH', 'chemin/vers/logo.png');`

**Couleurs :**
```php
define('CLUB_COLOR_PRIMARY', '#004b8d');    // Couleur principale
define('CLUB_COLOR_SECONDARY', '#00a0c6');  // Couleur secondaire
define('CLUB_COLOR_ACCENT', '#0078b8');     // Couleur d'accentuation
```

#### 4. Modules optionnels

Activez/désactivez des fonctionnalités selon vos besoins :

```php
define('CLUB_MODULE_EVENTS', true);        // Gestion des événements
define('CLUB_MODULE_POLLS', true);         // Sondages
define('CLUB_MODULE_PROPOSALS', true);     // Propositions de sorties
define('CLUB_MODULE_CHANGELOG', true);     // Historique des versions
define('CLUB_MODULE_STATS', true);         // Statistiques
define('CLUB_MODULE_BASULM_IMPORT', true); // Import BasULM
define('CLUB_MODULE_WEATHER', true);       // Météo
```

#### 5. Règles de gestion

```php
// Nombre de sorties visées par mois
define('CLUB_SORTIES_PER_MONTH', 2);

// Délai minimum d'inscription avant une sortie (en jours)
define('CLUB_INSCRIPTION_MIN_DAYS', 3);

// Priorité automatique pour membres inscrits aux 2 sorties
define('CLUB_PRIORITY_DOUBLE_INSCRIPTION', true);
```

### Étape 4 : Création du compte administrateur

Exécutez le script de création d'admin :

```bash
php create_admin.php
```

Suivez les instructions pour créer votre premier compte administrateur.

### Étape 5 : Intégration dans les pages existantes

Pour utiliser la configuration dans vos pages PHP, ajoutez en début de fichier :

```php
require_once 'config.php';
require_once 'club_config.php';

// Utiliser les constantes
echo CLUB_NAME; // Affiche le nom du club
echo CLUB_EMAIL_FROM; // Affiche l'email du club

// Utiliser les fonctions helper
$config = get_club_config();
echo $config['name'];

// Vérifier si un module est actif
if (is_module_enabled('polls')) {
    // Afficher le menu sondages
}
```

## 🎨 Personnalisation avancée

### CSS personnalisé avec les couleurs du club

Dans votre fichier `header.php` ou template HTML :

```php
<style>
<?php echo get_club_css_colors(); ?>

.btn-primary {
    background: linear-gradient(135deg, var(--club-color-primary), var(--club-color-accent));
}

.navbar {
    background-color: var(--club-color-primary);
}
</style>
```

### Signature email personnalisée

```php
require_once 'club_config.php';

$signature = get_club_email_signature('2.0.0'); // Version de l'application
$emailContent = $messageBody . $signature;
```

## 📦 Structure des fichiers

```
GESTNAV/
├── club_config.php           ← Fichier de configuration du club (À PERSONNALISER)
├── config.php                ← Configuration technique (BDD, chemins)
├── config_mail.php           ← Configuration SMTP
├── assets/
│   └── img/
│       ├── logo.png          ← Logo de votre club
│       └── cover.jpg         ← Photo de couverture
├── header.php                ← En-tête (intègre logo et couleurs)
├── footer.php                ← Pied de page
└── ...
```

## 🔧 Configuration SMTP (emails)

Modifiez `config_mail.php` pour configurer l'envoi d'emails :

```php
define('SMTP_HOST', 'smtp.votre-hebergeur.fr');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@votre-club.fr');
define('SMTP_PASS', 'votre_mot_de_passe');
define('SMTP_FROM_EMAIL', CLUB_EMAIL_FROM);
define('SMTP_FROM_NAME', CLUB_EMAIL_SENDER_NAME);
```

## 🌍 Intégrations externes

### API Météo (optionnel)

```php
define('CLUB_WEATHER_API_KEY', 'votre_cle_api');
define('CLUB_WEATHER_API_PROVIDER', 'openweathermap');
```

Obtenir une clé API : https://openweathermap.org/api

### Carte géographique

```php
// Coordonnées du centre de la carte (votre aérodrome)
define('CLUB_MAP_DEFAULT_CENTER_LAT', 48.8566); // Latitude
define('CLUB_MAP_DEFAULT_CENTER_LNG', 2.3522);  // Longitude
define('CLUB_MAP_DEFAULT_ZOOM', 8);
```

## ✅ Liste de contrôle

Avant la mise en production :

- [ ] Base de données créée et scripts de migration exécutés
- [ ] `config.php` configuré avec identifiants BDD
- [ ] `club_config.php` personnalisé avec infos du club
- [ ] Logo placé dans `assets/img/`
- [ ] `config_mail.php` configuré pour SMTP
- [ ] Compte administrateur créé
- [ ] Tests sur toutes les pages principales
- [ ] Vérification des emails envoyés
- [ ] Sauvegarde de la configuration

## 🆘 Support

Pour toute question ou problème :

1. Consultez la documentation dans `/ARCHITECTURE_*.md`
2. Vérifiez les logs d'erreurs PHP
3. Ouvrez une issue sur le dépôt GitHub

## 📝 Exemple de configuration complète

Voici un exemple pour un club fictif "Ailes du Nord" :

```php
// Informations du club
define('CLUB_NAME', 'Ailes du Nord ULM');
define('CLUB_SHORT_NAME', 'Ailes du Nord');
define('CLUB_CITY', 'Lille');
define('CLUB_DEPARTMENT', 'Nord (59)');
define('CLUB_HOME_BASE', 'LFQQ'); // Lille-Lesquin

// Contact
define('CLUB_EMAIL_FROM', 'contact@ailesdunord.fr');
define('CLUB_EMAIL_SENDER_NAME', 'AILES DU NORD ULM');
define('CLUB_PHONE', '+33 3 20 XX XX XX');
define('CLUB_WEBSITE', 'https://ailesdunord.fr');

// Couleurs (exemple en rouge/gris)
define('CLUB_COLOR_PRIMARY', '#c41e3a');
define('CLUB_COLOR_SECONDARY', '#e74c3c');
define('CLUB_COLOR_ACCENT', '#d63031');

// Logo
define('CLUB_LOGO_PATH', 'assets/img/logo_ailes_nord.png');

// Modules
define('CLUB_MODULE_EVENTS', true);
define('CLUB_MODULE_POLLS', false);  // Désactivé pour ce club
define('CLUB_MODULE_PROPOSALS', true);
```

## 🔄 Mises à jour

Pour mettre à jour GESTNAV vers une nouvelle version :

1. Sauvegardez votre fichier `club_config.php`
2. Sauvegardez votre base de données
3. Téléchargez la nouvelle version
4. Remplacez tous les fichiers SAUF `club_config.php` et `config.php`
5. Exécutez les nouveaux scripts de migration si nécessaire
6. Vérifiez que tout fonctionne

**Important :** Ne modifiez jamais les fichiers core de GESTNAV. Toute personnalisation doit passer par `club_config.php` pour faciliter les mises à jour.
