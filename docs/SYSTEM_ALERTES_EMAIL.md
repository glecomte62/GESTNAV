# Système d'Alertes Email - GestNav

## Vue d'ensemble

Le système d'alertes email permet de notifier automatiquement tous les utilisateurs inscrits quand une nouvelle sortie ou un nouvel événement est **publié** (statut ≠ "en étude").

### Composants principaux

1. **`utils/event_alerts_helper.php`** : Cœur du système avec fonctions d'envoi
2. **`migrate_event_alerts.php`** : Script pour créer les tables BD
3. **`send_event_alerts.php`** : Déclenche l'envoi via CLI ou cron
4. **`event_alert_optout.php`** : Page de désinscription pour utilisateurs
5. **`event_alerts_admin.php`** : Dashboard d'administration

---

## Installation

### Étape 1 : Créer les tables BD

Exécuter le script de migration :

```bash
php migrate_event_alerts.php
```

**Output attendu** :
```
✓ Create event_alerts table
✓ Create event_alert_optouts table
✓ Create event_alert_logs table

=== Résumé ===
Exécutées: 3
Erreurs: 0
```

Cela crée 3 tables :
- `event_alerts` : Historique global des alertes
- `event_alert_optouts` : Utilisateurs désinscrits
- `event_alert_logs` : Détail par utilisateur/envoi

### Étape 2 : Déploiement

Les fichiers ont été ajoutés à `tools/deploy_ftp.sh`. Lancer le déploiement :

```bash
bash tools/deploy_ftp.sh 2>&1 | grep -E "(event_alert|send_event|migrate_event)"
```

---

## Utilisation

### Envoyer une alerte pour une sortie publiée

**Via CLI (recommandé pour cron)** :
```bash
php send_event_alerts.php --event-type=sortie --event-id=9
```

**Ou variant simplifié** :
```bash
php send_event_alerts.php sortie 9
```

**Output** :
```
Envoi des alertes pour: Sortie ULM à Issoire
Type: sortie
ID: 9
URL: https://gestnav.clubulmevasion.fr/sortie_info.php?id=9

=== Résultats ===
Alert ID: 42
Envoyés: 23
Échoués: 2
Ignorés (optout): 3

Alertes envoyées avec succès !
```

### Envoyer une alerte pour un événement publié

```bash
php send_event_alerts.php --event-type=evenement --event-id=5
```

---

## Workflow d'opt-out

1. **Utilisateur reçoit l'email** avec lien "Se désinscrire des alertes"
2. **Clique sur le lien** → Accès à `/event_alert_optout.php?token=...`
3. **Formulaire optout** avec:
   - Saisie de l'email pour vérification
   - Champ optionnel "Raison" pour feedback
4. **Après soumission** :
   - Enregistrement en BD (`event_alert_optouts`)
   - Page de confirmation
   - Utilisateur ne reçoit plus d'alertes futures

---

## Administration

### Accès au dashboard

**URL** : `https://gestnav.clubulmevasion.fr/event_alerts_admin.php`

**Accès** : Admin seulement

### Onglets disponibles

#### 📊 Historique des alertes
- Nombre total d'alertes envoyées
- Compteurs: Emails envoyés avec succès, échoués
- Tableau : Date, Type (sortie/événement), Titre, Destinataires, Résultats

#### 📋 Utilisateurs désinscrits
- Compteur total des opt-outs
- Tableau : Nom, Email, Date désinscription, Raison donnée, Notes admin
- Permet de tracker les insatisfactions

#### 📝 Détail des envois
- Compteurs: Envoyés, Échoués, Ignorés (opt-out)
- Tableau détaillé par utilisateur : Email, Statut, Messages d'erreur
- Utile pour debuguer les problèmes de livraison

---

## Intégration avec sorties_detail.php / evenements_edit.php

Pour envoyer automatiquement une alerte après publication :

```php
// Après changement de statut vers "publiée" ou autre
if ($new_status !== 'en étude' && $old_status === 'en étude') {
    // Appeler le script d'envoi
    require_once 'utils/event_alerts_helper.php';
    
    $event_data = [
        'titre' => $sortie['titre'],
        'date_sortie' => $sortie['date_sortie'],
        'description' => $sortie['description'],
        'destination_label' => $destination_label
    ];
    
    $event_url = 'https://gestnav.clubulmevasion.fr/sortie_info.php?id=' . $sortie_id;
    
    $result = gestnav_send_event_alert($pdo, 'sortie', $sortie_id, $event_data, $event_url);
    // Logger les résultats si besoin
}
```

---

## Bases de données

### Schéma : event_alerts

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT AUTO_INCREMENT | ID unique |
| event_type | ENUM('sortie', 'evenement') | Type d'événement |
| event_id | INT | ID de la sortie/événement |
| event_title | VARCHAR(255) | Titre pour log |
| sent_at | DATETIME | Quand l'alerte a été lancée |
| recipient_count | INT | Total destinataires |
| success_count | INT | Emails envoyés ✓ |
| failed_count | INT | Emails échoués ✗ |

### Schéma : event_alert_optouts

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT AUTO_INCREMENT | ID unique |
| user_id | INT FK | Ref. users.id |
| opted_out_at | DATETIME | Quand désinscrit |
| reason | TEXT | Feedback utilisateur |
| opt_in_token | VARCHAR(64) | Token pour URL désinscription |
| notes | VARCHAR(255) | Annotations admin |

### Schéma : event_alert_logs

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT AUTO_INCREMENT | ID unique |
| alert_id | INT FK | Ref. event_alerts.id |
| user_id | INT FK | Ref. users.id |
| email | VARCHAR(255) | Email où envoyé |
| status | ENUM('sent', 'failed', 'skipped') | Résultat |
| error_message | TEXT | Si échec, pourquoi |
| sent_at | DATETIME | Quand tentative |

---

## Troubleshooting

### Emails non envoyés
1. Vérifier `mail_helper.php` et configuration SMTP
2. Consulter `event_alerts_admin.php` → onglet "Détail des envois"
3. Chercher `error_message` pour le problème spécifique

### Utilisateur reçoit encore des alertes après opt-out
1. Vérifier qu'il existe en `event_alert_optouts`
2. Vérifier son `user_id` correct
3. Relancer les migrations si changement de schéma

### Page optout vierge / erreur 404
1. Vérifier que `event_alert_optout.php` est déployé
2. Vérifier les permissions d'accès
3. Vérifier `token` valide en paramètre GET

---

## Sécurité

- **Tokens** : Générés avec `bin2hex(random_bytes(32))` = 64 chars hexadécimaux
- **Opt-out** : Tokens uniques par désinscription, évite double-optout
- **Admin** : Page `event_alerts_admin.php` requires `require_admin()`
- **Emails** : Pas de données sensibles exposées, liens HTTPS uniquement

---

## Notes

- Les alertes sont envoyées **une seule fois** par événement
- Les utilisateurs opt-out ne reçoivent **aucune alerte ultérieure**
- Un utilisateur peut demander sa **réinscription** en contactant l'admin
- L'admin peut ajouter des **notes** sur chaque opt-out (motif support, etc.)

