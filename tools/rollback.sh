#!/bin/bash

###############################################################################
# Script de rollback GESTNAV
# 
# Usage: ./tools/rollback.sh [--hash HASH | --last N]
# 
# Exemples:
# ./tools/rollback.sh --last 1          # Annuler le dernier commit
# ./tools/rollback.sh --hash abc123d    # Revenir à un commit spécifique
# ./tools/rollback.sh --show            # Voir l'historique
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

echo -e "${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║           GESTNAV - Rollback Git                      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"
echo ""

# Parser les arguments
SHOW_HISTORY=false
ROLLBACK_LAST=0
TARGET_HASH=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --show)
            SHOW_HISTORY=true
            shift
            ;;
        --last)
            ROLLBACK_LAST="$2"
            shift 2
            ;;
        --hash)
            TARGET_HASH="$2"
            shift 2
            ;;
        *)
            echo -e "${RED}❌ Argument inconnu: $1${NC}"
            exit 1
            ;;
    esac
done

# Afficher l'historique
if [ "$SHOW_HISTORY" = true ]; then
    echo -e "${BLUE}📜 Historique des commits:${NC}"
    git log --oneline --graph -20
    exit 0
fi

# Afficher l'historique par défaut
echo -e "${BLUE}📜 Historique des commits:${NC}"
git log --oneline -10
echo ""

# Rollback du dernier commit
if [ "$ROLLBACK_LAST" -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Vous êtes sur le point d'annuler les $ROLLBACK_LAST dernier(s) commit(s).${NC}"
    read -p "Êtes-vous sûr? (y/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        for ((i=0; i<$ROLLBACK_LAST; i++)); do
            echo -e "${BLUE}Création d'un revert pour annuler le commit...${NC}"
            git revert --no-edit HEAD
        done
        echo -e "${GREEN}✅ Revert créé avec succès${NC}"
        echo ""
        echo -e "${BLUE}📤 Push vers GitHub...${NC}"
        git push origin main
        echo -e "${GREEN}✅ Changements poussés${NC}"
    else
        echo "Opération annulée."
        exit 1
    fi
fi

# Rollback vers un hash spécifique
if [ -n "$TARGET_HASH" ]; then
    echo -e "${YELLOW}⚠️  Vous êtes sur le point de revenir au commit: $TARGET_HASH${NC}"
    read -p "Êtes-vous sûr? (y/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${BLUE}Création d'un revert...${NC}"
        git revert --no-edit "$TARGET_HASH"
        echo -e "${GREEN}✅ Revert créé avec succès${NC}"
        echo ""
        echo -e "${BLUE}📤 Push vers GitHub...${NC}"
        git push origin main
        echo -e "${GREEN}✅ Changements poussés${NC}"
    else
        echo "Opération annulée."
        exit 1
    fi
fi

echo ""
echo -e "${BLUE}💡 Pour redéployer la production:${NC}"
echo "   bash tools/deploy-all.sh 'Rollback'"
echo ""
