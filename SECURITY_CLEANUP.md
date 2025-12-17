# 🔒 Nettoyage de Sécurité - Historique Git

## ⚠️ PROBLÈME DÉTECTÉ

Les fichiers `config.php` et `club_config.php` ont été committé dans l'historique Git et **contenaient des informations sensibles** :
- Mot de passe de base de données MySQL
- Emails du club
- Clés secrètes

Ces fichiers ont été retirés du dernier commit, mais **l'historique Git les contient toujours**.

## 🛠️ SOLUTIONS

### Option 1 : Nettoyer l'historique (RECOMMANDÉ si repo public)

**Important** : Cela réécrit l'historique Git et nécessite un `git push --force`

#### Étape 1 : Installer git-filter-repo

```bash
# macOS avec Homebrew
brew install git-filter-repo

# Ou avec pip
pip3 install git-filter-repo
```

#### Étape 2 : Exécuter le nettoyage

```bash
# Utiliser le script fourni
./git-filter-repo-script.sh

# OU manuellement :
git filter-repo --invert-paths --path config.php --path club_config.php --force
```

#### Étape 3 : Pousser les changements

```bash
# Ajouter de nouveau le remote (filter-repo le supprime)
git remote add origin https://github.com/glecomte62/GESTNAV.git

# Force push pour réécrire l'historique
git push --force origin main
```

#### Étape 4 : Changer les mots de passe

**CRITIQUE** : Change immédiatement :
- ✅ Mot de passe MySQL : `Corvus2024@LFQJ` 
- ✅ Clé secrète migration : `gn-temp-KEY-2025-12-01-8f3c1b7e2d4a49b2a4c1`

```bash
# Se connecter à MySQL
mysql -u root -p

# Changer le mot de passe de l'utilisateur gestnav
ALTER USER 'kica7829_gestnav'@'localhost' IDENTIFIED BY 'NOUVEAU_MOT_DE_PASSE_FORT';
FLUSH PRIVILEGES;
```

### Option 2 : Repartir de zéro (PLUS SIMPLE)

Si personne n'a encore cloné le repo :

```bash
# 1. Supprimer le repo sur GitHub (via l'interface web)

# 2. Supprimer le dossier .git local
rm -rf .git

# 3. Réinitialiser Git
git init
git add .
git commit -m "🎉 Initial commit - GESTNAV 2.2.0"

# 4. Recréer le repo sur GitHub et pousser
git remote add origin https://github.com/glecomte62/GESTNAV.git
git branch -M main
git push -u origin main
```

## 📋 CHECKLIST DE SÉCURITÉ

Après nettoyage :

- [ ] ✅ `config.php` n'est plus dans le repo (vérifié)
- [ ] ✅ `club_config.php` n'est plus dans le repo (vérifié)
- [ ] ✅ `.gitignore` contient ces fichiers
- [ ] ⚠️ Historique Git nettoyé (à faire)
- [ ] ⚠️ Mot de passe MySQL changé (à faire)
- [ ] ⚠️ Clés secrètes régénérées (à faire)

## 🔍 Vérification

Pour vérifier que les fichiers ne sont plus dans l'historique :

```bash
# Chercher dans tout l'historique
git log --all --full-history --source --oneline -- config.php club_config.php

# Si la commande ne retourne rien = OK ✅
```

## 📚 Documentation de Référence

- [GitHub - Removing sensitive data](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository)
- [git-filter-repo Documentation](https://github.com/newren/git-filter-repo)

---

**Date de détection** : 13 décembre 2025  
**Fichiers concernés** : `config.php`, `club_config.php`  
**Commits affectés** : Tous les commits depuis 59551fb (environ 5 commits)
