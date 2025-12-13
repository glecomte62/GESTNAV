# 🚀 Démarrage Rapide GESTNAV

Guide pour démarrer en **moins de 10 minutes** !

## ⚡ Installation Express (Ubuntu/Debian)

```bash
# 1. Installer les dépendances
sudo apt update
sudo apt install -y apache2 mysql-server php php-mysql php-gd php-mbstring git

# 2. Cloner GESTNAV
cd /var/www
sudo git clone https://github.com/glecomte62/GESTNAV.git gestnav
cd gestnav

# 3. Configurer MySQL
sudo mysql -e "CREATE DATABASE gestnav CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'gestnav'@'localhost' IDENTIFIED BY 'MotDePasseSecurise123';"
sudo mysql -e "GRANT ALL PRIVILEGES ON gestnav.* TO 'gestnav'@'localhost';"
sudo mysql gestnav < setup/schema.sql

# 4. Configuration
sudo cp config.sample.php config.php
sudo nano config.php  # Éditer DB_USER et DB_PASS

sudo cp club_config.sample.php club_config.php
sudo nano club_config.php  # Nom de votre club, email, etc.

# 5. Permissions
sudo chown -R www-data:www-data /var/www/gestnav
sudo chmod -R 755 uploads backups
sudo chmod 600 config.php club_config.php

# 6. Créer admin
sudo -u www-data php create_admin.php

# 7. Configurer Apache
sudo cp tools/apache-vhost.conf /etc/apache2/sites-available/gestnav.conf
sudo nano /etc/apache2/sites-available/gestnav.conf  # Éditer ServerName
sudo a2ensite gestnav
sudo a2enmod rewrite
sudo systemctl reload apache2

# ✅ C'est prêt ! Accédez à http://gestnav.votreclub.fr
```

## 🖥️ Test en local (tous OS)

Utilisez PHP intégré :

```bash
# 1. Cloner
git clone https://github.com/glecomte62/GESTNAV.git
cd GESTNAV

# 2. Base de données (adapter selon votre MySQL local)
mysql -u root -p -e "CREATE DATABASE gestnav;"
mysql -u root -p gestnav < setup/schema.sql

# 3. Configuration
cp config.sample.php config.php
# Éditer config.php avec vos paramètres MySQL

cp club_config.sample.php club_config.php
# Éditer club_config.php avec infos de votre club

# 4. Permissions
chmod -R 755 uploads backups
chmod 600 config.php club_config.php

# 5. Créer admin
php create_admin.php

# 6. Démarrer le serveur PHP
php -S localhost:8000

# ✅ Ouvrir http://localhost:8000
```

## 📋 Checklist post-installation

- [ ] Connexion réussie avec compte admin
- [ ] Modifier les infos du club dans `club_config.php`
- [ ] Ajouter votre logo dans `/assets/img/logo.png`
- [ ] Initialiser les bases ULM (Aérodromes Admin → Bases ULM)
- [ ] Ajouter vos machines
- [ ] Créer quelques membres
- [ ] Tester l'envoi d'email
- [ ] Créer une première sortie test

## 🆘 Problèmes courants

### Erreur de connexion à la base de données

Vérifiez dans `config.php` :
- DB_HOST (généralement `localhost`)
- DB_NAME (nom de la base créée)
- DB_USER et DB_PASS (utilisateur MySQL)

### Page blanche

```bash
# Activer l'affichage des erreurs temporairement
nano config.php
# Ajouter : ini_set('display_errors', 1);
```

### Impossible d'uploader des photos

```bash
# Vérifier les permissions
ls -la uploads/
sudo chmod -R 755 uploads/
sudo chown -R www-data:www-data uploads/
```

## 📚 Documentation complète

- [INSTALLATION.md](INSTALLATION.md) - Guide détaillé
- [GUIDE_PERSONNALISATION.md](GUIDE_PERSONNALISATION.md) - Personnaliser l'apparence
- [README.md](README.md) - Documentation générale

## 💬 Besoin d'aide ?

- GitHub Issues : https://github.com/glecomte62/GESTNAV/issues
- Email : gestnav@clubulmevasion.fr

---

**Bon vol avec GESTNAV ! 🛩️**
