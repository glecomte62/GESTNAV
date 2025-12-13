# 🚀 GESTNAV - Guide d'installation

**GESTNAV v2.0** - Système de gestion des sorties et membres pour clubs ULM

---

## 📋 Prérequis

- **Serveur web** : Apache ou Nginx
- **PHP** : 7.4 ou supérieur
- **Base de données** : MySQL 5.7+ ou MariaDB 10.3+
- **Extensions PHP requises** :
  - `pdo_mysql`
  - `gd` (pour le traitement d'images)
  - `mbstring`
  - `json`
  - `fileinfo`

---

## 📦 Installation

### 1. Télécharger et décompresser

```bash
# Télécharger la dernière version
wget https://github.com/glecomte62/GESTNAV/archive/main.zip

# Décompresser
unzip main.zip
mv GESTNAV-main gestnav

# Placer dans le dossier web
sudo mv gestnav /var/www/html/
cd /var/www/html/gestnav
```

### 2. Configuration de la base de données

Créer la base de données :

```bash
mysql -u root -p
```

```sql
CREATE DATABASE gestnav CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gestnav_user'@'localhost' IDENTIFIED BY 'votre_mot_de_passe_fort';
GRANT ALL PRIVILEGES ON gestnav.* TO 'gestnav_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Importer le schéma :

```bash
mysql -u gestnav_user -p gestnav < setup/schema.sql
```

### 3. Configuration de l'application

Copier et éditer le fichier de configuration :

```bash
cp config.sample.php config.php
nano config.php
```

Modifier les paramètres de connexion :

```php
// Base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestnav');
define('DB_USER', 'gestnav_user');
define('DB_PASS', 'votre_mot_de_passe_fort');

// URL de base de l'application
define('BASE_URL', 'https://votre-domaine.fr/gestnav');
```

### 4. Créer le compte administrateur

Exécuter le script d'installation :

```bash
php setup/create_admin.php
```

Ou accéder à : `https://votre-domaine.fr/gestnav/setup_club.php`

Suivez les instructions pour :
- Créer le compte administrateur
- Configurer les informations du club
- Importer les aérodromes de base

### 5. Configuration des permissions

```bash
# Donner les permissions appropriées
sudo chown -R www-data:www-data /var/www/html/gestnav
sudo chmod -R 755 /var/www/html/gestnav
sudo chmod -R 775 /var/www/html/gestnav/uploads
sudo chmod 600 /var/www/html/gestnav/config.php
```

### 6. Configuration du club

Connectez-vous avec le compte administrateur et accédez à :

**Administration → Configuration générale** (`/config_generale.php`)

Remplissez tous les paramètres :
- Informations du club
- Contact et communication
- Visuels et branding
- Modules optionnels
- Règles de gestion
- Intégrations externes

---

## ⚙️ Configuration avancée

### Configuration des emails

Éditer `config_mail.php` :

```php
// Méthode d'envoi
define('MAIL_METHOD', 'smtp'); // ou 'php_mail'

// Configuration SMTP
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@example.com');
define('SMTP_PASSWORD', 'mot_de_passe');
define('SMTP_ENCRYPTION', 'tls'); // ou 'ssl'
```

### Configuration Apache (VirtualHost)

```apache
<VirtualHost *:80>
    ServerName gestnav.votre-domaine.fr
    DocumentRoot /var/www/html/gestnav
    
    <Directory /var/www/html/gestnav>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Redirection HTTPS (recommandé)
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName gestnav.votre-domaine.fr
    DocumentRoot /var/www/html/gestnav
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/votre-domaine.fr/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/votre-domaine.fr/privkey.pem
    
    <Directory /var/www/html/gestnav>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Configuration Nginx

```nginx
server {
    listen 80;
    server_name gestnav.votre-domaine.fr;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name gestnav.votre-domaine.fr;
    
    root /var/www/html/gestnav;
    index index.php;
    
    ssl_certificate /etc/letsencrypt/live/votre-domaine.fr/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/votre-domaine.fr/privkey.pem;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### Tâches CRON (optionnel)

Pour les notifications automatiques :

```bash
crontab -e
```

Ajouter :

```bash
# Envoi des alertes pour nouveaux événements
0 9 * * * cd /var/www/html/gestnav && php send_event_alerts.php

# Nettoyage des sessions expirées
0 2 * * * cd /var/www/html/gestnav && php cleanup_sessions.php
```

---

## 🎨 Personnalisation

### Logo du club

Placer votre logo dans `assets/img/logo.png` ou mettre à jour le chemin dans **Configuration générale**.

Formats acceptés : PNG, JPG, SVG
Taille recommandée : 200x200px minimum

### Couleurs

Dans **Configuration générale → Visuels et branding**, personnalisez :
- Couleur primaire (par défaut : `#004b8d`)
- Couleur secondaire (par défaut : `#00a0c6`)
- Couleur d'accent (par défaut : `#0078b8`)

Les couleurs sont appliquées automatiquement dans toute l'application.

### Photo de couverture

Placer une photo dans `assets/img/cover.jpg` pour la page d'accueil.

Taille recommandée : 1920x600px

---

## 📊 Modules optionnels

Activez/désactivez les modules dans **Configuration générale → Modules optionnels** :

- ✅ **Événements** : Gestion d'événements (assemblées, formations, etc.)
- ✅ **Sondages** : Création de sondages pour les membres
- ✅ **Propositions de sorties** : Les membres peuvent proposer des destinations
- ✅ **Changelog** : Affichage des nouveautés de l'application
- ✅ **Statistiques** : Tableaux de bord et graphiques
- ✅ **Bases ULM** : Import de la liste des aérodromes ULM français
- ✅ **Météo** : Intégration météo (nécessite une clé API)

---

## 🔐 Sécurité

### Recommandations

1. **HTTPS obligatoire** : Configurer un certificat SSL (Let's Encrypt gratuit)
2. **Mots de passe forts** : Utiliser des mots de passe complexes
3. **Sauvegardes régulières** : Base de données + dossier uploads
4. **Mises à jour** : Garder PHP et MySQL à jour
5. **Fichier config.php** : Permissions 600 (lecture seule propriétaire)

### Fichiers sensibles à protéger

Ajouter dans `.htaccess` :

```apache
# Bloquer l'accès aux fichiers sensibles
<FilesMatch "^(config\.php|club_config\.php|\.env)$">
    Require all denied
</FilesMatch>

# Bloquer les répertoires
<DirectoryMatch "^.*/(\.|setup|tools)">
    Require all denied
</DirectoryMatch>
```

---

## 🆘 Dépannage

### Problème : Page blanche

1. Activer l'affichage des erreurs dans `config.php` :
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```
2. Vérifier les logs Apache/Nginx
3. Vérifier les permissions des dossiers

### Problème : Erreur de connexion à la base

1. Vérifier les identifiants dans `config.php`
2. Tester la connexion :
   ```bash
   mysql -u gestnav_user -p gestnav
   ```
3. Vérifier que le serveur MySQL est démarré

### Problème : Upload de fichiers impossible

1. Vérifier les permissions du dossier `uploads/` :
   ```bash
   sudo chmod -R 775 uploads/
   sudo chown -R www-data:www-data uploads/
   ```
2. Augmenter les limites PHP dans `php.ini` :
   ```ini
   upload_max_filesize = 10M
   post_max_size = 12M
   ```

### Problème : Emails non envoyés

1. Vérifier la configuration SMTP dans `config_mail.php`
2. Tester l'envoi manuel :
   ```bash
   php -r "mail('test@example.com', 'Test', 'Message de test');"
   ```
3. Vérifier les logs d'erreurs

---

## 🔄 Mise à jour

### Depuis une version précédente

```bash
# Sauvegarder la base de données
mysqldump -u gestnav_user -p gestnav > backup_$(date +%Y%m%d).sql

# Sauvegarder les fichiers uploadés
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz uploads/

# Récupérer la nouvelle version
git pull origin main

# Exécuter les migrations
php setup/migrate.php

# Vider le cache (si applicable)
rm -rf cache/*
```

---

## 📚 Documentation

- **Guide utilisateur** : `docs/USER_GUIDE.md`
- **Guide administrateur** : `docs/ADMIN_GUIDE.md`
- **API** : `docs/API.md`
- **Changelog** : `CHANGELOG.md`

---

## 💬 Support

- **Documentation** : https://github.com/glecomte62/GESTNAV/wiki
- **Issues** : https://github.com/glecomte62/GESTNAV/issues
- **Email** : support@gestnav.fr

---

## 📄 Licence

GESTNAV est distribué sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

---

## 🙏 Crédits

Développé pour le **Club ULM Evasion** et partagé avec la communauté.

**Contributeurs** :
- Guillaume Lecomte - Développeur principal
- GitHub Copilot - Assistant IA

---

**Dernière mise à jour** : 12 décembre 2025
**Version** : 2.0.0
