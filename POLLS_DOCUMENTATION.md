# 🗳️ Module de Vote Électronique - Documentation

## Vue d'ensemble

Le module de vote électronique permet aux administrateurs de créer des sondages pour :
- **Caler des dates** (sondages spécialisés pour vote de dates)
- **Poser des questions** à choix multiple
- **Consulter les résultats** en temps réel
- **Envoyer des notifications** aux membres
- **Clôturer des sondages** manuellement ou automatiquement

## 📊 Structure de la base de données

### Table: `polls`
Stocke les sondages créés par les administrateurs.

```sql
CREATE TABLE polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,           -- Titre du sondage
    description TEXT,                       -- Description/contexte
    type ENUM('date', 'choix_multiple'),   -- Type de sondage
    status ENUM('ouvert', 'clos'),         -- État du sondage
    creator_id INT NOT NULL,                -- Admin qui a créé
    deadline DATETIME,                      -- Date de fermeture auto (optionnel)
    created_at TIMESTAMP DEFAULT NOW()
)
```

### Table: `poll_options`
Stocke les options/réponses possibles pour chaque sondage.

```sql
CREATE TABLE poll_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,                   -- Lien vers le sondage
    text VARCHAR(255) NOT NULL,             -- Texte de l'option
    votes INT DEFAULT 0,                    -- Nombre de votes
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
)
```

### Table: `poll_votes`
Enregistre chaque vote individuel pour assurer un seul vote par utilisateur.

```sql
CREATE TABLE poll_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    user_id INT NOT NULL,                   -- Qui a voté
    option_id INT NOT NULL,                 -- Quelle option
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE KEY uk_user_poll (poll_id, user_id),  -- Un vote par utilisateur
    FOREIGN KEY (poll_id) REFERENCES polls(id),
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id)
)
```

## 🚀 Installation

### 1. Créer les tables
Deux méthodes :

**Méthode A - Via navigateur (recommandé)**
```
https://gestnav.clubulmevasion.fr/install_polls.php
```
Cliquez sur "🚀 Exécuter la migration"

**Méthode B - Via terminal**
```bash
php migrate_polls.php
```

### 2. Vérifier l'installation
Les tables doivent être créées :
- `polls`
- `poll_options`
- `poll_votes`

## 💻 Utilisation pour les administrateurs

### Accès
Menu > Administration > **Gestion sondages**

### Créer un sondage

1. **Remplir le formulaire:**
   - **Titre** (requis): "Date de la prochaine sortie"
   - **Description**: Contexte optionnel
   - **Type**: Choisir entre:
     - 📊 **Choix multiple**: Questions avec plusieurs réponses possibles
     - 📅 **Sondage de date**: Pour caler une date (format libre)
   - **Options**: Une par ligne
   - **Deadline**: Date/heure de fermeture automatique (optionnel)

2. **Bouton**: "✅ Créer le sondage"

### Gérer les sondages

**Vue de liste** (`sondages_admin.php`):
- Badges d'état (OUVERT/CLÔTURÉ)
- Résultats en direct
- Boutons d'action:
  - **👁️ Détails**: Voir les résultats complets
  - **🔒 Clôturer**: Fermer manuellement

**Vue détails** (`sondages_detail.php`):
- Statistiques rapides (votes, options, date création)
- 📊 Résultats par option avec graphiques
- 🗳️ Historique détaillé des votes
- **📧 Notifier les membres**: Envoyer une notification email

### Envoyer une notification

1. Cliquez sur **📧 Notifier les membres** dans la page détails
2. Sélectionnez les destinataires:
   - Tous les membres
   - Membres Club
   - Membres Actifs
   - Invités
3. Cliquez sur "📧 Envoyer notification"

**Résultat:**
- Email personnalisé envoyé à chaque membre
- Lien direct vers les sondages
- Enregistrement dans l'historique des emails

### Clôturer un sondage

Deux options:
1. **Automatiquement**: Fixer une deadline lors de la création
2. **Manuellement**: Bouton "🔒 Clôturer" sur la carte du sondage

Une fois clôturé, les membres ne peuvent plus voter.

## 🗳️ Utilisation pour les membres

### Accès
Menu principal > **🗳️ Sondages**

### Voter

1. **Consulter les sondages ouverts**
   - Titre et description
   - Type de sondage (📅 Date / 📊 Choix)
   - Deadline si applicable

2. **Participer au vote**
   - Sélectionner une option
   - Les résultats s'affichent en temps réel (%)
   - Voir le nombre de votes par option
   - ✅ Indication "Vous avez voté pour cette option"

3. **Modifier son vote**
   - Sélectionner une autre option
   - Cliquer "Enregistrer mon vote"
   - Le vote précédent est remplacé

4. **Sondages fermés**
   - Affichés comme "🔴 CLÔTURÉ"
   - Résultats visibles mais pas de possibilité de voter

## 📧 Système de notifications

### Intégration avec email_logs

Quand une notification est envoyée:
1. Enregistrement dans `email_logs`:
   - Sujet: "🗳️ Nouveau sondage: [titre]"
   - Message: Description + lien vers sondages
   - Nombre de destinataires

2. Enregistrement dans `email_recipients`:
   - Chaque destinataire listé individuellement
   - Traçabilité complète dans l'historique des emails

### Contenu du mail

```
Bonjour [Prénom],

[Titre du sondage]
[Description]

Nous vous invitons à participer à ce sondage !

🗳️ ACCÉDER AUX SONDAGES

⏰ Date limite: [Si applicable]
```

## 🔧 Architecture technique

### Fichiers principaux

| Fichier | Rôle |
|---------|------|
| `migrate_polls.php` | Migration CLI pour créer les tables |
| `install_polls.php` | Installeur web (interface) |
| `sondages_admin.php` | Gestion admin - créer, lister, clôturer |
| `sondages_detail.php` | Détails d'un sondage - résultats + notification |
| `sondages.php` | Interface de vote pour les membres |
| `send_poll_notification.php` | API AJAX pour envoyer notifications |

### Flux de données

```
Admin crée sondage
    ↓
INSERT polls, poll_options
    ↓
Affichage dans sondages_admin.php
    ↓
Admin notifie membres
    ↓
send_poll_notification.php
    ↓
Emails envoyés + enregistrement email_logs
    ↓
Membres votent sur sondages.php
    ↓
Votes enregistrés dans poll_votes
    ↓
Résultats visibles en temps réel
```

## 🎯 Cas d'usage

### 1. Caler une date de sortie
- Type: Sondage de date
- Options: "Samedi 15 mars", "Dimanche 16 mars", "Samedi 22 mars"
- Notify: Tous les membres actifs
- Deadline: 3 jours avant la plus proche option

### 2. Choisir un repas commun
- Type: Choix multiple
- Options: "Grillades", "Pasta", "Asiatique", "Burgers"
- Notify: Tous les membres
- Deadline: Jour avant l'événement

### 3. Décision administrative
- Type: Choix multiple
- Options: "Oui", "Non", "Abstention"
- Notify: Membres Club uniquement
- Deadline: Fin de semaine

## 🔐 Sécurité

- ✅ Authentification requise pour voter
- ✅ Un seul vote par utilisateur (UNIQUE KEY)
- ✅ Vérifications d'intégrité (FK, sondage ouvert)
- ✅ Création réservée aux admins
- ✅ Votes modifiables par l'utilisateur
- ✅ Logs complets dans email_logs

## 📈 Améliorations futures

Possibilités d'extension:
- [ ] Sondages privés (membres spécifiques)
- [ ] Vote pondéré (avec poids différents)
- [ ] Résultats anonymes/nominatifs
- [ ] Export des résultats (PDF/Excel)
- [ ] Rappels de votes (email avant deadline)
- [ ] Graphiques avancés (diagrammes animés)
- [ ] API REST pour intégrations tierces
- [ ] Sondages récurrents (modèles)

## ❓ FAQ

**Q: Peut-on changer son vote?**
R: Oui, il suffit de sélectionner une autre option et cliquer "Enregistrer mon vote". Le vote précédent est remplacé.

**Q: Que se passe-t-il si deadline est dépassée?**
R: Les sondages avec deadline dépassée sont fermés automatiquement à l'affichage.

**Q: Les résultats sont-ils anonymes?**
R: Non, dans la vue admin, on voit qui a voté pour quelle option. Mais sur la page des membres, c'est anonyme.

**Q: Peut-on supprimer un sondage?**
R: Non, il faut le clôturer. Les données sont conservées pour la traçabilité.

**Q: Combien d'options maximum?**
R: Pas de limite technique, mais UX dégradée avec trop d'options (recommandé ≤ 8).

---

**Version**: 1.0.0  
**Date**: Décembre 2025  
**Auteur**: GESTNAV  
**Status**: Production Ready ✅
