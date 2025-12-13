<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();
require_admin();

$result = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute'])) {
    try {
        $sql = file_get_contents(__DIR__ . '/create_preinscriptions_table.sql');
        $pdo->exec($sql);
        $result = "✅ Table preinscriptions créée avec succès !";
    } catch (Exception $e) {
        $error = "❌ Erreur : " . $e->getMessage();
    }
}

// Vérifier si la table existe
$tableExists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'preinscriptions'");
    $tableExists = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $error = "Erreur de vérification : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Migration - Preinscriptions</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .btn {
            background: #004b8d;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
        }
        .btn:hover {
            background: #003d73;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Migration - Table preinscriptions</h1>
        
        <?php if ($result): ?>
            <div class="success"><?= $result ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($tableExists): ?>
            <div class="info">
                ✅ La table <code>preinscriptions</code> existe déjà dans la base de données.
            </div>
            <p><a href="preinscriptions_admin.php">→ Accéder à l'interface de gestion</a></p>
        <?php else: ?>
            <div class="info">
                ⚠️ La table <code>preinscriptions</code> n'existe pas encore.
            </div>
            <p>Cette migration va créer la table nécessaire pour gérer les pré-inscriptions publiques au club.</p>
            
            <form method="post">
                <button type="submit" name="execute" class="btn">🚀 Exécuter la migration</button>
            </form>
        <?php endif; ?>
        
        <hr style="margin: 2rem 0;">
        <p><a href="index.php">← Retour à l'accueil</a></p>
    </div>
</body>
</html>
