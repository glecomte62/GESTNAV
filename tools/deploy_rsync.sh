#!/usr/bin/env bash
set -euo pipefail

# Renseignez ces variables avant d'exécuter le script
REMOTE_USER="deploy"       # ex: deploy
REMOTE_HOST="example.org"  # ex: gestnav.clubulmevasion.fr
REMOTE_PATH="/var/www/gestnav"  # ex: /var/www/gestnav

# Fichiers à déployer (relatifs à la racine du dépôt)
FILES=(
  "machines.php"
  "sortie_detail.php"
)

#########################
# Ne rien modifier dessous
#########################
BASEDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"

if [[ -z "${REMOTE_USER}" || -z "${REMOTE_HOST}" || -z "${REMOTE_PATH}" ]]; then
  echo "[ERREUR] Variables REMOTE_USER/REMOTE_HOST/REMOTE_PATH non définies." >&2
  exit 1
fi

remote="${REMOTE_USER}@${REMOTE_HOST}"

echo "==> Test de connexion SSH à ${remote}"
ssh -o BatchMode=yes -o ConnectTimeout=5 "${remote}" 'echo ok' >/dev/null || {
  echo "[ERREUR] Connexion SSH impossible vers ${remote}." >&2
  exit 1
}

echo "==> Sauvegarde des fichiers distants avant déploiement"
for f in "${FILES[@]}"; do
  ssh "${remote}" "if [ -f '${REMOTE_PATH}/${f}' ]; then cp -a '${REMOTE_PATH}/${f}' '${REMOTE_PATH}/${f}.${TIMESTAMP}.bak'; fi"
done

echo "==> Déploiement des fichiers via rsync"
for f in "${FILES[@]}"; do
  src="${BASEDIR}/${f}"
  if [[ ! -f "${src}" ]]; then
    echo "[WARN] Fichier introuvable en local: ${src} — ignoré"
    continue
  fi
  rsync -avz --progress "${src}" "${remote}:${REMOTE_PATH}/"
done

echo "==> Reload PHP-FPM / OPCache (meilleure fraicheur du code)"
ssh "${remote}" 'sudo systemctl reload php-fpm 2>/dev/null || sudo service php8.2-fpm reload 2>/dev/null || true'

echo "==> Déploiement terminé avec succès."
