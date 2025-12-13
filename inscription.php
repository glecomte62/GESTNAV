<?php
require 'header.php';

// Si déjà connecté, on redirige vers l'accueil (ou sorties.php si tu préfères)
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom       = trim($_POST['nom'] ?? '');
    $prenom    = trim($_POST['prenom'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validations
    if ($nom === '') {
        $errors[] = "Le nom est obligatoire.";
    }

    if ($prenom === '') {
        $errors[] = "Le prénom est obligatoire.";
    }

    if ($email === '') {
        $errors[] = "L'adresse e-mail est obligatoire.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse e-mail n'est pas valide.";
    }

    if ($password === '' || $password2 === '') {
        $errors[] = "Le mot de passe et sa confirmation sont obligatoires.";
    } elseif ($password !== $password2) {
        $errors[] = "Les deux mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }

    if (empty($errors)) {
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Un compte existe déjà avec cette adresse e-mail.";
        } else {
            // Création de l'utilisateur
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (nom, prenom, email, password_hash, role, actif)
                VALUES (?, ?, ?, ?, 'membre', 1)
            ");
            $stmt->execute([$nom, $prenom, $email, $hash]);

            $user_id = (int)$pdo->lastInsertId();

            // Connexion automatique
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_nom'] = $nom;
            $_SESSION['user_prenom'] = $prenom;
            $_SESSION['user_role'] = 'membre';

            // Redirection après inscription
            header('Location: index.php');
            exit;
        }
    }
}
?>

<style>
    .inscription-page {
        max-width: 480px;
        margin: 0 auto;
        padding: 2.5rem 1rem 3rem;
    }
    .inscription-header {
        text-align: center;
        margin-bottom: 1.75rem;
    }
    .inscription-header h1 {
        font-size: 1.7rem;
        margin: 0 0 0.4rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #004b8d;
    }
    .inscription-header p {
        margin: 0;
        font-size: 0.95rem;
        color: #555;
    }
    .inscription-card {
        background: #ffffff;
        border-radius: 1.25rem;
        padding: 1.75rem 1.5rem 1.5rem;
        box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        border: 1px solid rgba(0,0,0,0.03);
    }
    .inscription-card-header {
        margin-bottom: 1rem;
        text-align: center;
    }
    .inscription-card-header h2 {
        font-size: 1.1rem;
        margin: 0;
        font-weight: 600;
    }
    .inscription-card-header p {
        font-size: 0.85rem;
        color: #666;
        margin: 0.25rem 0 0;
    }
    .form-group {
        margin-bottom: 0.9rem;
    }
    .form-group label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: #333;
    }
    .form-group input {
        width: 100%;
        border-radius: 999px;
        border: 1px solid #d0d7e2;
        padding: 0.6rem 0.9rem;
        font-size: 0.9rem;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        background: #f9fbff;
    }
    .form-group input:focus {
        border-color: #00a0c6;
        box-shadow: 0 0 0 3px rgba(0, 160, 198, 0.2);
        background: #ffffff;
    }
    .form-help {
        font-size: 0.75rem;
        color: #777;
        margin-top: 0.15rem;
    }
    .btn-primary-gestnav {
        width: 100%;
        border: none;
        border-radius: 999px;
        padding: 0.65rem 1.3rem;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        background: linear-gradient(135deg, #004b8d, #00a0c6);
        color: #fff;
        box-shadow: 0 8px 16px rgba(0, 75, 141, 0.35);
        transition: transform 0.1s ease, box-shadow 0.1s ease, filter 0.1s ease;
        margin-top: 0.5rem;
    }
    .btn-primary-gestnav:hover {
        filter: brightness(1.05);
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(0, 75, 141, 0.4);
    }
    .auth-footer {
        margin-top: 1rem;
        font-size: 0.85rem;
        text-align: center;
        color: #555;
    }
    .auth-footer a {
        color: #004b8d;
        text-decoration: none;
        font-weight: 600;
    }
    .auth-footer a:hover {
        text-decoration: underline;
    }
    .flash-error {
        margin-bottom: 0.9rem;
        padding: 0.6rem 0.8rem;
        border-radius: 0.75rem;
        background: #fde8e8;
        color: #b02525;
        font-size: 0.85rem;
    }
</style>

<div class="inscription-page">
    <div class="inscription-header">
        <h1>Inscription</h1>
        <p>Crée ton compte membre pour gérer tes sorties ULM.</p>
    </div>

    <div class="inscription-card">
        <div class="inscription-card-header">
            <h2>Créer un compte</h2>
            <p>Les comptes créés ici seront validés automatiquement en membre actif.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="flash-error">
                <?php foreach ($errors as $e): ?>
                    <div>• <?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="form-group">
                <label for="prenom">Prénom</label>
                <input
                    type="text"
                    id="prenom"
                    name="prenom"
                    required
                    value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="nom">Nom</label>
                <input
                    type="text"
                    id="nom"
                    name="nom"
                    required
                    value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                >
                <div class="form-help">Cette adresse servira pour les notifications de sorties.</div>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >
                <div class="form-help">Au moins 6 caractères.</div>
            </div>

            <div class="form-group">
                <label for="password2">Confirmer le mot de passe</label>
                <input
                    type="password"
                    id="password2"
                    name="password2"
                    required
                >
            </div>

            <button type="submit" class="btn-primary-gestnav">
                Créer mon compte
            </button>
        </form>

        <div class="auth-footer">
            Tu as déjà un compte ?  
            <a href="login.php">Connexion</a>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
