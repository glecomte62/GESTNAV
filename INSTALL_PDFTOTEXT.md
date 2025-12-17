# Installation de pdftotext sur le serveur

## 📋 Prérequis
- Accès SSH au serveur
- Droits sudo/root

## 🔧 Installation selon le système

### Ubuntu / Debian
```bash
# Se connecter en SSH
ssh gestnav@kica7829.odns.fr

# Installer poppler-utils (contient pdftotext)
sudo apt-get update
sudo apt-get install -y poppler-utils

# Vérifier l'installation
pdftotext -v
which pdftotext
```

### CentOS / RedHat / AlmaLinux
```bash
# Se connecter en SSH
ssh gestnav@kica7829.odns.fr

# Installer poppler-utils
sudo yum install -y poppler-utils

# Vérifier l'installation
pdftotext -v
which pdftotext
```

### Si pas d'accès sudo (hébergement mutualisé)

Si tu es sur un hébergement mutualisé sans accès root, contacte ton hébergeur pour demander l'installation de `poppler-utils`.

Ou crée un ticket de support avec ce message :

```
Bonjour,

Pourriez-vous installer le package "poppler-utils" (qui contient pdftotext) 
sur mon hébergement ?

Cet outil est nécessaire pour extraire du texte depuis des fichiers PDF 
dans mon application de gestion.

Merci d'avance !
```

## ✅ Vérification

Une fois installé, teste avec :

```bash
# Créer un PDF de test
echo "Test" > test.txt
# Si pdftotext est installé, cette commande devrait fonctionner
pdftotext -v
```

Tu peux aussi vérifier depuis PHP :
```php
<?php
exec('which pdftotext', $output, $return_var);
if ($return_var === 0) {
    echo "✅ pdftotext est installé : " . $output[0];
} else {
    echo "❌ pdftotext n'est pas installé";
}
?>
```

## 🚀 Avantages de pdftotext

- ⚡ 10x plus rapide que l'extraction brute PHP
- 🎯 Meilleure précision d'extraction
- 📐 Préserve la mise en page (option -layout)
- 💪 Supporte tous les types de PDF

## 📝 Note

En attendant l'installation, le système utilise automatiquement l'extraction brute PHP 
qui fonctionne sans dépendances, mais avec une précision moindre.
