#!/bin/bash

# Script de déploiement FTP pour GESTNAV
# Usage: ./deploy_ftp.sh

# Configuration
REMOTE_PATH="/gestnav.clubulmevasion.fr"
FILE_TO_UPLOAD="login.php"

# Demander les credentials
echo "=== Déploiement FTP GESTNAV ==="
echo ""
read -p "Serveur FTP (ex: ftp.clubulmevasion.fr): " FTP_HOST
read -p "Nom d'utilisateur FTP: " FTP_USER
read -sp "Mot de passe FTP: " FTP_PASS
echo ""

# Vérifier que le fichier existe
if [ ! -f "$FILE_TO_UPLOAD" ]; then
    echo "❌ Erreur: $FILE_TO_UPLOAD n'existe pas"
    exit 1
fi

echo ""
echo "📤 Upload de $FILE_TO_UPLOAD vers $FTP_HOST$REMOTE_PATH..."

# Upload via curl
curl -T "$FILE_TO_UPLOAD" \
    --user "$FTP_USER:$FTP_PASS" \
    "ftp://$FTP_HOST$REMOTE_PATH/$FILE_TO_UPLOAD" \
    --ftp-create-dirs \
    --verbose

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Déploiement réussi !"
else
    echo ""
    echo "❌ Erreur lors du déploiement"
    exit 1
fi
