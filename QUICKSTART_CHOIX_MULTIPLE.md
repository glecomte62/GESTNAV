# 🗳️ Choix Multiple pour les Sondages - Démarrage Rapide

## 🚀 Installation en 2 minutes

### 1. Exécuter la migration

**Via navigateur (recommandé) :**
```
https://gestnav.clubulmevasion.fr/setup/migrate_multiple_choice.php
```

**Via terminal :**
```bash
php setup/migrate_multiple_choice.php
```

### 2. C'est terminé ! ✅

La migration a :
- ✅ Ajouté la colonne `allow_multiple_choices` à la table `polls`
- ✅ Modifié la contrainte UNIQUE de `poll_votes`
- ✅ Activé le choix multiple pour les sondages de type "date"

---

## 📖 Utilisation

### Pour les administrateurs

1. **Éditer un sondage**
   - Allez sur [sondages_admin.php](https://gestnav.clubulmevasion.fr/sondages_admin.php)
   - Cliquez sur **"✏️ Éditer"** sur un sondage ouvert
   - Cochez **"✅ Autoriser le choix multiple"**
   - Enregistrez

2. **Résultat**
   - Les membres pourront voter pour plusieurs options
   - Cases à cocher au lieu de boutons radio

### Pour les membres

- Les sondages avec choix multiple affichent : **"✅ Vous pouvez sélectionner plusieurs options"**
- Cochez autant d'options que vous le souhaitez
- Cliquez sur **"✅ Enregistrer mon vote"**

---

## 📁 Fichiers modifiés

| Fichier | Description |
|---------|-------------|
| `sondages_admin.php` | Bouton d'édition + Modal d'édition |
| `sondages.php` | Support du vote multiple |
| `setup/migrate_multiple_choice.php` | Script de migration complet |
| `GUIDE_CHOIX_MULTIPLE.md` | Documentation complète |
| `DEPLOIEMENT_CHOIX_MULTIPLE.md` | Guide de déploiement |

---

## 🧪 Test rapide

1. Créez un sondage
2. Éditez-le et activez le choix multiple
3. Votez avec un compte membre
4. Vérifiez que plusieurs options peuvent être sélectionnées

---

## 📞 Besoin d'aide ?

- **Documentation complète :** [GUIDE_CHOIX_MULTIPLE.md](GUIDE_CHOIX_MULTIPLE.md)
- **Déploiement :** [DEPLOIEMENT_CHOIX_MULTIPLE.md](DEPLOIEMENT_CHOIX_MULTIPLE.md)
- **Sondages :** [POLLS_DOCUMENTATION.md](POLLS_DOCUMENTATION.md)

---

**Date de mise à jour :** 14 décembre 2025
