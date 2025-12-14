# 🗳️ Guide : Choix Multiple pour les Sondages

## Nouveauté ajoutée le 14 décembre 2025

### 🎯 Fonctionnalité

Les sondages peuvent maintenant autoriser les **choix multiples**, permettant aux membres de voter pour plusieurs options au lieu d'une seule.

### 📋 Utilisation

#### Pour les administrateurs

1. **Accédez à** [sondages_admin.php](https://gestnav.clubulmevasion.fr/sondages_admin.php)

2. **Éditer un sondage existant :**
   - Cliquez sur le bouton **"✏️ Éditer"** sur un sondage ouvert
   - Cochez la case **"✅ Autoriser le choix multiple"**
   - Sauvegardez les modifications

3. **Créer un nouveau sondage :**
   - Les nouveaux sondages sont créés en mode choix simple par défaut
   - Vous pouvez les éditer ensuite pour activer le choix multiple

#### Pour les membres

- Les sondages avec choix multiple affichent des **cases à cocher** (☐) au lieu de boutons radio (○)
- Un bandeau vert indique : **"✅ Vous pouvez sélectionner plusieurs options"**
- Les membres peuvent voter pour autant d'options qu'ils le souhaitent

### 🔧 Installation de la base de données

**IMPORTANT :** Avant d'utiliser cette fonctionnalité, vous devez exécuter la migration de base de données.

#### Option 1 : Via le navigateur (recommandé)
```
https://gestnav.clubulmevasion.fr/setup/add_allow_multiple_choices.php
```

#### Option 2 : Via terminal
```bash
cd /chemin/vers/GESTNAV
php setup/add_allow_multiple_choices.php
```

### 📊 Cas d'usage

**Idéal pour :**
- ✅ Sondages de dates (un membre peut être disponible plusieurs jours)
- ✅ Sélection de destinations multiples
- ✅ Choix d'équipements à acheter
- ✅ Votes pour plusieurs activités

**Pas recommandé pour :**
- ❌ Élections (un seul choix possible)
- ❌ Questions binaires (Oui/Non)
- ❌ Choix exclusifs

### 🗄️ Modification de la base de données

La migration ajoute une colonne à la table `polls` :

```sql
ALTER TABLE polls ADD COLUMN allow_multiple_choices TINYINT(1) DEFAULT 0 AFTER type
```

- **Type :** Boolean (0 = désactivé, 1 = activé)
- **Par défaut :** 0 (choix simple)
- **Emplacement :** Après la colonne `type`

### ⚙️ Comportement technique

1. **Choix simple (par défaut) :**
   - Un seul vote par utilisateur
   - Changer son vote remplace l'ancien

2. **Choix multiple (quand activé) :**
   - Plusieurs votes possibles par utilisateur
   - Les votes précédents sont supprimés et remplacés par les nouveaux
   - La contrainte `UNIQUE KEY uk_user_poll (poll_id, user_id)` a été modifiée

### 📝 Notes importantes

- ✅ Les sondages de type "date" existants sont automatiquement passés en choix multiple lors de la migration
- ✅ Le changement du mode choix simple → multiple est possible à tout moment (tant que le sondage est ouvert)
- ⚠️ Activer le choix multiple sur un sondage en cours ne supprime pas les votes existants
- ⚠️ Les résultats peuvent changer car les utilisateurs peuvent ajouter des votes supplémentaires

### 🔍 Vérification

Après la migration, vérifiez que :
1. La colonne `allow_multiple_choices` existe dans la table `polls`
2. Les sondages de type "date" ont `allow_multiple_choices = 1`
3. L'édition des sondages fonctionne correctement

### 🐛 Dépannage

**Erreur : "Unknown column 'allow_multiple_choices'"**
→ La migration n'a pas été exécutée. Lancez le script de migration.

**Les cases à cocher n'apparaissent pas**
→ Vérifiez que le sondage a bien `allow_multiple_choices = 1` dans la base de données.

**Les votes ne s'enregistrent pas**
→ Vérifiez la contrainte UNIQUE dans la table `poll_votes` : elle doit permettre plusieurs votes par utilisateur (un par option).

### 📞 Support

Pour toute question, consultez la [documentation complète des sondages](POLLS_DOCUMENTATION.md).
