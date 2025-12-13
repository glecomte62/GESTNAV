# Configuration du club en base de données

## 🎯 Objectif

Permettre la configuration de GESTNAV via une **interface web** plutôt que par modification de fichiers PHP. Les paramètres sont stockés en base de données et modifiables via `/config_generale.php`.

---

## 📁 Fichiers créés/modifiés

### Nouveaux fichiers

1. **`setup/migration_config_to_db.sql`**  
   Script SQL pour créer la table `club_settings` et insérer les valeurs par défaut

2. **`utils/club_config_manager.php`**  
   Gestionnaire de configuration : fonctions pour lire/écrire en BDD

3. **`setup/import_config_to_db.php`**  
   Script CLI pour migrer les valeurs de club_config.php vers la BDD

4. **`docs/CONFIG_DATABASE_MIGRATION.md`**  
   Documentation complète de la migration

### Fichiers modifiés

1. **`club_config.php`**  
   Simplifié : charge maintenant la config depuis la BDD via `club_config_manager.php`

2. **`config_generale.php`**  
   Modifié pour enregistrer en BDD au lieu de générer un fichier PHP

---

## 🚀 Installation (nouveau club)

### 1. Créer la table

```bash
mysql -u UTILISATEUR -p BASE_DE_DONNEES < setup/migration_config_to_db.sql
```

### 2. Configurer via l'interface web

1. Se connecter en tant qu'admin
2. Aller sur `/config_generale.php`
3. Remplir le formulaire avec les infos de votre club
4. Sauvegarder

✅ **C'est tout !** Pas besoin de modifier du code PHP.

---

## 🔄 Migration (club existant)

Si vous avez déjà un `club_config.php` personnalisé :

### Méthode 1 : Script automatique

```bash
# Sauvegarder l'ancien fichier
cp club_config.php club_config.php.backup

# Créer la table
mysql -u USER -p DATABASE < setup/migration_config_to_db.sql

# Importer les valeurs
php setup/import_config_to_db.php
```

### Méthode 2 : Manuelle

1. Créer la table : `mysql < setup/migration_config_to_db.sql`
2. Noter vos valeurs actuelles dans `club_config.php`
3. Aller sur `/config_generale.php`
4. Saisir vos valeurs
5. Sauvegarder

---

## 📚 Utilisation dans votre code

### Constantes (rétrocompatibilité)

```php
<?php
require_once 'club_config.php';

echo CLUB_NAME;              // "Club ULM Evasion"
echo CLUB_CITY;              // "Maubeuge"
echo CLUB_EMAIL_FROM;        // "info@clubulmevasion.fr"
echo CLUB_COLOR_PRIMARY;     // "#004b8d"
```

### Fonctions helper (recommandé)

```php
<?php
require_once 'utils/club_config_manager.php';

// Récupérer une valeur
$nom = get_club_setting('club_name');
$ville = get_club_setting('club_city', 'Défaut');

// Récupérer toutes les infos
$info = get_club_info();
// ['name' => '...', 'city' => '...', 'colors' => [...], ...]

// Récupérer les couleurs
$colors = get_club_colors();
// ['primary' => '#...', 'secondary' => '#...', 'accent' => '#...']

// Vérifier un module
if (is_module_enabled('events')) {
    // Module événements activé
}

// Modifier une valeur (dans le code si besoin)
update_club_setting('club_name', 'Nouveau nom', $userId);

// Modifier plusieurs valeurs
update_club_settings([
    'club_name' => 'Mon club',
    'club_city' => 'Ma ville'
], $userId);
```

---

## 🗂️ Structure de la table

```sql
CREATE TABLE club_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE,      -- Ex: 'club_name'
    setting_value TEXT,                   -- Valeur
    setting_type ENUM(...),               -- Type: string, integer, boolean, etc.
    category VARCHAR(50),                 -- Catégorie: info, contact, branding...
    description VARCHAR(255),             -- Description
    updated_at TIMESTAMP,                 -- Date de modification
    updated_by INT,                       -- Admin qui a modifié
    FOREIGN KEY (updated_by) REFERENCES users(id)
);
```

---

## 🔧 Paramètres disponibles

### Informations du club (category: `info`)
- `club_name`, `club_short_name`, `club_city`, `club_department`, `club_region`, `club_home_base`

### Contact (category: `contact`)
- `club_email_from`, `club_email_reply_to`, `club_phone`, `club_website`, `club_facebook`

### Adresse (category: `address`)
- `club_address_line1`, `club_address_line2`, `club_address_postal`

### Branding (category: `branding`)
- `club_logo_path`, `club_logo_height`, `club_cover_image`
- `club_color_primary`, `club_color_secondary`, `club_color_accent`

### Modules (category: `modules`)
- `module_events`, `module_polls`, `module_proposals`, `module_changelog`
- `module_stats`, `module_basulm_import`, `module_weather`

### Règles (category: `rules`)
- `sorties_per_month`, `inscription_min_days`, `notification_days_before`
- `priority_double_inscription`

### Uploads (category: `uploads`)
- `max_photo_size`, `max_attachment_size`, `max_event_cover_size`

### Intégrations (category: `integrations`)
- `weather_api_key`, `weather_api_provider`
- `map_default_center_lat`, `map_default_center_lng`, `map_default_zoom`

---

## ⚙️ Administration

### Interface web : `/config_generale.php`

- Accessible uniquement aux administrateurs
- Formulaire avec onglets : Informations, Contact, Branding, Règles, Modules
- Sauvegarde en temps réel en BDD
- Traçabilité : date + utilisateur qui a modifié

### Logs

Toutes les modifications sont enregistrées dans :
- `club_settings.updated_at` - Date de modification
- `club_settings.updated_by` - ID de l'admin
- `operation_logs` - Log détaillé de l'action

---

## 🛠️ Dépannage

### Erreur "CLUB_NAME not defined"

**Cause** : Table `club_settings` inexistante ou vide.

**Solution** :
```bash
mysql -u USER -p DB < setup/migration_config_to_db.sql
```

### Les modifications ne s'appliquent pas

**Cause** : Cache de configuration.

**Solution** : Redémarrer PHP-FPM ou Apache, ou invalider le cache :
```php
global $_CLUB_CONFIG_CACHE;
$_CLUB_CONFIG_CACHE = null;
```

### Valeurs par défaut affichées

**Cause** : Aucune donnée en BDD.

**Solution** :
```sql
-- Vérifier
SELECT COUNT(*) FROM club_settings;

-- Si 0, importer les valeurs
SOURCE setup/migration_config_to_db.sql;
```

---

## ✅ Avantages

### Pour les développeurs
- ✅ Pas de modification de code PHP pour configurer un club
- ✅ API cohérente avec fonctions helper
- ✅ Rétrocompatibilité avec constantes

### Pour les administrateurs
- ✅ Interface graphique intuitive
- ✅ Modifications en temps réel
- ✅ Pas de risque de casser le code
- ✅ Historique des modifications

### Pour les clubs
- ✅ Installation simplifiée
- ✅ Configuration portable (dump SQL)
- ✅ Multi-clubs sur même serveur possible
- ✅ Sauvegarde facile

---

## 📖 Documentation complète

Voir [`docs/CONFIG_DATABASE_MIGRATION.md`](../docs/CONFIG_DATABASE_MIGRATION.md) pour :
- Architecture détaillée
- Guide de migration pas à pas
- Exemples de code
- Référence complète des paramètres
- Troubleshooting approfondi

---

## 🎉 Résultat

**Avant** :
```php
// Pour configurer un club, il fallait modifier club_config.php
define('CLUB_NAME', 'Mon Club');
define('CLUB_CITY', 'Ma Ville');
// ... 50+ lignes de defines
```

**Maintenant** :
1. Aller sur `/config_generale.php`
2. Remplir le formulaire
3. Sauvegarder
4. ✅ C'est fait !

---

**Créé le** : 13 décembre 2025  
**Pour** : GESTNAV - Gestion de club ULM  
**Par** : Migration configuration vers BDD
