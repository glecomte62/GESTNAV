#!/bin/bash

# Script de déploiement FTP - Événements multi-jours
# Déploie les fichiers modifiés pour le support des événements multi-jours

# Configuration FTP
FTP_HOST="ftp.kica7829.odns.fr"
FTP_USER="ulmevasion@clubulmevasion.fr"
FTP_PASS="Corvus2024@LFQJ"
REMOTE_PATH="/gestnav.clubulmevasion.fr"

# Liste des fichiers à uploader
FILES=(
    "evenement_detail.php"
    "evenement_edit.php"
    "evenements_admin.php"
    "evenements_list.php"
    "index.php"
    "setup/install_archive/install_events_multi_days.php"
)

echo "=== Déploiement FTP - Événements multi-jours ==="
echo "Serveur: $FTP_HOST"
echo "Destination: $REMOTE_PATH"
echo ""

SUCCESS=0
FAILED=0

for FILE in "${FILES[@]}"; do
    if [ ! -f "$FILE" ]; then
        echo "⚠️  Fichier non trouvé: $FILE"
        ((FAILED++))
        continue
    fi
    
    echo "📤 Upload: $FILE"
    
    # Créer les répertoires si nécessaire et uploader
    DIR=$(dirname "$FILE")
    
    curl -T "$FILE" \
        --user "$FTP_USER:$FTP_PASS" \
        "ftp://$FTP_HOST$REMOTE_PATH/$FILE" \
        --ftp-create-dirs \
        --silent
    
    if [ $? -eq 0 ]; then
        echo "   ✅ OK"
        ((SUCCESS++))
    else
        echo "   ❌ ERREUR"
        ((FAILED++))
    fi
done

echo ""
echo "=== Résumé ==="
echo "✅ Réussis: $SUCCESS"
echo "❌ Échoués: $FAILED"
echo ""

if [ $FAILED -eq 0 ]; then
    echo "🎉 Déploiement terminé avec succès !"
    echo ""
    echo "⚠️  N'oubliez pas d'exécuter la migration sur le serveur:"
    echo "   → $REMOTE_PATH/setup/install_archive/install_events_multi_days.php"
    exit 0
else
    echo "⚠️  Déploiement terminé avec des erreurs"
    exit 1
fi
