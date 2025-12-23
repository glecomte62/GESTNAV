#!/bin/bash

# Script de déploiement pour la fonctionnalité "Mes sorties"
# Usage: ./deploy_mes_sorties.sh

# Configuration
REMOTE_PATH="/gestnav.clubulmevasion.fr"
FILES_TO_UPLOAD=("mes_sorties.php" "header.php")

# Demander les credentials
echo "=== Déploiement FTP GESTNAV - Mes sorties ==="
echo ""
read -p "Serveur FTP (ex: ftp.clubulmevasion.fr): " FTP_HOST
read -p "Nom d'utilisateur FTP: " FTP_USER
read -sp "Mot de passe FTP: " FTP_PASS
echo ""

# Upload de chaque fichier
for FILE in "${FILES_TO_UPLOAD[@]}"; do
    # Vérifier que le fichier existe
    if [ ! -f "$FILE" ]; then
        echo "❌ Erreur: $FILE n'existe pas"
        continue
    fi

    echo ""
    echo "📤 Upload de $FILE vers $FTP_HOST$REMOTE_PATH..."

    # Upload via curl
    curl -T "$FILE" \
        --user "$FTP_USER:$FTP_PASS" \
        "ftp://$FTP_HOST$REMOTE_PATH/$FILE" \
        --ftp-create-dirs

    if [ $? -eq 0 ]; then
        echo "✅ $FILE déployé avec succès !"
    else
        echo "❌ Erreur lors du déploiement de $FILE"
    fi
done

echo ""
echo "=== Déploiement terminé ==="
