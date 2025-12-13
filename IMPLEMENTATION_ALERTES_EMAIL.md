# ✅ Système d'Alertes Email - Mise en Œuvre Complète

## 📋 Fichiers créés

### Cœur du système
1. **`utils/event_alerts_helper.php`** (242 lignes)
   - `gestnav_event_alert_is_opted_out()` : Vérifier si user a opté-out
   - `gestnav_generate_optout_token()` : Générer token sécurisé
   - `gestnav_send_event_alert()` : Envoyer alertes à tous les utilisateurs

2. **`migrate_event_alerts.php`** (80 lignes)
   - Crée 3 tables : `event_alerts`, `event_alert_optouts`, `event_alert_logs`
   - À exécuter une seule fois : `php migrate_event_alerts.php`

3. **`send_event_alerts.php`** (110 lignes)
   - Script CLI/cron pour déclencher alertes
   - Usage: `php send_event_alerts.php --event-type=sortie --event-id=9`
   - Parse paramètres et valide avant envoi

### Interfaces utilisateur & Admin
4. **`event_alert_optout.php`** (150 lignes)
   - Page de désinscription accessible via email
   - Formulaire sécurisé avec email + raison optionnelle
   - Enregistre opt-out en BD avec token

5. **`event_alerts_admin.php`** (380 lignes)
   - Dashboard admin avec 3 onglets
   - **Onglet 1** : Historique des alertes (dates, titres, compteurs)
   - **Onglet 2** : Utilisateurs désinscrits (raisons, notes admin)
   - **Onglet 3** : Détail des envois par utilisateur

### Documentation
6. **`docs/SYSTEM_ALERTES_EMAIL.md`** (320 lignes)
   - Guide complet d'installation et usage
   - Schémas BD détaillés
   - Troubleshooting

---

## 📊 Bases de données

### Table: event_alerts
```
- id (INT AUTO_INCREMENT)
- event_type (ENUM: sortie, evenement)
- event_id (INT)
- event_title (VARCHAR 255)
- sent_at (DATETIME)
- recipient_count, success_count, failed_count (INT)
```

### Table: event_alert_optouts
```
- id (INT AUTO_INCREMENT)
- user_id (INT FK users)
- opted_out_at (DATETIME)
- reason (TEXT)
- opt_in_token (VARCHAR 64 UNIQUE)
- notes (VARCHAR 255)
```

### Table: event_alert_logs
```
- id (INT AUTO_INCREMENT)
- alert_id (INT FK event_alerts)
- user_id (INT FK users)
- email (VARCHAR 255)
- status (ENUM: sent, failed, skipped)
- error_message (TEXT)
- sent_at (DATETIME)
```

---

## 🚀 Déploiement

### Fichiers ajoutés à tools/deploy_ftp.sh
```
event_alerts_admin.php
event_alert_optout.php
send_event_alerts.php
migrate_event_alerts.php
utils/event_alerts_helper.php
```

### Étapes avant mise en production

1. **Exécuter migration** :
   ```bash
   php migrate_event_alerts.php
   ```
   Crée les tables automatiquement

2. **Tester envoi** :
   ```bash
   php send_event_alerts.php --event-type=sortie --event-id=1
   ```

3. **Vérifier dashboard** :
   Accès: `event_alerts_admin.php` (admin seulement)

---

## 📧 Flux d'envoi d'alerte

```
1. Sortie/Événement publié(é)
   ↓
2. Admin ou système appelle:
   php send_event_alerts.php --event-type=sortie --event-id=9
   ↓
3. Script récupère données de la sortie
   ↓
4. Pour chaque utilisateur:
   - Vérifier opt-out? → Sauter si oui
   - Générer token désinscription
   - Construire email HTML+texte
   - Envoyer via gestnav_send_mail()
   - Logger résultat (sent/failed/skipped)
   ↓
5. Mettre à jour stats: success_count, failed_count, recipient_count
   ↓
6. Dashboard admin affiche résultats
```

---

## 🔗 Intégration avec sorties_detail.php

**Optionnel** : Pour envoi auto après publication, ajouter dans `sorties_detail.php` après changement de statut:

```php
if ($new_status !== 'en étude' && $old_status === 'en étude') {
    require_once 'utils/event_alerts_helper.php';
    
    $event_data = [
        'titre' => $sortie['titre'],
        'date_sortie' => $sortie['date_sortie'],
        'description' => $sortie['description'],
        'destination_label' => $destination_label
    ];
    
    $event_url = 'https://gestnav.clubulmevasion.fr/sortie_info.php?id=' . $sortie_id;
    
    $result = gestnav_send_event_alert($pdo, 'sortie', $sortie_id, $event_data, $event_url);
    
    // Logger: "Alerte envoyée à {$result['sent']} utilisateurs"
}
```

---

## 🔒 Sécurité

✅ Tokens générés: `bin2hex(random_bytes(32))` (64 hex chars)
✅ Opt-out token unique par désinscription
✅ Admin only: `require_admin()` sur `event_alerts_admin.php`
✅ Emails: URLs HTTPS uniquement, pas de données sensibles
✅ Opt-out: Une fois changé, irrévocable (must contact admin to revert)

---

## 📱 Template Email

- Header gradient bleu (`#004b8d` → `#00a0c6`)
- Carte événement avec détails (titre, date, destination)
- Bouton CTA "👁️ Voir la sortie" (cliquable)
- Footer avec lien désinscription
- Version HTML + texte brut

---

## 📝 Changelog

**Version 1.3.0** (6 décembre 2025)
- ✨ Nouveau système d'alertes email complet
- 5 nouveaux fichiers
- 3 tables BD
- Dashboard d'administration
- Documentation complète

---

## ✅ Checklist avant production

- [ ] Exécuter `migrate_event_alerts.php`
- [ ] Déployer tous les fichiers via FTP
- [ ] Tester envoi d'alerte: `php send_event_alerts.php --event-type=sortie --event-id=1`
- [ ] Vérifier dashboard: `event_alerts_admin.php`
- [ ] Tester opt-out: Visiter `event_alert_optout.php`, soumettre formulaire
- [ ] Vérifier BD: Voir enregistrements dans `event_alert_optouts`
- [ ] Tester envoi deuxième fois: Vérifier que user opté-out ne reçoit rien (status='skipped')
- [ ] Documenter dans runbooks l'utilisation du système

---

## 🎯 Prochaines étapes (optionnel)

- Auto-trigger sur publication (ajouter code dans `sorties_edit.php`)
- Cron scheduler pour alertes programmées
- Stats dashboard pour KPIs (taux ouverture, clics, etc.)
- Segmentation: alertes par type sortie (ULM, planeur, etc.)

---

