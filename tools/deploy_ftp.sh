#!/usr/bin/env bash
set -euo pipefail

# Config — à renseigner avant usage
PROTO="ftp"                 # sftp | ftp | ftps
FTP_HOST="ftp.votrehebergeur.fr"
FTP_USER="votre_utilisateur_ftp"          # utilisateur FTP/SFTP
FTP_PASS="VOTRE_MOT_DE_PASSE"         # mot de passe (ou utilisez un gestionnaire de secrets)
FTP_PATH="/"  # dossier distant cible (doit exister)

#########################
# Ne rien modifier dessous
#########################
BASEDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
URL_BASE="${PROTO}://${FTP_HOST}${FTP_PATH%/}/"

if [[ -z "${FTP_HOST}" || -z "${FTP_USER}" || -z "${FTP_PASS}" || -z "${FTP_PATH}" ]]; then
  echo "[ERREUR] Variables FTP_* non configurées." >&2
  exit 1
fi

# Récupérer les fichiers modifiés depuis le dernier commit
# On utilise git diff-index pour voir les fichiers changés
CHANGED_FILES=$(cd "$BASEDIR" && git diff-index --cached --name-only HEAD 2>/dev/null || echo "")

# Si pas de fichiers en cache (déploiement manuel), on check l'historique
if [[ -z "$CHANGED_FILES" ]]; then
  # Chercher les fichiers du dernier commit
  CHANGED_FILES=$(cd "$BASEDIR" && git diff-tree --no-commit-id --name-only -r HEAD 2>/dev/null || echo "")
fi

# Si on ne peut pas récupérer les fichiers via git, deployer tout
if [[ -z "$CHANGED_FILES" ]]; then
  echo "[INFO] Impossible de déterminer les fichiers changés - déploiement complet"
  DEPLOY_ALL=1
else
  DEPLOY_ALL=0
  echo "[INFO] Fichiers à déployer:"
  echo "$CHANGED_FILES" | sed 's/^/  - /'
fi

echo ""

upload_file() {
  local src="$1"
  local rel_path="$2"
  
  if [[ ! -f "${src}" ]]; then
    echo "[WARN] Fichier introuvable: ${src}"
    return 1
  fi
  
  echo "==> Upload ${rel_path} vers ${URL_BASE}"
  CURL_OPTS=(--fail --silent --show-error --upload-file "${src}" --user "${FTP_USER}:${FTP_PASS}")
  if [[ "${PROTO}" != "sftp" ]]; then
    CURL_OPTS+=(--ftp-create-dirs)
  fi
  
  if curl "${CURL_OPTS[@]}" "${URL_BASE}${rel_path}"; then
    echo "OK: ${rel_path}"
    return 0
  else
    echo "[ERREUR] Upload échoué pour ${rel_path}"
    return 1
  fi
}

# Déploiement sélectif ou complet
if [[ $DEPLOY_ALL -eq 1 ]]; then
  # Déploiement complet - tous les fichiers PHP et configs
  echo "[INFO] Déploiement complet de tous les fichiers..."
  
  while IFS= read -r -d '' file; do
    rel_path="${file#${BASEDIR}/}"
    upload_file "$file" "$rel_path" || true
  done < <(find "${BASEDIR}" -maxdepth 1 -type f \( -name "*.php" -o -name "*.md" -o -name ".htaccess" \) -print0)
  
  # Déployer assets et docs complètement
  for dir in assets docs; do
    if [[ -d "${BASEDIR}/${dir}" ]]; then
      while IFS= read -r -d '' file; do
        rel_path="${file#${BASEDIR}/}"
        upload_file "$file" "$rel_path" || true
      done < <(find "${BASEDIR}/${dir}" -type f -print0)
    fi
  done
  
  # Déployer utils
  if [[ -d "${BASEDIR}/utils" ]]; then
    while IFS= read -r -d '' file; do
      rel_path="${file#${BASEDIR}/}"
      upload_file "$file" "$rel_path" || true
    done < <(find "${BASEDIR}/utils" -type f -print0)
  fi
else
  # Déploiement sélectif - uniquement les fichiers changés
  while IFS= read -r file; do
    # Ignorer les fichiers de config sensibles
    if [[ "$file" == ".git"* ]] || [[ "$file" == "tools/"* ]] || [[ "$file" == "backups/"* ]]; then
      continue
    fi
    
    src="${BASEDIR}/${file}"
    
    # Vérifier que le fichier est un fichier (pas un dossier supprimé)
    if [[ -f "$src" ]]; then
      upload_file "$src" "$file" || true
    fi
  done < <(echo "$CHANGED_FILES")
fi

echo "==> Déploiement FTP/SFTP terminé."
