# 📚 Programme de Formation Administrateurs GESTNAV
## Club ULM Évasion - Formation Complète

---

## 🎯 Objectifs de la Formation

À l'issue de cette formation, les administrateurs seront capables de :
- ✅ Gérer l'ensemble des membres du club
- ✅ Organiser et planifier des sorties ULM
- ✅ Administrer les machines et leur affectation
- ✅ Créer et gérer des événements
- ✅ Communiquer efficacement avec les membres
- ✅ Gérer les documents et rapports
- ✅ Configurer et personnaliser l'application
- ✅ Résoudre les problèmes courants

---

## 📋 Table des Matières

1. [Module 1 - Introduction (30 min)](#module-1---introduction)
2. [Module 2 - Gestion des Membres (1h30)](#module-2---gestion-des-membres)
3. [Module 3 - Gestion des Sorties (2h)](#module-3---gestion-des-sorties)
4. [Module 4 - Gestion des Machines (1h)](#module-4---gestion-des-machines)
5. [Module 5 - Événements (1h)](#module-5---événements)
6. [Module 6 - Communication & Emails (1h30)](#module-6---communication--emails)
7. [Module 7 - Sondages (45 min)](#module-7---sondages)
8. [Module 8 - Documents & Rapports (45 min)](#module-8---documents--rapports)
9. [Module 9 - Configuration & Paramètres (1h)](#module-9---configuration--paramètres)
10. [Module 10 - Maintenance & Dépannage (45 min)](#module-10---maintenance--dépannage)

**Durée totale estimée : 11h15**

---

## Module 1 - Introduction
**⏱️ Durée : 30 minutes**

### 1.1 Présentation de GESTNAV (10 min)
- Qu'est-ce que GESTNAV ?
- Architecture de l'application
- Les différents rôles (Admin, Membre)
- Tour d'horizon de l'interface

### 1.2 Connexion et Navigation (10 min)
- Se connecter à l'interface admin
- Menu principal et navigation
- Tableau de bord administrateur
- Raccourcis clavier et astuces

### 1.3 Philosophie et Bonnes Pratiques (10 min)
- Principes de gestion du club
- Rôle et responsabilités des admins
- Sécurité et confidentialité
- Support et aide

**📝 Exercice pratique :**
- Connexion et exploration de l'interface
- Navigation dans les différentes sections

---

## Module 2 - Gestion des Membres
**⏱️ Durée : 1h30**

### 2.1 Liste des Membres (20 min)
- Accéder à la liste des membres (`membres.php`)
- Filtrage et recherche
- Export des données
- Comprendre les statuts de membres

### 2.2 Ajouter un Membre (20 min)
- Processus d'inscription manuelle
- Champs obligatoires vs optionnels
- Création des identifiants
- Envoi de l'email de bienvenue

### 2.3 Éditer un Membre (25 min)
- Modifier les informations personnelles
- Gestion des qualifications pilote
- Upload et recadrage de photo
- Gestion des autorisations

### 2.4 Gestion des Pré-inscriptions (25 min)
- Interface `preinscriptions_admin.php`
- Accepter une pré-inscription
- Refuser une pré-inscription
- Traçabilité des actions

**📝 Exercices pratiques :**
1. Créer un membre test
2. Modifier ses qualifications
3. Traiter une pré-inscription
4. Exporter la liste des membres

**📚 Ressources :**
- `membres.php` - Liste des membres
- `editer_membre.php` - Édition détaillée
- `preinscriptions_admin.php` - Gestion des demandes

---

## Module 3 - Gestion des Sorties
**⏱️ Durée : 2h**

### 3.1 Vue d'Ensemble des Sorties (20 min)
- Page `sorties.php` - Vue membre
- Filtres et recherche
- Statuts des sorties (prévue, en étude, terminée, annulée)
- Calendrier des sorties

### 3.2 Créer une Sortie (30 min)
- Formulaire de création
- Choix de la destination (aérodrome vs ULM base)
- Définition des places disponibles
- Gestion multi-jours
- Options : repas, activités, hébergement
- Publication immédiate vs brouillon

### 3.3 Gérer les Inscriptions (30 min)
- Page `inscriptions_admin.php`
- Voir les inscrits
- Affectation pilote/passager
- Gestion de la liste d'attente
- Changement de coéquipier
- Changement de machine

### 3.4 Propositions de Sorties (25 min)
- `sortie_proposals_admin.php`
- Examiner les propositions des membres
- Accepter/Refuser une proposition
- Créer une sortie depuis une proposition
- Notifier le membre

### 3.5 Clôture et Bilan (15 min)
- Marquer une sortie comme terminée
- Ajouter un compte-rendu
- Photos et documentation
- Statistiques

**📝 Exercices pratiques :**
1. Créer une sortie complète
2. Gérer les inscriptions et affectations
3. Traiter une proposition de membre
4. Clôturer une sortie avec bilan

**📚 Ressources :**
- `sorties.php` - Liste des sorties
- `sortie_detail.php` - Détail et gestion
- `propose_sortie.php` - Propositions membres
- `sortie_proposals_admin.php` - Gestion des propositions

---

## Module 4 - Gestion des Machines
**⏱️ Durée : 1h**

### 4.1 Liste des Machines (15 min)
- Page `machines.php`
- Types de machines
- Statut (disponible, maintenance, hors service)
- Propriétaires multiples

### 4.2 Ajouter/Éditer une Machine (20 min)
- Formulaire de création
- Caractéristiques techniques
- Upload de photos
- Définir la disponibilité

### 4.3 Gestion des Propriétaires (15 min)
- `machines_owners_admin.php`
- Ajouter/Retirer un propriétaire
- Droits et permissions
- Partage de machine

### 4.4 Affectation aux Sorties (10 min)
- Lier machines et pilotes
- Changement de machine en cours de sortie
- Historique des affectations

**📝 Exercices pratiques :**
1. Ajouter une nouvelle machine
2. Gérer les propriétaires
3. Consulter l'historique

**📚 Ressources :**
- `machines.php` - Gestion des machines
- `machines_owners_admin.php` - Propriétaires

---

## Module 5 - Événements
**⏱️ Durée : 1h**

### 5.1 Types d'Événements (10 min)
- Événements vs Sorties
- Types : formation, social, maintenance, réunion
- Cas d'usage

### 5.2 Créer un Événement (20 min)
- `evenements_admin.php`
- Formulaire de création
- Date, heure, lieu
- Nombre de places
- Date limite d'inscription

### 5.3 Gérer les Inscriptions (15 min)
- Voir les participants
- Validation/Refus des inscriptions
- Liste d'attente
- Notifications

### 5.4 Alertes et Rappels (15 min)
- Système d'alertes automatiques
- Configuration des rappels
- Emails de notification

**📝 Exercices pratiques :**
1. Créer un événement formation
2. Gérer les inscriptions
3. Envoyer des rappels

**📚 Ressources :**
- `evenements_admin.php` - Liste et gestion
- `evenement_detail.php` - Détails
- `send_event_alerts.php` - Système d'alertes

---

## Module 6 - Communication & Emails
**⏱️ Durée : 1h30**

### 6.1 Système d'Emails (20 min)
- Configuration SMTP (`config_mail.php`)
- Test de configuration
- Résolution des problèmes d'envoi

### 6.2 Envoyer des Emails (30 min)
- `envoyer_email.php` - Interface d'envoi
- Sélection des destinataires (groupes, rôles)
- Rédaction HTML
- Personnalisation (variables)
- Pièces jointes

### 6.3 Historique des Emails (15 min)
- `historique_emails.php`
- Consulter les emails envoyés
- Voir les destinataires
- Statistiques de délivrabilité

### 6.4 Templates et Communication Type (15 min)
- Emails de bienvenue
- Rappels de sortie
- Newsletter du club
- Bonnes pratiques rédactionnelles

### 6.5 Gestion des Contacts (10 min)
- Annuaire (`annuaire.php`)
- Export des contacts
- Respect RGPD

**📝 Exercices pratiques :**
1. Envoyer un email à tous les pilotes
2. Créer un template de newsletter
3. Consulter l'historique

**📚 Ressources :**
- `envoyer_email.php` - Envoi d'emails
- `historique_emails.php` - Historique
- `config_mail.php` - Configuration SMTP
- `EMAIL_SYSTEM_DOCUMENTATION.md` - Documentation complète

---

## Module 7 - Sondages
**⏱️ Durée : 45 minutes**

### 7.1 Types de Sondages (10 min)
- Sondages de date
- Choix multiple
- Autoriser plusieurs choix

### 7.2 Créer un Sondage (15 min)
- `sondages_admin.php`
- Titre et description
- Ajouter les options
- Date de clôture
- Publication

### 7.3 Gérer un Sondage (10 min)
- Éditer les options
- Ajouter/Supprimer des options
- Protection des options avec votes
- Clôturer un sondage

### 7.4 Résultats et Analyse (10 min)
- `sondages_detail.php`
- Voir les votes
- Graphiques et statistiques
- Export des résultats

**📝 Exercices pratiques :**
1. Créer un sondage de date pour une sortie
2. Ajouter une option après création
3. Analyser les résultats

**📚 Ressources :**
- `sondages_admin.php` - Gestion
- `sondages_detail.php` - Résultats
- `POLLS_DOCUMENTATION.md` - Documentation complète

---

## Module 8 - Documents & Rapports
**⏱️ Durée : 45 minutes**

### 8.1 Gestion des Documents (20 min)
- `documents_admin.php`
- Upload de documents
- Classification automatique
- Organisation par type
- Gestion des versions

### 8.2 Logs et Traçabilité (15 min)
- `logs_connexions.php` - Connexions
- `logs_operations.php` - Actions admin
- `logs_affectations.php` - Historique sorties
- `logs_documents.php` - Documents

### 8.3 Statistiques et Rapports (10 min)
- Statistiques du club
- Rapports d'activité
- Export des données

**📝 Exercices pratiques :**
1. Uploader et classifier un document
2. Consulter les logs de connexion
3. Générer un rapport mensuel

**📚 Ressources :**
- `documents_admin.php` - Gestion documents
- `logs_*.php` - Différents logs
- `DOCUMENT_CLASSIFICATION_GUIDE.md` - Classification

---

## Module 9 - Configuration & Paramètres
**⏱️ Durée : 1h**

### 9.1 Configuration Générale (20 min)
- `config_generale.php`
- Informations du club
- Personnalisation (logo, couleurs)
- Base ULM par défaut

### 9.2 Configuration Email (15 min)
- `config_mail.php`
- Paramètres SMTP
- Test de configuration
- Résolution des problèmes

### 9.3 Aérodromes et Bases ULM (15 min)
- `aerodromes_admin.php`
- Ajouter une destination
- Import depuis API
- Gestion des favoris

### 9.4 Modules Optionnels (10 min)
- Activer/Désactiver des modules
- Événements
- Sondages
- Documents

**📝 Exercices pratiques :**
1. Personnaliser les informations du club
2. Configurer SMTP
3. Ajouter un aérodrome

**📚 Ressources :**
- `config_generale.php` - Configuration générale
- `config_mail.php` - Configuration email
- `aerodromes_admin.php` - Destinations
- `GUIDE_PERSONNALISATION.md` - Guide complet

---

## Module 10 - Maintenance & Dépannage
**⏱️ Durée : 45 minutes**

### 10.1 Diagnostic et Tests (15 min)
- `diagnostic.php` - Page de diagnostic
- Vérification de la configuration
- Tests de connexion DB
- Tests d'envoi d'emails

### 10.2 Problèmes Courants (20 min)
- Emails non reçus → Vérifier SMTP, spam
- Photos ne s'affichent pas → Permissions
- Erreurs de connexion → Cache, cookies
- Base de données → Backup et restauration

### 10.3 Support et Documentation (10 min)
- Documentation technique
- Changelog (`CHANGELOG.md`)
- Contacter le support
- Forum communautaire

**📝 Exercices pratiques :**
1. Utiliser la page de diagnostic
2. Vider le cache
3. Consulter le changelog

**📚 Ressources :**
- `diagnostic.php` - Diagnostic système
- `CHANGELOG.md` - Historique des modifications
- Documentation technique

---

## 📊 Évaluation des Connaissances

### Quiz Final (30 questions)

**Gestion des Membres**
1. Comment accepter une pré-inscription ?
2. Quelles sont les qualifications pilote disponibles ?
3. Comment recadrer une photo de profil ?

**Gestion des Sorties**
4. Quelle est la différence entre "prévue" et "en étude" ?
5. Comment gérer la liste d'attente ?
6. Comment créer une sortie multi-jours ?

**Communication**
7. Comment envoyer un email à tous les pilotes ?
8. Où consulter l'historique des emails ?
9. Comment tester la configuration SMTP ?

**Sondages**
10. Peut-on modifier les options après création ?
11. Que se passe-t-il si on supprime une option avec votes ?
12. Comment créer un sondage avec choix multiple ?

**Configuration**
13. Où personnaliser le logo du club ?
14. Comment ajouter un nouvel aérodrome ?
15. Comment activer/désactiver le module événements ?

### Exercice Final Pratique (1h)

**Scénario complet :**
1. Créer une sortie ULM pour le weekend prochain
2. Gérer 5 inscriptions avec affectations
3. Envoyer un email de rappel aux participants
4. Créer un sondage pour choisir la prochaine destination
5. Traiter une pré-inscription et créer le compte
6. Générer un rapport d'activité du mois

---

## 📅 Planning de Formation Suggéré

### Option 1 : Formation Intensive (2 jours)
**Jour 1 (6h)**
- Matin : Modules 1, 2, 3
- Après-midi : Modules 4, 5

**Jour 2 (6h)**
- Matin : Modules 6, 7, 8
- Après-midi : Modules 9, 10 + Évaluation

### Option 2 : Formation Progressive (4 sessions)
**Session 1 (3h)** : Modules 1, 2
**Session 2 (3h)** : Modules 3, 4
**Session 3 (3h)** : Modules 5, 6, 7
**Session 4 (3h)** : Modules 8, 9, 10 + Évaluation

### Option 3 : Auto-formation (flexibilité totale)
- Suivre les modules à son rythme
- Support par email/visio si besoin
- Évaluation finale en ligne

---

## 📚 Ressources Complémentaires

### Documentation Technique
- `README.md` - Vue d'ensemble
- `INSTALLATION.md` - Installation et configuration
- `EMAIL_SYSTEM_DOCUMENTATION.md` - Système d'emails
- `POLLS_DOCUMENTATION.md` - Sondages
- `DOCUMENT_CLASSIFICATION_GUIDE.md` - Classification documents
- `GUIDE_PERSONNALISATION.md` - Personnalisation

### Fichiers de Référence
- `CHANGELOG.md` - Historique des versions
- `DEPLOY.md` - Procédures de déploiement
- `CONTRIBUTING.md` - Guide de contribution

### Support
- 📧 Email : support@gestnav.fr (à personnaliser)
- 💬 Forum : (à créer si besoin)
- 📱 Groupe WhatsApp/Telegram des admins

---

## ✅ Certification

À l'issue de la formation et après réussite de l'évaluation :
- 🎓 Certificat d'Administrateur GESTNAV
- 🔑 Accès au groupe privé des administrateurs
- 📖 Accès à la documentation avancée
- 🆘 Support prioritaire

---

## 🔄 Formation Continue

**Mises à jour :**
- Session de formation trimestrielle (nouvelles fonctionnalités)
- Newsletter mensuelle des admins
- Webinaires sur demande

**Perfectionnement :**
- Astuces et raccourcis
- Optimisation des processus
- Retours d'expérience entre clubs

---

## 📝 Notes pour le Formateur

### Matériel Nécessaire
- ✅ Accès à une instance de test GESTNAV
- ✅ Projecteur/écran partagé
- ✅ Supports de cours imprimés
- ✅ Accès Wi-Fi pour les participants
- ✅ Comptes de test pour les exercices

### Points d'Attention
- ⚠️ Bien séparer les modes "Admin" et "Membre"
- ⚠️ Insister sur la sécurité des données
- ⚠️ Rappeler les bonnes pratiques RGPD
- ⚠️ Prévoir des pauses régulières
- ⚠️ Adapter le rythme au groupe

### Trucs & Astuces Formateur
- 💡 Préparer des scénarios réalistes
- 💡 Encourager les questions
- 💡 Utiliser des exemples concrets du club
- 💡 Faire des démonstrations en live
- 💡 Prévoir des exercices en binôme

---

## 📞 Contact

Pour toute question sur cette formation :
- **Formateur** : [Nom]
- **Email** : [Email]
- **Téléphone** : [Téléphone]

---

**Version du document :** 1.0  
**Dernière mise à jour :** 14 décembre 2025  
**Application :** GESTNAV v2.0.0
