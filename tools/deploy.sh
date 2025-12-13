#!/bin/bash

###############################################################################
# Script de déploiement GESTNAV - FTP + Git
# 
# Usage: ./tools/deploy.sh [message]
# 
# Fonctionnement:
# 1. Commit les modifications en local
# 2. Déploie via FTP
# 3. Affiche l'historique
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
echo -e "${BLUE}║      GESTNAV - Déploiement FTP + Historique Git       ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"
echo ""

# 1. Vérifier si git est initialisé
if [ ! -d ".git" ]; then
    echo -e "${YELLOW}⚠️  Git n'est pas initialisé. Initialisation...${NC}"
    git init
    git config user.name "Guillaume Lecomte"
    git config user.email "guillaume@clubulmevasion.fr"
fi

# 2. Ajouter les fichiers modifiés
echo -e "${BLUE}📝 Ajout des fichiers modifiés...${NC}"
git add -A
git status

# 3. Vérifier s'il y a des changements
if git diff-index --quiet HEAD --; then
    echo -e "${YELLOW}ℹ️  Aucun changement détecté.${NC}"
else
    echo -e "${GREEN}✅ Création du commit...${NC}"
    git commit -m "$COMMIT_MSG"
fi

# 4. Afficher l'historique récent
echo ""
echo -e "${BLUE}📜 Historique récent:${NC}"
git log --oneline --graph -10

# 5. Lancer le déploiement FTP
echo ""
echo -e "${BLUE}🚀 Déploiement FTP en cours...${NC}"
bash "$(dirname "${BASH_SOURCE[0]}")/deploy_ftp.sh"

echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          ✅ Déploiement réussi!                       ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}💡 Prochaines actions:${NC}"
echo "   1. Vérifier https://gestnav.clubulmevasion.fr/"
echo "   2. Tester le menu Administration → 🔔 Alertes email"
echo "   3. En cas de problème: git log --oneline pour voir l'historique"
echo "   4. Rollback: git revert HEAD (crée un nouveau commit)"
echo ""
