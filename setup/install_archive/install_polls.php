<?php
/**
 * Installateur Web - Sondages
 * Crée les tables de sondages via le navigateur
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$success = false;
$message = '';
$pdo = null;

// Charger les paramètres de configuration
$config_file = __DIR__ . '/config.php';
if (file_exists($config_file)) {
    // Charger config.php pour obtenir les variables $host, $db, $user, $pass, et $pdo
    require_once $config_file;
}

// Vérifier que $pdo est défini (config.php crée la connexion)
if (!isset($pdo) || $pdo === null) {
    $message = "❌ Erreur de configuration: Impossible de se connecter à la base de données. Vérifiez config.php";
    $success = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($pdo) || $message !== '') {
        $message = "❌ Erreur: Configuration invalide ou connexion impossible";
    } else {
        try {
        // Table des sondages
        $sql_polls = "CREATE TABLE IF NOT EXISTS `polls` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `titre` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `type` ENUM('date', 'choix_multiple') NOT NULL DEFAULT 'choix_multiple',
            `status` ENUM('ouvert', 'clos') NOT NULL DEFAULT 'ouvert',
            `creator_id` INT NOT NULL,
            `deadline` DATETIME,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY `idx_status` (`status`),
            KEY `idx_creator` (`creator_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql_polls);
        $message .= "✓ Table 'polls' créée\n";

        // Table des options
        $sql_options = "CREATE TABLE IF NOT EXISTS `poll_options` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `poll_id` INT NOT NULL,
            `text` VARCHAR(255) NOT NULL,
            `votes` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_poll` (`poll_id`),
            FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql_options);
        $message .= "✓ Table 'poll_options' créée\n";

        // Table des votes
        $sql_votes = "CREATE TABLE IF NOT EXISTS `poll_votes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `poll_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `option_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_user_poll` (`poll_id`, `user_id`),
            KEY `idx_poll` (`poll_id`),
            KEY `idx_user` (`user_id`),
            KEY `idx_option` (`option_id`),
            FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`option_id`) REFERENCES `poll_options` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql_votes);
        $message .= "✓ Table 'poll_votes' créée\n";
        $success = true;
        $message .= "\n✅ Migration des sondages terminée avec succès !";
        } catch (Exception $e) {
            $message = "❌ Erreur : " . $e->getMessage();
            $success = false;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer - Sondages</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #004b8d 0%, #00a0c6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 1rem;
        }

        .container {
            background: white;
            border-radius: 1.25rem;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        h1 {
            color: #004b8d;
            margin: 0 0 1rem;
            font-size: 1.8rem;
        }

        p {
            color: #666;
            margin: 0 0 2rem;
        }

        form {
            margin: 2rem 0;
        }

        button {
            background: linear-gradient(135deg, #004b8d 0%, #00a0c6 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 75, 141, 0.3);
        }

        .output {
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-word;
            color: #333;
        }

        .output.success {
            background: #f0fdf4;
            border-color: #86efac;
            color: #166534;
        }

        .output.error {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }

        .success-message {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: 0.5rem;
            color: #166534;
        }

        .error-message {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: 0.5rem;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗳️ Installer - Module Sondages</h1>
        <p>Créez les tables de base de données pour le système de sondages</p>

        <?php if ($message): ?>
            <div class="output <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php if ($success): ?>
                <div class="success-message">
                    ✅ Les tables de sondages ont été créées avec succès ! Le module est prêt à être utilisé.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$success): ?>
            <form method="POST">
                <button type="submit">🚀 Exécuter la migration</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
