# 🛩️ GESTNAV

**Système de gestion des sorties et membres pour clubs ULM**

[![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)](CHANGELOG.md)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/glecomte62/GESTNAV?style=social)](https://github.com/glecomte62/GESTNAV)

---

## 📖 À propos

GESTNAV est une application web complète pour gérer les activités d'un club ULM :
- 🛫 Gestion des sorties et événements
- 👥 Inscriptions et affectations des membres
- 📧 Système de pré-inscription publique
- ✉️ Envoi d'emails personnalisés
- 📊 Statistiques et tableaux de bord
- ⚙️ Configuration multi-club

Développé initialement pour le **Club ULM Evasion**, GESTNAV est conçu pour être **facilement adaptable à n'importe quel club ULM**. Il suffit de personnaliser quelques fichiers de configuration pour adapter le nom, le logo, les couleurs et les paramètres de votre club.

> **🎯 Installation en moins de 10 minutes** avec le [guide de démarrage rapide](QUICKSTART.md) !

---

## ✨ Fonctionnalités principales

### 🛫 Gestion des sorties
- Création et édition de sorties ULM
- Affectation des machines et équipages (2 personnes/machine)
- Photos et descriptions détaillées
- Gestion des destinations (aérodromes)
- Statuts: En étude, Prévue, Terminée, Annulée

### 👥 Inscriptions et participants
- Auto-inscription des membres
- Liste d'attente automatique
- Liens d'action par email (annuler, changer machine, changer coéquipier)
- Promotion automatique en cas de désistement
- Pages publiques de participants

### 🎉 Événements
- Gestion d'événements club (assemblées, formations, etc.)
- Invitations par email
- Gestion des réponses (en attente, confirmée, annulée)
- Date limite d'inscription

### 📧 Système d'emails
- Envoi d'emails HTML personnalisés
- Catégories: Communication, Nouveau membre, Libre
- Éditeur de texte enrichi avec toolbar
- Upload de photos, pièces jointes et liens
- Sélection des destinataires (tous, club, invité, personnalisé)
- Signature automatique avec logo du club

### 📊 Statistiques
- KPIs du club
- Classements des pilotes actifs
- Filtres de dates
- Export CSV

### 🎨 Personnalisation
- Configuration du club (nom, logo, couleurs, contact)
- Modules optionnels activables/désactivables
- Gestion des règles du club
- Multi-langue (français par défaut)

---

## 🚀 Installation rapide

### Pour votre club ULM

GESTNAV est **100% personnalisable** pour n'importe quel club ! Installation en 7 étapes :

```bash
# 1. Télécharger
git clone https://github.com/glecomte62/GESTNAV.git
cd GESTNAV

# 2. Configurer la base de données
mysql -u root -p -e "CREATE DATABASE gestnav CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p gestnav < setup/schema.sql

# 3. Copier et éditer la configuration
cp config.sample.php config.php
nano config.php  # DB_HOST, DB_NAME, DB_USER, DB_PASS

# 4. Personnaliser pour votre club
cp club_config.sample.php club_config.php
nano club_config.php  # Nom, logo, email, couleurs...

# 5. Ajouter votre logo
cp votre-logo.png assets/img/logo.png

# 6. Configurer les permissions
chmod 600 config.php club_config.php
chmod -R 755 uploads/ backups/

# 7. Créer le compte admin
php create_admin.php
```

✅ **C'est prêt !** Accédez à votre GESTNAV et connectez-vous.

📚 **Guide complet** : [INSTALLATION.md](INSTALLATION.md)  
🎨 **Personnalisation** : [GUIDE_PERSONNALISATION.md](GUIDE_PERSONNALISATION.md)

---

## 🎨 Personnalisation pour votre club

Tout se configure dans **`club_config.php`** :

```php
return [
    'club' => [
        'nom' => 'Votre Club ULM',           // Nom de votre club
        'code_oaci' => 'LFXX',               // Code OACI de votre terrain
        'adresse' => '...',                  // Adresse
        'telephone' => '+33 ...',            // Téléphone
    ],
    'email' => [
        'from_address' => 'contact@votreclub.fr',  // Email d'expédition
        'from_name' => 'VOTRE CLUB ULM',           // Nom expéditeur
    ],
    'branding' => [
        'logo_path' => '/assets/img/logo.png',     // Chemin de votre logo
        'couleur_primaire' => '#004b8d',           // Couleur principale
        'couleur_secondaire' => '#00a0c6',         // Couleur secondaire
    ],
    'features' => [
        'propositions_sorties' => true,      // Propositions par membres
        'sondages' => true,                  // Module sondages
        'evenements' => true,                // Module événements
    ],
];
```

**Aucune modification du code source n'est nécessaire !**

---

## 📋 Prérequis

- **Serveur web** : Apache 2.4+ ou Nginx 1.18+
- **PHP** : 7.4 ou supérieur
- **Base de données** : MySQL 5.7+ ou MariaDB 10.3+
- **Extensions PHP** : pdo_mysql, gd, mbstring, json, fileinfo

---

## 🎯 Démarrage

1. Accéder à l'URL de l'application
2. Se connecter avec le compte administrateur créé
3. Aller dans **Administration → Configuration générale**
4. Remplir les informations du club
5. Activer les modules souhaités
6. Commencer à créer des sorties !

---

## 📚 Documentation

- [Guide d'installation](INSTALLATION.md) - Installation complète pas à pas
- [Guide de démarrage rapide](QUICKSTART.md) - Installation en 10 minutes
- [Guide de personnalisation](GUIDE_PERSONNALISATION.md) - Adapter GESTNAV à votre club
- [Changelog](CHANGELOG.md) - Historique des versions

## 🔒 Sécurité

**⚠️ IMPORTANT** : Ne jamais commiter les fichiers suivants dans votre repo :

- `config.php` - Contient vos identifiants de base de données
- `club_config.php` - Contient les informations de votre club  
- `config_mail.php` - Configuration SMTP
- Fichiers `.env*`

✅ Ces fichiers sont déjà dans `.gitignore`  
✅ Utilisez les fichiers `.sample` comme modèles

📋 **Pour plus d'informations** : [SECURITY_REPORT.md](SECURITY_REPORT.md)

---

## 🏗️ Architecture technique

### Stack
- **Backend** : PHP 8.0 + PDO MySQL
- **Frontend** : Bootstrap 5 + Bootstrap Icons
- **Emails** : PHPMailer (SMTP)
- **Maps** : Leaflet.js

### Structure des données
- `users` - Membres du club
- `machines` - Flotte ULM
- `sorties` - Sorties organisées
- `sortie_inscriptions` - Inscriptions aux sorties
- `sortie_assignations` - Affectations pilote/passager
- `evenements` - Événements club
- `preinscriptions` - Demandes d'adhésion

### Pages principales
```
index.php                  → Accueil et prochaines activités
sorties.php               → Liste des sorties
sortie_detail.php         → Détail d'une sortie
evenements_list.php       → Liste des événements
envoyer_email.php         → Envoi d'emails
config_generale.php       → Configuration du club
stats.php                 → Statistiques
```

---

## 🔐 Sécurité

- ✅ Authentification sécurisée (bcrypt)
- ✅ Protection CSRF
- ✅ Préparation des requêtes SQL (PDO)
- ✅ Validation des uploads
- ✅ Headers de sécurité HTTP
- ✅ Sessions sécurisées
- ✅ HTTPS recommandé

---

## 🛠️ Configuration du club

Toute la configuration se fait via l'interface web dans **Administration → Configuration générale** :

### Informations du club
- Nom complet et nom court
- Ville, département, région
- Base principale (code OACI)

### Contact
- Email officiel
- Téléphone
- Site web et réseaux sociaux
- Adresse postale

### Visuels
- Logo du club
- Couleurs primaire, secondaire, accent
- Photo de couverture

### Modules optionnels
- Événements
- Sondages
- Propositions de sorties
- Changelog
- Statistiques
- Bases ULM
- Météo

### Règles de gestion
- Nombre de sorties par mois
- Délai minimum d'inscription
- Jours de notification avant sortie
- Priorité double inscription

---

## 🤝 Contribution

Les contributions sont les bienvenues ! Pour contribuer :

1. Fork le projet
2. Créer une branche (`git checkout -b feature/amelioration`)
3. Commit les changements (`git commit -m 'Ajout fonctionnalité'`)
4. Push vers la branche (`git push origin feature/amelioration`)
5. Ouvrir une Pull Request

---

## 📝 Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

---

## 💬 Support

- **Documentation** : [Wiki GitHub](https://github.com/glecomte62/GESTNAV/wiki)
- **Issues** : [GitHub Issues](https://github.com/glecomte62/GESTNAV/issues)
- **Email** : support@gestnav.fr

---

## 🙏 Crédits

**Développé pour le Club ULM Evasion**

- **Auteur principal** : Guillaume Lecomte
- **Assistant** : GitHub Copilot
- **Contributeurs** : Voir [CONTRIBUTORS.md](CONTRIBUTORS.md)

---

## 🗺️ Roadmap

- [ ] Application mobile (PWA)
- [ ] Module de réservation de machines
- [ ] Intégration calendrier (iCal)
- [ ] API REST complète
- [ ] Multi-langue (EN, ES, DE)
- [ ] Module de comptabilité
- [ ] Système de badges et achievements

---

**Dernière mise à jour** : 12 décembre 2025 | **Version** : 2.0.0

## Fonctionnalités principales
- Sorties club: création/édition, choix des machines, affectations (2 personnes/machine), photos, suppression sécurisée.
- Inscriptions aux sorties: auto-inscription, liens d'action (annuler/changer machine/changer coéquipier) envoyés par email.
- Liste d'attente: visible publiquement, ordonnée par ordre d'arrivée; promotion automatique en cas de désistement, mails de notification.
- Événements: invitations par email, gestion des réponses (en_attente/confirmée/annulée), date limite d'inscription.
- Pages publiques: participants des sorties et événements, statistiques du club.
- Statistiques: KPIs, classements, filtres de dates, export CSV.
- Emails: PHPMailer SMTP, gabarits HTML soignés.
- Sécurité UX: traitement des actions avant HTML, redirections fiables, transactions DB.

## Accueil / Raccourcis administrateur
- Sur les cartes « Sortie » (page `index.php`), si vous êtes administrateur:
  - « Voir les détails » → `sortie_detail.php?id=...`
  - « Éditer » → `sortie_edit.php?id=...`
  - « Participants » (accessible à tous) → `sortie_participants.php?id=...`
  - « S'inscrire » (accessible à tous) → `preinscription_sortie.php?sortie_id=...`
- Sur les cartes « Événement », un bouton « Éditer » est également affiché aux administrateurs.

## Architecture
- PHP 8 + PDO MySQL, Bootstrap 5 + Bootstrap Icons, CSS custom `assets/css/gestnav.css`.
- PHPMailer pour l'envoi de mails (`mail_helper.php`).
- Organisation des pages: `index.php`, `sorties.php`, `sortie_edit.php`, `sortie_participants.php`, `assignations.php`, `evenements_list.php`, `evenements_admin.php`, `evenement_participants.php`, `stats.php`, etc.
- Utilitaires: `utils/waitlist.php` (liste d'attente & promotions automatiques).

## Base de données (tables clés)
- `users`: membres (actif, rôle admin/membre, email).
- `machines`: flotte ULM du club.
- `sorties`: sorties club (date_sortie, titre, description, détails, statut, created_by).
- `sortie_machines`: association des machines à une sortie.
- `sortie_assignations`: affectations des membres aux machines (rôle: pilote/passager).
- `sortie_inscriptions`: inscriptions des membres aux sorties (action_token pour actions email).
- `sortie_photos`: photos associées à une sortie.
- `evenements`: événements club.
- `evenement_inscriptions`: inscriptions aux événements (statut en_attente/confirmée/annulée, action_token).

## Contexte/Configuration
- `config.php` gère la connexion DB, la session, la locale et des helpers d'URL:
  - `base_url()`: URL de base, peut être forcée via l'ENV `GESTNAV_BASE_URL`.
  - `app_url($path)`: construit un lien applicatif.
  - `asset_url($path)`: construit un lien d'asset.
- Timezone: `Europe/Paris`, locale française.

## Processus de release
1) Bumper version et date, et préfixer le changelog:

```sh
python3 tools/release_bump.py --version 1.0.1 \
  --added "Nouvelle page d'aide" \
  --changed "Optimisation statistiques" \
  --fixed "Correction suppression sorties"
```

2) Déployer rapidement les fichiers modifiés (exemple FTP):

```sh
python3 - << 'PYTHON_EOF'
import ftplib, os
H="ftp.votrehebergeur.fr"; U="votre_utilisateur_ftp"; P="VOTRE_MOT_DE_PASSE_FTP"
BASE="/Users/guillaumelecomte/Library/Mobile Documents/com~apple~CloudDocs/Documents/VSCODE/GESTNAV"
for fname in ("config.php","CHANGELOG.md"):
    with ftplib.FTP() as ftp:
        ftp.connect(H,21,timeout=30); ftp.login(U,P)
        with open(os.path.join(BASE,fname),'rb') as f:
            ftp.storbinary(f"STOR {fname}", f)
        print(f"✓ {fname} déployé")
PYTHON_EOF
```

3) Vérifier le footer (version + date) et la page À propos.

## Flux d'inscription (sorties)
1. Un membre soumet une pré-inscription via `preinscription_sortie.php` (cela crée ou assure une ligne dans `sortie_inscriptions`).
2. L’administrateur affecte les équipages dans `sortie_detail.php`, puis valide les affectations: c’est à ce moment que les emails de confirmation sont envoyés (avec liens d’action `annuler` / `changer_machine` / `changer_coequipier`).
3. Annulation/suppression: aucun email automatique n’est envoyé; les mails partent uniquement lors de la validation des affectations par un administrateur.

## Liste d'attente
- Affichée dans `sortie_participants.php` (publique).
- Ordonnée par ordre d'arrivée (id d'inscription croissant).
- Promotion automatique via `utils/waitlist.php`.

## Pages publiques
- `sortie_participants.php`: infos sortie, participants, machines & équipages, liste d'attente.
- `evenement_participants.php`: participants confirmés d'un événement.
- `stats.php`: statistiques publiques.

## Rôles & accès
- Membre: peut consulter, s'inscrire, recevoir des liens d'action, voir les pages publiques.
- Admin: créer/éditer sorties & événements, affecter des équipages, notifier par mail, supprimer.

## Emails
- Gestion via `mail_helper.php` (PHPMailer).
- Gabarits HTML; fallback texte.
- Liens d'action générés via `app_url()`.

## Déploiement
  - Hôte: `ftp.votrehebergeur.fr`
  - Utilisateur: `votre_utilisateur_ftp`

## Documentation

## Configuration SIA (VAC)

- La page `sortie_info.php` propose un bouton pour accéder aux cartes VAC du SIA.
- Le lien PDF direct utilise le cycle eAIP défini par `GESTNAV_SIA_CYCLE_PATH`.
- Définir via variable d’environnement (recommandé):

```sh
export GESTNAV_SIA_CYCLE_PATH="eAIP_27_NOV_2025"
```

- Fallback: `config.php` utilise une valeur par défaut si la variable n’est pas présente.
- En cas de changement d’URL SIA dans le futur, consultez `docs/deployment.md` (section Cartes VAC) pour la procédure de mise à jour.
## Sécurité & bonnes pratiques
- Les actions (POST/GET) sont traitées avant tout HTML pour autoriser `header()`/redirects.
- Suppression de sortie: transaction et nettoyage des dépendances (assignations, inscriptions, machines) pour éviter les erreurs.
- Transactions pour l'édition/création de sorties.

## FAQ
- Pourquoi je vois une page blanche ?
  - Les pages ont été corrigées pour traiter les actions avant HTML; si cela arrive encore, consulter les logs serveur et `?deleted=0`/alertes.
- Comment changer l'URL de base ?
  - Définir `GESTNAV_BASE_URL` dans l'environnement; sinon `base_url()` est calculé depuis `HTTP_HOST`.

## Auteur

**Guillaume Lecomte** - [Club ULM Evasion](https://www.clubulmevasion.fr)
- 🐙 GitHub: [@glecomte62](https://github.com/glecomte62)
- 💼 LinkedIn: [guillaume-lecomte-frbe](https://www.linkedin.com/in/guillaume-lecomte-frbe)
- 📧 Email: gestnav@clubulmevasion.fr

## 🤝 Contribution

Les contributions sont les bienvenues ! Consultez [CONTRIBUTING.md](CONTRIBUTING.md) pour :
- 🐛 Signaler un bug
- ✨ Proposer une fonctionnalité
- 💻 Soumettre du code
- 📝 Améliorer la documentation

## 📄 License

Ce projet est sous licence MIT. Voir [LICENSE](LICENSE) pour plus de détails.

## 🙏 Remerciements

- Tous les contributeurs du projet
- La communauté ULM française
- Les clubs bêta-testeurs

## 💬 Support et Documentation

- 📖 [Documentation complète](docs/)
- 🚀 [Guide de démarrage rapide](QUICKSTART.md)
- 📚 [Installation détaillée](INSTALLATION.md)
- 🎨 [Guide de personnalisation](GUIDE_PERSONNALISATION.md)
- 🐛 [GitHub Issues](https://github.com/glecomte62/GESTNAV/issues)

## Qu'est-ce qu'un fichier .md ?

`.md` est une extension pour **Markdown**, un langage de balisage léger permettant d'écrire une documentation lisible en texte brut avec titres, listes, liens, code, etc., et d'être rendu joliment sur GitHub et les éditeurs.

---

<div align="center">

### ⭐ Si GESTNAV vous est utile, donnez-lui une étoile sur GitHub !

**Made with ❤️ for the ULM community**

[🚀 Démarrer](QUICKSTART.md) • [📚 Installer](INSTALLATION.md) • [🎨 Personnaliser](GUIDE_PERSONNALISATION.md) • [🤝 Contribuer](CONTRIBUTING.md)

</div>

