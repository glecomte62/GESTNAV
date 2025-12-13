<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
?>

<?php include 'header.php'; ?>

<style>
.changelog-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.changelog-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #004b8d;
    margin: 0 0 0.5rem;
    text-align: center;
}

.changelog-subtitle {
    text-align: center;
    color: #666;
    margin: 0 0 3rem;
    font-size: 1rem;
}

.changelog-version-block {
    background: #ffffff;
    border-top: 4px solid #004b8d;
    border-radius: 0.5rem;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.changelog-version-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.version-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a1a;
}

.version-date {
    font-size: 0.9rem;
    color: #999;
    background: #f5f5f5;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    white-space: nowrap;
}

.changelog-section-type {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 1.5rem 0 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e8ecf1;
    display: inline-block;
}

.changelog-section-added .changelog-section-type {
    color: #10b981;
    border-bottom-color: #10b981;
}

.changelog-section-changed .changelog-section-type {
    color: #f59e0b;
    border-bottom-color: #f59e0b;
}

.changelog-section-fixed .changelog-section-type {
    color: #ef4444;
    border-bottom-color: #ef4444;
}

.changelog-items {
    list-style: none;
    padding: 0;
    margin: 1rem 0 0;
}

.changelog-items li {
    padding: 0.6rem 0 0.6rem 1.8rem;
    color: #555;
    line-height: 1.6;
    position: relative;
}

.changelog-items li:before {
    content: '•';
    position: absolute;
    left: 0;
    color: #004b8d;
    font-weight: bold;
}

.changelog-section-added .changelog-items li:before {
    content: '✨';
}

.changelog-section-changed .changelog-items li:before {
    content: '🔄';
}

.changelog-section-fixed .changelog-items li:before {
    content: '🐛';
}

.changelog-items code {
    background: #f5f5f5;
    padding: 0.2rem 0.4rem;
    border-radius: 0.3rem;
    font-family: 'Courier New', monospace;
    color: #d97706;
    font-size: 0.9em;
}

@media (max-width: 768px) {
    .changelog-container {
        padding: 1rem;
    }
    
    .changelog-title {
        font-size: 2rem;
    }
    
    .changelog-version-block {
        padding: 1.5rem;
    }
}
</style>

<div class="changelog-container">
    <h1 class="changelog-title">Historique</h1>
    <p class="changelog-subtitle">Suivi des mises a jour de GESTNAV ULM</p>

    <!-- Version 2.4.0 -->
    <div class="changelog-version-block">
        <div class="changelog-version-header">
            <span class="version-number">[2.4.0]</span>
            <span class="version-date">2025-12-12</span>
        </div>

        <div class="changelog-section-added">
            <h3 class="changelog-section-type">Added</h3>
            <ul class="changelog-items">
                <li><strong>Système de pré-inscription publique</strong>: Nouveau formulaire accessible sans authentification pour les candidats souhaitant rejoindre le club.</li>
                <li>Page publique <code>preinscription_publique.php</code> avec formulaire complet (nom, prénom, adresse complète, contacts, photo, présentation, statut pilote).</li>
                <li>Upload de photo obligatoire (JPG/PNG, max 5MB) avec validation côté serveur.</li>
                <li>Validation automatique de l'unicité de l'email (prévention des doublons).</li>
                <li>Envoi automatique d'email de confirmation au candidat après soumission.</li>
                <li>Notification email à <code>info@clubulmevasion.fr</code> avec toutes les informations du candidat.</li>
                <li>Notification aux administrateurs avec lien direct vers l'interface de validation.</li>
                <li><strong>Interface d'administration des pré-inscriptions</strong>: Nouvelle page <code>preinscriptions_admin.php</code> pour la gestion des candidatures.</li>
                <li>Tableau de bord avec statistiques en temps réel (En attente / Validées / Refusées).</li>
                <li>Filtres rapides par statut avec compteurs dynamiques.</li>
                <li>Affichage des photos et informations complètes de chaque candidat.</li>
                <li>Bouton "Valider" qui crée automatiquement le compte utilisateur avec mot de passe temporaire.</li>
                <li>Envoi automatique d'un email de bienvenue avec identifiants de connexion.</li>
                <li>Bouton "Refuser" avec possibilité de personnaliser le motif envoyé au candidat.</li>
                <li>Modal de détails avec vue complète du dossier (infos personnelles, adresse, contact urgence, présentation, expérience pilotage).</li>
                <li>Lien "Pré-inscriptions" ajouté dans le menu Administration pour les admins.</li>
                <li><strong>Module d'envoi d'emails enrichi</strong>: Refonte complète de l'interface d'envoi d'emails.</li>
                <li>Éditeur de texte riche avec toolbar (gras, italique, souligné, listes, liens, couleurs).</li>
                <li>Section "Photo" : ajout d'une image qui sera affichée en haut du message.</li>
                <li>Section "Pièces jointes" : upload de fichiers multiples (max 10MB chacun).</li>
                <li>Section "Liens utiles" : ajout de liens avec texte personnalisé affichés en bas du message.</li>
                <li>Design harmonisé avec la page sorties.php (gradient, cartes arrondies, spacing cohérent).</li>
                <li>Validation en temps réel du bouton "Envoyer" (désactivé si sujet ou message vide).</li>
            </ul>
        </div>

        <div class="changelog-section-changed">
            <h3 class="changelog-section-type">Changed</h3>
            <ul class="changelog-items">
                <li><strong>Architecture des pré-inscriptions</strong>: Table <code>preinscriptions</code> créée avec tous les champs nécessaires.</li>
                <li>Champs d'adresse complets : ligne 1, ligne 2, code postal, ville, pays.</li>
                <li>Contacts d'urgence : nom, téléphone, email stockés séparément.</li>
                <li>Statut pilote avec numéro de licence optionnel.</li>
                <li>Liaison automatique avec la table <code>users</code> après validation.</li>
                <li><strong>Validation des champs de contact</strong>: GSM rendu obligatoire, téléphone fixe optionnel dans le formulaire de pré-inscription.</li>
                <li><strong>Organisation du code</strong>: Nettoyage complet du dossier <code>setup/</code>.</li>
                <li>Archivage de 25 scripts de migration obsolètes dans <code>setup/migrations_archive/</code>.</li>
                <li>Archivage de 4 scripts d'installation obsolètes dans <code>setup/install_archive/</code>.</li>
                <li>Ajout de fichiers README dans chaque archive pour documentation.</li>
                <li>Dossier <code>setup/</code> maintenant limité aux scripts actifs uniquement.</li>
                <li><strong>Création automatique de compte</strong>: Lors de la validation d'une pré-inscription, toutes les données sont transférées automatiquement vers la table <code>users</code>.</li>
                <li>Type membre défini sur "invite" par défaut.</li>
                <li>Mot de passe temporaire de 16 caractères généré aléatoirement.</li>
                <li>Adresse reconstituée et formatée depuis les champs séparés.</li>
            </ul>
        </div>

        <div class="changelog-section-fixed">
            <h3 class="changelog-section-type">Fixed</h3>
            <ul class="changelog-items">
                <li><strong>Chemins relatifs</strong>: Correction du script de migration <code>migrate_preinscriptions.php</code> qui affichait une page blanche.</li>
                <li>Utilisation de <code>__DIR__</code> pour les chemins relatifs vers <code>config.php</code> et <code>auth.php</code>.</li>
                <li><strong>Version de l'application</strong>: Mise à jour de la version affichée sur la page de login de 1.5.0 vers 2.0.0.</li>
            </ul>
        </div>
    </div>

    <!-- Version 2.3.0 -->
    <div class="changelog-version-block">
        <div class="changelog-version-header">
            <span class="version-number">[2.3.0]</span>
            <span class="version-date">2025-12-11</span>
        </div>

        <div class="changelog-section-added">
            <h3 class="changelog-section-type">Added</h3>
            <ul class="changelog-items">
                <li><strong>Panneau "Actuellement en préparation"</strong>: Nouveau panneau d'affichage dynamique sur la page d'accueil présentant les sorties en cours d'étude.</li>
                <li>Animation de défilement vertical infini avec effet de pause au survol.</li>
                <li>Icône de sablier animée avec rotation continue pour chaque sortie en préparation.</li>
                <li>Affichage de la date et de la destination avec icônes distinctives (🪂 bases ULM, 🛩️ aérodromes).</li>
                <li>Design glassmorphism avec gradient et effets visuels modernes.</li>
                <li><strong>Configuration multi-club</strong>: Système complet de configuration pour partager GESTNAV avec d'autres clubs.</li>
                <li>Fichier <code>club_config.php</code> centralisé avec toutes les informations du club.</li>
                <li>Script d'installation interactif <code>setup_club.php</code> avec validation des entrées.</li>
                <li>Page d'administration <code>config_generale.php</code> avec interface à 6 onglets (Informations, Contact, Visuels, Règles, Modules, Intégrations).</li>
                <li>Sélecteurs de couleurs visuels pour la personnalisation des couleurs du club.</li>
                <li>Documentation complète : <code>GUIDE_PERSONNALISATION.md</code> et <code>DISTRIBUTION.md</code>.</li>
            </ul>
        </div>

        <div class="changelog-section-changed">
            <h3 class="changelog-section-type">Changed</h3>
            <ul class="changelog-items">
                <li><strong>Organisation du projet</strong>: Réorganisation de l'arborescence avec création de dossiers <code>archive/</code> et <code>setup/</code>.</li>
                <li>Déplacement de 24 fichiers obsolètes vers <code>archive/</code> (tests, fix scripts, anciens backups).</li>
                <li>Déplacement de 30 scripts d'installation/migration vers <code>setup/</code>.</li>
                <li>Réduction du nombre de fichiers à la racine de 111 à 64 fichiers fonctionnels.</li>
                <li><strong>Responsive mobile</strong>: Optimisation complète du panneau de préparation pour tablettes et smartphones.</li>
                <li>Breakpoint tablette (991px) avec layout adaptatif et réduction des tailles.</li>
                <li>Breakpoint mobile (576px) avec design ultra-compact et espacement optimisé.</li>
                <li>Hauteurs ajustées : panneau à 180px (desktop), 200px (tablette), 180px (mobile).</li>
            </ul>
        </div>

        <div class="changelog-section-fixed">
            <h3 class="changelog-section-type">Fixed</h3>
            <ul class="changelog-items">
                <li><strong>Encodage UTF-8 des emails</strong>: Correction de l'encodage pour l'envoi des nouveautés par email.</li>
                <li>Ajout de <code>mb_convert_encoding()</code> pour garantir l'UTF-8 du contenu du changelog.</li>
                <li>Encodage MIME du sujet avec <code>mb_encode_mimeheader()</code> pour supporter les emojis.</li>
                <li>Ajout de <code>&lt;meta charset="UTF-8"&gt;</code> dans le HTML des emails.</li>
                <li>Les accents et caractères spéciaux s'affichent maintenant correctement dans tous les emails.</li>
            </ul>
        </div>
    </div>

    <!-- Version 2.2.0 -->
    <div class="changelog-version-block">
        <div class="changelog-version-header">
            <span class="version-number">[2.2.0]</span>
            <span class="version-date">2025-12-10</span>
        </div>

        <div class="changelog-section-added">
            <h3 class="changelog-section-type">Added</h3>
            <ul class="changelog-items">
                <li><strong>Carte interactive des destinations</strong>: Carte de France sur la page d'accueil affichant toutes les destinations des sorties à venir.</li>
                <li>Marqueurs cliquables avec popup contenant : titre, date, destination et lien direct vers la sortie.</li>
                <li>Icônes distinctives : 🪂 pour les bases ULM, 🛩️ pour les aérodromes.</li>
                <li>Auto-zoom pour afficher toutes les destinations si plusieurs sorties planifiées.</li>
                <li>Intégration Leaflet.js avec fond de carte OpenStreetMap.</li>
            </ul>
        </div>

        <div class="changelog-section-changed">
            <h3 class="changelog-section-type">Changed</h3>
            <ul class="changelog-items">
                <li>Requête SQL de <code>index.php</code> enrichie pour récupérer les coordonnées des destinations (aérodromes et bases ULM).</li>
                <li>Carte affichée uniquement si au moins une sortie possède des coordonnées de destination.</li>
            </ul>
        </div>

        <div class="changelog-section-fixed">
            <h3 class="changelog-section-type">Fixed</h3>
            <ul class="changelog-items">
                <li><strong>Version dynamique</strong>: La page de connexion <code>login.php</code> utilise maintenant la constante <code>GESTNAV_VERSION</code> pour afficher la version courante.</li>
            </ul>
        </div>
    </div>

    <!-- Version 2.1.0 -->
    <div class="changelog-version-block">
        <div class="changelog-version-header">
            <span class="version-number">[2.1.0]</span>
            <span class="version-date">2025-12-10</span>
        </div>

        <div class="changelog-section-added">
            <h3 class="changelog-section-type">Added</h3>
            <ul class="changelog-items">
                <li><strong>Intégration des bases ULM</strong>: Support complet des destinations vers les bases ULM françaises.</li>
                <li>Recherche de bases ULM dans le sélecteur de destination (500 résultats max).</li>
                <li>Icône distinctive 🪂 pour différencier les bases ULM des aérodromes 🛩️.</li>
                <li><strong>Calcul de distance</strong>: Affichage de la distance et du temps de vol pour les bases ULM.</li>
                <li><strong>Affichage cartographique</strong>: Carte interactive Leaflet pour les bases ULM.</li>
                <li><strong>Téléchargement de fiches</strong>: Bouton pour télécharger la fiche BaseULM (FFPlum) avec le code OACI.</li>
                <li>Badge visuel dans la liste des sorties (<code>sorties.php</code>) indiquant le type de destination.</li>
            </ul>
        </div>

        <div class="changelog-section-fixed">
            <h3 class="changelog-section-type">Fixed</h3>
            <ul class="changelog-items">
                <li><strong>Correction colonnes base de données</strong>: Utilisation des bonnes colonnes <code>lat</code> et <code>lon</code> au lieu de <code>latitude</code> et <code>longitude</code>.</li>
                <li><strong>Persistance de la destination</strong>: Le champ destination reste maintenant sélectionné après sauvegarde dans <code>sortie_edit.php</code>.</li>
                <li><strong>Recherche JavaScript</strong>: La recherche de destination ne supprime plus les bases ULM de la liste.</li>
            </ul>
        </div>

        <div class="changelog-section-changed">
            <h3 class="changelog-section-type">Changed</h3>
            <ul class="changelog-items">
                <li><strong>Priorité ULM</strong>: Les bases ULM ont maintenant priorité d'affichage sur les aérodromes si les deux sont renseignés.</li>
                <li>Pages mises à jour: <code>sortie_detail.php</code>, <code>sortie_info.php</code>, <code>sortie_edit.php</code>, <code>sorties.php</code>.</li>
                <li>Amélioration de la requête SQL pour joindre la table <code>ulm_bases_fr</code>.</li>
            </ul>
        </div>
    </div>

    <!-- Version 2.0.1 -->
    <div class="changelog-version-block">
        <div class="changelog-version-header">
            <span class="version-number">[2.0.1]</span>
            <span class="version-date">2025-12-08</span>
        </div>

        <div class="changelog-section-fixed">
            <h3 class="changelog-section-type">Fixed</h3>
            <ul class="changelog-items">
                <li><strong>Images dans les emails</strong>: Intégration complète avec PHPMailer et Brevo SMTP.</li>
                <li>Images affichées centrées avec texte du message en dessous.</li>
                <li>Gestion des pièces jointes et liens attachés aux emails.</li>
                <li><strong>Couleurs de texte</strong>: Implémentation d'un sélecteur de couleur dans l'éditeur WYSIWYG.</li>
                <li>5 couleurs disponibles: Rouge, Bleu, Vert, Orange, Violet.</li>
                <li>Préservation des styles CSS (couleurs, gras, italique, souligné) dans les emails reçus.</li>
                <li><strong>UTF-8</strong>: Configuration correcte du charset pour éviter les problèmes d'encodage.</li>
                <li><strong>Nettoyage HTML</strong>: Sécurisation du contenu en supprimant les balises dangereuses tout en préservant le texte et la mise en forme.</li>
                <li>Boutons d'upload renommés pour plus de clarté: <code>📤</code> → <code>Ajouter</code>.</li>
                <li>Suppression du debug affichage en interface.</li>
            </ul>
        </div>

        <div class="changelog-section-changed">
            <h3 class="changelog-section-type">Changed</h3>
            <ul class="changelog-items">
                <li><code>mail_helper_advanced.php</code>: EmailSender utilise maintenant PHPMailer v6.9.1 avec SMTP Brevo.</li>
                <li><code>envoyer_email.php</code>: Amélioration du système de traitement HTML pour préserver les styles.</li>
                <li>Interface étape 4 (Compléments): Clarification de l'ajout de photos et pièces jointes.</li>
            </ul>
        </div>

        <div class="changelog-section-added">
            <h3 class="changelog-section-type">Added</h3>
            <ul class="changelog-items">
                <li>Sélecteur de couleur dans l'éditeur d'emails (étape 3).</li>
                <li>Support des balises <code>&lt;font color=&quot;&quot;&gt;</code> et <code>&lt;span style=&quot;color:&quot;&gt;</code> dans les emails.</li>
            </ul>
        </div>
    </div>

    <!-- Version 2.0.0 -->
    <div class="changelog-version-block">
        <div class="changelog-version-header">
            <span class="version-number">[2.0.0]</span>
            <span class="version-date">2025-12-07</span>
        </div>

        <div class="changelog-section-added">
            <h3 class="changelog-section-type">Added</h3>
            <ul class="changelog-items">
                <li><strong>Module complet d'envoi d'emails</strong> (<code>envoyer_email.php</code>):</li>
                <li>Éditeur WYSIWYG professionnel (TinyMCE 6) avec mise en forme enrichie.</li>
                <li>3 catégories d'emails: <strong>Libre</strong>, <strong>Communication club</strong>, <strong>Nouveau membre</strong>.</li>
                <li>6 types de destinataires: <strong>Tous</strong>, <strong>CLUB</strong>, <strong>INVITE</strong>, <strong>Actifs</strong>, <strong>Inactifs</strong>, <strong>Personnalisé</strong>.</li>
                <li>Sélection individuelle des membres avec recherche en temps réel.</li>
                <li>Compteur dynamique de destinataires mis à jour en temps réel.</li>
                <li>Brouillons sauvegardés automatiquement en session.</li>
                <li>Préfixes de sujet automatiques selon la catégorie:
                    <ul style="margin-top: 0.5rem;">
                        <li><strong>Communication</strong>: <code>Communication club - {sujet}</code></li>
                        <li><strong>Nouveau membre</strong>: <code>Bienvenue - {sujet}</code></li>
                        <li><strong>Libre</strong>: Sujet tel quel</li>
                    </ul>
                </li>
                <li>Signature professionnelle avec logo et version GESTNAV.</li>
                <li>Interface responsive avec layout 2 colonnes (formulaire + aperçu).</li>
                <li>Intégration Bootstrap 5.3.3 et design cohérent avec GESTNAV.</li>
            </ul>
        </div>

        <div class="changelog-section-changed">
            <h3 class="changelog-section-type">Changed</h3>
            <ul class="changelog-items">
                <li>Version globale: <strong>1.1.3 → 2.0.0</strong> (<code>config.php</code> + <code>footer.php</code>).</li>
                <li>Footer: Affichage de la version via constante <code>GESTNAV_VERSION</code>.</li>
                <li>Deploy script: Ajout de <code>config.php</code> et <code>footer.php</code> à la liste de déploiement.</li>
            </ul>
        </div>
    </div>

    <!-- Version 1.5.0 -->
    <div class="changelog-version-block">
        <div class="changelog-version-header">
            <span class="version-number">[1.5.0]</span>
            <span class="version-date">2025-12-06</span>
        </div>

        <div class="changelog-section-added">
            <h3 class="changelog-section-type">Added</h3>
            <ul class="changelog-items">
                <li>Système de machines lâchées: Membres peuvent cocher les machines club sur lesquelles ils sont lâchés.</li>
                <li>Table <code>user_machines</code>: Junction table (id, user_id, machine_id, created_at) pour stocker les qualifications machines.</li>
                <li><code>migrate_user_machines.php</code>: Script de migration créant la table avec contraintes de clés étrangères.</li>
                <li><code>account.php</code>: Formulaire de sélection machine (2 colonnes) avec sauvegarde auto en BD.</li>
                <li><code>annuaire.php</code>: Badges cyan affichant les noms des machines (ex: "68GS", "62ARR") en section "Qualifications".</li>
                <li>Filtres machine (checkboxes) pour filtrer les pilotes lâchés sur une machine spécifique.</li>
                <li>Logique OR pour les filtres machine: sélectionner plusieurs machines affiche les pilotes lâchés sur AU MOINS une d'elles.</li>
                <li>Persistent state: Les sélections machines restent cochées après soumission du formulaire.</li>
            </ul>
        </div>

        <div class="changelog-section-changed">
            <h3 class="changelog-section-type">Changed</h3>
            <ul class="changelog-items">
                <li><code>annuaire.php</code>: Affichage des filtres machine avec les noms des machines (ancien: immatriculation).</li>
                <li><code>annuaire.php</code>: Badges machines affichent le nom complet avec tooltip contenant immatriculation.</li>
            </ul>
        </div>

        <div class="changelog-section-fixed">
            <h3 class="changelog-section-type">Fixed</h3>
            <ul class="changelog-items">
                <li>Production database schema: Migration pour créer table <code>user_machines</code> manquante en production.</li>
            </ul>
        </div>
    </div>

    <!-- Version 1.4.0 -->
    <div class="changelog-version-block">
        <div class="changelog-version-header">
            <span class="version-number">[1.4.0]</span>
            <span class="version-date">2025-12-06</span>
        </div>

        <div class="changelog-section-added">
            <h3 class="changelog-section-type">Added</h3>
            <ul class="changelog-items">
                <li>Section "Événements passés" sur la page d'accueil (<code>index.php</code>): Affiche les sorties/événements expirés séparés des prochaines activités.</li>
                <li>Bannière rouge "Terminé" en overlay sur les images des événements passés.</li>
                <li>Système de qualifications pilote (<code>account.php</code> + <code>annuaire.php</code>): Deux nouveaux champs dans le profil pilote.</li>
                <li>Emport passager: Capacité à transporter un passager (checkbox).</li>
                <li>Qualification radio IFR: Autorisation pour terrains avec entrée IFR (checkbox).</li>
                <li><code>migrate_pilot_qualifications.php</code>: Migration créant les colonnes <code>emport_passager</code> et <code>qualification_radio_ifr</code> en BD.</li>
                <li>Badges colorés dans l'annuaire: 🟢 "Emport" (vert) et 🟠 "Radio IFR" (orange).</li>
                <li>Dynamic schema detection: Les colonnes sont créées automatiquement si manquantes (compatibilité prod/dev).</li>
            </ul>
        </div>

        <div class="changelog-section-changed">
            <h3 class="changelog-section-type">Changed</h3>
            <ul class="changelog-items">
                <li><code>index.php</code>: Séparation des requêtes pour sorties/événements passés et futurs par date (NOW()).</li>
                <li><code>annuaire.php</code>: Hauteur des cartes modifiée de <code>height: 190px</code> à <code>height: auto; min-height: 190px</code>.</li>
                <li><code>annuaire.php</code>: <code>.member-photo-section</code> Ajout de <code>min-width: 230px; min-height: 190px</code>.</li>
                <li><code>annuaire.php</code>: <code>.member-content</code> Padding augmenté à 1rem, hauteur à min-height: 190px.</li>
            </ul>
        </div>

        <div class="changelog-section-fixed">
            <h3 class="changelog-section-type">Fixed</h3>
            <ul class="changelog-items">
                <li><code>account.php</code>: Page blanche au sauvegarde du profil → Ajout try-catch sur UPDATE + rechargement variables.</li>
                <li><code>annuaire.php</code>: Page blanche sans membres → Suppression des doubles PHP tags.</li>
                <li>Données de qualifications pilote ne se sauvegardaient pas → Variables mal rechargées après POST.</li>
                <li>Production database schema mismatch → Implémentation détection/création colonnes dynamiques.</li>
            </ul>
        </div>
    </div>

    <!-- Version 1.2.2 -->
    <div class="changelog-version-block">
        <div class="changelog-version-header">
            <span class="version-number">[1.2.2]</span>
            <span class="version-date">2025-12-04</span>
        </div>

        <div class="changelog-section-added">
            <h3 class="changelog-section-type">Added</h3>
            <ul class="changelog-items">
                <li><code>propose_sortie.php</code>: nouveau formulaire pour soumettre des sorties proposees par les membres.</li>
                <li><code>sortie_proposals_list.php</code>: page publique affichant toutes les sorties proposees avec recherche et filtrage.</li>
                <li><code>sortie_proposal_detail.php</code>: page de detail pour chaque proposition avec photos et informations completes.</li>
                <li><code>sortie_proposals_admin.php</code>: panel administrateur pour examiner et valider les propositions.</li>
                <li>Workflow de statuts: en_attente -> accepte -> en_preparation -> validee (ou rejetee).</li>
                <li>Table <code>sortie_proposals</code>: schema complet avec user_id, aerodrome_id, photos, restauration et activites.</li>
                <li><code>migrate_sortie_proposals.php</code>: script de migration pour creer la table avec indexes.</li>
                <li>Dossier <code>uploads/proposals</code> pour stocker les photos des propositions (max 10MB).</li>
            </ul>
        </div>

        <div class="changelog-section-changed">
            <h3 class="changelog-section-type">Changed</h3>
            <ul class="changelog-items">
                <li><code>tools/deploy_ftp.sh</code>: ajout des nouveaux fichiers a la liste de deploiement.</li>
            </ul>
        </div>

        <div class="changelog-section-fixed">
            <h3 class="changelog-section-type">Fixed</h3>
            <ul class="changelog-items">
                <li>N/A</li>
            </ul>
        </div>
    </div>

    <!-- Version 1.2.1 -->
    <div class="changelog-version-block">
        <div class="changelog-version-header">
            <span class="version-number">[1.2.1]</span>
            <span class="version-date">2025-12-04</span>
        </div>

        <div class="changelog-section-added">
            <h3 class="changelog-section-type">Added</h3>
            <ul class="changelog-items">
                <li><code>about.php</code>: nouvelle page "À propos" avec présentation de l'auteur, du projet et des remerciements.</li>
                <li>Photo de profil de Guillaume LECOMTE avec section bio gradient bleu.</li>
                <li>Photo illustration du Club ULM Evasion dans la section "À propos de GESTNAV ULM".</li>
                <li>Grille de fonctionnalités (6 items): sorties, annuaire, inscriptions, cartes, responsive, notifications.</li>
                <li>Section remerciements avec 4 contributeurs: Julien CHANET, Frédéric DUMONT, Alain DEPRAETER, Jean-Luc LALUYE.</li>
                <li>Contact direct: téléphone (+33 6 46 36 56 29) et email (lecomteguillaume@outlook.com) cliquables.</li>
                <li>Bouton "Voir l'historique des versions" vers <code>changelog.php</code>.</li>
            </ul>
        </div>

        <div class="changelog-section-changed">
            <h3 class="changelog-section-type">Changed</h3>
            <ul class="changelog-items">
                <li><code>account.php</code>: redesign complet avec sidebar profil et sections cards.</li>
                <li>Photos des membres affichées en cercle 150px dans sidebar.</li>
                <li>Boutons redesignés: "Enregistrer" vert, "Annuler" gris, "Centrer photo" subtle.</li>
                <li>Layout 2 colonnes desktop, 1 colonne mobile pour meilleure UX.</li>
                <li><code>sortie_info.php</code>: nouvelle section "Inscrits" affichant photos circulaires de tous les inscrits.</li>
                <li>Section notes dans <code>sortie_detail.php</code>: affichage complet sans troncature, retours à la ligne préservés.</li>
            </ul>
        </div>

        <div class="changelog-section-fixed">
            <h3 class="changelog-section-type">Fixed</h3>
            <ul class="changelog-items">
                <li>Optimisation drastique du chargement des photos: pré-calcul des chemins, élimination des boucles file_exists répétées.</li>
                <li>Lazy loading ajouté sur les images (<code>loading="lazy"</code>, <code>decoding="async"</code>).</li>
                <li>Cache des photos des machines dans <code>sortie_detail.php</code> pour éviter les appels FS répétés.</li>
                <li>Optimisation <code>index.php</code>: suppression des requêtes SQL dupliquées pour événements.</li>
                <li>Pré-calcul des chemins photos en début de page au lieu de en boucle affichage.</li>
            </ul>
        </div>
    </div>

    <!-- Version 1.2.0 -->
    <div class="changelog-version-block">
        <div class="changelog-version-header">
            <span class="version-number">[1.2.0]</span>
            <span class="version-date">2025-12-04</span>
        </div>

        <div class="changelog-section-added">
            <h3 class="changelog-section-type">Added</h3>
            <ul class="changelog-items">
                <li><code>annuaire.php</code>: refonte complète du répertoire des membres avec design moderne et coloré.</li>
                <li>Layout horizontal desktop (2 colonnes) avec photos circulaires 160px dans section colorée.</li>
                <li>Layout vertical mobile (cartes empilées) avec photo au-dessus du contenu.</li>
                <li>Dégradés de couleur alternés par membre (6 couleurs: bleu, cyan, violet, vert, orange, rouge).</li>
                <li>Système de recherche en temps réel par nom/prénom/qualification/email/téléphone.</li>
                <li><code>crop_photo.php</code>: outil de centrage des photos profil avec drag-and-drop et sliders.</li>
                <li><code>account.php</code>: profil utilisateur avec upload de photo, gestion du téléphone et qualification.</li>
                <li>Database migrations: colonnes <code>photo_path</code>, <code>qualification</code>, <code>telephone</code>, <code>photo_metadata</code>.</li>
            </ul>
        </div>

        <div class="changelog-section-changed">
            <h3 class="changelog-section-type">Changed</h3>
            <ul class="changelog-items">
                <li><code>header.php</code>: redesign navbar une seule ligne avec profil utilisateur et photo circulaire (40px).</li>
                <li><code>sortie_info.php</code>: amélioration layout (3/2/1 colonnes responsive) et pratical info section.</li>
            </ul>
        </div>

        <div class="changelog-section-fixed">
            <h3 class="changelog-section-type">Fixed</h3>
            <ul class="changelog-items">
                <li>CSS cascade issues dans annuaire (duplication de règles supprimée).</li>
                <li>Mobile responsiveness: layout vertical forces avec <code>!important</code>.</li>
                <li>Search input: font-size 1rem sur mobile pour éviter auto-zoom iPhone.</li>
            </ul>
        </div>
    </div>

    <!-- Version 1.1.3 -->
    <div class="changelog-version-block">
        <div class="changelog-version-header">
            <span class="version-number">[1.1.3]</span>
            <span class="version-date">2025-12-03</span>
        </div>

        <div class="changelog-section-added">
            <h3 class="changelog-section-type">Added</h3>
            <ul class="changelog-items">
                <li><code>sortie_info.php</code>: nouvelle page de visualisation read-only des sorties pour les membres.</li>
                <li>Affichage du titre, destination (OACI), distance/ETA calculées via Haversine.</li>
                <li>Carte Leaflet interactive centrée sur la destination avec marqueur.</li>
                <li>Section "Informations pratiques" avec date, heure, destination, statut, repas prévu.</li>
                <li>Section "Machines & équipages" affichant les machines avec photos et affectations.</li>
                <li>Bouton "Télécharger la carte VAC" pour accéder au PDF SIA.</li>
            </ul>
        </div>

        <div class="changelog-section-changed">
            <h3 class="changelog-section-type">Changed</h3>
            <ul class="changelog-items">
                <li><code>header.php</code>: ajout cache-busting version param sur CSS.</li>
            </ul>
        </div>

        <div class="changelog-section-fixed">
            <h3 class="changelog-section-type">Fixed</h3>
            <ul class="changelog-items">
                <li>SQL query optimisation pour éviter colonnes non-existentes.</li>
                <li>LEFT JOIN pour affichage des affectations même avec user_id = NULL.</li>
            </ul>
        </div>
    </div>

</div>

<?php require 'footer.php'; ?>

