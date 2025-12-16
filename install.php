<?php
/**
 * GESTNAV - Installation Wizard
 * Guide d'installation pas à pas pour déployer l'application
 */

session_start();

// Vérifier si l'installation est déjà faite
if (file_exists(__DIR__ . '/config.php') && !isset($_GET['force'])) {
    die('
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Installation déjà effectuée - GESTNAV</title>
        <style>
            body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
            .container { background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; text-align: center; }
            h1 { color: #667eea; margin: 0 0 1rem; }
            p { color: #666; margin-bottom: 1.5rem; }
            a { display: inline-block; padding: 0.75rem 2rem; background: #667eea; color: white; text-decoration: none; border-radius: 0.5rem; font-weight: 600; }
            a:hover { background: #5568d3; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>✅ Installation déjà effectuée</h1>
            <p>GESTNAV est déjà installé sur ce serveur.</p>
            <p>Si vous souhaitez réinstaller l\'application, supprimez le fichier <code>config.php</code> ou ajoutez <code>?force=1</code> à l\'URL.</p>
            <a href="index.php">Accéder à GESTNAV</a>
        </div>
    </body>
    </html>
    ');
}

// Initialiser la session d'installation
if (!isset($_SESSION['install_step'])) {
    $_SESSION['install_step'] = 1;
    $_SESSION['install_data'] = [];
}

$step = $_SESSION['install_step'];
$data = $_SESSION['install_data'];
$error = '';
$success = '';

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'check_requirements') {
        $_SESSION['install_step'] = 2;
        header('Location: install.php');
        exit;
    }
    
    if ($action === 'save_db_config') {
        $host = trim($_POST['db_host'] ?? 'localhost');
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = $_POST['db_password'] ?? '';
        
        if (empty($name) || empty($user)) {
            $error = 'Le nom de la base et l\'utilisateur sont obligatoires';
        } else {
            // Tester la connexion
            try {
                $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                
                $_SESSION['install_data']['db_host'] = $host;
                $_SESSION['install_data']['db_name'] = $name;
                $_SESSION['install_data']['db_user'] = $user;
                $_SESSION['install_data']['db_password'] = $pass;
                $_SESSION['install_step'] = 3;
                
                header('Location: install.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Connexion impossible : ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'create_tables') {
        try {
            $dsn = "mysql:host={$data['db_host']};dbname={$data['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $data['db_user'], $data['db_password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            
            // Lire et exécuter le fichier schema.sql
            $schemaFile = __DIR__ . '/setup/schema.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                $pdo->exec($sql);
                $_SESSION['install_step'] = 4;
                header('Location: install.php');
                exit;
            } else {
                $error = 'Fichier schema.sql introuvable';
            }
        } catch (PDOException $e) {
            $error = 'Erreur lors de la création des tables : ' . $e->getMessage();
        }
    }
    
    if ($action === 'create_admin') {
        $prenom = trim($_POST['admin_prenom'] ?? '');
        $nom = trim($_POST['admin_nom'] ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $password = $_POST['admin_password'] ?? '';
        $password_confirm = $_POST['admin_password_confirm'] ?? '';
        
        if (empty($prenom) || empty($nom) || empty($email) || empty($password)) {
            $error = 'Tous les champs sont obligatoires';
        } elseif ($password !== $password_confirm) {
            $error = 'Les mots de passe ne correspondent pas';
        } elseif (strlen($password) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères';
        } else {
            try {
                $dsn = "mysql:host={$data['db_host']};dbname={$data['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $data['db_user'], $data['db_password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (prenom, nom, email, password_hash, type_membre, admin, actif, created_at) VALUES (?, ?, ?, ?, 'club', 1, 1, NOW())");
                $stmt->execute([$prenom, $nom, $email, $hashedPassword]);
                
                $_SESSION['install_data']['admin_email'] = $email;
                $_SESSION['install_step'] = 5;
                header('Location: install.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Erreur lors de la création de l\'administrateur : ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'create_config') {
        try {
            $configContent = "<?php
/**
 * Configuration de GESTNAV
 * Généré automatiquement par l'assistant d'installation
 */

// Configuration de la base de données
define('DB_HOST', '{$data['db_host']}');
define('DB_NAME', '{$data['db_name']}');
define('DB_USER', '{$data['db_user']}');
define('DB_PASS', '{$data['db_password']}');

// Connexion PDO
try {
    \$pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException \$e) {
    die('Erreur de connexion à la base de données: ' . \$e->getMessage());
}

// Configuration du club (à personnaliser via l'interface admin)
define('CLUB_NAME', 'Club ULM Evasion');
define('CLUB_EMAIL', 'info@clubulmevasion.fr');
define('CLUB_PHONE', '');
define('CLUB_ADDRESS', '');

// Timezone
date_default_timezone_set('Europe/Paris');

// Version de l'application
define('GESTNAV_VERSION', '2.4.1');
";
            
            file_put_contents(__DIR__ . '/config.php', $configContent);
            $_SESSION['install_step'] = 6;
            header('Location: install.php');
            exit;
        } catch (Exception $e) {
            $error = 'Erreur lors de la création du fichier config.php : ' . $e->getMessage();
        }
    }
    
    if ($action === 'finish') {
        // Nettoyer la session d'installation
        unset($_SESSION['install_step']);
        unset($_SESSION['install_data']);
        header('Location: index.php');
        exit;
    }
}

// Fonction pour vérifier les prérequis
function checkRequirements() {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'Extension PDO' => extension_loaded('pdo'),
        'Extension PDO MySQL' => extension_loaded('pdo_mysql'),
        'Extension MBString' => extension_loaded('mbstring'),
        'Extension GD (images)' => extension_loaded('gd'),
        'Dossier uploads/ accessible en écriture' => is_writable(__DIR__ . '/uploads'),
        'Dossier racine accessible en écriture' => is_writable(__DIR__),
    ];
    
    return $requirements;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation GESTNAV - Étape <?= $step ?>/6</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 2rem;
            border-radius: 1rem 1rem 0 0;
            text-align: center;
        }
        .header h1 {
            color: #667eea;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        .progress {
            background: #f0f0f0;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 1.5rem;
        }
        .progress-bar {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            transition: width 0.3s;
            width: <?= ($step / 6 * 100) ?>%;
        }
        .steps {
            background: white;
            padding: 0 2rem;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
        }
        .step-item {
            flex: 1;
            min-width: 100px;
            text-align: center;
            padding: 1rem 0.5rem;
            font-size: 0.85rem;
            color: #999;
            position: relative;
        }
        .step-item.active {
            color: #667eea;
            font-weight: 600;
        }
        .step-item.completed {
            color: #10b981;
        }
        .step-item::before {
            content: attr(data-step);
            display: block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            margin: 0 auto 0.5rem;
            border-radius: 50%;
            background: #e0e0e0;
            color: white;
            font-weight: 600;
        }
        .step-item.active::before {
            background: #667eea;
        }
        .step-item.completed::before {
            content: '✓';
            background: #10b981;
        }
        .content {
            background: white;
            padding: 2rem;
            border-radius: 0 0 1rem 1rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .alert-error {
            background: #fee;
            border-left: 4px solid #f00;
            color: #c00;
        }
        .alert-success {
            background: #efe;
            border-left: 4px solid #0a0;
            color: #060;
        }
        .alert-info {
            background: #eef;
            border-left: 4px solid #00a;
            color: #006;
        }
        .requirements {
            list-style: none;
        }
        .requirements li {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .requirements li.ok {
            background: #efe;
            color: #060;
        }
        .requirements li.error {
            background: #fee;
            color: #c00;
        }
        .requirements li::before {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .requirements li.ok::before {
            content: '✓';
        }
        .requirements li.error::before {
            content: '✗';
        }
        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: flex-end;
        }
        .card {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .card h3 {
            color: #667eea;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Installation de GESTNAV</h1>
            <p>Assistant de déploiement - Étape <?= $step ?> sur 6</p>
            <div class="progress">
                <div class="progress-bar"></div>
            </div>
        </div>
        
        <div class="steps">
            <div class="step-item <?= $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' ?>" data-step="1">Prérequis</div>
            <div class="step-item <?= $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' ?>" data-step="2">Base de données</div>
            <div class="step-item <?= $step >= 3 ? ($step > 3 ? 'completed' : 'active') : '' ?>" data-step="3">Tables</div>
            <div class="step-item <?= $step >= 4 ? ($step > 4 ? 'completed' : 'active') : '' ?>" data-step="4">Admin</div>
            <div class="step-item <?= $step >= 5 ? ($step > 5 ? 'completed' : 'active') : '' ?>" data-step="5">Configuration</div>
            <div class="step-item <?= $step >= 6 ? 'active' : '' ?>" data-step="6">Terminé</div>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
                <h2>Vérification des prérequis</h2>
                <p style="margin: 1rem 0; color: #666;">Avant de commencer l'installation, vérifions que votre serveur dispose de tout le nécessaire.</p>
                
                <ul class="requirements">
                    <?php foreach (checkRequirements() as $label => $status): ?>
                        <li class="<?= $status ? 'ok' : 'error' ?>">
                            <?= $label ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <?php 
                $allOk = !in_array(false, checkRequirements(), true);
                if (!$allOk): 
                ?>
                    <div class="alert alert-error" style="margin-top: 1.5rem;">
                        <strong>⚠️ Certains prérequis ne sont pas satisfaits</strong><br>
                        Veuillez corriger les problèmes avant de continuer.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success" style="margin-top: 1.5rem;">
                        <strong>✅ Tous les prérequis sont satisfaits !</strong><br>
                        Vous pouvez passer à l'étape suivante.
                    </div>
                <?php endif; ?>
                
                <div class="actions">
                    <form method="post">
                        <input type="hidden" name="action" value="check_requirements">
                        <button type="submit" class="btn btn-primary" <?= !$allOk ? 'disabled' : '' ?>>
                            Continuer →
                        </button>
                    </form>
                </div>
                
            <?php elseif ($step === 2): ?>
                <h2>Configuration de la base de données</h2>
                <p style="margin: 1rem 0; color: #666;">Entrez les informations de connexion à votre base de données MySQL.</p>
                
                <div class="alert alert-info">
                    💡 <strong>Astuce :</strong> Créez d'abord une base de données vide sur votre serveur MySQL avant de continuer.
                </div>
                
                <form method="post">
                    <input type="hidden" name="action" value="save_db_config">
                    
                    <div class="form-group">
                        <label class="form-label">Hôte de la base de données</label>
                        <input type="text" name="db_host" class="form-input" value="localhost" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nom de la base de données</label>
                        <input type="text" name="db_name" class="form-input" placeholder="gestnav" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Utilisateur</label>
                        <input type="text" name="db_user" class="form-input" placeholder="root" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="db_password" class="form-input">
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Tester & Continuer →</button>
                    </div>
                </form>
                
            <?php elseif ($step === 3): ?>
                <h2>Création des tables</h2>
                <p style="margin: 1rem 0; color: #666;">Nous allons maintenant créer toutes les tables nécessaires dans votre base de données.</p>
                
                <div class="card">
                    <h3>✅ Connexion établie</h3>
                    <p><strong>Hôte :</strong> <?= htmlspecialchars($data['db_host']) ?></p>
                    <p><strong>Base :</strong> <?= htmlspecialchars($data['db_name']) ?></p>
                    <p><strong>Utilisateur :</strong> <?= htmlspecialchars($data['db_user']) ?></p>
                </div>
                
                <div class="alert alert-info">
                    ℹ️ Cette opération va créer toutes les tables (users, sorties, machines, documents, etc.) à partir du fichier <code>setup/schema.sql</code>
                </div>
                
                <form method="post">
                    <input type="hidden" name="action" value="create_tables">
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Créer les tables →</button>
                    </div>
                </form>
                
            <?php elseif ($step === 4): ?>
                <h2>Création du compte administrateur</h2>
                <p style="margin: 1rem 0; color: #666;">Créez le premier compte administrateur qui vous permettra d'accéder à l'application.</p>
                
                <form method="post">
                    <input type="hidden" name="action" value="create_admin">
                    
                    <div class="form-group">
                        <label class="form-label">Prénom</label>
                        <input type="text" name="admin_prenom" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nom</label>
                        <input type="text" name="admin_nom" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="admin_email" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Mot de passe (min. 6 caractères)</label>
                        <input type="password" name="admin_password" class="form-input" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirmation du mot de passe</label>
                        <input type="password" name="admin_password_confirm" class="form-input" required>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Créer l'administrateur →</button>
                    </div>
                </form>
                
            <?php elseif ($step === 5): ?>
                <h2>Création du fichier de configuration</h2>
                <p style="margin: 1rem 0; color: #666;">Dernière étape : création du fichier config.php avec vos paramètres.</p>
                
                <div class="card">
                    <h3>📋 Récapitulatif de l'installation</h3>
                    <p><strong>Base de données :</strong> <?= htmlspecialchars($data['db_name']) ?> @ <?= htmlspecialchars($data['db_host']) ?></p>
                    <p><strong>Administrateur :</strong> <?= htmlspecialchars($data['admin_email'] ?? 'Créé') ?></p>
                </div>
                
                <div class="alert alert-info">
                    ℹ️ Le fichier <code>config.php</code> sera créé à la racine de l'application avec vos paramètres de connexion.
                </div>
                
                <form method="post">
                    <input type="hidden" name="action" value="create_config">
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Créer la configuration →</button>
                    </div>
                </form>
                
            <?php elseif ($step === 6): ?>
                <h2>🎉 Installation terminée !</h2>
                <p style="margin: 1rem 0; color: #666;">GESTNAV est maintenant installé et prêt à être utilisé.</p>
                
                <div class="card">
                    <h3>✅ Installation réussie</h3>
                    <p>Toutes les étapes ont été complétées avec succès :</p>
                    <ul style="margin: 1rem 0; padding-left: 2rem;">
                        <li>✓ Base de données configurée</li>
                        <li>✓ Tables créées</li>
                        <li>✓ Compte administrateur créé</li>
                        <li>✓ Fichier config.php généré</li>
                    </ul>
                </div>
                
                <div class="alert alert-info">
                    🔐 <strong>Prochaines étapes :</strong><br>
                    1. Connectez-vous avec votre compte administrateur<br>
                    2. Accédez à la configuration générale pour personnaliser votre club<br>
                    3. Ajoutez vos premiers membres et machines<br>
                    4. <strong>Important :</strong> Supprimez ou renommez le fichier <code>install.php</code> pour des raisons de sécurité
                </div>
                
                <div class="card" style="background: #fffbeb; border-left: 4px solid #f59e0b;">
                    <h3 style="color: #f59e0b;">⚠️ Sécurité</h3>
                    <p>Pour sécuriser votre installation, supprimez le fichier <code>install.php</code> dès maintenant :</p>
                    <pre style="background: #fff; padding: 0.5rem; border-radius: 0.25rem; margin-top: 0.5rem;">rm install.php</pre>
                </div>
                
                <form method="post">
                    <input type="hidden" name="action" value="finish">
                    <div class="actions">
                        <button type="submit" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2.5rem;">
                            🚀 Accéder à GESTNAV
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
