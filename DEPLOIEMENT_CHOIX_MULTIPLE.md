# 🗳️ Déploiement : Fonctionnalité Choix Multiple pour les Sondages

**Date :** 14 décembre 2025  
**Fonctionnalité :** Édition des sondages avec possibilité de choix multiples

---

## 📦 Fichiers modifiés

### 1. `/sondages_admin.php` ✏️
**Modifications :**
- Ajout d'un bouton "✏️ Éditer" pour chaque sondage ouvert
- Modal d'édition permettant de modifier :
  - Titre du sondage
  - Description
  - **Choix multiple** (nouvelle fonctionnalité)
  - Date de fermeture
- Gestion du traitement de l'édition (action `edit`)
- Styles CSS pour le modal d'édition

**Nouvelles actions :**
- `GET ?edit=<id>` : Affiche le modal d'édition
- `POST action=edit` : Enregistre les modifications

---

### 2. `/sondages.php` 🗳️
**Modifications :**
- Traitement des votes avec choix multiple
- Affichage adaptatif : cases à cocher (☑️) pour choix multiple, boutons radio (○) pour choix simple
- Gestion de plusieurs votes par utilisateur pour un même sondage
- Bandeau informatif "✅ Vous pouvez sélectionner plusieurs options"
- Mise à jour des résultats pour les sondages clôturés (affichage de tous les votes)

**Nouvelles fonctionnalités :**
- Support des champs `option_ids[]` pour votes multiples
- Validation : vérification que le choix multiple est autorisé
- Suppression et remplacement des votes existants lors d'un nouveau vote

---

## 🗄️ Fichiers de migration

### 3. `/setup/add_allow_multiple_choices.php` 🆕
**Objectif :** Ajouter la colonne `allow_multiple_choices` à la table `polls`

**Actions :**
- Vérifie si la colonne existe déjà
- Ajoute `allow_multiple_choices TINYINT(1) DEFAULT 0`
- Active automatiquement le choix multiple pour les sondages de type "date" existants
- Affiche la structure de la table après migration

**Utilisation :**
```bash
# Via navigateur
https://gestnav.clubulmevasion.fr/setup/add_allow_multiple_choices.php

# Via terminal
php setup/add_allow_multiple_choices.php
```

---

## 📚 Documentation

### 4. `/GUIDE_CHOIX_MULTIPLE.md` 🆕
Guide complet d'utilisation de la fonctionnalité avec :
- Instructions pour les administrateurs
- Instructions pour les membres
- Cas d'usage recommandés
- Procédure d'installation
- Dépannage

---

## 🚀 Procédure de déploiement

### Étape 1 : Upload des fichiers
```bash
# Fichiers modifiés
sondages_admin.php
sondages.php

# Nouveaux fichiers
setup/add_allow_multiple_choices.php
GUIDE_CHOIX_MULTIPLE.md
```

### Étape 2 : Exécuter la migration
**Option A - Via navigateur (recommandé) :**
1. Aller sur `https://gestnav.clubulmevasion.fr/setup/add_allow_multiple_choices.php`
2. Vérifier que la migration s'est bien déroulée

**Option B - Via terminal :**
```bash
cd /chemin/vers/GESTNAV
php setup/add_allow_multiple_choices.php
```

### Étape 3 : Vérifications
```sql
-- Vérifier que la colonne existe
SHOW COLUMNS FROM polls LIKE 'allow_multiple_choices';

-- Vérifier les sondages de type date
SELECT id, titre, type, allow_multiple_choices FROM polls WHERE type = 'date';
```

### Étape 4 : Tests
1. ✅ Créer un nouveau sondage
2. ✅ Éditer un sondage existant
3. ✅ Activer le choix multiple
4. ✅ Voter avec choix multiple
5. ✅ Vérifier l'affichage des résultats

---

## ⚠️ Points d'attention

### Contrainte UNIQUE
La table `poll_votes` a normalement une contrainte :
```sql
UNIQUE KEY uk_user_poll (poll_id, user_id)
```

**⚠️ Cette contrainte EMPÊCHE le choix multiple !**

**Solution :** Modifier la contrainte pour permettre plusieurs votes :
```sql
-- Supprimer l'ancienne contrainte
ALTER TABLE poll_votes DROP INDEX uk_user_poll;

-- NE PAS recréer de contrainte UNIQUE
-- (Ou créer une contrainte sur poll_id, user_id, option_id si nécessaire)
```

### Migration de la contrainte
Créez un nouveau fichier `/setup/fix_poll_votes_constraint.php` :

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if (php_sapi_name() !== 'cli') {
    require_login();
    if (!is_admin()) die("❌ Accès refusé");
}

try {
    // Supprimer la contrainte UNIQUE
    $pdo->exec("ALTER TABLE poll_votes DROP INDEX uk_user_poll");
    echo "✅ Contrainte UNIQUE supprimée\n";
    
    // Optionnel : Ajouter un index pour les performances
    $pdo->exec("CREATE INDEX idx_poll_user ON poll_votes(poll_id, user_id)");
    echo "✅ Index ajouté pour les performances\n";
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
?>
```

---

## 🧪 Tests recommandés

### Test 1 : Création et édition
- [ ] Créer un nouveau sondage simple
- [ ] Éditer le sondage
- [ ] Activer le choix multiple
- [ ] Vérifier la sauvegarde

### Test 2 : Vote simple
- [ ] Voter pour une option (choix simple)
- [ ] Changer son vote
- [ ] Vérifier que l'ancien vote est remplacé

### Test 3 : Vote multiple
- [ ] Activer le choix multiple
- [ ] Voter pour plusieurs options
- [ ] Modifier ses votes
- [ ] Vérifier les résultats

### Test 4 : Affichage
- [ ] Sondage simple : boutons radio
- [ ] Sondage multiple : cases à cocher
- [ ] Bandeau informatif visible
- [ ] Résultats corrects

---

## 📊 Structure de la base de données

### Table `polls`
```sql
SHOW CREATE TABLE polls;

-- Nouvelle colonne :
allow_multiple_choices TINYINT(1) DEFAULT 0
```

### Table `poll_votes`
```sql
SHOW CREATE TABLE poll_votes;

-- ATTENTION : Vérifier que la contrainte UNIQUE a été supprimée
```

---

## 🔄 Rollback (en cas de problème)

### Annuler la migration
```sql
ALTER TABLE polls DROP COLUMN allow_multiple_choices;
```

### Restaurer les fichiers
```bash
git checkout sondages_admin.php
git checkout sondages.php
```

---

## ✅ Checklist finale

- [ ] Fichiers uploadés sur le serveur
- [ ] Migration exécutée avec succès
- [ ] Contrainte UNIQUE modifiée
- [ ] Tests effectués
- [ ] Documentation consultée
- [ ] Utilisateurs informés de la nouvelle fonctionnalité

---

## 📞 Support

En cas de problème :
1. Vérifier les logs d'erreur PHP
2. Consulter `GUIDE_CHOIX_MULTIPLE.md`
3. Vérifier la structure de la base de données
4. Tester avec un compte administrateur

**Logs à consulter :**
- Logs PHP du serveur
- Console navigateur (F12)
- Base de données : votes enregistrés

---

*Déploiement préparé le 14 décembre 2025*
