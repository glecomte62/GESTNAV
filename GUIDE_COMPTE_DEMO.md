# Guide d'utilisation du compte de démonstration

## 🎯 Objectif

Permettre aux visiteurs de tester l'application sans impact sur les données réelles.

## 📋 Création du compte démo

1. Accédez à : `https://gestnav.clubulmevasion.fr/create_demo_user.php`
2. Le compte sera créé avec les identifiants :
   - **Email** : demo@clubulmevasion.fr
   - **Mot de passe** : Demo2024!
3. Supprimez le fichier après création : `rm create_demo_user.php`

## 🛡️ Protection des actions (optionnel)

Pour limiter l'impact du compte démo, vous pouvez ajouter des protections.

### Méthode 1 : Bandeau d'information

Ajoutez après `require 'header.php'` dans vos pages :

```php
require_once 'demo_helper.php';
show_demo_banner();
```

### Méthode 2 : Bloquer les actions destructives

#### Exemple 1 : Bloquer la suppression

```php
require_once 'demo_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    block_demo_action(
        "La suppression n'est pas autorisée en mode démonstration.",
        "index.php?msg=demo_blocked"
    );
}
```

#### Exemple 2 : Bloquer la modification

```php
require_once 'demo_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_demo_protected()) {
        $_SESSION['error'] = "Modification non autorisée en mode démo";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}
```

#### Exemple 3 : Afficher un message d'avertissement

```php
require_once 'demo_helper.php';
show_demo_message(); // Affiche les messages de blocage
```

### Méthode 3 : Mode lecture seule

Pour rendre certaines pages en lecture seule pour le compte démo :

```php
require_once 'demo_helper.php';

$readonly = is_demo_user();

// Dans le formulaire HTML
<input type="text" name="field" <?= $readonly ? 'disabled readonly' : '' ?>>
<button type="submit" <?= $readonly ? 'disabled' : '' ?>>Enregistrer</button>

<?php if ($readonly): ?>
    <div class="alert alert-info">
        <i class="bi bi-eye"></i> Mode consultation uniquement (compte démo)
    </div>
<?php endif; ?>
```

## 📝 Exemples d'intégration

### Page avec formulaire protégé

```php
<?php
require 'config.php';
session_start();
require_once 'demo_helper.php';

// Bloquer les modifications pour le compte démo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_demo_user()) {
    // Traitement normal
    $stmt = $pdo->prepare("INSERT INTO ...");
    // ...
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && is_demo_user()) {
    $_SESSION['demo_message'] = "Les modifications ne sont pas autorisées en mode démonstration";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

require 'header.php';
show_demo_banner();
show_demo_message();
?>
```

### Protection des suppressions

```php
// Dans evenement_edit.php ou similaire
require_once 'demo_helper.php';

if (isset($_POST['delete'])) {
    block_demo_action(
        "La suppression d'événements n'est pas autorisée en mode démonstration.",
        "evenements_admin.php"
    );
    
    // Code de suppression (ne sera jamais atteint pour compte démo)
    $stmt = $pdo->prepare("DELETE FROM evenements WHERE id = ?");
    // ...
}
```

## 🎨 Personnalisation du bandeau

Modifiez `show_demo_banner()` dans `demo_helper.php` :

```php
function show_demo_banner() {
    if (!is_demo_user()) return;
    ?>
    <div class="demo-banner">
        🎭 MODE DÉMO - Explorez librement, aucune donnée ne sera modifiée !
    </div>
    <style>
        .demo-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            text-align: center;
            font-weight: 600;
        }
    </style>
    <?php
}
```

## 🔒 Sécurité

### Bonnes pratiques

1. **Changez le mot de passe régulièrement**
2. **Surveillez les logs** : `SELECT * FROM logs_operations WHERE user_id = (SELECT id FROM users WHERE email = 'demo@clubulmevasion.fr')`
3. **Limitez les permissions** : Le compte est créé en tant que 'member' (non admin)
4. **Créez des données de test** dédiées pour le compte démo
5. **Nettoyez périodiquement** les données créées par le compte démo

### Script de nettoyage (optionnel)

```sql
-- Supprimer les sorties proposées par le compte démo
DELETE FROM sortie_proposals 
WHERE user_id = (SELECT id FROM users WHERE email = 'demo@clubulmevasion.fr');

-- Supprimer les commentaires du compte démo
DELETE FROM event_comments 
WHERE user_id = (SELECT id FROM users WHERE email = 'demo@clubulmevasion.fr');
```

## 📊 Monitoring

### Voir les actions du compte démo

```php
$stmt = $pdo->prepare("
    SELECT * FROM logs_operations 
    WHERE user_id = (SELECT id FROM users WHERE email = 'demo@clubulmevasion.fr')
    ORDER BY created_at DESC
    LIMIT 50
");
```

## 🚀 Mise en place rapide (recommandé)

**Niveau 1 : Information seule** (aucun blocage)
- Ajouter `show_demo_banner()` dans les pages principales

**Niveau 2 : Blocage des suppressions**
- Protéger les actions DELETE avec `block_demo_action()`

**Niveau 3 : Mode lecture seule complet**
- Bloquer toutes les modifications POST
- Rendre les formulaires en readonly

Choisissez le niveau selon vos besoins !

## ⚙️ Désactivation

Pour supprimer le compte démo :

```sql
DELETE FROM users WHERE email = 'demo@clubulmevasion.fr';
```

Ou depuis l'interface admin dans la gestion des membres.
