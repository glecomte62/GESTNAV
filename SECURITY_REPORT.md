# 🔒 Rapport de Sécurité - GESTNAV

**Date** : 13 décembre 2025  
**Statut** : ⚠️ **ACTION REQUISE**

## ✅ Mesures Prises

### Fichiers Retirés du Repo Public

Les fichiers sensibles suivants ont été **supprimés** du dernier commit :

1. ✅ `config.php` - Contenait le mot de passe MySQL et clés secrètes
2. ✅ `club_config.php` - Contenait emails et informations du club
3. ✅ `config_mail.php` - Formulaire de configuration SMTP
4. ✅ `auth.php` - Fonctions d'authentification

### Protection .gitignore

Le `.gitignore` a été mis à jour pour protéger :

```gitignore
# Fichiers sensibles
config.php
club_config.php
config_mail.php
auth.php
.env
.env.*
```

## ⚠️ ACTIONS URGENTES REQUISES

### 1️⃣ Nettoyer l'Historique Git (CRITIQUE)

**Problème** : Les fichiers sensibles sont toujours dans l'historique Git et **publiquement accessibles**.

Ton mot de passe MySQL `Corvus2024@LFQJ` est visible dans l'historique !

**Solutions** :

#### Option A : Nettoyage avec git-filter-repo (RECOMMANDÉ)

```bash
# 1. Installer git-filter-repo
brew install git-filter-repo

# 2. Exécuter le nettoyage
./git-filter-repo-script.sh
# OU manuellement :
git filter-repo --invert-paths \
  --path config.php \
  --path club_config.php \
  --path config_mail.php \
  --path auth.php \
  --force

# 3. Re-ajouter le remote
git remote add origin https://github.com/glecomte62/GESTNAV.git

# 4. Force push
git push --force origin main
```

#### Option B : Nouveau Repo (PLUS SIMPLE)

Si personne n'a encore cloné le repo :

```bash
# 1. Supprimer le repo GitHub (via web)
# 2. Supprimer .git local
rm -rf .git

# 3. Réinitialiser
git init
git add .
git commit -m "🎉 Initial commit - GESTNAV 2.2.0 (clean)"
git branch -M main

# 4. Nouveau repo GitHub
git remote add origin https://github.com/glecomte62/GESTNAV.git
git push -u origin main
```

### 2️⃣ Changer Tous les Mots de Passe (CRITIQUE)

**Exposés dans l'historique Git** :

#### Base de données MySQL

```bash
mysql -u root -p

# Changer le mot de passe de l'utilisateur gestnav
ALTER USER 'kica7829_gestnav'@'localhost' 
IDENTIFIED BY 'NOUVEAU_MOT_DE_PASSE_TRES_FORT_ET_ALEATOIRE';

FLUSH PRIVILEGES;
EXIT;
```

Puis mettre à jour `config.php` local :

```php
$pass = 'NOUVEAU_MOT_DE_PASSE_TRES_FORT_ET_ALEATOIRE';
```

#### Clés Secrètes

Dans `config.php` local, régénérer :

```bash
# Générer une nouvelle clé
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

Copier le résultat dans `config.php` :

```php
$MIGRATE_SECRET_KEY = 'NOUVELLE_CLE_GENEREE_ICI';
```

### 3️⃣ Vérifier SMTP (si utilisé)

Si tu as configuré un compte SMTP/Gmail dans l'interface, **change le mot de passe** :

- Gmail : https://myaccount.google.com/security
- Génère un nouveau "Mot de passe d'application"
- Mets-le à jour dans l'interface GESTNAV (Config > Email)

## 📋 Checklist de Sécurité

### Fichiers Protégés
- [x] `config.php` retiré du dernier commit
- [x] `club_config.php` retiré du dernier commit
- [x] `config_mail.php` retiré du dernier commit
- [x] `auth.php` retiré du dernier commit
- [x] `.gitignore` mis à jour
- [x] Fichiers `.sample` vérifiés (✅ OK, pas de secrets)

### Historique Git
- [ ] **À FAIRE** : Nettoyer l'historique avec git-filter-repo
- [ ] **À FAIRE** : Force push après nettoyage

### Credentials
- [ ] **À FAIRE** : Changer mot de passe MySQL
- [ ] **À FAIRE** : Régénérer clé secrète migration
- [ ] **À VÉRIFIER** : Mot de passe SMTP (si configuré)

### Vérification Finale
- [ ] Vérifier historique : `git log --all --source --oneline -- config.php` (doit être vide)
- [ ] Vérifier repo GitHub : pas de fichiers sensibles visibles
- [ ] Tester connexion avec nouveaux mots de passe

## 🔍 Comment Vérifier

### Vérifier que les fichiers ne sont plus dans le dernier commit

```bash
git ls-tree -r HEAD | grep -E "config\.php|club_config\.php|config_mail\.php|auth\.php"
# Doit être vide ✅
```

### Vérifier l'historique complet

```bash
git log --all --full-history --source --oneline -- config.php club_config.php
# Après nettoyage, doit être vide ⚠️ Actuellement contient 5 commits
```

### Vérifier sur GitHub

Va sur : https://github.com/glecomte62/GESTNAV/blob/main/config.php

**Actuellement** : ❌ Fichier supprimé du dernier commit mais visible dans l'historique  
**Objectif** : ✅ "404 - File not found" même dans l'historique

## 📚 Documentation

- [GitHub - Supprimer données sensibles](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository)
- [git-filter-repo](https://github.com/newren/git-filter-repo)
- Documentation complète : `SECURITY_CLEANUP.md`

## 🎯 État Actuel

| Élément | Statut | Action |
|---------|--------|--------|
| Dernier commit | ✅ Propre | Fichiers sensibles retirés |
| Historique Git | ⚠️ Compromis | **Nettoyer avec filter-repo** |
| Mot de passe MySQL | ⚠️ Exposé | **Changer immédiatement** |
| Clé secrète | ⚠️ Exposée | **Régénérer** |
| .gitignore | ✅ OK | Fichiers protégés |
| Fichiers .sample | ✅ Sûrs | Pas de secrets en dur |

---

**Priorité 1** : Nettoyer l'historique Git (Option A ou B)  
**Priorité 2** : Changer mot de passe MySQL  
**Priorité 3** : Régénérer clés secrètes

**Temps estimé** : 15-20 minutes
