# 📂 Manifest - Système d'Alertes Email

## ✨ Fichiers créés (9 fichiers)

### Code source (5 fichiers)
```
✓ migrate_event_alerts.php
  └─ 80 lignes | Crée 3 tables BD
  └─ À exécuter UNE SEULE FOIS
  
✓ send_event_alerts.php
  └─ 110 lignes | CLI script pour déclencher alertes
  └─ Usage: php send_event_alerts.php --event-type=sortie --event-id=9
  
✓ event_alert_optout.php
  └─ 150 lignes | Page de désinscription publique
  └─ Route: /event_alert_optout.php?token=...
  
✓ event_alerts_admin.php
  └─ 380 lignes | Dashboard d'administration
  └─ Route: /event_alerts_admin.php (admin only)
  └─ Onglets: Historique | Désinscrits | Détail envois
  
✓ utils/event_alerts_helper.php
  └─ 240 lignes | Cœur du système avec 3 fonctions
  └─ gestnav_send_event_alert()
  └─ gestnav_event_alert_is_opted_out()
  └─ gestnav_generate_optout_token()
```

### Documentation (5 fichiers)
```
✓ QUICKSTART_ALERTES.md
  └─ 200 lignes | Guide rapide (setup 3 étapes)
  
✓ docs/SYSTEM_ALERTES_EMAIL.md
  └─ 320 lignes | Documentation complète
  └─ Installation, usage, BD schema, troubleshooting
  
✓ IMPLEMENTATION_ALERTES_EMAIL.md
  └─ 300 lignes | Détails implémentation
  └─ Fichiers créés, flux, sécurité, checklist
  
✓ PRODUCTION_CHECKLIST_ALERTES.md
  └─ 250 lignes | Checklist déploiement
  └─ 5 phases: BD, FTP, Tests, Performance, Edge cases
  
✓ ARCHITECTURE_ALERTES.md
  └─ 400 lignes | Diagrammes et architecture
  └─ Flux, fichiers, intégration système
```

---

## 🔧 Fichiers modifiés (2 fichiers)

### Configuration
```
✓ tools/deploy_ftp.sh
  └─ Ajout 5 nouveaux fichiers à la liste de déploiement
  └─ Lignes modifiées: 18-45
  
✓ CHANGELOG.md
  └─ Nouvelle version 1.3.0 ajoutée en haut
  └─ Description: Système d'alertes email complet
```

---

## 📊 Bases de données créées (3 tables)

```
event_alerts
├─ id (INT AUTO_INCREMENT)
├─ event_type (ENUM: sortie, evenement)
├─ event_id (INT)
├─ event_title (VARCHAR 255)
├─ sent_at (DATETIME)
├─ recipient_count (INT)
├─ success_count (INT)
└─ failed_count (INT)

event_alert_optouts
├─ id (INT AUTO_INCREMENT)
├─ user_id (INT FK users)
├─ opted_out_at (DATETIME)
├─ reason (TEXT)
├─ opt_in_token (VARCHAR 64 UNIQUE)
└─ notes (VARCHAR 255)

event_alert_logs
├─ id (INT AUTO_INCREMENT)
├─ alert_id (INT FK event_alerts)
├─ user_id (INT FK users)
├─ email (VARCHAR 255)
├─ status (ENUM: sent, failed, skipped)
├─ error_message (TEXT)
└─ sent_at (DATETIME)
```

---

## 📈 Statistiques

| Catégorie | Quantité | Taille |
|-----------|----------|--------|
| Fichiers code | 5 | 37 KB |
| Fichiers doc | 5 | ~250 KB |
| **Total** | **10** | **~287 KB** |
| Lignes de code | ~900 | - |
| Lignes de doc | ~1,500 | - |
| Tables BD | 3 | - |

---

## ✅ Déploiement résumé

### Dans tools/deploy_ftp.sh (ajouté)
```bash
# Système d'alertes email
event_alerts_admin.php
event_alert_optout.php
send_event_alerts.php
migrate_event_alerts.php

# Utils
utils/event_alerts_helper.php
```

### Après FTP upload
```
✓ migrate_event_alerts.php → Exécuter 1x
✓ send_event_alerts.php    → Accessible via CLI
✓ event_alert_optout.php   → Public
✓ event_alerts_admin.php   → Admin only
✓ utils/event_alerts_helper.php → Include automatique
```

---

## 🚀 Points d'intégration

### 1. Auto-trigger (optionnel)
**Fichier**: `sorties_edit.php` ou `evenements_edit.php`

```php
if ($new_status !== 'en étude' && $old_status === 'en étude') {
    require_once 'utils/event_alerts_helper.php';
    gestnav_send_event_alert($pdo, 'sortie', $sortie_id, $event_data, $event_url);
}
```

### 2. Cron job (optionnel)
```bash
# /etc/cron.d/gestnav-alerts
0 9 * * * cd /var/www/gestnav && php send_event_alerts.php --event-type=sortie --event-id=9
```

### 3. Menu admin
**Fichier**: `header.php`

Ajouter lien vers `event_alerts_admin.php` dans menu admin

---

## 📖 Documentation organisée par rôle

### Pour admins
→ `QUICKSTART_ALERTES.md` (10 min read)

### Pour devs
→ `docs/SYSTEM_ALERTES_EMAIL.md` (30 min read)
→ `ARCHITECTURE_ALERTES.md` (20 min read)

### Pour déploiement
→ `PRODUCTION_CHECKLIST_ALERTES.md` (checklist)
→ `IMPLEMENTATION_ALERTES_EMAIL.md` (détails)

### Pour troubleshooting
→ `docs/SYSTEM_ALERTES_EMAIL.md` (section troubleshooting)
→ `event_alerts_admin.php` (dashboard logs)

---

## 🔐 Sécurité

- ✅ Tokens: `bin2hex(random_bytes(32))` = 64 hex chars
- ✅ Admin: `require_admin()` sur dashboard
- ✅ Opt-out: Irrévocable (contact admin required)
- ✅ Emails: HTTPS uniquement
- ✅ SQL Injection: Prepared statements partout

---

## 🎯 Fonctionnalités principales

1. **Alertes email** → Sorties/événements publiés
2. **Opt-out** → Utilisateurs peuvent se désinscrire
3. **Dashboard admin** → Stats, logs, feedback utilisateurs
4. **Tracking** → Détail per-user (sent/failed/skipped)
5. **Templates HTML** → Professionnels avec dégradé bleu
6. **Error handling** → Gracieux avec logs détaillés

---

## 🎬 Quick start

```bash
# 1. Créer BD
php migrate_event_alerts.php

# 2. Tester envoi
php send_event_alerts.php --event-type=sortie --event-id=1

# 3. Admin dashboard
https://gestnav.clubulmevasion.fr/event_alerts_admin.php
```

---

## 📞 Support

**Questions?** → Consulter `docs/SYSTEM_ALERTES_EMAIL.md`
**Bug?** → Checker `event_alerts_admin.php` → "Détail des envois"
**Feedback?** → Ajouter note dans `event_alert_optouts.notes` (admin)

---

**Version**: 1.3.0
**Date**: 6 décembre 2025
**Statut**: ✅ Prêt pour production

