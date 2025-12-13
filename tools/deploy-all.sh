#!/bin/bash

###############################################################################
# Script de déploiement GESTNAV complet
# 
# Usage: ./tools/deploy-all.sh [message]
# 
# Fonctionnement:
# 1. Commit les modifications en local
# 2. Push vers GitHub
# 3. Déploie via FTP vers la production
###############################################################################

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Message de commit
COMMIT_MSG="${1:-🔄 Mise à jour GESTNAV $(date '+%Y-%m-%d %H:%M:%S')}"

echo -e "${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   GESTNAV - Déploiement complet (Git + FTP)          ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"
echo ""

# 1. Vérifier le statut Git
echo -e "${BLUE}📊 Statut Git:${NC}"
git status --short || true

# 2. Ajouter les fichiers modifiés
echo ""
echo -e "${BLUE}📝 Ajout des fichiers modifiés...${NC}"
git add -A

# 3. Vérifier s'il y a des changements
if git diff-index --quiet HEAD --; then
    echo -e "${YELLOW}ℹ️  Aucun changement détecté.${NC}"
    exit 0
fi

# 4. Créer le commit
echo -e "${GREEN}✅ Création du commit...${NC}"
git commit -m "$COMMIT_MSG"

# 5. Afficher l'historique récent
echo ""
echo -e "${BLUE}📜 Historique récent:${NC}"
git log --oneline --graph -5

# 6. Push vers GitHub
echo ""
echo -e "${BLUE}📤 Push vers GitHub...${NC}"
git push origin main
echo -e "${GREEN}✅ Code sauvegardé sur GitHub${NC}"

# 7. Sauvegarde de la base de données
echo ""
echo -e "${BLUE}💾 Sauvegarde de la base de données...${NC}"
if bash "$(dirname "${BASH_SOURCE[0]}")/backup-db.sh"; then
    echo -e "${GREEN}✅ Base de données sauvegardée${NC}"
else
    echo -e "${YELLOW}⚠️  Attention: La sauvegarde BD a échoué (cela n'empêche pas le déploiement)${NC}"
fi

# 8. Lancer le déploiement FTP
echo ""
echo -e "${BLUE}🚀 Déploiement FTP en cours...${NC}"
bash "$(dirname "${BASH_SOURCE[0]}")/deploy_ftp.sh"

# 9. Résumé final
echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          ✅ Déploiement complet réussi!               ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}📍 Accès:${NC}"
echo "   • Prod: https://gestnav.clubulmevasion.fr/"
echo "   • GitHub: https://github.com/glecomte62/GESTNAV"
echo ""
echo -e "${BLUE}💡 Commandes utiles:${NC}"
echo "   • Voir l'historique: git log --oneline -10"
echo "   • Voir le dernier commit: git show"
echo "   • Revenir en arrière: git revert HEAD"
echo ""
