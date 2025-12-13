#!/bin/bash

###############################################################################
# Script de sauvegarde MySQL GESTNAV
# 
# Usage: ./tools/backup-db.sh
# 
# Fonctionnement:
# 1. Crée un dump SQL de la base de données
# 2. Compresse et date le fichier
# 3. Conserve les 10 dernières sauvegardes
###############################################################################

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="$PROJECT_DIR/backups"

# Créer le dossier backups s'il n'existe pas
mkdir -p "$BACKUP_DIR"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║      GESTNAV - Sauvegarde Base de Données             ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"
echo ""

# Configuration MySQL
DB_HOST="votrehebergeur.mysql.db"
DB_NAME="votre_base_donnees"
DB_USER="votre_utilisateur_mysql"

# Le mot de passe sera lu depuis une variable d'environnement pour la sécurité
# Ou depuis ~/.my.cnf
if [ -z "$DB_PASSWORD" ]; then
    echo -e "${YELLOW}⚠️  Variable DB_PASSWORD non définie${NC}"
    echo "   Utilisation de ~/.my.cnf ou invite de mot de passe"
    DB_PASSWORD_ARG=""
else
    DB_PASSWORD_ARG="-p$DB_PASSWORD"
fi

# Timestamp
TIMESTAMP=$(date '+%Y-%m-%d_%H-%M-%S')
BACKUP_FILE="$BACKUP_DIR/gestnav_${TIMESTAMP}.sql.gz"

echo -e "${BLUE}📊 Informations:${NC}"
echo "   Base: $DB_NAME"
echo "   Host: $DB_HOST"
echo "   User: $DB_USER"
echo "   Destination: $BACKUP_FILE"
echo ""

# Créer la sauvegarde
echo -e "${BLUE}💾 Création de la sauvegarde...${NC}"
if mysqldump -h "$DB_HOST" -u "$DB_USER" $DB_PASSWORD_ARG "$DB_NAME" 2>/dev/null | gzip > "$BACKUP_FILE"; then
    SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo -e "${GREEN}✅ Sauvegarde réussie ($SIZE)${NC}"
else
    echo -e "${RED}❌ Erreur lors de la sauvegarde${NC}"
    echo "   Assurez-vous que:"
    echo "   - MySQL est accessible"
    echo "   - Les identifiants sont corrects"
    echo "   - La variable DB_PASSWORD est définie (optionnel)"
    exit 1
fi

# Compter les sauvegardes
BACKUP_COUNT=$(ls -1 "$BACKUP_DIR"/gestnav_*.sql.gz 2>/dev/null | wc -l)
echo -e "${BLUE}📁 Sauvegardes conservées: $BACKUP_COUNT${NC}"

# Garder seulement les 10 dernières sauvegardes
MAX_BACKUPS=10
if [ "$BACKUP_COUNT" -gt "$MAX_BACKUPS" ]; then
    echo -e "${BLUE}🧹 Nettoyage des anciennes sauvegardes...${NC}"
    ls -1t "$BACKUP_DIR"/gestnav_*.sql.gz | tail -n +$((MAX_BACKUPS + 1)) | while read old_backup; do
        echo "   Suppression: $(basename "$old_backup")"
        rm "$old_backup"
    done
fi

# Afficher les sauvegardes disponibles
echo ""
echo -e "${BLUE}📜 Dernières sauvegardes:${NC}"
ls -lhtr "$BACKUP_DIR"/gestnav_*.sql.gz 2>/dev/null | tail -5 | awk '{print "   " $9 " (" $5 ")"}'

echo ""
echo -e "${GREEN}✅ Sauvegarde terminée${NC}"
echo ""
echo -e "${BLUE}💡 Pour restaurer une sauvegarde:${NC}"
echo "   gunzip < backups/gestnav_DATE.sql.gz | mysql -h $DB_HOST -u $DB_USER -p $DB_NAME"
echo ""
