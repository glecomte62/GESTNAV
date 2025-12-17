#!/bin/bash
# Script pour nettoyer l'historique Git des fichiers sensibles
echo "⚠️  ATTENTION: Cette opération va réécrire l'historique Git"
echo "Cela nécessite 'git filter-repo' (pip install git-filter-repo)"
echo ""
echo "Fichiers à supprimer de l'historique:"
echo "  - config.php"
echo "  - club_config.php"
echo ""
read -p "Continuer ? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]
then
    git filter-repo --invert-paths --path config.php --path club_config.php --force
    echo "✅ Historique nettoyé"
    echo "⚠️  Il faut maintenant faire: git push --force origin main"
fi
