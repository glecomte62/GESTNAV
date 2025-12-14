# 🚀 Classification Automatique - Démarrage Rapide

## ✅ Installation en 3 étapes

### 1. Exécuter la migration
```
https://gestnav.clubulmevasion.fr/setup/migrate_document_classification.php
```
Cela crée la table et ajoute 10 règles par défaut.

### 2. Tester l'upload
1. Allez sur [documents_admin.php](https://gestnav.clubulmevasion.fr/documents_admin.php)
2. Uploadez un document (facture, assurance, etc.)
3. Le système l'analysera automatiquement ✨

### 3. Gérer les règles (optionnel)
[classification_rules.php](https://gestnav.clubulmevasion.fr/classification_rules.php)

---

## 🎯 Ce qui fonctionne MAINTENANT

### Sans outils externes
- ✅ **PDF textuels** via bibliothèque PHP
- ✅ **DOCX** via ZIP
- ✅ **TXT** directement
- ✅ Extraction de dates, immatriculations, montants
- ✅ Classification selon 10 règles

### Avec outils système (recommandé)
- ⭐ **pdftotext** - Meilleure extraction PDF
- ⭐ **tesseract** - OCR pour images et PDF scannés
- ⭐ **ImageMagick** - Conversion PDF→images

---

## 📊 Règles pré-configurées

| Type | Détecte | Catégorie |
|------|---------|-----------|
| Facture | facture, invoice, montant | Factures |
| Assurance | assurance, police | Assurances |
| Certificat | certificat navigabilité | Certificats |
| Carnet vol | log book, heures vol | Carnets de vol |
| Manuel | manuel, guide | Manuels |
| PV | procès-verbal | Administratif |
| Révision | entretien, maintenance | Entretien |
| Devis | devis, estimation | Factures |

---

## 🧪 Test rapide

1. **Uploadez une facture PDF**
   - Le système détecte "facture" dans le texte
   - Extrait le montant et la date
   - Classe dans "Factures" automatiquement

2. **Uploadez un certificat de navigabilité**
   - Détecte "certificat" + "navigabilité"
   - Extrait la date de validité
   - Trouve l'immatriculation (ex: F-ABCD)
   - Associe la machine automatiquement
   - Classe dans "Certificats"

---

## 📈 Améliorer la précision

### Installer les outils (serveur Linux/Mac)

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install poppler-utils tesseract-ocr tesseract-ocr-fra imagemagick

# macOS
brew install poppler tesseract tesseract-lang imagemagick
```

### Vérifier l'installation

```bash
pdftotext --version
tesseract --version
convert --version
```

---

## ⚙️ Personnaliser

### Ajouter une règle

1. Allez sur [classification_rules.php](https://gestnav.clubulmevasion.fr/classification_rules.php)
2. Cliquez "➕ Nouvelle règle"
3. Configurez :
   - Nom : "Mon type de document"
   - Mots-clés obligatoires : `mot1|mot2`
   - Priorité : 80
   - Exigences : cochez si besoin

### Exemple : Détecter les bons de livraison

```
Nom: Bon de livraison
Catégorie: Factures
Mots-clés obligatoires: bon.*livraison|delivery.*note
Mots-clés optionnels: livraison,delivery,expédition,shipping
Priorité: 85
Nécessite une date: ✓
```

---

## 🔍 Données extraites automatiquement

Pour chaque document uploadé :
- 📅 **Dates** (toutes les dates trouvées)
- ✈️ **Immatriculations** (F-ABCD, etc.)
- 🔢 **Numéros de série** (SN12345)
- 💰 **Montants** (1234,56 €)
- 📧 **Emails**
- ☎️ **Téléphones**

Ces données sont utilisées pour :
- Suggérer la catégorie
- Associer une machine
- Remplir la date du document
- Générer des tags de recherche

---

## 💡 Astuces

### Nommage des fichiers
- Les noms originaux sont préservés
- Pas besoin de renommer avant upload
- Le contenu du document compte plus que le nom

### Formats supportés
- ✅ PDF (texte ou image)
- ✅ JPG, PNG (avec OCR)
- ✅ DOCX
- ✅ TXT

### Performance
- PDF texte : instantané
- PDF image (OCR) : 5-30s selon taille
- DOCX : instantané

---

## 📞 Support

**Documentation complète :** [DOCUMENT_CLASSIFICATION_GUIDE.md](DOCUMENT_CLASSIFICATION_GUIDE.md)

**En cas de problème :**
1. Vérifier que la migration a réussi
2. Tester avec un PDF simple
3. Consulter les logs dans documents_admin.php

---

**Prêt à tester ?** → [Uploader un document](https://gestnav.clubulmevasion.fr/documents_admin.php)

---

*Système créé le 14 décembre 2025*
