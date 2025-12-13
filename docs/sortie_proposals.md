# Système de Propositions de Sorties

## Vue d'ensemble

Le système **sortie_proposals** permet aux membres du club de proposer des sorties. Les administrateurs examinent et valident ensuite ces propositions pour les transformer en sorties officielles.

## Flux de travail

### 1. Soumission par les membres (`propose_sortie.php`)

**Accès**: Tout membre connecté  
**URL**: `/propose_sortie.php`

Les membres peuvent soumettre une proposition avec:
- **Titre** de la sortie (obligatoire)
- **Description** détaillée
- **Mois proposé** (janvier à décembre)
- **Aérodrome** de destination (optionnel, sélectionné depuis `aerodromes_fr`)
- **Restaurant proposé** et détails culinaires
- **Activités ou visites** sur place
- **Photo illustrative** (max 10MB, JPG/PNG/WebP)

Après validation, une proposition reçoit le statut `en_attente`.

### 2. Liste publique (`sortie_proposals_list.php`)

**Accès**: Tout membre connecté  
**URL**: `/sortie_proposals_list.php`

Affiche toutes les propositions avec:
- **Cartes visuelles** avec photos (placeholder avion si pas de photo)
- **Recherche** par titre, aérodrome, proposeur
- **Filtres** par statut et mois
- **Lien "Détails"** vers page d'information complète
- **Bouton "Proposer une sortie"** pour accès rapide au formulaire

### 3. Détails d'une proposition (`sortie_proposal_detail.php`)

**Accès**: Tout membre connecté  
**URL**: `/sortie_proposal_detail.php?id=X`

Affiche:
- Photo illustration en grand format
- Description complète
- Section restauration (restaurant, menu, prix)
- Section activités
- **Proposeur**: photo, nom, téléphone, email
- **Statut** avec badge de couleur
- Dates de création et dernière mise à jour

### 4. Panel administrateur (`sortie_proposals_admin.php`)

**Accès**: Admin uniquement  
**URL**: `/sortie_proposals_admin.php`

Tableau de gestion avec:
- **Filtres de statut**: Tous, En attente, Acceptées, En préparation, Validées, Rejetées
- **Colonne** titre, mois, proposeur, statut, actions
- **Boutons** "Voir" et "Editer" pour chaque proposition
- **Modal d'édition** pour modifier le statut et ajouter des notes

## Statuts et transitions

```
en_attente
    ↓ [Admin accepte]
accepte
    ↓ [Admin lance préparation]
en_preparation
    ↓ [Admin valide & lance inscriptions]
validee

[Alternativement]
en_attente → rejetee [avec raison]
```

### États expliqués

| Statut | Sens | Action |
|--------|------|--------|
| `en_attente` | Proposée, en attente de validation | Admin examine |
| `accepte` | Approuvée par admin | Admin prépare la sortie |
| `en_preparation` | Sortie officielle en cours de création | Admin vérifie détails |
| `validee` | Prête, ouvertes aux inscriptions | Membres peuvent s'inscrire |
| `rejetee` | Refusée (raison dans notes admin) | Pas de sortie créée |

## Base de données

### Table `sortie_proposals`

```sql
CREATE TABLE sortie_proposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL (FK users),
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    month_proposed VARCHAR(50),
    aerodrome_id INT (FK aerodromes_fr),
    restaurant_choice VARCHAR(255),
    restaurant_details TEXT,
    activity_details TEXT,
    photo_filename VARCHAR(255),
    status ENUM('en_attente', 'accepte', 'en_preparation', 'validee', 'rejetee'),
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (aerodrome_id) REFERENCES aerodromes_fr(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
)
```

**Création**: Exécuter `migrate_sortie_proposals.php` une fois.

## Fichiers et uploads

### Stockage des photos

- **Dossier**: `/uploads/proposals/`
- **Nommage**: `proposal_TIMESTAMP_RANDOMHEX.ext`
- **Formats acceptés**: JPG, PNG, WebP
- **Taille max**: 10 MB
- **Exemple**: `proposal_1733338560_a1b2c3d4.jpg`

## Flux d'email (TODO)

À implémenter:

1. **Soumission**: Email à `info@clubulmevasion.fr` + proposeur avec lien admin
2. **Acceptation**: Email au proposeur: "Votre proposition a été acceptée"
3. **En préparation**: Email au proposeur: "La sortie est en cours de création"
4. **Validée**: Email au proposeur: "Inscriptions ouvertes!"
5. **Rejetée**: Email au proposeur avec raison (admin_notes)

## Sécurité

- ✅ **Authentification**: Requires `require_login()`
- ✅ **Admin-only**: `sortie_proposals_admin.php` vérifie `$_SESSION['is_admin']`
- ✅ **XSS Protection**: `htmlspecialchars()` sur tous les echappements
- ✅ **SQL Injection**: Prepared statements avec PDO
- ✅ **File Upload**: Validation MIME type, limite taille, répertoire sécurisé
- ⚠️ **À faire**: Valider `user_id` de session en admin (ne pas faire confiance au POST)

## Performance

- Requête optimisée: JOIN users + aerodromes_fr, ORDER BY created_at DESC
- Limite affichage: 100 propositions par page
- Lazy loading images: `loading="lazy"` + `decoding="async"`

## Responsive Design

### Desktop
- Grille 3-4 colonnes de cartes
- Table admin multi-colonne

### Tablet (768px+)
- Grille 2 colonnes
- Table simplifiée

### Mobile (<768px)
- Grille 1 colonne
- Table en colonne unique

## Navigation et intégration

### Menu (`header.php`)
À ajouter lien vers `sortie_proposals_list.php`:
```html
<a href="sortie_proposals_list.php">Propositions</a>
```

### Barre de recherche (`index.php`)
Optionnel: Ajouter filtre sortie_proposals_list.php

## Couleurs des statuts

| Statut | Couleur | Hex |
|--------|---------|-----|
| en_attente | Jaune | #fbbf24 |
| accepte | Vert clair | #34d399 |
| en_preparation | Bleu | #60a5fa |
| validee | Vert | #10b981 |
| rejetee | Rouge | #f87171 |

## Tests manuels

1. ✅ Se connecter comme membre
2. ✅ Visiter `/propose_sortie.php`
3. ✅ Soumettre une proposition avec photo
4. ✅ Voir dans `/sortie_proposals_list.php`
5. ✅ Cliquer "Détails" pour voir `/sortie_proposal_detail.php`
6. ✅ Se connecter comme admin
7. ✅ Visiter `/sortie_proposals_admin.php`
8. ✅ Cliquer "Editer", modifier statut à "accepte"
9. ✅ Vérifier mise à jour en direct

## Prochaines étapes

1. **Emails**: Implémenter notifications via `mail_helper.php`
2. **Sorties auto**: Créer sortie officielle à partir d'une proposal acceptée
3. **Historique**: Enregistrer qui a approuvé, quand, notes
4. **Export**: PDF de propositions pour réunions
5. **Commentaires**: Permettre feedback des autres membres
