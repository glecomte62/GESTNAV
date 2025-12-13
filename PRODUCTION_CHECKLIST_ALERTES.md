# 📋 Checklist Production - Système d'Alertes Email

## Pre-Production Setup

### ✅ Phase 1: Préparation BD (1-2 min)
- [ ] Exécuter script migration: `php migrate_event_alerts.php`
- [ ] Vérifier création des 3 tables: `event_alerts`, `event_alert_optouts`, `event_alert_logs`
- [ ] Confirmer dans phpMyAdmin que les tables existent et sont vides

### ✅ Phase 2: Déploiement FTP (5-10 min)
- [ ] Lancer deployment: `bash tools/deploy_ftp.sh`
- [ ] Vérifier tous les fichiers uploadés:
  ```
  ✓ event_alerts_admin.php
  ✓ event_alert_optout.php
  ✓ send_event_alerts.php
  ✓ migrate_event_alerts.php
  ✓ utils/event_alerts_helper.php
  ```
- [ ] Confirmer sur le serveur (FTP): tous les fichiers présents et à jour

### ✅ Phase 3: Tests fonctionnels (10-15 min)

#### 3a) Test dashboard admin
- [ ] Naviguer: `https://gestnav.clubulmevasion.fr/event_alerts_admin.php`
- [ ] Vérifier: Connecté en tant qu'admin
- [ ] Vérifier: 3 onglets visibles et chargent
- [ ] Vérifier: Tables vides (pas d'alertes encore)

#### 3b) Test envoi d'alerte
- [ ] Préparer une sortie publiée (ID=1 par exemple)
- [ ] Exécuter: `php send_event_alerts.php --event-type=sortie --event-id=1`
- [ ] Vérifier output:
  ```
  ✓ Alert ID: 1
  ✓ Envoyés: [nombre] 
  ✓ Échoués: 0 ou peu
  ```
- [ ] Attendre 30 sec, vérifier un utilisateur a reçu l'email
- [ ] Dans inbox: Vérifier template email (header bleu, boutons, footer)

#### 3c) Test dashboard après envoi
- [ ] Rafraîchir: `event_alerts_admin.php`
- [ ] Onglet "Historique": Voir l'alerte listée
- [ ] Onglet "Détail des envois": Voir logs (statut, emails)
- [ ] Vérifier compteurs = nombres d'envois

#### 3d) Test opt-out
- [ ] Dans email reçu, cliquer "Se désinscrire"
- [ ] Accès page: `event_alert_optout.php?token=...`
- [ ] Formulaire visible
- [ ] Remplir: Email + raison optionnelle
- [ ] Soumettre
- [ ] Vérifier: Page affiche "✓ Vous avez été désincrit"
- [ ] Dashboard → "Utilisateurs désinscrits": Voir nouveau désabonnement

#### 3e) Test non-envoi à opt-out
- [ ] Relancer: `php send_event_alerts.php --event-type=sortie --event-id=2`
- [ ] Vérifier output: `Ignorés (optout): [x]`
- [ ] Dashboard → Détail des envois: Status "⊘ Ignoré" pour user désincrit

### ✅ Phase 4: Performance & Load (5 min)
- [ ] Tester avec 50+ users (créer users test si nécessaire)
- [ ] Exécuter: `php send_event_alerts.php --event-type=sortie --event-id=3`
- [ ] Vérifier: Pas de timeout, pas de crash BD
- [ ] Vérifier: Dashboard charge rapidement

### ✅ Phase 5: Edge cases (10 min)

#### Test: Événement inexistant
```bash
php send_event_alerts.php --event-type=sortie --event-id=999
```
- [ ] Vérifier: Message "Événement non trouvé" clair

#### Test: Email invalide dans BD
- [ ] Ajouter user avec email vide
- [ ] Relancer alerte
- [ ] Vérifier: Skip gracieusement (pas d'erreur)

#### Test: Paramètres invalides
```bash
php send_event_alerts.php --event-type=invalid --event-id=1
```
- [ ] Vérifier: Message "event_type doit être..." clair

#### Test: SMTP down
- [ ] Désactiver/arrêter SMTP temporairement
- [ ] Lancer alerte
- [ ] Vérifier: Emails listés comme "✗ Échoué" en BD
- [ ] Message d'erreur SMTP visible dans logs

---

## Post-Production Monitoring

### 📊 Monitoring quotidien
- [ ] Vérifier dashboard: Pas d'alertes "échoués" massives
- [ ] Vérifier table `event_alert_logs`: Pas de patterns d'erreur répétitifs
- [ ] Vérifier opt-outs: Alerter si > 5% de la base

### 🔔 Alertes à mettre en place
- [ ] Log STDERR si > 10% d'emails échouent
- [ ] Notification Slack si table `event_alert_optouts` > 50 entries
- [ ] Alerte mensuelle: Statistiques opt-outs vs totaux

### 📚 Documentation à communiquer
- [ ] Share `QUICKSTART_ALERTES.md` aux admins
- [ ] Share `docs/SYSTEM_ALERTES_EMAIL.md` aux développeurs
- [ ] Ajouter lien dashboard au runbook admin

---

## Rollback Plan (si problème)

Si emails non reçus après déploiement:

1. Vérifier connexion BD: `SELECT COUNT(*) FROM event_alerts;`
2. Vérifier `mail_helper.php` unchanged
3. Re-exécuter migration: `php migrate_event_alerts.php`
4. Tester petit envoi: `php send_event_alerts.php --event-type=sortie --event-id=1`
5. Si toujours KO: Contacter support email provider

Si dashboard down:

1. Vérifier file uploadé: `ls -lh event_alerts_admin.php`
2. Vérifier permissions: `chmod 644 event_alerts_admin.php`
3. Vérifier error.log server pour PHP syntax errors
4. Si besoin: Re-déployer depuis archive locale

---

## Sign-off

```
Production Deployment Checklist v1.0
System: Email Alerts for Published Events
Date Deployed: ____________________
Deployed By: ______________________
Tested By: ________________________

All phases completed: ☐ YES ☐ NO

Issues found:
...

Ready for production: ☐ YES ☐ NO

Sign-off: _________________________ (Admin)
```

---

## Support Contact

- **For questions**: See `docs/SYSTEM_ALERTES_EMAIL.md`
- **For bugs**: Check `event_alerts_admin.php` logs
- **For feedback**: Add notes in `event_alert_optouts.notes` BD column

