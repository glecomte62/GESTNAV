# GESTNAV - Guide de Distribution

## 📦 Pour partager GESTNAV avec d'autres clubs

### Package à fournir

Créez une archive contenant :

```
GESTNAV-distribution/
├── README.md                          ← Ce fichier
├── GUIDE_PERSONNALISATION.md          ← Guide détaillé
├── setup_club.php                     ← Script d'installation interactif ⭐
├── .env.example                       ← Template de configuration
├── club_config.php.example            ← Exemple de configuration
├── config.php.example                 ← Exemple config technique
├── tous les autres fichiers PHP
└── assets/
    ├── css/
    ├── js/
    └── img/
        └── logo-example.png           ← Logo d'exemple
```

### Instructions pour le nouveau club

**Option 1 : Installation guidée (recommandée)**

```bash
# 1. Extraire l'archive
unzip GESTNAV-distribution.zip
cd GESTNAV-distribution

# 2. Lancer l'assistant d'installation
php setup_club.php
```

L'assistant interactif va :
- ✅ Poser toutes les questions nécessaires
- ✅ Générer automatiquement les fichiers de configuration
- ✅ Tester la connexion à la base de données
- ✅ Créer la base si nécessaire
- ✅ Proposer d'exécuter les migrations
- ✅ Guider pour les prochaines étapes

**Option 2 : Installation manuelle**

1. Copier `.env.example` vers `.env` et remplir les valeurs
2. Modifier `club_config.php.example` et renommer en `club_config.php`
3. Modifier `config.php.example` et renommer en `config.php`
4. Exécuter les migrations : `php install_*.php`
5. Créer un admin : `php create_admin.php`
6. Consulter `GUIDE_PERSONNALISATION.md`

### Ce qu'il faut personnaliser (minimum)

1. **Logo** : Remplacer `assets/img/logo.png`
2. **Nom du club** : Dans `club_config.php`
3. **Couleurs** : Les 3 couleurs principales
4. **Email** : Adresse de contact et SMTP
5. **Base OACI** : Code de l'aérodrome principal

### Ce qui n'a PAS besoin d'être modifié

- ❌ Tous les fichiers PHP fonctionnels
- ❌ Structure de la base de données
- ❌ Scripts JavaScript
- ❌ Feuilles de style CSS (sauf personnalisation avancée)
- ❌ Scripts de migration

### Mises à jour futures

Quand vous publiez une nouvelle version de GESTNAV :

1. Le club garde son `club_config.php` existant
2. Il télécharge la nouvelle version
3. Il remplace tous les fichiers SAUF :
   - `club_config.php`
   - `config.php`
   - `config_mail.php`
   - `uploads/` (photos et fichiers)
4. Il exécute les nouvelles migrations si nécessaire

### Support

Indiquez aux clubs comment obtenir de l'aide :
- 📧 Email de support
- 🐛 GitHub Issues
- 📖 Documentation complète
- 💬 Forum / Discord

### Licence

Précisez la licence d'utilisation de GESTNAV pour d'autres clubs.

---

## 🔧 Checklist pour créer le package de distribution

- [ ] Supprimer les fichiers spécifiques à votre club :
  - [ ] `club_config.php` (fournir `club_config.php.example` à la place)
  - [ ] `config.php` (fournir `config.php.example`)
  - [ ] `config_mail.php` (fournir `config_mail.php.example`)
  - [ ] Dossier `uploads/` (contenu sensible)
  - [ ] `.env` si présent

- [ ] Inclure les fichiers de documentation :
  - [ ] `README.md` (ce fichier)
  - [ ] `GUIDE_PERSONNALISATION.md`
  - [ ] `ARCHITECTURE_*.md`
  - [ ] `CHANGELOG.md`

- [ ] Inclure le script d'installation :
  - [ ] `setup_club.php` (avec chmod +x)
  - [ ] `.env.example`

- [ ] Vérifier les fichiers exemple :
  - [ ] `club_config.php.example` (avec valeurs génériques)
  - [ ] `config.php.example` (avec placeholders)
  - [ ] Logo d'exemple dans `assets/img/`

- [ ] Scripts utiles :
  - [ ] `create_admin.php`
  - [ ] Tous les `install_*.php`
  - [ ] Tous les `migrate_*.php`
  - [ ] Script de déploiement FTP (optionnel)

- [ ] Assets :
  - [ ] CSS compilé et minifié
  - [ ] JavaScript
  - [ ] Icônes et images génériques
  - [ ] Polices

- [ ] Tester l'installation complète :
  - [ ] Sur un serveur vierge
  - [ ] Avec une base de données vide
  - [ ] Vérifier que `setup_club.php` fonctionne
  - [ ] Créer un compte admin
  - [ ] Tester toutes les fonctionnalités principales

- [ ] Documentation finale :
  - [ ] Prérequis système clairement indiqués
  - [ ] Versions PHP/MySQL supportées
  - [ ] Instructions d'installation pas à pas
  - [ ] FAQ et troubleshooting

---

## 📤 Commandes pour créer le package

```bash
# Se placer dans le dossier du projet
cd /chemin/vers/GESTNAV

# Créer les fichiers exemple
cp club_config.php club_config.php.example
cp config.php config.php.example
cp config_mail.php config_mail.php.example

# Modifier les exemples pour remplacer les valeurs sensibles par des placeholders
# (Faire manuellement)

# Créer l'archive de distribution (exclure les fichiers sensibles)
zip -r GESTNAV-distribution.zip . \
  -x "*.git*" \
  -x "*uploads/*" \
  -x "club_config.php" \
  -x "config.php" \
  -x "config_mail.php" \
  -x ".env" \
  -x "*.backup*" \
  -x "*node_modules/*" \
  -x "*.DS_Store"

# Vérifier le contenu de l'archive
unzip -l GESTNAV-distribution.zip
```

---

## 🎓 Formation des nouveaux clubs

Proposez un accompagnement :

### Webinar / Vidéo de démo
- Installation complète
- Configuration
- Première utilisation
- Bonnes pratiques

### Documentation vidéo
- Installation en 10 minutes
- Personnalisation du branding
- Gestion quotidienne
- Administration

### Support technique
- Email de support dédié
- Forum communautaire
- Base de connaissances
- Temps de réponse garanti

---

## 💰 Modèle économique (optionnel)

Si vous souhaitez monétiser :

- **Open source gratuit** : Code libre, support payant
- **Freemium** : Base gratuite, modules premium
- **Licence par club** : Prix fixe par installation
- **SaaS** : Hébergement mutualisé avec abonnement

---

## 🌟 Exemples de clubs utilisant GESTNAV

Créez une page vitrine avec :
- Liste des clubs utilisant GESTNAV
- Témoignages
- Captures d'écran personnalisées
- Coordonnées des clubs (avec permission)

Cela inspire confiance et montre la flexibilité de l'application.
