# 📚 Index de documentation - Système d'Alertes Email

## 🎯 Par rôle - Aller directement à votre doc

### 👤 Je suis **Admin**
**Temps de lecture: 10 minutes**

1. **Démarrage rapide**: [QUICKSTART_ALERTES.md](QUICKSTART_ALERTES.md)
   - Les 3 étapes pour commencer
   - Accès au dashboard
   - Premiers pas

2. **Utilisation quotidienne**:
   - Voir historique des alertes: `event_alerts_admin.php` → Onglet "Historique"
   - Voir qui se retire: `event_alerts_admin.php` → Onglet "Désinscrits"
   - Analyser erreurs: `event_alerts_admin.php` → Onglet "Détail des envois"

3. **Questions?** → [Troubleshooting dans SYSTEM_ALERTES_EMAIL.md](docs/SYSTEM_ALERTES_EMAIL.md#troubleshooting)

---

### 👨‍💻 Je suis **Développeur**
**Temps de lecture: 30-45 minutes**

1. **Architecture globale**: [ARCHITECTURE_ALERTES.md](ARCHITECTURE_ALERTES.md)
   - Diagrammes du système
   - Flux de traitement
   - Intégration

2. **Documentation technique**: [docs/SYSTEM_ALERTES_EMAIL.md](docs/SYSTEM_ALERTES_EMAIL.md)
   - Installation détaillée
   - Schémas BD complets
   - Fonctions disponibles
   - Exemples d'intégration

3. **Détails implémentation**: [IMPLEMENTATION_ALERTES_EMAIL.md](IMPLEMENTATION_ALERTES_EMAIL.md)
   - Ce qui a été créé
   - Comment ça fonctionne
   - Sécurité
   - Workflow utilisateur

4. **Code source**: 
   - Cœur: `utils/event_alerts_helper.php` (consulter pour API)
   - CLI: `send_event_alerts.php` (exemples d'usage)
   - Admin: `event_alerts_admin.php` (UI reference)

---

### 🚀 Je **déploie en production**
**Temps de lecture: 45-60 minutes + checklist**

1. **Checklist complète**: [PRODUCTION_CHECKLIST_ALERTES.md](PRODUCTION_CHECKLIST_ALERTES.md)
   - 5 phases: BD, FTP, Tests, Performance, Edge cases
   - À cocher case par case
   - Sign-off final

2. **Fichiers à déployer**: [MANIFEST_ALERTES.md](MANIFEST_ALERTES.md#livrables)
   - Liste complète des 10 fichiers
   - Tailles, rôles, localisations
   - Modification faites aux fichiers existants

3. **Points d'intégration**: [MANIFEST_ALERTES.md](MANIFEST_ALERTES.md#-points-dintégration)
   - Auto-trigger (optionnel)
   - Cron jobs (optionnel)
   - Menu admin links

---

### 🔧 Je **maintiens le système**
**Référence rapide**

**Liens utiles**:
- Dashboard admin: `/event_alerts_admin.php`
- Opt-out page: `/event_alert_optout.php`
- DB tables: `event_alerts`, `event_alert_optouts`, `event_alert_logs`

**Monitoring checklist**:
- [ ] Vérifier dashboard quotidien (pas d'erreurs massives)
- [ ] Monitor opt-outs (alerte si > 5%)
- [ ] Vérifier logs errors (table `event_alert_logs`)

**Troubleshooting**: [SYSTEM_ALERTES_EMAIL.md → Troubleshooting](docs/SYSTEM_ALERTES_EMAIL.md#troubleshooting)

---

## 📑 Index complet de tous les documents

| Document | Type | Audience | Durée | Lien |
|----------|------|----------|-------|------|
| **QUICKSTART_ALERTES.md** | Guide | Admin | 5 min | [→](QUICKSTART_ALERTES.md) |
| **SYSTEM_ALERTES_EMAIL.md** | Tech ref | Dev | 30 min | [→](docs/SYSTEM_ALERTES_EMAIL.md) |
| **ARCHITECTURE_ALERTES.md** | Diagrammes | Dev/Tech | 20 min | [→](ARCHITECTURE_ALERTES.md) |
| **IMPLEMENTATION_ALERTES_EMAIL.md** | Détails | Dev | 25 min | [→](IMPLEMENTATION_ALERTES_EMAIL.md) |
| **PRODUCTION_CHECKLIST_ALERTES.md** | Checklist | DevOps | Checklist | [→](PRODUCTION_CHECKLIST_ALERTES.md) |
| **MANIFEST_ALERTES.md** | Listing | Tech | 10 min | [→](MANIFEST_ALERTES.md) |
| **INDEX_ALERTES.md** | Nav | Tous | 5 min | 👈 Vous êtes ici |

---

## 🔍 Recherche par sujet

### Installation & Setup
- [QUICKSTART_ALERTES.md](QUICKSTART_ALERTES.md) - 3 étapes
- [PRODUCTION_CHECKLIST_ALERTES.md](PRODUCTION_CHECKLIST_ALERTES.md) - Phase 1
- [docs/SYSTEM_ALERTES_EMAIL.md](docs/SYSTEM_ALERTES_EMAIL.md#installation) - Détails

### Configuration BD
- [MANIFEST_ALERTES.md](MANIFEST_ALERTES.md#-bases-de-données-créées-3-tables) - Schéma
- [docs/SYSTEM_ALERTES_EMAIL.md](docs/SYSTEM_ALERTES_EMAIL.md#bases-de-données) - Détails colonnes
- [migrate_event_alerts.php](migrate_event_alerts.php) - SQL brut

### Utilisation
- [QUICKSTART_ALERTES.md](QUICKSTART_ALERTES.md#-étapes-de-setup) - Étapes simples
- [docs/SYSTEM_ALERTES_EMAIL.md](docs/SYSTEM_ALERTES_EMAIL.md#utilisation) - Usage avancé
- [send_event_alerts.php](send_event_alerts.php) - CLI examples

### Dashboard Admin
- [QUICKSTART_ALERTES.md](QUICKSTART_ALERTES.md#-dashboard-admin---vue-densemble) - Aperçu
- [event_alerts_admin.php](event_alerts_admin.php) - Code source
- [ARCHITECTURE_ALERTES.md](ARCHITECTURE_ALERTES.md) - Diagrammes UI

### Opt-out utilisateur
- [QUICKSTART_ALERTES.md](QUICKSTART_ALERTES.md#-workflow-opt-out-utilisateur) - Flux
- [event_alert_optout.php](event_alert_optout.php) - Code source
- [ARCHITECTURE_ALERTES.md](ARCHITECTURE_ALERTES.md) - Diagrammes

### Sécurité
- [docs/SYSTEM_ALERTES_EMAIL.md](docs/SYSTEM_ALERTES_EMAIL.md#sécurité) - Détails sécurité
- [IMPLEMENTATION_ALERTES_EMAIL.md](IMPLEMENTATION_ALERTES_EMAIL.md#-sécurité) - Checklist
- [utils/event_alerts_helper.php](utils/event_alerts_helper.php) - Implémentation

### Troubleshooting
- [docs/SYSTEM_ALERTES_EMAIL.md](docs/SYSTEM_ALERTES_EMAIL.md#troubleshooting) - Solutions
- [QUICKSTART_ALERTES.md](QUICKSTART_ALERTES.md#-troubleshooting-rapide) - Quick fixes
- [PRODUCTION_CHECKLIST_ALERTES.md](PRODUCTION_CHECKLIST_ALERTES.md#rollback-plan) - Rollback

### Architecture & Technique
- [ARCHITECTURE_ALERTES.md](ARCHITECTURE_ALERTES.md) - Diagrammes détaillés
- [IMPLEMENTATION_ALERTES_EMAIL.md](IMPLEMENTATION_ALERTES_EMAIL.md) - Structure
- [MANIFEST_ALERTES.md](MANIFEST_ALERTES.md) - Fichiers & integration

---

## 🎬 Workflows courants

### "Je veux envoyer une alerte"
1. Lire: [QUICKSTART_ALERTES.md](QUICKSTART_ALERTES.md) (5 min)
2. Exécuter: `php send_event_alerts.php --event-type=sortie --event-id=9`
3. Vérifier: Dashboard → [event_alerts_admin.php](event_alerts_admin.php)

### "Je dois déployer en production"
1. Lire: [PRODUCTION_CHECKLIST_ALERTES.md](PRODUCTION_CHECKLIST_ALERTES.md)
2. Cocher cases Phase 1-5
3. Signer le sign-off

### "J'intègre avec sorties_edit.php"
1. Lire: [IMPLEMENTATION_ALERTES_EMAIL.md](IMPLEMENTATION_ALERTES_EMAIL.md#-intégration-avec-sorties_detailphp)
2. Consulter: [ARCHITECTURE_ALERTES.md](ARCHITECTURE_ALERTES.md#intégration-avec-système-existant)
3. Code exemple dans [docs/SYSTEM_ALERTES_EMAIL.md](docs/SYSTEM_ALERTES_EMAIL.md#intégration-avec-sorties_detail)

### "Quelque chose ne marche pas"
1. Consulter: [docs/SYSTEM_ALERTES_EMAIL.md](docs/SYSTEM_ALERTES_EMAIL.md#troubleshooting)
2. Checker: [event_alerts_admin.php](event_alerts_admin.php) → "Détail des envois"
3. Si besoin: Logs serveur ou test envoi manuel

---

## 📞 Support rapide

**Question type** | **Réponse rapide** | **Lien détail**
---|---|---
"Comment ça marche?" | 3 étapes → QUICKSTART | [QUICKSTART_ALERTES.md](QUICKSTART_ALERTES.md)
"Où est le dashboard?" | `/event_alerts_admin.php` | [→](event_alerts_admin.php)
"Pourquoi emails ne partent pas?" | Voir logs admin onglet 3 | [→](event_alerts_admin.php)
"Comment se désinscrire?" | Lien dans email ou `/event_alert_optout.php` | [→](event_alert_optout.php)
"Authentification requise?" | Oui, admin only sur dashboard | [→](docs/SYSTEM_ALERTES_EMAIL.md#sécurité)
"Quels utilisateurs reçoivent?" | Tous sauf opt-out | [→](ARCHITECTURE_ALERTES.md#flux-de-traitement-détaillé)

---

## ✅ Checklist lecture

- [ ] Lis ta doc selon ton rôle (5-30 min)
- [ ] Teste localement: `php send_event_alerts.php --event-type=sortie --event-id=1` (5 min)
- [ ] Accède au dashboard: `/event_alerts_admin.php` (2 min)
- [ ] Enregistre les 3 liens utiles:
  - Dashboard: `/event_alerts_admin.php`
  - Opt-out: `/event_alert_optout.php`
  - Docs: Ce fichier

---

**Version**: 1.3.0 | **Date**: 6 décembre 2025 | **Statut**: ✅ Prêt production

