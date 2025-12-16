# Fichiers de test archivés

Ce dossier contient les fichiers de test et de développement qui ne sont plus utilisés en production.

## Contenu

### Tests de base de données
- `test_db_simple.php` - Test de connexion basique à la base de données
- `test_db_structure.php` - Vérification de la structure de la base
- `test_complete.php` - Tests complets de la base de données

### Tests de fonctionnalités
- `test_login.php` / `test_login_post.php` / `test_simple_login.php` - Tests du système d'authentification
- `test_propose.php` / `test_simple_propose.php` / `test_proposal_debug.php` - Tests des propositions de sorties
- `test_sorties.php` / `test_sorties_all.php` / `test_sorties_structure.php` - Tests du module sorties
- `test_relance.php` - Tests des relances email
- `test_mail.php` - Tests d'envoi d'emails
- `test_mes_stats.php` - Tests du module statistiques

### Tests de parsing et documents
- `parse_pdf_test.php` - Tests d'extraction PDF
- `test_parser.php` - Tests du parser de documents
- `test_extraction.php` - Tests d'extraction de contenu
- `test_documents.php` - Tests du module documents
- `test_classification.php` - Tests de la classification automatique

### Tests API et intégrations
- `test_keyyo.php` - Tests de l'API Keyyo (téléphonie)
- `test_changelog_parser.php` - Tests du parser de changelog

### Tests d'interface
- `test_dragdrop.html` - Tests du drag & drop (interface)

### Tests système
- `test_admin_simple.php` - Tests des fonctions admin
- `test_functions.php` - Tests des fonctions utilitaires
- `test_users_structure.php` - Tests de la structure utilisateurs
- `test_errors.php` - Tests de gestion d'erreurs

## Note
Ces fichiers sont conservés à titre de référence et pour d'éventuels besoins de débogage futurs.
Ils ne doivent pas être déployés en production.

**Date d'archivage** : 16 décembre 2025
