<?php
require_once 'config.php';

$status = [
    'success' => false,
    'messages' => [],
];

try {
    // Vérifier si la colonne photo_metadata existe déjà
    $colsStmt = $pdo->query('SHOW COLUMNS FROM users LIKE "photo_metadata"');
    $colExists = $colsStmt->rowCount() > 0;

    if (!$colExists) {
        $pdo->exec('ALTER TABLE users ADD COLUMN photo_metadata JSON NULL AFTER photo_path');
        $status['messages'][] = "✓ Colonne 'photo_metadata' ajoutée";
    } else {
        $status['messages'][] = "ℹ Colonne 'photo_metadata' existe déjà";
    }

    $status['success'] = true;
    $status['messages'][] = "Statut: Migration terminée avec succès ✓";
} catch (Throwable $e) {
    $status['success'] = false;
    $status['messages'][] = "✗ Erreur: " . $e->getMessage();
}

// Afficher en HTML simple
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Migration photo_metadata</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; max-width: 600px; margin: 0 auto; }
        .success { background: #e7f7ec; color: #0a8a0a; padding: 1rem; border-radius: 0.5rem; }
        .error { background: #fde8e8; color: #b02525; padding: 1rem; border-radius: 0.5rem; }
        h1 { margin-top: 0; }
        code { background: #f0f0f0; padding: 0.2rem 0.4rem; border-radius: 0.25rem; }
    </style>
</head>
<body>
    <h1>Migration: Metadata de photo</h1>
    <div class="<?= $status['success'] ? 'success' : 'error' ?>">
        <?php foreach ($status['messages'] as $msg): ?>
            <div><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
    </div>
</body>
</html>
