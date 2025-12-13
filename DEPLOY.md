# 🚀 GESTNAV - Guide de Déploiement

## Workflow de déploiement

### 🚀 Déployer les modifications (Git + Sauvegarde DB + FTP)

```bash
bash tools/deploy-all.sh "Description de vos changements"
```

Cela va automatiquement :
1. ✅ Commiter les modifications locales
2. ✅ Pusher vers GitHub (https://github.com/glecomte62/GESTNAV)
3. ✅ Sauvegarder la base de données
4. ✅ Déployer via FTP en production

**Exemple:**
```bash
bash tools/deploy-all.sh "✨ Ajout système d'alertes email"
```

---

## 💾 Gestion des sauvegardes

### Créer une sauvegarde manuelle

```bash
bash tools/backup-db.sh
```

Les sauvegardes sont conservées dans le dossier `backups/` (max 10 dernières).

### Restaurer une sauvegarde

```bash
# Lister les sauvegardes disponibles
ls -lh backups/

# Restaurer une sauvegarde
gunzip < backups/gestnav_2025-12-06_14-30-45.sql.gz | mysql -h votre_serveur.mysql.db -u votre_utilisateur_mysql -p votre_base_donnees
```

### Automatiser les sauvegardes quotidiennes

Ajouter à cron :
```bash
0 2 * * * cd /path/to/GESTNAV && bash tools/backup-db.sh
```

---

## 📜 Historique et Rollback

### Voir l'historique des commits

```bash
git log --oneline -10
```

### Annuler le dernier commit

```bash
bash tools/rollback.sh --last 1
```

### Revenir à un commit spécifique

```bash
bash tools/rollback.sh --hash abc123d
```

### Afficher tout l'historique

```bash
bash tools/rollback.sh --show
```

---

## 🔧 Commandes Git directes

### Voir les fichiers modifiés

```bash
git status
```

### Voir les changements détaillés

```bash
git diff
```

### Voir un commit spécifique

```bash
git show abc123d
```

### Comparer deux commits

```bash
git diff commit1 commit2
```

---

## 📍 Accès

- **Production:** https://gestnav.clubulmevasion.fr/
- **GitHub:** https://github.com/glecomte62/GESTNAV
- **Admin Alertes:** https://gestnav.clubulmevasion.fr/event_alerts_admin.php

---

## ⚠️ Avant de déployer

1. ✅ Tester localement
2. ✅ Vérifier `git status` (pas de fichiers sensibles)
3. ✅ Commit avec un message explicite
4. ✅ Voir l'historique avec `git log`

---

## 🆘 En cas de problème

### Le déploiement échoue

1. Vérifier la connexion FTP :
   ```bash
   ping ftp.votrehebergeur.fr
   ```

2. Vérifier le statut Git :
   ```bash
   git status
   ```

3. Voir les erreurs détaillées :
   ```bash
   bash tools/deploy_ftp.sh
   ```

### Revenir en arrière

```bash
bash tools/rollback.sh --last 1
bash tools/deploy-all.sh "Rollback - Annulation du dernier déploiement"
```

---

## 📁 Structure des scripts

```
tools/
├── deploy-all.sh      # Déploiement complet (Git + FTP)
├── deploy_ftp.sh      # Déploiement FTP uniquement
├── rollback.sh        # Annuler des commits
└── deploy_rsync.sh    # Alternative rsync
```

---

## 💾 Fichiers ignorés par Git

Les fichiers sensibles sont ignorés (`.gitignore`) :
- `config.php` (configuration)
- `config_mail.php` (SMTP)
- `auth.php` (authentification)
- `.env` (variables d'environnement)
- `uploads/` (fichiers uploadés)
- Et autres fichiers temporaires

---

## 🎯 Système d'alertes email

**Nouveau système intégré:**
- 📊 Dashboard admin : `/event_alerts_admin.php`
- 🔔 Gestion des alertes : Menu Administration → Alertes email
- 📝 Opt-out utilisateurs : `/event_alert_optout.php`
- 📤 Envoi manuel : `/send_event_alerts.php`

**À savoir:**
- Les alertes ne s'envoient PAS automatiquement lors de la publication d'une sortie
- Vous devez les envoyer manuellement via le dashboard admin
- Les utilisateurs peuvent gérer leurs préférences d'alerte

---

**Dernier déploiement:** `git log -1`

