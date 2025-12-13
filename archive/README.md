# Archive des fichiers GESTNAV

Ce dossier contient les fichiers qui ne sont plus nécessaires en production mais conservés pour référence.

## 📁 Structure

### `fix_scripts/`
Scripts de correction ponctuelle exécutés une seule fois :
- `fix_invitations.php` - Correction des invitations aux événements
- `fix_sorties_status_en_etude.php` - Migration du statut "en étude" des sorties

**⚠️ Ces scripts ne doivent PAS être ré-exécutés sur une base de données en production.**

### `old_files/`
Anciens fichiers remplacés ou obsolètes :
- `annuaire_old_backup.php` - Ancienne version de l'annuaire (remplacé par `annuaire.php`)
- `envoyer_email.php.bak` - Backup de l'ancien système d'email
- `analyze_users_structure.py` - Script Python de debug de la structure utilisateurs
- `deploy_events.py` - Ancien script de déploiement des événements
- `users_structure_report.py` - Génération de rapport sur la structure users

### `test_files/`
Fichiers de test et debug (17 fichiers) :
- `test_*.php` - Divers tests unitaires et d'intégration

## 🗑️ Suppression

Ces fichiers peuvent être supprimés définitivement après 6 mois si aucun besoin de référence n'est constaté.

Date d'archivage : 11 décembre 2025
