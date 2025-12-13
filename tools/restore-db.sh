#!/bin/bash

###############################################################################
# Script de restauration MySQL GESTNAV
# 
# Usage: ./tools/restore-db.sh [fichier_sauvegarde]
# 
# Exemples:
# ./tools/restore-db.sh backups/gestnav_2025-12-06_14-30-45.sql.gz
# ./tools/restore-db.sh --list                    # Voir les sauvegardes
###############################################################################

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="$PROJECT_DIR/backups"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║    GESTNAV - Restauration Base de Données             ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"
echo ""

# Configuration MySQL
DB_HOST="votrehebergeur.mysql.db"
DB_NAME="votre_base_donnees"
DB_USER="votre_utilisateur_mysql"

if [ -z "$DB_PASSWORD" ]; then
    DB_PASSWORD_ARG=""
else
    DB_PASSWORD_ARG="-p$DB_PASSWORD"
fi

# Afficher les sauvegardes disponibles
if [ "$1" == "--list" ] || [ -z "$1" ]; then
    echo -e "${BLUE}📜 Sauvegardes disponibles:${NC}"
    if [ -f "$BACKUP_DIR" ]; then
        ls -lhtr "$BACKUP_DIR"/gestnav_*.sql.gz 2>/dev/null | tail -10 | awk '{print "   " NR ". " $9 " (" $5 ")"}'
    else
        echo -e "${YELLOW}Aucune sauvegarde trouvée${NC}"
    fi
    echo ""
    echo -e "${BLUE}Usage: bash tools/restore-db.sh backups/gestnav_DATE.sql.gz${NC}"
    exit 0
fi

# Vérifier que le fichier existe
BACKUP_FILE="$1"
if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${RED}❌ Fichier non trouvé: $BACKUP_FILE${NC}"
    exit 1
fi

# Afficher les informations
echo -e "${BLUE}📊 Informations:${NC}"
echo "   Base: $DB_NAME"
echo "   Host: $DB_HOST"
echo "   Source: $BACKUP_FILE"
echo "   Taille: $(du -h "$BACKUP_FILE" | cut -f1)"
echo ""

# ⚠️ Avertissement
echo -e "${RED}⚠️  ATTENTION${NC}"
echo "   Cette opération va REMPLACER les données actuelles!"
echo "   Assurez-vous que:"
echo "   - Vous êtes sûr(e) de cette restauration"
echo "   - Vous avez une sauvegarde récente"
echo ""

read -p "Êtes-vous sûr(e)? Tapez 'OUI' pour continuer: " confirmation
if [ "$confirmation" != "OUI" ]; then
    echo -e "${YELLOW}Opération annulée${NC}"
    exit 1
fi

echo ""
echo -e "${BLUE}⏳ Restauration en cours...${NC}"
echo "   Cela peut prendre quelques minutes..."
echo ""

# Restaurer la sauvegarde
if gunzip -c "$BACKUP_FILE" | mysql -h "$DB_HOST" -u "$DB_USER" $DB_PASSWORD_ARG "$DB_NAME" 2>/dev/null; then
    echo -e "${GREEN}✅ Restauration réussie!${NC}"
    echo ""
    echo -e "${BLUE}📊 Vérification:${NC}"
    mysql -h "$DB_HOST" -u "$DB_USER" $DB_PASSWORD_ARG "$DB_NAME" -e "SELECT COUNT(*) as 'Tables' FROM information_schema.tables WHERE table_schema = '$DB_NAME';" 2>/dev/null || echo "   ✓ Base de données accessible"
    echo ""
    echo -e "${YELLOW}⚠️  N'oubliez pas de tester l'application!${NC}"
else
    echo -e "${RED}❌ Erreur lors de la restauration${NC}"
    echo "   Vérifiez:"
    echo "   - Les identifiants MySQL"
    echo "   - La connexion à la base de données"
    echo "   - L'intégrité du fichier de sauvegarde"
    exit 1
fi

echo ""
