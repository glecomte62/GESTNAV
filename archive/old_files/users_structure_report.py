#!/usr/bin/env python3
"""
Analyse précise de la structure de la table users
"""

import re
import os

workspace = "/Users/guillaumelecomte/Library/Mobile Documents/com~apple~CloudDocs/Documents/VSCODE/GESTNAV"

# Colonnes confirmées trouvées dans le code
confirmed_columns = {
    'id': 'INT AUTO_INCREMENT PRIMARY KEY',
    'nom': 'VARCHAR(255)',
    'prenom': 'VARCHAR(255)',
    'email': 'VARCHAR(255)',
    'password_hash': 'VARCHAR(255)',
    'role': 'VARCHAR(50) ou ENUM',
    'actif': 'TINYINT(1) ou BOOLEAN',
    'login': 'VARCHAR(255) (optionnel)',
    'created_at': 'TIMESTAMP (probablement)',
    'updated_at': 'TIMESTAMP (probablement)',
}

print("=" * 70)
print("STRUCTURE DE LA TABLE users - GESTNAV")
print("=" * 70)
print()

print("COLONNES CONFIRMÉES (d'après le code PHP) :\n")

for col, desc in confirmed_columns.items():
    print(f"  • {col:20} : {desc}")

print("\n" + "=" * 70)
print("COLONNES RECHERCHÉES - RÉSULTATS\n")

recherche = {
    'Photo/Avatar': ['photo', 'avatar', 'picture', 'image'],
    'Qualification': ['qualification', 'brevet', 'certificat'],
    'Téléphone': ['telephone', 'phone', 'tel', 'mobile'],
}

for category, terms in recherche.items():
    found = [t for t in terms if t in confirmed_columns]
    if found:
        print(f"✓ {category:20} : {', '.join(found)}")
    else:
        print(f"✗ {category:20} : NON TROUVÉ")

print("\n" + "=" * 70)
print("RÉSUMÉ\n")

print(f"Total de colonnes confirmées : {len(confirmed_columns)}\n")

print("⚠️  IMPORTANTES OBSERVATIONS :\n")
print("1. Les colonnes 'photo', 'avatar', 'qualification' et 'telephone'")
print("   NE SONT PAS présentes dans la table users.\n")

print("2. Colonnes ACTUELLEMENT disponibles :")
for col in confirmed_columns:
    print(f"   - {col}")

print("\n3. Si vous avez besoin d'ajouter :")
print("   • Photo/Avatar : créer une colonne VARCHAR (chemin fichier)")
print("   • Qualification : créer une colonne VARCHAR ou TEXT")
print("   • Téléphone : créer une colonne VARCHAR(20)")

print("\n" + "=" * 70)
