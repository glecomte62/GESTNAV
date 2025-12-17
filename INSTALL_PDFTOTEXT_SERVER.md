# Installation de pdftotext sur le serveur

## Option 1: Via SSH (recommandé)

Connectez-vous à votre serveur et exécutez :

```bash
# Pour Debian/Ubuntu
sudo apt-get update
sudo apt-get install poppler-utils

# Pour CentOS/RHEL
sudo yum install poppler-utils

# Pour Alpine Linux
sudo apk add poppler-utils
```

## Option 2: Via cPanel ou Plesk

1. Accédez à votre panneau d'administration
2. Cherchez "Terminal" ou "SSH Access"
3. Exécutez la commande d'installation ci-dessus

## Option 3: Via le support de votre hébergeur

Si vous n'avez pas accès SSH, contactez votre hébergeur (OVH, o2switch, etc.) et demandez l'installation de `poppler-utils`.

## Option 4: Solution PHP pure (si pdftotext impossible)

Le fichier `parse_pdf_server.php` a déjà une solution de secours qui utilise la bibliothèque PHP Smalot.

Pour l'activer, connectez-vous en SSH et exécutez :

```bash
cd /votre/chemin/web
composer require smalot/pdfparser
```

## Vérifier l'installation

Une fois installé, testez avec :

```bash
which pdftotext
pdftotext -v
```

Vous devriez voir la version de pdftotext s'afficher.
