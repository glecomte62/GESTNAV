# 🤝 Contribuer à GESTNAV

Merci de votre intérêt pour contribuer à GESTNAV ! Ce document explique comment vous pouvez aider.

## 🎯 Comment contribuer

Il existe plusieurs façons de contribuer :

### 1. Signaler un bug 🐛

Utilisez les [GitHub Issues](https://github.com/glecomte62/GESTNAV/issues) en fournissant :
- Description détaillée du problème
- Étapes pour reproduire
- Version de GESTNAV
- Version PHP et MySQL
- Logs d'erreur si disponibles

### 2. Proposer une fonctionnalité ✨

Ouvrez une issue avec le label `enhancement` :
- Décrivez la fonctionnalité souhaitée
- Expliquez le cas d'usage
- Proposez une implémentation si possible

### 3. Améliorer la documentation 📝

La documentation peut toujours être améliorée :
- Corriger les fautes
- Ajouter des exemples
- Traduire (anglais, espagnol...)
- Créer des tutoriels vidéo

### 4. Soumettre du code 💻

1. **Fork** le repository
2. **Créez** une branche (`git checkout -b feature/MaSuperFonctionnalite`)
3. **Committez** vos changements (`git commit -m '✨ Add MaSuperFonctionnalite'`)
4. **Push** vers la branche (`git push origin feature/MaSuperFonctionnalite`)
5. **Ouvrez** une Pull Request

## 📋 Conventions de code

### PHP

- **PSR-12** : Suivez les standards PHP-FIG
- **Indentation** : 4 espaces
- **Encodage** : UTF-8
- **Commentaires** : En français pour ce projet

```php
<?php
// Bon
function createSortie(array $data): bool
{
    if (empty($data['titre'])) {
        return false;
    }
    
    // Traitement...
    return true;
}

// Éviter
function createSortie($data) {
  if(empty($data['titre']))
    return false;
  return true;
}
```

### SQL

- Utilisez **toujours** des requêtes préparées
- Nommage : `snake_case`
- Transactions pour les opérations multiples

```php
// Bon
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// JAMAIS ça
$result = $pdo->query("SELECT * FROM users WHERE email = '$email'");
```

### JavaScript

- **ES6+** : Utilisez les fonctionnalités modernes
- **const/let** : Pas de `var`
- **Arrow functions** : Quand approprié

```javascript
// Bon
const membres = data.map(m => ({
    id: m.id,
    nom: `${m.prenom} ${m.nom}`
}));

// Éviter
var membres = [];
for (var i = 0; i < data.length; i++) {
    membres.push({id: data[i].id, nom: data[i].prenom + ' ' + data[i].nom});
}
```

### CSS

- **Mobile-first** : Media queries progressives
- **BEM** : Pour les classes complexes
- **Variables CSS** : Pour les couleurs et espacements

```css
/* Bon */
.sortie-card {
    padding: 1rem;
}

@media (min-width: 768px) {
    .sortie-card {
        padding: 1.5rem;
    }
}

/* Éviter */
.sortie-card {
    padding: 1.5rem;
}

@media (max-width: 767px) {
    .sortie-card {
        padding: 1rem;
    }
}
```

## 📝 Conventions de commit

Utilisez les **Gitmoji** pour les commits :

```
✨ :sparkles: Nouvelle fonctionnalité
🐛 :bug: Correction de bug
📝 :memo: Documentation
🎨 :art: Amélioration UI/style
♻️ :recycle: Refactoring
⚡️ :zap: Performance
🔒 :lock: Sécurité
🔧 :wrench: Configuration
🚀 :rocket: Déploiement
✅ :white_check_mark: Tests
🌐 :globe_with_meridians: Internationalisation
```

Exemples :
```bash
git commit -m "✨ Ajouter recherche dans l'annuaire"
git commit -m "🐛 Corriger affichage des dates"
git commit -m "📝 Améliorer documentation installation"
```

## 🧪 Tests

Avant de soumettre :

1. **Testez** votre code localement
2. **Vérifiez** qu'il n'y a pas d'erreurs PHP
3. **Testez** sur différents navigateurs si UI
4. **Vérifiez** la compatibilité mobile

## 🔒 Sécurité

Si vous découvrez une vulnérabilité :

- **NE PAS** ouvrir une issue publique
- **Contactez** directement : gestnav@clubulmevasion.fr
- **Attendez** la correction avant divulgation

## 📜 License

En contribuant, vous acceptez que votre code soit sous licence MIT.

## 🙏 Remerciements

Tous les contributeurs seront mentionnés dans le CHANGELOG.

---

**Merci de contribuer à GESTNAV ! 🛩️**
