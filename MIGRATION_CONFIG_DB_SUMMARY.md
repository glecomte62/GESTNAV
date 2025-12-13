# ✅ Migration configuration BDD - Résumé

**Date** : 13 décembre 2025  
**Objectif** : Lier la configuration du club à la base de données plutôt qu'à un fichier PHP statique

---

## 🎯 Ce qui a été fait

### 1. **Table de base de données `club_settings`**

✅ Créée dans `setup/schema.sql` (lignes 524-617)  
✅ Aussi disponible séparément dans `setup/migration_config_to_db.sql`

**Structure** :
- `setting_key` : Nom du paramètre (ex: 'club_name')
- `setting_value` : Valeur du paramètre
- `setting_type` : Type (string, integer, boolean, float, json)
- `category` : Catégorie (info, contact, branding, modules, rules, uploads, integrations)
- `description` : Description du paramètre
- `updated_at` : Date de dernière modification
- `updated_by` : ID de l'admin qui a modifié

**Valeurs par défaut** : Club ULM Evasion (Maubeuge) déjà insérées

---

### 2. **Gestionnaire de configuration : `utils/club_config_manager.php`**

✅ **Créé** - 263 lignes

**Fonctions principales** :
- `load_club_config()` - Charge tous les paramètres depuis la BDD (avec cache)
- `get_club_setting($key, $default)` - Récupère une valeur
- `update_club_setting($key, $value, $userId)` - Met à jour une valeur
- `update_club_settings($settings, $userId)` - Met à jour plusieurs valeurs
- `is_module_enabled($moduleName)` - Vérifie si un module est activé
- `get_club_colors()` - Récupère les 3 couleurs du club
- `get_club_map_center()` - Récupère lat/lng/zoom de la carte
- `get_club_info()` - Récupère toutes les infos du club

**Fonctionnalités** :
- ✅ Cache en mémoire pour éviter les requêtes répétées
- ✅ Conversion automatique des types (string → int, boolean, float)
- ✅ Configuration par défaut si BDD vide
- ✅ Définition automatique des constantes `CLUB_*` pour rétrocompatibilité

---

### 3. **Fichier de configuration : `club_config.php`**

✅ **Simplifié** de 294 lignes → 159 lignes

**Avant** :
```php
define('CLUB_NAME', 'Club ULM Evasion');
define('CLUB_CITY', 'Maubeuge');
// ... 50+ defines
```

**Maintenant** :
```php
require_once 'config.php';
require_once 'utils/club_config_manager.php';
// Les constantes sont auto-définies depuis la BDD
```

**Ce qui reste en dur** :
- Types de membres (`CLUB_MEMBER_TYPES`)
- Types d'événements (`CLUB_EVENT_TYPES`)
- Qualifications pilotes (`CLUB_PILOT_QUALIFICATIONS`)
- Préfixes d'emails (`CLUB_EMAIL_PREFIXES`)
- Template signature email
- Types de fichiers autorisés
- Textes statiques (Home, About, Legal)

---

### 4. **Interface web : `config_generale.php`**

✅ **Modifié** pour enregistrer en BDD au lieu de générer un fichier PHP

**Avant** :
- Lisait `club_config.php` avec regex
- Générait un nouveau fichier PHP complet
- Écrasait `club_config.php` avec `file_put_contents()`

**Maintenant** :
- Lit les valeurs depuis la BDD via `get_club_setting()`
- Enregistre via `update_club_settings()` en BDD
- Log dans `operation_logs`
- Traçabilité : date + utilisateur dans `club_settings.updated_by`

---

### 5. **Script de migration : `setup/import_config_to_db.php`**

✅ **Créé** - 149 lignes

**Utilité** : Migrer un `club_config.php` existant vers la BDD

**Fonctionnement** :
1. Lit `club_config.php.backup` ou `club_config.php`
2. Extrait toutes les valeurs avec regex
3. Affiche un résumé et demande confirmation
4. Insère dans `club_settings` avec types et catégories corrects
5. Affiche le résultat (X paramètres importés)

**Usage** :
```bash
php setup/import_config_to_db.php
```

---

### 6. **Documentation**

✅ **`docs/CONFIG_DATABASE_MIGRATION.md`** (365 lignes)
- Architecture complète
- Guide de migration pas à pas
- Référence de tous les paramètres
- Exemples de code
- Troubleshooting

✅ **`setup/README_CONFIG_DB.md`** (271 lignes)
- Vue d'ensemble rapide
- Installation pour nouveau club
- Migration pour club existant
- Exemples d'utilisation
- Liste des paramètres

---

## 📁 Fichiers créés/modifiés

### ✨ Nouveaux fichiers (5)
1. `setup/migration_config_to_db.sql` - Script SQL de création table + données
2. `utils/club_config_manager.php` - Gestionnaire de configuration
3. `setup/import_config_to_db.php` - Script CLI de migration
4. `docs/CONFIG_DATABASE_MIGRATION.md` - Documentation complète
5. `setup/README_CONFIG_DB.md` - Guide rapide

### 📝 Fichiers modifiés (3)
1. `club_config.php` - Simplifié, charge depuis BDD
2. `config_generale.php` - Enregistre en BDD au lieu de fichier
3. `setup/schema.sql` - Ajout table `club_settings` + INSERT

---

## 🚀 Pour utiliser immédiatement

### Option A : Nouvelle installation

```bash
# 1. Créer la BDD
mysql -u USER -p DATABASE < setup/schema.sql

# 2. Se connecter en admin et aller sur /config_generale.php

# 3. Modifier les valeurs si besoin
```

### Option B : Migration d'une installation existante

```bash
# 1. Sauvegarder l'ancien fichier
cp club_config.php club_config.php.backup

# 2. Créer la table
mysql -u USER -p DATABASE < setup/migration_config_to_db.sql

# 3. Importer les valeurs
php setup/import_config_to_db.php

# 4. Vérifier sur /config_generale.php
```

---

## 💡 Utilisation dans le code

### Constantes (rétrocompatibilité)
```php
echo CLUB_NAME;              // "Club ULM Evasion"
echo CLUB_CITY;              // "Maubeuge"
echo CLUB_COLOR_PRIMARY;     // "#004b8d"
```

### Fonctions (recommandé)
```php
$nom = get_club_setting('club_name');
$info = get_club_info();
$colors = get_club_colors();

if (is_module_enabled('events')) {
    // ...
}

update_club_setting('club_name', 'Nouveau nom', $userId);
```

---

## ✅ Bénéfices

### Pour les clubs
- ✅ Configuration via formulaire web (pas de FTP)
- ✅ Installation simplifiée
- ✅ Pas de risque de casser le code

### Pour les développeurs
- ✅ Configuration centralisée en BDD
- ✅ API cohérente
- ✅ Rétrocompatibilité assurée

### Pour les admins
- ✅ Modifications en temps réel
- ✅ Historique tracé
- ✅ Interface intuitive

---

## 🔧 Prochaines étapes possibles

**Optionnel** (pas fait aujourd'hui) :
- [ ] Migrer les textes statiques (Home, About) en BDD
- [ ] Interface pour uploader le logo via le formulaire
- [ ] Preview en temps réel des couleurs
- [ ] Export/Import de configuration entre clubs
- [ ] API REST pour configuration programmatique

---

## 📊 Statistiques

- **Fichiers créés** : 5
- **Fichiers modifiés** : 3
- **Lignes de code ajoutées** : ~1500
- **Lignes de documentation** : ~636
- **Tables BDD** : +1 (`club_settings`)
- **Paramètres configurables** : 41

---

**Résultat** : La configuration du club est maintenant **100% dynamique** et modifiable via l'interface web `/config_generale.php`. Plus besoin de modifier du code PHP pour configurer un nouveau club ! 🎉
