# 📄 Système de Classification Automatique de Documents

## 🎯 Vue d'ensemble

Le système analyse automatiquement les documents uploadés et :
- ✅ Extrait le texte (PDF, images, DOCX)
- ✅ Identifie les informations clés (dates, immatriculations, montants)
- ✅ Classe automatiquement le document
- ✅ Suggère la machine associée
- ✅ Génère des tags de recherche

---

## 📋 Prérequis Serveur

### Outils recommandés (optionnels mais améliorent la précision)

**pdftotext** - Extraction de texte depuis PDF
```bash
# Debian/Ubuntu
sudo apt-get install poppler-utils

# macOS
brew install poppler

# Test
pdftotext --version
```

**Tesseract OCR** - Reconnaissance optique de caractères
```bash
# Debian/Ubuntu
sudo apt-get install tesseract-ocr tesseract-ocr-fra

# macOS
brew install tesseract tesseract-lang

# Test
tesseract --version
```

**ImageMagick** - Conversion PDF en images
```bash
# Debian/Ubuntu
sudo apt-get install imagemagick

# macOS
brew install imagemagick

# Test
convert --version
```

### Libraries PHP (optionnelles)

**smalot/pdfparser** - Parser PDF en PHP pur
```bash
composer require smalot/pdfparser
```

---

## 🚀 Installation

### 1. Exécuter la migration

**Via navigateur :**
```
https://gestnav.clubulmevasion.fr/setup/migrate_document_classification.php
```

**Via terminal :**
```bash
php setup/migrate_document_classification.php
```

### 2. Vérifier les dépendances

```bash
php -r "
echo 'PHP Extensions:\n';
echo '- ZIP: ' . (extension_loaded('zip') ? 'OK' : 'MANQUANT') . '\n';
echo '- PDO: ' . (extension_loaded('pdo') ? 'OK' : 'MANQUANT') . '\n';
echo '\nOutils système:\n';
system('which pdftotext && echo \"- pdftotext: OK\" || echo \"- pdftotext: MANQUANT\"');
system('which tesseract && echo \"- tesseract: OK\" || echo \"- tesseract: MANQUANT\"');
system('which convert && echo \"- ImageMagick: OK\" || echo \"- ImageMagick: MANQUANT\"');
"
```

---

## 📖 Utilisation

### Upload automatique

Lors de l'upload d'un document dans `documents_admin.php` :

1. **Analyse automatique** du contenu
2. **Suggestion de catégorie** (avec score de confiance)
3. **Extraction des métadonnées** :
   - Dates (création, validité, etc.)
   - Immatriculations (format FR)
   - Numéros de série
   - Montants
4. **Association machine** automatique
5. **Génération de tags** pour la recherche

### Règles de classification

Le système utilise 10 règles par défaut :

| Règle | Catégorie | Mots-clés obligatoires |
|-------|-----------|------------------------|
| Facture | Factures | facture, invoice |
| Assurance | Assurances | assurance |
| Certificat | Certificats | certificat + navigabilité/médical |
| Carnet de vol | Carnets de vol | carnet de vol, log book |
| Manuel | Manuels | manuel, guide |
| PV | Administratif | procès-verbal, assemblée |
| Révision | Entretien | révision, maintenance |
| Devis | Factures | devis, estimation |
| Bon de commande | Factures | bon de commande |
| Notice pilote | Manuels | notice pilote, POH |

---

## ⚙️ Architecture

### Classes principales

```php
// 1. Parser - Extraction de texte
$parser = new DocumentParser($file_path);
$parser->parse();
$text = $parser->getText();

// 2. Analyzer - Extraction de métadonnées
$analyzer = new DocumentAnalyzer($text);
$data = $analyzer->analyze();

// 3. Classifier - Classification
$classifier = new DocumentClassifier($pdo, $text, $data);
$result = $classifier->classify();
```

### Données extraites

```php
[
    'dates' => ['2025-12-14', '2026-01-15'],
    'most_recent_date' => '2026-01-15',
    'immatriculations' => ['F-ABCD'],
    'serial_numbers' => ['SN12345'],
    'amounts' => [1234.56, 789.00],
    'total_amount' => 1234.56,
    'emails' => ['contact@example.com'],
    'phones' => ['0123456789']
]
```

### Résultat de classification

```php
[
    'category_id' => 5,
    'category_name' => 'Factures',
    'confidence' => 85.5,
    'matched_rule' => [...],
    'rule_name' => 'Facture'
]
```

---

## 🎨 Personnalisation

### Ajouter une règle de classification

```sql
INSERT INTO document_classification_rules 
(name, category_name, keywords, required_keywords, priority, requires_amount, requires_date)
VALUES 
('Ma règle', 'Ma catégorie', 'mot1,mot2,mot3', 'mot_obligatoire', 80, 0, 1);
```

### Paramètres

- **name** : Nom de la règle
- **category_name** : Nom de la catégorie cible
- **keywords** : Mots-clés optionnels (séparés par virgules)
- **required_keywords** : Pattern regex obligatoire (séparé par |)
- **priority** : 0-100 (plus élevé = prioritaire)
- **requires_amount** : Nécessite un montant
- **requires_date** : Nécessite une date
- **requires_immatriculation** : Nécessite une immatriculation

---

## 🔍 Formats supportés

| Format | Méthode | Précision | Outils requis |
|--------|---------|-----------|---------------|
| PDF texte | pdftotext | ⭐⭐⭐⭐⭐ | poppler-utils |
| PDF texte | PdfParser | ⭐⭐⭐⭐ | Composer |
| PDF image | OCR | ⭐⭐⭐ | tesseract + imagemagick |
| JPG/PNG | OCR | ⭐⭐⭐ | tesseract |
| DOCX | ZIP + XML | ⭐⭐⭐⭐⭐ | PHP ZIP extension |
| TXT | Direct | ⭐⭐⭐⭐⭐ | Aucun |

---

## 🐛 Dépannage

### Pas de texte extrait

1. Vérifier que les outils sont installés :
```bash
which pdftotext tesseract convert
```

2. Test manuel :
```bash
pdftotext document.pdf -
```

3. Vérifier les permissions :
```bash
ls -la uploads/documents/
```

### Classification incorrecte

1. Vérifier les règles actives :
```sql
SELECT * FROM document_classification_rules WHERE active = 1;
```

2. Ajuster la priorité ou les mots-clés

3. Consulter les logs :
```sql
SELECT * FROM document_logs WHERE action = 'upload' ORDER BY created_at DESC LIMIT 10;
```

### Performances

- Les PDF image + OCR sont lents (10-30s par page)
- Utiliser pdftotext pour les PDF textuels (instantané)
- Limiter la taille max des uploads (recommandé : 10 MB)

---

## 📊 Statistiques

Après installation, vous pouvez voir :
- Nombre de documents par catégorie
- Taux de classification automatique
- Documents nécessitant une révision manuelle

---

## 🔐 Sécurité

- ✅ Isolation des fichiers dans `/uploads/documents/`
- ✅ Validation des extensions
- ✅ Logs de toutes les actions
- ✅ Vérification des droits d'accès
- ✅ Nettoyage des noms de fichiers

---

**Date de création :** 14 décembre 2025  
**Version :** 1.0
