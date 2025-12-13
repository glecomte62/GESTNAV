# 🚀 Quick-Start: Système d'Alertes Email

## 📦 Fichiers créés (5 fichiers - 37 KB total)

```
migrate_event_alerts.php       (2.6K)  → Crée les tables BD
send_event_alerts.php          (3.2K)  → CLI pour déclencher alertes
event_alert_optout.php         (8.1K)  → Page désinscription
event_alerts_admin.php        (15.0K)  → Dashboard admin
utils/event_alerts_helper.php  (8.1K)  → Cœur du système
```

---

## 🎬 Setup en 3 étapes

### Étape 1️⃣: Créer les tables BD (une seule fois)

```bash
php migrate_event_alerts.php
```

✅ **Résultat attendu** :
```
✓ Create event_alerts table
✓ Create event_alert_optouts table
✓ Create event_alert_logs table

=== Résumé ===
Exécutées: 3
Erreurs: 0
```

### Étape 2️⃣: Déployer les fichiers

```bash
bash tools/deploy_ftp.sh 2>&1 | grep -E "(event_alert|send_event|migrate_event)"
```

✅ **Résultat attendu** :
```
==> Upload event_alerts_admin.php vers ftp://...
OK: event_alerts_admin.php
==> Upload event_alert_optout.php vers ftp://...
OK: event_alert_optout.php
... etc ...
```

### Étape 3️⃣: Tester l'envoi

```bash
php send_event_alerts.php --event-type=sortie --event-id=1
```

✅ **Résultat attendu** :
```
Envoi des alertes pour: Sortie ULM à Issoire
Type: sortie
ID: 1
URL: https://gestnav.clubulmevasion.fr/sortie_info.php?id=1

=== Résultats ===
Alert ID: 1
Envoyés: 25
Échoués: 0
Ignorés (optout): 2

Alertes envoyées avec succès !
```

---

## 🌐 Accès aux pages

| URL | Rôle | Description |
|-----|------|-------------|
| `/event_alerts_admin.php` | **Admin** | Dashboard (3 onglets) |
| `/event_alert_optout.php?token=...` | **Public** | Page désinscription |

---

## 🎨 Dashboard Admin - Vue d'ensemble

### 📊 Onglet 1: Historique des alertes
```
Total alertes envoyées:  3
Emails envoyés:         68
Emails échoués:          1

Date      | Type   | Titre                    | Destinataires | ✓ Envoyés | ✗ Échoués
--------  |--------|--------------------------|---------------|-----------|----------
06/12 14h | Sortie | Sortie ULM à Issoire    | 25            | 25        | 0
06/12 10h | Evento | Grand meeting ULM 2025  | 24            | 23        | 1
```

### 📋 Onglet 2: Utilisateurs désinscrits
```
Total désinscrits: 2

Nom              | Email              | Désinscrit le | Raison                    | Notes admin
-----------------|-------------------|---------------|---------------------------|-------------
Jean Dupont      | jean@email.com     | 06/12 15h     | Trop d'emails            | A relancer Q1 2026
Marie Martin     | marie@email.com    | 05/12 18h     | Changement mail préféré   | —
```

### 📝 Onglet 3: Détail des envois
```
Envoyés: 68  |  Échoués: 1  |  Ignorés (optout): 2

Date      | Utilisateur      | Email              | Alerte              | Statut    | Message d'erreur
--------  |------------------|-------------------|---------------------|-----------|------------------
06/12 14h | Pierre Lenoir    | pierre@mail.com    | Sortie Issoire      | ✓ Envoyé  | —
06/12 14h | Anne Sophie      | anne@mail.fr       | Sortie Issoire      | ✓ Envoyé  | —
06/12 14h | Marc Olivier     | marc@mail.com      | Sortie Issoire      | ✗ Échoué  | SMTP timeout
```

---

## 📧 Workflow opt-out utilisateur

```
1. Utilisateur reçoit email avec lien de désinscription
   ↓
2. Clique sur lien → /event_alert_optout.php?token=...
   ↓
3. Voit formulaire de confirmation
   ├─ Email (required)
   └─ Raison (optional)
   ↓
4. Clique "Se désinscrire"
   ↓
5. Confirmation: "✓ Vous avez été désincrit avec succès"
   ↓
6. Plus aucune alerte reçue
   ↓
7. Admin voit le désabonnement dans le dashboard
```

---

## 🔐 Sécurité

✅ Tokens: 64 hex chars uniques
✅ Admin only: Dashboard protégé `require_admin()`
✅ Opt-out irrévocable sauf contact admin
✅ Pas de données sensibles dans emails

---

## 🐛 Troubleshooting rapide

| Problème | Solution |
|----------|----------|
| "Erreur: Événement non trouvé" | Vérifier ID sortie/événement correct |
| "0 emails envoyés" | Vérifier users avec emails valides en BD |
| "Erreur gestnav_send_mail()" | Vérifier config SMTP dans `mail_helper.php` |
| Page opt-out vierge | Vérifier `event_alert_optout.php` déployé |

---

## 📚 Docs complètes

- **Installation détaillée**: `docs/SYSTEM_ALERTES_EMAIL.md`
- **Implémentation**: `IMPLEMENTATION_ALERTES_EMAIL.md`
- **Changelog**: `CHANGELOG.md` (v1.3.0)

---

## ✨ Prochaines étapes

**Auto-trigger sur publication** (optionnel):

Dans `sorties_edit.php` après changement de statut:

```php
if ($new_status !== 'en étude') {
    require_once 'utils/event_alerts_helper.php';
    gestnav_send_event_alert($pdo, 'sortie', $sortie_id, $event_data, $event_url);
}
```

---

**🎉 Système prêt à l'emploi !**

