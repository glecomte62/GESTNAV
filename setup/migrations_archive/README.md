# Archives des migrations

Ce dossier contient les anciens scripts de migration qui ont déjà été exécutés en production.

**⚠️ Ne pas supprimer** : Ces fichiers sont conservés pour référence historique et documentation.

**Status** : Migrations déjà appliquées - Ne nécessitent plus d'être exécutées.

## Organisation

- **migrations_archive/** : Scripts de migration de schéma de base de données déjà appliqués
- **install_archive/** : Scripts d'installation initiaux déjà exécutés

## Scripts actifs

Les scripts encore nécessaires sont dans le dossier parent `setup/` :
- `create_preinscriptions_table.sql` : Table pour les pré-inscriptions
- `import_users.php` : Import de membres
- `import_basulm_api.php` : Import des bases ULM

---

Date d'archivage : 12 décembre 2025
