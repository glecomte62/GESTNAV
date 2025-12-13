# 📧 Système d'Envoi d'Emails GESTNAV v2.0.0

## Vue d'Ensemble

Le système d'envoi d'emails de GESTNAV a été complètement restructuré pour offrir une expérience utilisateur supérieure avec un flux étape par étape et un historique complet des emails envoyés.

## 🎯 Architecture en 5 Étapes

### Étape 1: Sélection de la Catégorie
- **Libre** (📝) - Email personnalisé sans préfixe
- **Communication** (📢) - Préfixe automatique "📢 Communication - "
- **Nouveau Membre** (🎉) - Préfixe automatique "🎉 Bienvenue - "

### Étape 2: Sélection des Destinataires
- **Tous** - Tous les membres avec email
- **CLUB** - Membres de type "club"
- **INVITE** - Membres de type "invite"
- **Actifs** - Membres actifs (actif = 1)
- **Inactifs** - Membres inactifs (actif = 0)
- **Spécifique** - Sélection manuelle avec recherche en temps réel

### Étape 3: Rédaction du Contenu
- Éditeur HTML5 contenteditable avec toolbar
- Outils disponibles: **Gras**, *Italique*, <u>Souligner</u>, Listes, Couleurs
- Aperçu en temps réel du sujet avec préfixe
- Support des caractères spéciaux et unicode

### Étape 4: Ajout de Compléments
- **📸 Photo** - Une image principale (JPG, PNG, GIF, WebP, max 5 MB) - Embedée en base64
- **📎 Pièces Jointes** - Fichiers multiples (max 10 MB chacun) - Noms affichés
- **🔗 Liens Utiles** - Lien(s) clickable(s) avec texte personnalisé

### Étape 5: Confirmation et Envoi
- Aperçu complet de l'email (objet, message tronqué, complément)
- Affichage du nombre de destinataires
- Bouton "Envoyer maintenant" pour finaliser

## 🔄 Persistance des Données

Tous les données saisies à chaque étape sont automatiquement stockées en session :

```php
$_SESSION['email_draft'] = [
    'step' => 1-5,                      // Étape actuelle
    'subjectType' => 'custom|communication|nouveau_membre',
    'recipientType' => 'all|club|invite|actif|inactif|specific',
    'specificMembers' => [1, 5, 12],    // IDs si recipientType === 'specific'
    'subject' => 'Mon sujet',
    'message' => '<p>Mon message HTML</p>',
    'emailImage' => ['id', 'name', 'path'],
    'attachments' => [['id', 'name', 'path'], ...],
    'links' => [['id', 'text', 'url'], ...]
];
```

- Navigation avant/arrière conserve toutes les données
- Données supprimées uniquement au clic "Envoyer" ou "Effacer le brouillon"

## 📨 Processus d'Envoi

### Format de l'Email
- **Type**: `text/html` avec charset UTF-8
- **From**: `CLUB ULM EVASION <info@clubulmevasion.fr>`
- **Contenu**:
  1. Photo (si présente, embedée en base64)
  2. Message HTML avec breaks préservés
  3. Section "Liens utiles" (si présents)
  4. Signature avec logo base64 + "GESTNAV v2.0.0"

### Limitations pour les Pièces Jointes
⚠️ Les pièces jointes sont actuellement affichées (leurs noms) mais **pas encore attachées** au mail. Les fichiers sont sauvegardés sur le serveur avec un ID unique (`uniqid() + _original_filename`).

**À implémenter**: Utiliser une librairie comme `PHPMailer` ou `SwiftMailer` pour activer les vrais attachments.

## 📊 Historique des Emails

### Table `email_history`
```sql
CREATE TABLE email_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT,
    sender_name VARCHAR(255),
    recipient_type VARCHAR(50),
    recipient_count INT,
    subject VARCHAR(255),
    message_preview TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sent_at (sent_at),
    INDEX idx_sender (sender_id),
    INDEX idx_recipient_type (recipient_type)
);
```

### Page `historique_emails.php`
- Affiche les 100 derniers emails envoyés
- Colonnes: Date, Expéditeur, Objet, Type destinataires, Nombre, Aperçu
- Filtres: Recherche texte (objet + expéditeur), Type de destinataires
- Liens: Retour à "Envoyer un email"

### Logging Automatique
Après chaque envoi réussi (avant effacement du brouillon):

```php
$pdo->prepare("INSERT INTO email_history 
    (sender_id, sender_name, recipient_type, recipient_count, subject, message_preview, sent_at) 
VALUES (?, ?, ?, ?, ?, ?, NOW())")
    ->execute([
        $_SESSION['user_id'],
        $senderName,
        $recipientType,
        $successCount,
        $finalSubject,
        substr(strip_tags($message), 0, 100) . '...'
    ]);
```

## 🔧 Installation & Migration

### Prérequis
- PHP 7.4+
- MySQL 5.7+ ou MariaDB 10.2+
- Session PHP activée

### Migration
Exécuter le script de migration pour créer la table:
```bash
php migrate_email_history.php
```

**Résultat attendu**:
```
✅ Table email_history créée avec succès!
Migration appliquée avec succès.
```

## 🎨 Styles & Responsive

### Palette de Couleurs
- **Primary**: Gradient `#004b8d` → `#00a0c6`
- **Success**: `#d1fae5` (vert clair)
- **Background**: `#f9fafb` (gris très clair)
- **Border**: `#d1d5db` (gris léger)

### Responsive
- Mobile: Stack vertical, flex wrap
- Tablet: Grid 2 colonnes
- Desktop: Grid full avec espacements généreux

## 📁 Fichiers Impliqués

```
envoyer_email.php           # Page principale (1,280 lignes)
├─ Étapes 1-5 UI
├─ Actions POST (next_step, prev_step, save_content, etc.)
├─ Validations
├─ Envoi mail HTML
└─ Logging email_history

historique_emails.php       # Page d'historique (280 lignes)
├─ Récupération email_history
├─ Filtres & recherche
└─ UI responsive

migrate_email_history.php   # Migration BD
└─ Création table email_history

header.php                  # Navigation (existant)
footer.php                  # Pied de page (existant)
config.php                  # Configuration BD (existant)
auth.php                    # Authentification (existant)
```

## 🚀 Déploiement

```bash
# Vérifier la syntaxe
php -l envoyer_email.php
php -l historique_emails.php
php -l migrate_email_history.php

# Déployer via FTP
bash tools/deploy_ftp.sh

# Appliquer migration
php migrate_email_history.php
```

## 🧪 Checklist de Test

- [ ] Étape 1: Sélection catégorie persiste
- [ ] Étape 2: Sélection destinataires persiste + filtrage spécifique
- [ ] Étape 3: Rédaction et préfixe sujet automatique
- [ ] Étape 4: Upload photo et pièces jointes
- [ ] Étape 5: Envoi email HTML reçu correctement
- [ ] Historique: Email enregistré après envoi
- [ ] Navigation: Retour ne supprime pas les données
- [ ] Erreur: Message d'erreur approprié si destinataire vide
- [ ] Responsive: Mobile, tablet, desktop ok

## ⚠️ Limitations Connues

1. **Pièces jointes**: Sauvegardées sur serveur mais pas attachées au mail (nécessite PHPMailer/SwiftMailer)
2. **Images**: Embedées en base64 dans le HTML (peut augmenter la taille du mail)
3. **Limite de courriels**: Boucle PHP limite à PHP_INT_MAX (généralement ~2M)
4. **Historique**: Gardé 100 derniers emails (configurable)

## 🔐 Sécurité

- Vérification `require_admin()` sur les deux pages
- Validation inputs via `trim()`, `htmlspecialchars()`, `intval()`
- Préparation des requêtes SQL avec `:?` placeholders
- Protection CSRF implicite via session unique par utilisateur
- Upload: Vérification type MIME, taille, extension

## 📞 Support

Pour les questions ou modifications:
- Contacter: Guillaume Lecomte
- Repository: GESTNAV
- Version actuelle: 2.0.0

---

**Dernière mise à jour**: 7 décembre 2024
**Commit**: 7fd4171 "✨ Système de wizard 5-étapes pour emails + historique"
