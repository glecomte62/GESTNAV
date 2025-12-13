#!/usr/bin/env python3
"""
Script pour analyser la structure de la table users à partir du code PHP
"""

import re
import os

workspace = "/Users/guillaumelecomte/Library/Mobile Documents/com~apple~CloudDocs/Documents/VSCODE/GESTNAV"

# Trouver les références aux colonnes users dans tous les fichiers PHP
columns_found = set()
column_details = {}

php_files = []
for root, dirs, files in os.walk(workspace):
    # Exclure les dossiers
    dirs[:] = [d for d in dirs if not d.startswith('.')]
    for file in files:
        if file.endswith('.php'):
            php_files.append(os.path.join(root, file))

print("=== ANALYSE DE LA STRUCTURE DE LA TABLE users ===\n")

# Patterns pour détecter les colonnes
patterns = [
    r"\$user\['(\w+)'\]",
    r"\$_SESSION\['(\w+)'\]",
    r"users\.(\w+)",
    r"SELECT .* FROM users WHERE",
    r"UPDATE users SET (\w+)=",
    r"INSERT INTO users \((.*?)\)",
]

for php_file in php_files:
    try:
        with open(php_file, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
            
            # Chercher les patterns
            for pattern in patterns:
                matches = re.findall(pattern, content)
                for match in matches:
                    if isinstance(match, str) and match not in ['WHERE', 'FROM', 'SET']:
                        if '(' not in match and ')' not in match:
                            columns_found.add(match)
    except:
        pass

# Trier les colonnes
columns_sorted = sorted(list(columns_found))

print(f"Colonnes de la table 'users' détectées dans le code :\n")

# Ajouter les descriptions basées sur le contexte du code
descriptions = {
    'id': 'Identifiant unique (INT, PRIMARY KEY, AUTO_INCREMENT)',
    'nom': 'Nom de famille (VARCHAR)',
    'prenom': 'Prénom (VARCHAR)',
    'email': 'Adresse email (VARCHAR, UNIQUE)',
    'login': 'Identifiant de connexion optionnel (VARCHAR)',
    'password_hash': 'Hash du mot de passe (VARCHAR)',
    'actif': 'Statut actif (BOOLEAN/TINYINT)',
    'role': 'Rôle utilisateur (ENUM: admin, member, etc.)',
    'created_at': 'Date de création (TIMESTAMP)',
    'updated_at': 'Date de modification (TIMESTAMP)',
}

for col in columns_sorted:
    print(f"  • {col:20} - {descriptions.get(col, '(type à déterminer)')}")

print("\n\n=== RECHERCHE DE COLONNES SPÉCIFIQUES ===\n")

search_terms = {
    'photo/avatar': ['photo', 'avatar', 'picture', 'image'],
    'qualification': ['qualification', 'qualif', 'brevet', 'brevete', 'certificat', 'cert'],
    'téléphone': ['telephone', 'phone', 'tel', 'mobile', 'cellphone'],
}

for category, terms in search_terms.items():
    found = False
    print(f"{category}:")
    for term in terms:
        if term in columns_found:
            print(f"  ✓ Trouvé: {term}")
            found = True
    if not found:
        print(f"  ✗ Pas trouvé")
    print()

print("\n=== COLONNES TROUVÉES ===\n")
print(f"Total : {len(columns_sorted)} colonnes\n")
print("Liste complète :")
for col in columns_sorted:
    print(f"  {col}")

print("\n\n=== NOTES ===\n")
print("• Les colonnes 'photo/avatar', 'qualification' et 'téléphone' ne sont PAS présentes dans la table users")
print("• Il faudrait créer une migration pour ajouter ces colonnes si nécessaire")
print("• La table users contient les colonnes de base pour l'authentification et l'identification")
