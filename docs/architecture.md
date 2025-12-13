# Architecture et conventions

## Stack
- PHP 8 + PDO MySQL.
- Front: Bootstrap 5, CSS custom `assets/css/gestnav.css`, Leaflet pour carte.
- Déploiement: scripts `tools/deploy_ftp.sh` (principal) et `tools/deploy_rsync.sh`.

## Organisation du code
- Pages PHP plates (action + rendu), avec try/catch et DDL conditionnels (SHOW TABLES/COLUMNS).
- Helpers: `config.php` (env, helpers URL, version, locale), `mail_helper.php`, `utils/activity_log.php`.
- Assets: `assets/css`, `assets/img`, `assets/leaflet` (local JS/CSS/images icônes).

## Conventions
- Traiter les POST/actions avant HTML (éviter headers déjà envoyés).
- Utiliser `app_url()` et `asset_url()` pour générer des liens absolus.
- `GESTNAV_VERSION` et `GESTNAV_BUILD_DATE` affichés dans footer.
- Emails: via `gestnav_send_mail()`; limiter les envois aux validations.

## Schéma de base de données (principales)
- Sorties: `sorties`, `sortie_inscriptions`, `sortie_machines`, `sortie_assignations`, `sortie_photos`.
- Extensions sorties: `sortie_preinscriptions`, `sortie_machines_exclusions`, `sortie_assignations_guests`, `sortie_priorites`.
- Machines: `machines`, `machines_owners`.
- Événements: `evenements`, `evenement_inscriptions`.
- Logs: `logs_connexions`, `logs_operations`.

## Flux clés
- Inscription sortie: membre s’inscrit → éventuellement pré-inscription → admin valide affectations → emails envoyés.
- Priorité: lors de validation, non affectés deviennent `sortie_priorites.active=1`; affichage badge.
- Auto-association machines: détection propriétaires → insertion machine sortie si non exclue.

## Sécurité/robustesse
- PDO exceptions activées; DDL/queries conditionnels pour tables/colonnes manquantes.
- Action tokens pour liens sensibles (annulation, changements).
- Fallback carte OSM si Leaflet indisponible.
