# Changelog

## [2.0.0] - 2025-12-12
### Added
- **Module d'envoi d'emails complet** (`envoyer_email.php`):
  - Éditeur de texte enrichi avec toolbar (gras, italique, souligné, listes, liens, couleurs).
  - Catégories de messages : Libre, Communication, Nouveau membre (avec préfixes automatiques).
  - Section **Photo** : Upload d'une image principale pour le message (max 5 MB, JPG/PNG/GIF/WebP).
  - Section **Pièces jointes** : Upload de fichiers multiples (max 10 MB par fichier).
  - Section **Liens utiles** : Ajout de liens avec texte personnalisé affichés en bas du message.
  - Types de destinataires : Tous, CLUB, INVITE, Actifs, Inactifs, Personnalisé (recherche AJAX).
  - Brouillon auto-sauvegardé en session avec tous les éléments (texte, photo, pièces jointes, liens).
  - Design cohérent avec le reste de l'application (cartes arrondies, gradient bleu).
  - Signature automatique avec logo du club et version GESTNAV.
- **Script de correction photos** (`fix_existing_photos.php`):
  - Copie automatique des photos de pré-inscription vers le dossier uploads/.
  - Mise à jour de la base de données pour les membres déjà validés.
  - Détection dynamique des colonnes photo_path/photo selon le schéma BD.
### Changed
- `login.php`: Version mise à jour de 1.5.0 à 2.0.0.
- `editer_membre.php`: 
  - Amélioration de l'affichage des photos avec gestion des chemins relatifs et absolus.
  - Correction du texte cassé "oup form-full" qui s'affichait sous la photo.
- `preinscriptions_admin.php`: 
  - Copie automatique des photos depuis uploads/preinscriptions/ vers uploads/ lors de la validation.
  - Nouveau nom de fichier unique (member_timestamp_uniqid.ext) pour éviter les conflits.
### Fixed
- Photos de pré-inscription non affichées dans les profils membres après validation.
- Chemins de photos incohérents entre différents dossiers (preinscriptions/ vs uploads/).

## [1.5.0] - 2025-12-06
### Added
- **Système de machines lâchées** (`account.php` + `annuaire.php` + `user_machines` table):
  - Nouveaux champs de profil: Membres peuvent cocher les machines club sur lesquelles ils sont lâchés.
  - Table `user_machines`: Junction table (id, user_id, machine_id, created_at) pour stocker les qualifications machines.
  - `migrate_user_machines.php`: Script de migration créant la table avec contraintes de clés étrangères.
  - **account.php**: Formulaire de sélection machine (2 colonnes) avec sauvegarde auto en BD.
  - **annuaire.php**: 
    - Badges cyan affichant les noms des machines (ex: "68GS", "62ARR") en section "Qualifications".
    - Filtres machine (checkboxes) pour filtrer les pilotes lâchés sur une machine spécifique.
    - Logique OR pour les filtres machine: sélectionner plusieurs machines affiche les pilotes lâchés sur AU MOINS une d'elles.
  - **Persistent state**: Les sélections machines restent cochées après soumission du formulaire.
### Changed
- `annuaire.php`: 
  - Affichage des filtres machine avec les noms des machines (ancien: immatriculation).
  - Badges machines affichent le nom complet avec tooltip contenant immatriculation.

## [1.4.0] - 2025-12-06
### Added
- **Section "Événements passés"** sur la page d'accueil (`index.php`):
  - Affiche les sorties/événements expirés (date < NOW()) séparés des prochaines activités.
  - Bannière rouge "Terminé" en overlay sur les images des événements passés.
  - Triage anti-chronologique (plus récent en premier) pour meilleure lisibilité.
  - Deux sections distinctes: "Prochaines activités" et "Événements passés".
- **Système de qualifications pilote** (`account.php` + `annuaire.php`):
  - Deux nouveaux champs dans le profil pilote:
    - **Emport passager**: Capacité à transporter un passager (checkbox).
    - **Qualification radio IFR**: Autorisation pour terrains avec entrée IFR (checkbox).
  - `migrate_pilot_qualifications.php`: Migration créant les colonnes `emport_passager` et `qualification_radio_ifr` en BD.
  - Badges colorés dans l'annuaire: 🟢 "Emport" (vert) et 🟠 "Radio IFR" (orange).
  - Dynamic schema detection: Les colonnes sont créées automatiquement si manquantes (compatibilité prod/dev).
- `account.php`: Détection dynamique des colonnes BD avec création automatique des colonnes manquantes.
  - Checkboxes pour les deux qualifications avec mise à jour immédiate en BD.
  - Rechargement des données après POST pour affichage de la mise à jour.
  - Logging DEBUG pour déboguer les requêtes SQL.
- `annuaire.php`: Hauteur flexible des cartes pour accommoder les badges de qualifications.
  - CSS: `height: auto; min-height: 190px` pour expansion naturelle.
  - Détection dynamique des colonnes de qualifications.
  - Affichage conditionnel des badges (uniquement si colonnes existent).
### Changed
- `index.php`: Séparation des requêtes pour sorties/événements passés et futurs par date (NOW()).
- `annuaire.php`: 
  - Hauteur des cartes modifiée de `height: 190px` à `height: auto; min-height: 190px`.
  - `.member-photo-section`: Ajout de `min-width: 230px; min-height: 190px`.
  - `.member-content`: Padding augmenté à `1rem`, hauteur à `min-height: 190px`.
### Fixed
- `account.php`: Page blanche au sauvegarde du profil → Ajout try-catch sur UPDATE + rechargement variables.
- `annuaire.php`: Page blanche sans membres → Suppression des doubles PHP tags (`?> <?php include`).
- Données de qualifications pilote ne se sauvegardaient pas → Variables mal rechargées après POST.
- Production database schema mismatch → Implémentation détection/création colonnes dynamiques.

## [1.3.0] - 2025-12-06
### Added
- **Système d'alertes email pour sorties/événements publiés**.
  - Nouveau module: `utils/event_alerts_helper.php` avec fonction `gestnav_send_event_alert()` pour envoyer les notifications.
  - `migrate_event_alerts.php`: Script de migration créant les tables `event_alerts`, `event_alert_optouts`, `event_alert_logs`.
  - `send_event_alerts.php`: Script CLI/cron pour déclencher l'envoi des alertes (usage: `php send_event_alerts.php --event-type=sortie --event-id=9`).
  - `event_alert_optout.php`: Page de désinscription avec formulaire sécurisé (token-based).
  - `event_alerts_admin.php`: Dashboard d'administration avec 3 onglets:
    - Historique des alertes (dates, titres, compteurs envoyés/échoués).
    - Liste des utilisateurs désinscrits avec raisons et notes admin.
    - Détail des envois par utilisateur (statut sent/failed/skipped, messages d'erreur).
  - **Bases de données**: Tables pour tracking des alertes, optouts et logs détaillés.
  - **Emails HTML**: Templates avec dégradé bleu, boutons CTA, infos événement, lien de désinscription.
  - **Gestion opt-out**: Utilisateurs peuvent se désinscrire facilement, tracked en BD.
### Changed
- `tools/deploy_ftp.sh`: Ajout des nouveaux fichiers d'alertes à la liste de déploiement.
### Fixed
- N/A

## [1.2.2] - 2025-12-06
### Added
- `sortie_participants.php`: gestion des participants avec séparation "affectés" vs "en attente".
  - Nouvelle logique de tracking des participants assignés aux machines (`$affectes_user_ids` array).
  - Filtrage des inscrits pour afficher uniquement ceux assignés à une machine dans la section "Participants affectés".
  - Section "Liste d'attente" affichant les inscrits non assignés avec gestion visuelle de la waitlist.
### Changed
- `sortie_participants.php`: restructuration complète de la logique d'affichage.
  - Ajout de `u.id AS user_id` au SELECT SQL pour tracker les IDs utilisateurs dans les affectations.
  - Participants section : itération sur `$participants_affectes` au lieu de tous les inscrits.
  - Statistiques : affichage du nombre de "Participants affectés" au lieu du total des inscrits.
  - Section header mise à jour : "Participants affectés (N)" avec décompte correct.
- `sortie_detail.php`: amélioration style des boutons d'action dans les emails de confirmation.
  - Ajout de `style='color: #ffffff !important;'` aux liens des boutons (Annuler, Changer machine, Changer coéquipier).
  - Garantit la lisibilité du texte blanc sur les boutons colorés en tous les contextes clients email.
### Fixed
- `tools/deploy_ftp.sh`: ajout de `sortie_participants.php` à la liste de déploiement FTP pour assurer les mises à jour en production.

## [1.2.1] - 2025-12-05
### Added
- `sortie_proposals_admin.php`: bouton "Créer sortie" pour convertir une proposition en sortie officielle avec statut "en étude".
  - Récupère la photo depuis uploads/proposals et la copie dans uploads/sorties.
  - Récupère la destination (aerodrome_id) et la lie à la sortie créée.
  - Marque la proposition comme "validée" avec note admin.
  - Envoie une notification email au proposant.
  - Crée la sortie à la date du premier du mois proposé à 09:00.
- Badges distance/temps sur `sortie_proposal_detail.php` affichant la distance depuis LFQJ et le temps de vol estimé.
- Dictionary aerodromes_distances avec distances précalculées pour les aérodromes principaux (LFAC, LFBO, etc.).
### Changed
- `sortie_proposals_admin.php`: suppression du bouton "Éditer" ; seul le bouton "Créer sortie" est disponible pour les admins.
- Workflow conversion proposition → sortie "en étude" est maintenant entièrement automatisé.
### Fixed
- Syntax error dans `sortie_proposals_admin.php` : restructuration du bloc POST pour gérer correctement l'action "create_sortie".
- Form submission avec input hidden pour l'action au lieu de `name="action"` sur le bouton.

## [1.2.0] - 2025-12-04
### Added
- `annuaire.php`: refonte complète du répertoire des membres avec design moderne et coloré.
  - Layout horizontal desktop (2 colonnes) avec photos circulaires 160px dans section colorée à gauche (gradient bleu).
  - Layout vertical mobile (cartes empilées) avec photo au-dessus du contenu.
  - Dégradés de couleur alternés par membre (6 couleurs: bleu, cyan, violet, vert, orange, rouge) pour la section photo.
  - Gradient transparent blanc sur la section contenu (texte/email/téléphone).
  - Affichage: nom, prénom, qualification (badge dégradé), email cliquable, téléphone cliquable.
  - Système de recherche en temps réel par nom/prénom/qualification/email/téléphone.
  - Responsive: 2 colonnes desktop, 1 colonne tablette (>768px), vertical mobile (<768px).
- `crop_photo.php`: outil de centrage des photos profil avec drag-and-drop et sliders.
- `account.php`: profil utilisateur avec upload de photo, gestion du téléphone, qualification, lien vers crop_photo.php.
- Database migrations: colonnes `photo_path`, `qualification`, `telephone`, `photo_metadata` (JSON avec offsetX/offsetY).
### Changed
- `header.php`: redesign navbar une seule ligne avec logo, titre, menu hamburger (mobile), et profil utilisateur avec photo circulaire (40px) + offsets appliqués.
- `sortie_info.php`: amélioration layout (3/2/1 colonnes responsive), pratical info section avec badges de couleur.
### Fixed
- CSS cascade issues dans annuaire (duplication de règles supprimée).
- Mobile responsiveness: layout vertical forces avec `!important` pour éviter CSS desktop.
- Search input: font-size 1rem sur mobile pour éviter auto-zoom iPhone.

## [1.1.3] - 2025-12-03
### Added
- `sortie_info.php`: nouvelle page de visualisation read-only des sorties pour les membres réguliers (sans affectation machines).
  - Affichage du titre, destination (OACI), distance/ETA calculées via Haversine, coordonnées depuis table aerodromes.
  - Carte Leaflet interactive centrée sur la destination avec marqueur.
  - Section "Informations pratiques" avec date, heure, destination, statut (prévue/en étude/terminée/annulée), fin (multi-jour), repas prévu.
  - Briefing et détails repas avec linkification (URLs cliquables).
  - Section "Machines & équipages" affichant les machines avec photos (fallback placeholder SVG), immatriculation, et affectations avec badges rôles (pilote/copilote/à valider).
  - Bouton "Télécharger la carte VAC" pour accéder au PDF SIA.
  - Utilisation de `destination_id` (FK vers aerodromes) pour récupération coordonnées et OACI.
  - LEFT JOIN pour affichage des affectations même avec `user_id = NULL` (affichage "? — à valider").
### Changed
- `header.php`: ajout cache-busting version param sur CSS (`?v=desk-20251203-v2`) pour force reload.
### Fixed
- SQL query optimisation pour éviter colonnes non-existent (`modele` n'existe pas dans `machines`).

## 2025-12-03
- sorties: bouton admin renommé en `Diffuser la sortie` avec fond vert (classe `broadcast`) pour l'action de notification globale (`notify`).
- sorties: ajout d'un bouton `Mail inscrits` ouvrant un modal pour composer un message ciblé aux inscrits d'une sortie. Sujet auto: `CONCERNE - <titre sortie> - IMPORTANT`.
- action_email_sortie.php: nouveau handler POST pour envoyer un email uniquement aux membres inscrits à la sortie; retourne JSON avec succès/échecs et liste des destinataires.
- sorties: après envoi, le modal affiche maintenant la liste des destinataires (nom + email) en confirmation.
- action_email_sortie.php: mise à jour de la signature des emails en `Le comité du Club ULM Evasion` (HTML et texte).
- inscriptions_admin: amélioration visuelle de l'onglet Événements (avatars initiales, badges de statut, table plus lisible) tout en gardant l'édition (statut, accompagnants).

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project adheres to Semantic Versioning.

## [1.1.2] - 2025-12-02
### Added
- `sortie_detail.php`: ajout d’une légende sous la liste des inscrits expliquant le badge « PRIORITAIRE » avec un exemple visuel.
### Changed
- `sorties.php`: harmonisation du badge « PRIORITAIRE » avec une info‑bulle (`title="Vous êtes prioritaire sur la prochaine sortie"`) pour cohérence UX.

## [1.1.1] - 2025-12-02
### Added
- Accueil (`index.php`) : bouton « Éditer » pour les sorties, visible uniquement par les administrateurs, pointant vers `sortie_edit.php?id=...`.
### Changed
- Annulations / suppressions d’inscription: aucun email automatique n’est envoyé (ni aux coéquipiers, ni au club, ni de promotion de file d’attente). Les emails ne partent que lors de la validation des affectations par un administrateur.

## [1.1.0] - 2025-12-02
### Added
- Administration des aérodromes: nouvelle page `aerodromes_admin.php` (admin-only) pour lister/rechercher/ajouter/éditer/supprimer, compatible `aerodromes_fr` ou `aerodromes` avec détection dynamique des colonnes (OACI, nom, IATA, ville, pays, lat, lon).
- Pré-inscriptions aux sorties: page `preinscription_sortie.php` permettant aux membres d’indiquer machine/coéquipier préférés et notes; affichage admin en lecture seule dans `sortie_detail.php`.
- Notifications aux non-affectés: après validation des affectations, email aux inscrits non affectés avec rappel de la priorité sur l’autre sortie et lien vers `sorties.php`.
- Coéquipier invité: possibilité d’assigner un « INVITÉ » comme personne 2 avec nom libre; persistance via table `sortie_assignations_guests` et inclusion dans l’email de confirmation.
- Gestion des inscrits (admin): page `inscriptions_admin.php` pour gérer les inscriptions sorties/événements.
- Machines propriétaires intégrées au flux d’affectation: badges d’appartenance, affichage propriétaire, séparation « Flotte du club » / « Machines propriétaires », champ « Catégorie » (source) quand disponible.
- Action « Rendre indisponible »: exclusion persistante d’une machine (`sortie_machines_exclusions`) évitant la ré-auto-association; suppression de l’exclusion si ré-ajout explicite par un admin.
- Outils de déploiement: scripts `tools/deploy_ftp.sh` et `tools/deploy_rsync.sh` (standardisation sur FTP, utilisés pour les derniers déploiements).

### Changed
- `evenements_participants.php`: lecture seule pour tous, n’affiche plus que les participants confirmés; les admins voient un badge de statut.
- `sorties.php`: l’email « Envoyer un mail » pointe vers `preinscription_sortie.php`; texte mis à jour avec note de politique club (2 sorties/mois, priorité si inscrit aux deux et non servi à la première).
- `sortie_detail.php` emails: construction des URLs d’actions (annuler / changer_machine / changer_coequipier) via `action_token`; ajout du bouton « Changer mon coéquipier »; inclusion de l’invité le cas échéant.

### Fixed
- Suppression machine: plus de page blanche; suppression propre des dépendances (liaisons, propriétaires, photos) et redirection avec messages.
- Régressions email: liens d’action désormais correctement générés; bouton « Changer mon coéquipier » restauré.
- Bug SQL transitoire sur `sortie_detail.php` (bloc dupliqué) supprimé.
- Robustesse DB: requêtes et DDL conditionnels (détection de colonnes/tables) pour éviter les erreurs « unknown column ».

### Database
- Migrations/DDL: `migrate_machines_owners.php`, `migrate_sorties_destination.php`.
- Tables créées à la demande: `sortie_preinscriptions`, `sortie_machines_exclusions`, `sortie_assignations_guests`.

## [1.0.0] - 2025-11-30
### Added
- Public participants page for sorties now shows machines and full crews.
- Public waitlist for sorties (ordered by arrival), with automatic promotion on cancellations, and email notifications to promoted users and remaining copilots.
- About page (`about.php`) with documentation and author section; README.md created.
- Global URL helpers: `base_url()`, `app_url()`, `asset_url()`; locale/timezone initialization.
- Versioning helper `gestnav_version()` (footer shows version & credits).

### Changed
- Header widened (`container` → `container-fluid`) to keep icons and text on one line.
- `sortie_edit.php`: process POST before HTML + transaction for insert/update and machine links; redirects with flash flags.
- `sorties.php`: notification links use `app_url()`, and flash messages refined.
- Event invitation links now use `app_url('action_evenement.php')` instead of hardcoded domain.

### Fixed
- Deleting a sortie no longer causes a blank page: wrapped in a transaction and deleted dependent rows (assignations, inscriptions, machines) before deleting the sortie; error flash on failure.
- Several pages re-ordered to process actions before emitting HTML to avoid header issues.

## [0.9.0] - 2025-11
### Added
- Public statistics page with KPIs, rankings, filters (all/last12/year) and CSV export.
- Public participants pages for events and sorties; navigation links from listings.
- Events system: CRUD, invitations by email, statuses, and deadlines.

### Fixed
- Non-admin users cannot see assignment details link on sorties list.
- Resolved prior blank page issues on admin pages by moving action handling before HTML.

--

How to update this file:
- Add a new section for each version bump (e.g., `## [1.0.1] - YYYY-MM-DD`).
- Group entries under Added / Changed / Fixed / Removed.
- Update `GESTNAV_VERSION` in `config.php` accordingly.
