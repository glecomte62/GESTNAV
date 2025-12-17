# Demande d'installation de pdftotext sur O2Switch

## 📧 Support O2Switch
URL: https://www.o2switch.fr/support
Login: Utilise tes identifiants O2Switch

---

## 📝 Message à copier-coller dans le ticket

**Objet du ticket:** Installation de poppler-utils (pdftotext)

**Message:**

```
Bonjour,

Je souhaiterais installer le package "poppler-utils" sur mon hébergement pour mon application de gestion documentaire.

Détails techniques :
- Package requis : poppler-utils
- Commande d'installation : yum install poppler-utils
- Outil nécessaire : pdftotext
- Domaine concerné : gestnav.clubulmevasion.fr

Usage :
Ce package est nécessaire pour extraire le texte des fichiers PDF uploadés 
par les utilisateurs dans mon application de gestion. L'outil pdftotext est 
l'utilitaire standard pour cette tâche.

Commande de vérification après installation :
pdftotext -v

Merci d'avance pour votre aide !

Cordialement
```

---

## ✅ Après installation

Une fois que le support O2Switch confirme l'installation, il suffira de :

1. Retourner sur https://gestnav.clubulmevasion.fr/test_extraction.php
2. Uploader à nouveau une facture Starlink
3. L'extraction fonctionnera automatiquement avec pdftotext

Le système détectera automatiquement que pdftotext est disponible et l'utilisera 
en priorité sur les autres méthodes.

---

## 📊 Délai estimé

Le support O2Switch est généralement très réactif :
- Réponse : quelques heures
- Installation : même jour dans la plupart des cas

---

## 🔄 Alternative temporaire

En attendant l'installation, tu peux saisir manuellement la date et le montant 
lors de l'upload des documents dans documents_admin.php
