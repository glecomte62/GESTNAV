#!/usr/bin/env python3
import ftplib
import os
from pathlib import Path

# Configuration FTP
FTP_HOST = "ftp.votrehebergeur.fr"
FTP_USER = "votre_utilisateur_ftp"
FTP_PASS = "VOTRE_MOT_DE_PASSE"
FTP_PATH = "/public_html/gestnav/"

# Fichiers à déployer
FILES_TO_DEPLOY = [
    "config.php",
    "footer.php",
    "changelog.php",
    "envoyer_email.php",
    "evenements_admin.php",
    "action_evenement.php",
    "evenements_list.php",
    "evenement_detail.php",
    "evenement_inscription_detail.php",
    "header.php"
]

def deploy_files():
    """Déploie les fichiers sur le serveur FTP"""
    try:
        # Connexion FTP
        ftp = ftplib.FTP(FTP_HOST, FTP_USER, FTP_PASS)
        print(f"✓ Connecté à {FTP_HOST}")
        
        for file_name in FILES_TO_DEPLOY:
            local_path = Path(__file__).parent / file_name
            
            if not local_path.exists():
                print(f"✗ Fichier non trouvé: {file_name}")
                continue
            
            # Upload du fichier
            with open(local_path, 'rb') as f:
                ftp.storbinary(f'STOR {file_name}', f)
                print(f"✓ Déployé: {file_name}")
        
        ftp.quit()
        print("\n✓ Déploiement terminé avec succès!")
        
    except Exception as e:
        print(f"✗ Erreur: {e}")

if __name__ == "__main__":
    deploy_files()
