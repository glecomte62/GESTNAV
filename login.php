<?php
require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND actif = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nom'] = $user['nom'];
        $_SESSION['prenom'] = $user['prenom'];

        // Log de connexion: nom, prénom, IP
        try {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            if (strpos($ip, ',') !== false) {
                $parts = explode(',', $ip);
                $ip = trim($parts[0]);
            }
            $stmtLog = $pdo->prepare("INSERT INTO connection_logs (user_id, nom, prenom, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmtLog->execute([$user['id'], $user['nom'], $user['prenom'], $ip]);
        } catch (Throwable $e) {
            // Ne pas bloquer la connexion en cas d'erreur de log
        }

        header('Location: index.php');
        exit;
    } else {
        $error = "Identifiants incorrects ou compte inactif.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion – GESTNAV ULM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap & CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/gestnav.css">
</head>
<body style="display:flex; align-items:center; justify-content:center; min-height:100vh;">

<div class="container" style="max-width: 420px;">
    <div class="gn-card" style="padding: 1.8rem 1.8rem 1.4rem;">
        <div class="text-center mb-3">
            <img src="/assets/img/logo.jpg" alt="Logo club" height="64"
                 style="border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.3);">
            <h1 class="mt-3 mb-0" style="font-size: 1.5rem; color: var(--gn-primary);">
                Sorties Club ULM Evasion
            </h1>
            <p class="gn-card-subtitle mb-0">GESTNAV - Vers. <?= defined('GESTNAV_VERSION') ? GESTNAV_VERSION : '2.1.0' ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="gn-form-group mb-3">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" required autofocus>
            </div>
            <div class="gn-form-group mb-3">
                <label for="password">Mot de passe</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="gn-btn gn-btn-primary justify-content-center">
                    <i class="bi bi-box-arrow-in-right"></i> Se connecter
                </button>
            </div>
        </form>
    </div>

    <p class="text-center mt-3" style="font-size:.75rem; color: var(--gn-muted);">
        Accès réservé aux membres du club ULM.
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
