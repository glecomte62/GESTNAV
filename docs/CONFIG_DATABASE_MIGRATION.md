# Migration de la configuration vers la base de données

## Vue d'ensemble

La configuration du club GESTNAV est désormais stockée en **base de données** au lieu d'être codée en dur dans `club_config.php`. Cela permet :

✅ **Configuration dynamique** via l'interface web `/config_generale.php`  
✅ **Pas de modification de code** pour configurer un nouveau club  
✅ **Historique des modifications** (qui a modifié quoi et quand)  
✅ **Compatibilité** avec l'ancien système grâce aux constantes `define()`

---

## Architecture

### 1. Table de base de données : `club_settings`

```sql
CREATE TABLE club_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,    -- Ex: 'club_name'
    setting_value TEXT,                          -- Valeur du paramètre
    setting_type ENUM('string', 'integer', 'float', 'boolean', 'json'),
    category VARCHAR(50),                        -- 'info', 'contact', 'branding', etc.
    description VARCHAR(255),                    -- Description du paramètre
    updated_at TIMESTAMP,                        -- Date de dernière modification
    updated_by INT,                              -- ID de l'admin qui a modifié
    FOREIGN KEY (updated_by) REFERENCES users(id)
);
```

### 2. Gestionnaire de configuration : `utils/club_config_manager.php`

**Fonctions principales** :

```php
// Charger toute la configuration
$config = load_club_config();

// Récupérer une valeur
$clubName = get_club_setting('club_name', 'Défaut');

// Mettre à jour une valeur
update_club_setting('club_name', 'Nouveau nom', $userId);

// Mettre à jour plusieurs valeurs
update_club_settings([
    'club_name' => 'Mon club',
    'club_city' => 'Ma ville'
], $userId);

// Vérifier si un module est activé
if (is_module_enabled('events')) {
    // ...
}

// Récupérer les couleurs
$colors = get_club_colors();

// Récupérer toutes les infos du club
$info = get_club_info();
```

### 3. Fichier de configuration : `club_config.php`

**Nouveau comportement** :

```php
<?php
// Charge la configuration depuis la BDD
require_once 'config.php';
require_once 'utils/club_config_manager.php';

// Les constantes sont automatiquement définies :
// CLUB_NAME, CLUB_CITY, CLUB_EMAIL_FROM, etc.
```

⚠️ **Ne plus modifier ce fichier manuellement !**  
Toutes les modifications doivent passer par l'interface web `/config_generale.php`

### 4. Interface web : `config_generale.php`

L'interface admin permet de :
- Modifier toutes les informations du club via formulaire
- Activer/désactiver les modules
- Configurer les couleurs et le branding
- Définir les règles de gestion

**Modifications sauvegardées** :
- En base de données dans `club_settings`
- Avec traçabilité : date + utilisateur dans `updated_at` et `updated_by`
- Log dans `operation_logs`

---

## Migration d'une installation existante

### Étape 1 : Créer la table

```bash
mysql -u VOTRE_UTILISATEUR -p VOTRE_BASE < setup/migration_config_to_db.sql
```

Ou via phpMyAdmin :
1. Ouvrir phpMyAdmin
2. Sélectionner la base de données
3. Cliquer sur "SQL"
4. Coller le contenu de `setup/migration_config_to_db.sql`
5. Exécuter

### Étape 2 : Vérifier les valeurs insérées

Par défaut, le script insère les valeurs du **Club ULM Evasion** (Maubeuge).

**Pour personnaliser AVANT l'import** :
1. Éditer `setup/migration_config_to_db.sql`
2. Modifier les valeurs dans les INSERT (nom, ville, couleurs, etc.)
3. Exécuter le script SQL

**Pour personnaliser APRÈS l'import** :
1. Se connecter en tant qu'administrateur
2. Aller sur `/config_generale.php`
3. Remplir le formulaire
4. Sauvegarder

### Étape 3 : Remplacer `club_config.php`

**Option A : Sauvegarde et remplacement**

```bash
# Sauvegarder l'ancien fichier
cp club_config.php club_config.php.backup

# Le nouveau fichier est déjà en place
# Il charge automatiquement depuis la BDD
```

**Option B : Migration manuelle**

Si vous avez des valeurs personnalisées dans votre ancien `club_config.php` :

1. Noter vos valeurs actuelles (nom, ville, couleurs, etc.)
2. Aller sur `/config_generale.php`
3. Remplir le formulaire avec vos valeurs
4. Sauvegarder
5. Vérifier que tout fonctionne
6. Remplacer `club_config.php` par la nouvelle version

### Étape 4 : Vérification

**Vérifier que la configuration est chargée** :

```php
// Dans n'importe quelle page
echo CLUB_NAME; // Doit afficher le nom de votre club
echo CLUB_CITY; // Doit afficher votre ville
var_dump(get_club_setting('club_name')); // Doit retourner votre nom
```

**Vérifier les modules** :

```php
if (is_module_enabled('events')) {
    echo "Module événements actif";
}
```

---

## Paramètres disponibles

### Informations du club
- `club_name` - Nom complet
- `club_short_name` - Nom court
- `club_city` - Ville
- `club_department` - Département
- `club_region` - Région
- `club_home_base` - Code OACI de la base

### Contact
- `club_email_from` - Email principal
- `club_email_reply_to` - Email de réponse
- `club_phone` - Téléphone
- `club_website` - Site web
- `club_facebook` - Page Facebook

### Adresse
- `club_address_line1` - Adresse ligne 1
- `club_address_line2` - Adresse ligne 2
- `club_address_postal` - Code postal + ville

### Branding
- `club_logo_path` - Chemin du logo
- `club_logo_alt` - Texte alternatif du logo
- `club_logo_height` - Hauteur du logo (pixels)
- `club_cover_image` - Image de couverture
- `club_color_primary` - Couleur primaire (#hex)
- `club_color_secondary` - Couleur secondaire (#hex)
- `club_color_accent` - Couleur d'accent (#hex)

### Modules
- `module_events` - Événements (booléen)
- `module_polls` - Sondages (booléen)
- `module_proposals` - Propositions (booléen)
- `module_changelog` - Changelog (booléen)
- `module_stats` - Statistiques (booléen)
- `module_basulm_import` - Import BasULM (booléen)
- `module_weather` - Météo (booléen)

### Règles de gestion
- `sorties_per_month` - Nombre de sorties/mois (entier)
- `inscription_min_days` - Délai min d'inscription (jours)
- `notification_days_before` - Délai de notification (jours)
- `priority_double_inscription` - Priorité double inscription (booléen)

### Uploads
- `max_photo_size` - Taille max photo (octets)
- `max_attachment_size` - Taille max pièce jointe (octets)
- `max_event_cover_size` - Taille max couverture événement (octets)

### Intégrations
- `weather_api_key` - Clé API météo
- `weather_api_provider` - Fournisseur API météo
- `map_default_center_lat` - Latitude centre carte
- `map_default_center_lng` - Longitude centre carte
- `map_default_zoom` - Zoom par défaut

---

## Avantages de cette architecture

### Pour les développeurs

✅ **Pas besoin de modifier du code PHP** pour configurer un nouveau club  
✅ **Configuration centralisée** dans une seule table  
✅ **API cohérente** avec fonctions helper  
✅ **Rétrocompatibilité** avec constantes `define()`

### Pour les administrateurs

✅ **Interface graphique** facile à utiliser  
✅ **Modifications en temps réel** sans FTP  
✅ **Pas de risque de casser le code**  
✅ **Historique des modifications** tracé

### Pour les clubs

✅ **Installation simplifiée** : importer la BDD + remplir le formulaire  
✅ **Multi-clubs sur même serveur** : une table par club  
✅ **Sauvegarde facile** : exporter la table `club_settings`  
✅ **Configuration portable** : dump SQL → import ailleurs

---

## Dépannage

### Erreur "CLUB_NAME not defined"

**Cause** : `club_config_manager.php` n'est pas chargé ou la table `club_settings` n'existe pas.

**Solution** :
1. Vérifier que la table existe : `SHOW TABLES LIKE 'club_settings';`
2. Vérifier que `club_config.php` charge bien `club_config_manager.php`
3. Vérifier les logs d'erreur PHP

### Les modifications ne s'appliquent pas

**Cause** : Cache de configuration.

**Solution** :
```php
// Invalider le cache manuellement
global $_CLUB_CONFIG_CACHE;
$_CLUB_CONFIG_CACHE = null;
```

Ou redémarrer PHP-FPM / Apache.

### Valeurs par défaut au lieu des miennes

**Cause** : Aucune valeur en base de données.

**Solution** :
1. Vérifier : `SELECT * FROM club_settings;`
2. Si vide : importer `setup/migration_config_to_db.sql`
3. Ou remplir via `/config_generale.php`

---

## Exemple d'utilisation

### Dans vos pages PHP

```php
<?php
require_once 'config.php';
require_once 'club_config.php';

// Les constantes sont disponibles
echo CLUB_NAME;              // "Club ULM Evasion"
echo CLUB_CITY;              // "Maubeuge"
echo CLUB_EMAIL_FROM;        // "info@clubulmevasion.fr"

// Ou via les fonctions
$clubName = get_club_setting('club_name');
$colors = get_club_colors();
$info = get_club_info();

// Vérifier un module
if (is_module_enabled('events')) {
    // Afficher les événements
}
```

### Modifier un paramètre par code

```php
<?php
require_once 'config.php';
require_once 'utils/club_config_manager.php';

// Mettre à jour le nom du club
update_club_setting('club_name', 'Nouveau nom', $_SESSION['user_id']);

// Activer un module
update_club_setting('module_weather', true, $_SESSION['user_id']);

// Mettre à jour plusieurs paramètres
update_club_settings([
    'club_name' => 'Mon Club ULM',
    'club_city' => 'Paris',
    'club_color_primary' => '#FF5733'
], $_SESSION['user_id']);
```

---

## Support

Pour toute question ou problème :
1. Vérifier cette documentation
2. Consulter les logs : `error_log` PHP + `operation_logs` en BDD
3. Tester avec les valeurs par défaut
4. Contacter le support GESTNAV
