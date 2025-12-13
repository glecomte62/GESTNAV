# Scripts d'installation et de migration GESTNAV

Ce dossier contient tous les scripts nécessaires pour **l'installation initiale** et les **migrations** de la base de données.

## ⚠️ Important

Ces scripts doivent être exécutés **une seule fois** lors de l'installation d'une nouvelle instance de GESTNAV ou lors d'une mise à jour majeure.

**Ne PAS exécuter ces scripts sur une base de données en production** sauf si vous savez exactement ce que vous faites.

## 📦 Scripts d'installation

Ces scripts créent les tables et structures de base nécessaires :

### `install_email_system.php`
Crée les tables pour le système d'emails :
- `email_logs` - Historique des emails envoyés
- `email_recipients` - Destinataires des emails
- Configuration initiale

### `install_events.php`
Crée les tables pour la gestion des événements :
- `evenements` - Événements du club
- `evenement_inscriptions` - Inscriptions aux événements
- `event_alerts` - Alertes automatiques

### `install_polls.php`
Crée les tables pour les sondages :
- `sondages` - Liste des sondages
- `sondage_options` - Options de réponse
- `sondage_votes` - Votes des membres

### `install_email_logs.php`
Installation complémentaire pour les logs d'emails détaillés.

## 🔄 Scripts de migration

Ces scripts ajoutent ou modifient des fonctionnalités existantes. Ils sont **idempotents** (peuvent être exécutés plusieurs fois sans dommage).

### Migrations des utilisateurs
- `migrate_add_type_membre.php` - Ajoute le champ type_membre (club/invité)
- `migrate_users_profile.php` - Ajoute les champs de profil utilisateur
- `migrate_pilot_qualifications.php` - Ajoute les qualifications pilotes

### Migrations des sorties
- `migrate_sorties_destination.php` - Ajoute la gestion des destinations
- `migrate_sorties_multi_days.php` - Active les sorties sur plusieurs jours
- `migrate_sorties_repas.php` - Ajoute la gestion des repas
- `migrate_sorties_status_en_etude.php` - Ajoute le statut "en étude"
- `migrate_add_ulm_base_to_sorties.php` - Ajoute les bases ULM aux sorties
- `migrate_sortie_proposals.php` - Crée le système de propositions

### Migrations des machines
- `migrate_machines_owners.php` - Gestion des propriétaires de machines
- `migrate_user_machines.php` - Association membres-machines

### Migrations des bases ULM
- `migrate_ulm_bases.php` - Import des bases ULM françaises

### Migrations des emails
- `migrate_email_history.php` - Historique complet des emails
- `migrate_email_logs.php` - Logs d'envoi
- `migrate_email_logs_message.php` - Corps des messages
- `migrate_email_recipients.php` - Destinataires détaillés

### Migrations des événements
- `migrate_events_schema.php` - Structure de base
- `migrate_events_deadline.php` - Dates limites d'inscription
- `migrate_event_alerts.php` - Système d'alertes

### Migrations des logs
- `migrate_connection_logs.php` - Logs de connexion
- `migrate_operations_logs.php` - Logs des opérations

### Autres migrations
- `migrate_photo_metadata.php` - Métadonnées des photos
- `migrate_polls.php` - Sondages

## 🚀 Ordre d'exécution pour une nouvelle installation

```bash
# 1. Installations de base
php setup/install_email_system.php
php setup/install_events.php
php setup/install_polls.php

# 2. Migrations essentielles (dans l'ordre)
php setup/migrate_add_type_membre.php
php setup/migrate_users_profile.php
php setup/migrate_pilot_qualifications.php
php setup/migrate_sorties_destination.php
php setup/migrate_ulm_bases.php
php setup/migrate_add_ulm_base_to_sorties.php
php setup/migrate_machines_owners.php
php setup/migrate_sortie_proposals.php

# 3. Migrations complémentaires (ordre non critique)
php setup/migrate_sorties_multi_days.php
php setup/migrate_sorties_repas.php
php setup/migrate_email_logs.php
php setup/migrate_connection_logs.php
php setup/migrate_operations_logs.php
# ... etc.
```

## 💡 Script d'installation automatique

Pour une installation complète automatisée, utilisez le script principal :

```bash
php setup_club.php
```

Ce script interactif :
- Configure automatiquement votre club
- Crée les fichiers de configuration
- Exécute toutes les migrations nécessaires
- Crée le premier compte administrateur

## 🔧 Dépannage

### Erreur "Table already exists"
C'est normal si vous ré-exécutez un script d'installation. Les scripts de migration gèrent ce cas.

### Erreur de connexion à la base de données
Vérifiez votre fichier `config.php` :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestnav');
define('DB_USER', 'votre_user');
define('DB_PASS', 'votre_password');
```

### Migration déjà appliquée
La plupart des migrations vérifient si les modifications existent déjà. Aucun risque de duplication.

## 📋 Checklist post-installation

- [ ] Toutes les tables créées sans erreur
- [ ] Compte administrateur créé (`php create_admin.php`)
- [ ] Logo du club placé dans `assets/img/`
- [ ] Configuration email testée
- [ ] Première connexion réussie
- [ ] Première sortie créée pour test

## 🗄️ Sauvegarde

**Important** : Avant d'exécuter une migration sur une base de production, **sauvegardez toujours** :

```bash
mysqldump -u user -p gestnav > backup_$(date +%Y%m%d_%H%M%S).sql
```

## 📞 Support

En cas de problème lors de l'installation :
1. Vérifiez les prérequis (PHP 7.4+, MySQL 5.7+)
2. Consultez les logs d'erreur PHP
3. Ouvrez une issue sur GitHub avec le message d'erreur complet
