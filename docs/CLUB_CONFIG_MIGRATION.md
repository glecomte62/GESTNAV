# 📝 Plan d'exploitation des constantes club_config.php

## Objectif
Remplacer toutes les références en dur à "Club ULM Evasion" et "info@clubulmevasion.fr" par les constantes définies dans `club_config.php`.

## Constantes disponibles

```php
// Nom du club
CLUB_NAME                    // "Club ULM Evasion"
CLUB_SHORT_NAME              // "ULM EVASION"

// Emails
CLUB_EMAIL_FROM              // "info@clubulmevasion.fr"
CLUB_EMAIL_REPLY_TO          // "info@clubulmevasion.fr"
CLUB_EMAIL_SENDER_NAME       // "CLUB ULM EVASION"

// Logo
CLUB_LOGO_PATH               // "assets/img/logo.png"
CLUB_LOGO_ALT                // "Logo Club ULM Evasion"
CLUB_LOGO_HEIGHT             // 50

// Localisation
CLUB_CITY                    // "Steenvoorde"
CLUB_DEPARTMENT              // "Nord"
CLUB_HOME_BASE               // "LFQJ"
```

## Fichiers à mettre à jour

### 1. envoyer_email.php ✅ PRIORITÉ
- Ligne 225: `alt="Logo Club ULM Evasion"` → `alt="<?= CLUB_LOGO_ALT ?>"`
- Ligne 228: `<strong>Club ULM Evasion</strong>` → `<strong><?= CLUB_NAME ?></strong>`
- Ligne 236: `From: CLUB ULM EVASION <info@clubulmevasion.fr>` → `From: <?= CLUB_EMAIL_SENDER_NAME ?> <<?= CLUB_EMAIL_FROM ?>>`
- Ligne 591: `Gestion des Sorties et Membres - Club ULM Evasion` → `Gestion des Sorties et Membres - <?= CLUB_NAME ?>`

### 2. sortie_detail.php
- Ligne 34: `Club ULM Evasion` → `<?= CLUB_NAME ?>`
- Ligne 43: `Club ULM Evasion` → `<?= CLUB_NAME ?>`
- Ligne 709: `info@clubulmevasion.fr` → `<?= CLUB_EMAIL_FROM ?>`
- Ligne 838: `info@clubulmevasion.fr` → `<?= CLUB_EMAIL_FROM ?>`
- Ligne 851: `Le club ULM Evasion` → `Le <?= CLUB_NAME ?>`

### 3. preinscription_publique.php
- Ligne 115: `Pré-inscription au Club ULM Evasion` → `Pré-inscription au <?= CLUB_NAME ?>`
- Ligne 124: `Club ULM Evasion` → `<?= CLUB_NAME ?>`
- Ligne 130: `Club ULM Evasion` → `<?= CLUB_NAME ?>`
- Ligne 191: `Club ULM Evasion` → `<?= CLUB_NAME ?>`
- Ligne 196: `info@clubulmevasion.fr` → `<?= CLUB_EMAIL_FROM ?>`
- Ligne 231: `Club ULM Evasion` → `<?= CLUB_NAME ?>`
- Ligne 253: `Club ULM Evasion` → `<?= CLUB_NAME ?>`
- Ligne 348: `Club ULM Evasion` → `<?= CLUB_NAME ?>`
- Ligne 355: `Club ULM Evasion` → `<?= CLUB_NAME ?>`

### 4. preinscriptions_admin.php
- Ligne 115: `Bienvenue au Club ULM Evasion` → `Bienvenue au <?= CLUB_NAME ?>`
- Ligne 126: `Club ULM Evasion` → `<?= CLUB_NAME ?>`
- Ligne 148: `L'équipe du Club ULM Evasion` → `L'équipe du <?= CLUB_NAME ?>`
- Ligne 161: `From: Club ULM Evasion <info@clubulmevasion.fr>` → `From: <?= CLUB_NAME ?> <<?= CLUB_EMAIL_FROM ?>>`
- Ligne 202: `Demande d'inscription au Club ULM Evasion` → `Demande d'inscription au <?= CLUB_NAME ?>`
- Ligne 205: `Club ULM Evasion` → `<?= CLUB_NAME ?>`
- Ligne 209: `L'équipe du Club ULM Evasion` → `L'équipe du <?= CLUB_NAME ?>`
- Ligne 214: `From: Club ULM Evasion <info@clubulmevasion.fr>` → `From: <?= CLUB_NAME ?> <<?= CLUB_EMAIL_FROM ?>>`

### 5. sortie_proposals_admin.php
- Ligne 179: `alt="Logo Club ULM Evasion"` → `alt="<?= CLUB_LOGO_ALT ?>"`
- Ligne 187: `Gestion des Sorties et Membres - Club ULM Evasion` → `Gestion des Sorties et Membres - <?= CLUB_NAME ?>`
- Ligne 197: `From: CLUB ULM EVASION <info@clubulmevasion.fr>` → `From: <?= CLUB_EMAIL_SENDER_NAME ?> <<?= CLUB_EMAIL_FROM ?>>`

### 6. about.php
- Ligne 275: `GESTNAV ULM - Espace membres du Club ULM Evasion` → `GESTNAV ULM - Espace membres du <?= CLUB_NAME ?>`
- Ligne 301: `alt="Club ULM Evasion"` → `alt="<?= CLUB_NAME ?>"`
- Ligne 303: `Club ULM Evasion basé à LFQJ` → `<?= CLUB_NAME ?> basé à <?= CLUB_HOME_BASE ?>`

### 7. index.php
- Ligne 196: `club ULM Evasion` → `<?= CLUB_NAME ?>`

### 8. action_email_sortie.php
- Ligne 78: `Le comité du Club ULM Evasion` → `Le comité du <?= CLUB_NAME ?>`
- Ligne 81: `Le comité du Club ULM Evasion` → `Le comité du <?= CLUB_NAME ?>`

### 9. evenements_admin.php
- Ligne 98: `info@clubulmevasion.fr` → `<?= CLUB_EMAIL_FROM ?>`

### 10. utils/proposal_email_notifier.php
- Ligne 12: `private $adminEmail = 'info@clubulmevasion.fr';` → `private $adminEmail = CLUB_EMAIL_FROM;`
- Ligne 44: `Club ULM Evasion` → CLUB_NAME
- Ligne 89: `Club ULM Evasion` → CLUB_NAME

### 11. utils/event_alerts_helper.php
- Ligne 113: `info@clubulmevasion.fr` → CLUB_EMAIL_FROM

### 12. mail_helper_advanced.php
- Ligne 72: `'info@clubulmevasion.fr', 'CLUB ULM EVASION'` → `CLUB_EMAIL_FROM, CLUB_EMAIL_SENDER_NAME`

### 13. preview_changelog_email.php
- Ligne 105: `Club ULM Evasion` → CLUB_NAME

## Bénéfices

Une fois ces modifications effectuées :

1. ✅ **Personnalisation facile** : Changement du nom/email du club via l'interface web
2. ✅ **Multi-club** : L'application peut être utilisée par n'importe quel club
3. ✅ **Cohérence** : Toutes les références au club sont centralisées
4. ✅ **Maintenance** : Un seul endroit à modifier pour changer les informations

## Prochaines étapes

1. Créer un script automatique de remplacement
2. Tester sur un environnement de développement
3. Déployer progressivement
4. Documenter dans le guide d'installation

## Notes

- Vérifier que `club_config.php` est bien chargé dans `config.php`
- S'assurer que les constantes sont définies avant leur utilisation
- Prévoir des valeurs par défaut au cas où le fichier n'existe pas encore
