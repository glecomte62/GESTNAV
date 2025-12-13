# 🚀 Installation GESTNAV sur O2Switch

Guide d'installation pas à pas pour déployer GESTNAV sur un hébergement O2Switch.

---

## 📋 Prérequis

### Vérifier votre hébergement O2Switch

Connectez-vous à votre **cPanel** et vérifiez :

1. **PHP Version** : Outils → Sélectionner une version PHP
   - ✅ Minimum : PHP 7.4
   - ✅ Recommandé : PHP 8.0 ou 8.1

2. **Extensions PHP activées** (dans "Sélectionner une version PHP" > Options) :
   - ✅ `pdo_mysql` ou `mysqli`
   - ✅ `gd`
   - ✅ `mbstring`
   - ✅ `json`
   - ✅ `fileinfo`

3. **Base de données MySQL disponible** :
   - Bases de données MySQL → Vérifier l'espace disponible

---

## 🗂️ Étape 1 : Créer la Base de Données

### Via cPanel

1. **Bases de données MySQL** → **Créer une nouvelle base de données**
   - Nom : `gestnav` (ou votre_utilisateur_gestnav)
   - Cliquer sur "Créer une base de données"

2. **Créer un utilisateur MySQL**
   - Utilisateur : `gestnav_user`
   - Mot de passe : **Générer un mot de passe fort** (noter le mot de passe !)
   - Cliquer sur "Créer un utilisateur"

3. **Ajouter l'utilisateur à la base de données**
   - Sélectionner l'utilisateur `gestnav_user`
   - Sélectionner la base `gestnav`
   - Privilèges : **TOUS LES PRIVILÈGES**
   - Cliquer sur "Ajouter"

4. **Noter les informations** :
   ```
   Hôte : localhost
   Base : votre_prefixe_gestnav
   Utilisateur : votre_prefixe_gestnav_user
   Mot de passe : le_mot_de_passe_généré
   ```

---

## 📥 Étape 2 : Télécharger GESTNAV

### Option A : Depuis GitHub (recommandé)

1. **Accès SSH** (si activé chez O2Switch) :
   ```bash
   ssh votre_utilisateur@votredomaine.fr
   cd public_html  # ou le dossier de votre sous-domaine
   
   # Cloner le repo
   git clone https://github.com/glecomte62/GESTNAV.git gestnav
   cd gestnav
   ```

2. **Si pas d'accès SSH**, télécharger en ZIP :
   - https://github.com/glecomte62/GESTNAV/archive/refs/heads/main.zip
   - Extraire sur votre ordinateur
   - Passer à l'Option B

### Option B : Upload via FTP/Gestionnaire de fichiers

1. **Connectez-vous au Gestionnaire de fichiers** (cPanel → Gestionnaire de fichiers)
   - Ou via FTP (FileZilla, Cyberduck, etc.)
   - Serveur : ftp.votredomaine.fr
   - Utilisateur : votre_utilisateur_cpanel
   - Mot de passe : votre_mot_de_passe_cpanel

2. **Naviguer vers le bon dossier** :
   - `public_html/` pour le domaine principal
   - `public_html/gestnav/` pour un sous-dossier
   - Ou le dossier de votre sous-domaine

3. **Upload des fichiers** :
   - Transférer tous les fichiers GESTNAV
   - ⚠️ **Ne PAS uploader** : `.git/`, `config.php`, `club_config.php`

---

## ⚙️ Étape 3 : Configuration

### 3.1 Configuration de la base de données

1. **Créer `config.php`** à partir du modèle :
   - Copier `config.sample.php` → `config.php`
   - Via Gestionnaire de fichiers : Clic droit > Copier
   - Ou en ligne de commande : `cp config.sample.php config.php`

2. **Éditer `config.php`** :
   ```php
   <?php
   $host = 'localhost';
   $db   = 'votre_prefixe_gestnav';        // ⬅️ Votre nom de BDD
   $user = 'votre_prefixe_gestnav_user';   // ⬅️ Votre utilisateur
   $pass = 'VOTRE_MOT_DE_PASSE_MYSQL';     // ⬅️ Le mot de passe noté

   $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
   
   // ... reste du fichier identique
   ```

3. **Permissions du fichier** (recommandé) :
   ```bash
   chmod 600 config.php
   ```

### 3.2 Configuration du club

1. **Créer `club_config.php`** :
   ```bash
   cp club_config.sample.php club_config.php
   ```

2. **Éditer `club_config.php`** avec les informations de VOTRE club :
   ```php
   define('CLUB_NAME', 'Nom de votre club');
   define('CLUB_SHORT_NAME', 'Abréviation');
   define('CLUB_HOME_BASE', 'LFXX');  // Code OACI de votre terrain
   
   define('CLUB_EMAIL_FROM', 'contact@votreclub.fr');
   define('CLUB_EMAIL_SENDER_NAME', 'VOTRE CLUB ULM');
   
   // ... personnaliser le reste
   ```

---

## 🗄️ Étape 4 : Importer le Schéma de Base de Données

### Via phpMyAdmin (le plus simple)

1. **Ouvrir phpMyAdmin** (cPanel → phpMyAdmin)

2. **Sélectionner votre base** `gestnav` dans le menu gauche

3. **Onglet "Importer"**
   - Fichier à importer : `setup/schema.sql`
   - Format : SQL
   - Cliquer sur "Exécuter"

4. **Vérifier** :
   - Vous devriez voir 27 tables créées
   - Aucune erreur affichée

### Via ligne de commande (si SSH disponible)

```bash
cd /chemin/vers/gestnav
mysql -u votre_prefixe_gestnav_user -p votre_prefixe_gestnav < setup/schema.sql
# Entrer le mot de passe MySQL quand demandé
```

---

## 👤 Étape 5 : Créer le Compte Administrateur

### Méthode 1 : Via le navigateur (recommandé)

1. **Accéder au script** :
   ```
   https://votredomaine.fr/gestnav/create_admin.php
   ```

2. **Remplir le formulaire** :
   - Nom, Prénom
   - Email (utilisé pour la connexion)
   - Mot de passe fort

3. **Cliquer sur "Créer l'administrateur"**

4. **⚠️ Supprimer le fichier** après utilisation :
   - Via Gestionnaire de fichiers : Supprimer `create_admin.php`
   - Ou : `rm create_admin.php`

### Méthode 2 : Via ligne de commande

```bash
cd /chemin/vers/gestnav
php create_admin.php

# Suivre les instructions à l'écran
# Puis supprimer le fichier
rm create_admin.php
```

---

## 🎨 Étape 6 : Personnalisation

### 6.1 Logo du club

1. **Préparer votre logo** :
   - Format : PNG ou JPG
   - Dimensions recommandées : 200x50 px (hauteur 50px)
   - Fond transparent de préférence

2. **Upload du logo** :
   - Via Gestionnaire de fichiers → `assets/img/`
   - Renommer en `logo.png` (ou `logo.jpg`)

3. **Mettre à jour `club_config.php`** :
   ```php
   define('CLUB_LOGO_PATH', 'assets/img/logo.png');
   ```

### 6.2 Couleurs du club

Dans `club_config.php` :
```php
define('CLUB_COLOR_PRIMARY', '#004b8d');      // Couleur principale
define('CLUB_COLOR_SECONDARY', '#00a0c6');    // Couleur secondaire
define('CLUB_COLOR_ACCENT', '#f39c12');       // Couleur d'accentuation
```

---

## 🔒 Étape 7 : Sécurité

### 7.1 Permissions des fichiers

```bash
# Fichiers de configuration (lecture seule pour PHP)
chmod 600 config.php
chmod 600 club_config.php

# Dossiers d'upload (écriture pour PHP)
chmod 755 uploads/
chmod 755 backups/

# Tous les fichiers PHP
find . -name "*.php" -type f -exec chmod 644 {} \;
```

### 7.2 Fichier .htaccess (déjà présent)

Vérifier que `.htaccess` existe et contient la protection des fichiers sensibles :
```apache
# Protéger les fichiers de configuration
<Files "config.php">
    Require all denied
</Files>
<Files "club_config.php">
    Require all denied
</Files>
```

### 7.3 SSL/HTTPS (recommandé)

1. **Activer SSL** via cPanel :
   - Sécurité → SSL/TLS → Let's Encrypt (gratuit chez O2Switch)
   - Activer pour votre domaine

2. **Forcer HTTPS** dans `.htaccess` :
   ```apache
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

---

## ✅ Étape 8 : Premier Accès

1. **Accéder à GESTNAV** :
   ```
   https://votredomaine.fr/gestnav/
   ```

2. **Se connecter** avec le compte admin créé

3. **Configuration initiale** :
   - Aller dans **Administration → Configuration générale**
   - Vérifier/compléter les informations du club
   - Activer les modules souhaités

4. **Ajouter les membres** :
   - Administration → Membres → Ajouter un membre
   - Ou importer depuis un fichier CSV

5. **Ajouter les machines** :
   - Administration → Machines → Ajouter une machine

---

## 🐛 Dépannage

### Erreur "500 Internal Server Error"

1. **Vérifier les logs d'erreur** :
   - cPanel → Métriques → Erreurs
   - Ou via FTP : `/error_log`

2. **Version PHP** :
   - S'assurer que PHP 7.4+ est activé
   - cPanel → Sélectionner une version PHP

3. **Permissions** :
   - Fichiers : 644
   - Dossiers : 755
   - config.php : 600

### Erreur de connexion à la base de données

1. **Vérifier `config.php`** :
   - Nom de la base de données correct (avec préfixe)
   - Nom d'utilisateur correct (avec préfixe)
   - Mot de passe correct

2. **Vérifier que l'utilisateur a les privilèges** :
   - cPanel → Bases de données MySQL
   - Vérifier que l'utilisateur est bien associé à la base

### Pages blanches / erreurs PHP

1. **Activer l'affichage des erreurs** temporairement :
   - Dans `config.php` :
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

2. **Vérifier les extensions PHP requises** :
   - cPanel → Sélectionner une version PHP → Options

### Problème d'upload de photos

1. **Vérifier les permissions** :
   ```bash
   chmod 755 uploads/
   ```

2. **Augmenter la limite d'upload** (si nécessaire) :
   - cPanel → Sélectionner une version PHP → Options
   - `upload_max_filesize` = 10M
   - `post_max_size` = 10M

---

## 📞 Support O2Switch

**Documentation O2Switch** : https://www.o2switch.fr/documentation/

**Support** :
- Email : support@o2switch.fr
- Chat en ligne (9h-20h)
- Tickets via cPanel

---

## ✨ Prochaines Étapes

Une fois l'installation terminée :

1. ✅ Configurer l'envoi d'emails (SMTP)
   - Administration → Configuration → Email
   - Utiliser les paramètres SMTP O2Switch

2. ✅ Importer les bases ULM françaises
   - Administration → Bases ULM → Importer

3. ✅ Tester la création d'une sortie

4. ✅ Inviter les membres à s'inscrire

---

**Temps d'installation estimé** : 20-30 minutes

Besoin d'aide ? Consultez [INSTALLATION.md](../INSTALLATION.md) ou ouvrez une issue sur GitHub.
