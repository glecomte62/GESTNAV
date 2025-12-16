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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/gestnav.css">
    
    <style>
        :root {
            --login-primary: #003a64;
            --login-secondary: #0a548b;
            --login-accent: #f0a500;
        }
        
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #003a64 0%, #0a548b 100%);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background circles */
        body::before {
            content: '';
            position: absolute;
            width: 800px;
            height: 800px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -400px;
            right: -400px;
            animation: float 20s ease-in-out infinite;
        }
        
        body::after {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -300px;
            left: -300px;
            animation: float 15s ease-in-out infinite reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(50px, 50px); }
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
            position: relative;
            z-index: 10;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 
                        0 0 0 1px rgba(255, 255, 255, 0.2) inset;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.35);
        }
        
        .logo-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1.5rem;
        }
        
        .logo-container::after {
            content: '';
            position: absolute;
            inset: -5px;
            background: linear-gradient(135deg, var(--login-primary), var(--login-accent));
            border-radius: 16px;
            opacity: 0.2;
            z-index: -1;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.2; }
            50% { transform: scale(1.05); opacity: 0.3; }
        }
        
        .logo-img {
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            display: block;
            transition: transform 0.3s ease;
        }
        
        .logo-container:hover .logo-img {
            transform: scale(1.05);
        }
        
        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--login-primary), var(--login-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .form-floating {
            margin-bottom: 1.25rem;
        }
        
        .form-floating input {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-floating input:focus {
            border-color: var(--login-primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        .form-floating label {
            padding: 1.25rem 1rem;
            color: #94a3b8;
        }
        
        .btn-login {
            width: 100%;
            padding: 0.875rem 1.5rem;
            font-size: 1.05rem;
            font-weight: 600;
            background: linear-gradient(135deg, var(--login-primary), var(--login-accent));
            border: none;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
            background: linear-gradient(135deg, var(--login-secondary), var(--login-primary));
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert-danger {
            background: #fee2e2;
            border: 2px solid #fecaca;
            border-radius: 12px;
            color: #991b1b;
            padding: 0.875rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            animation: shake 0.4s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.85rem;
            font-weight: 500;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .version-badge {
            display: inline-block;
            background: var(--login-accent);
            padding: 0.3rem 0.85rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-top: 0.5rem;
            color: #1f2933;
            font-weight: 600;
            border: none;
            box-shadow: 0 2px 8px rgba(240, 165, 0, 0.3);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="text-center">
                <div class="logo-container">
                    <img src="/assets/img/logo.jpg" alt="Logo club" height="80" class="logo-img">
                </div>
                <h1 class="login-title">
                    Sorties Club ULM Evasion
                </h1>
                <p class="login-subtitle">
                    Bienvenue sur GESTNAV
                </p>
                <div class="text-center mt-2">
                    <span class="version-badge">Version <?= defined('GESTNAV_VERSION') ? GESTNAV_VERSION : '2.2.0' ?></span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" placeholder="nom@exemple.com" required autofocus>
                    <label for="email"><i class="bi bi-envelope me-2"></i>Email</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Mot de passe" required>
                    <label for="password"><i class="bi bi-key me-2"></i>Mot de passe</label>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Se connecter</span>
                </button>
            </form>
        </div>

        <p class="footer-text">
            <i class="bi bi-shield-lock me-1"></i>
            Accès réservé aux membres du club ULM
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
