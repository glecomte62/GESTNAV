<?php
// Script pour copier les photos existantes de preinscriptions vers uploads/
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

$fixed = 0;
$errors = [];

try {
    // Récupérer les colonnes de la table users
    $colsStmt = $pdo->query('SHOW COLUMNS FROM users');
    $userCols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $hasPhotoPath = in_array('photo_path', $userCols);
    $hasPhoto = in_array('photo', $userCols);
    
    // Construire la requête SELECT dynamiquement
    $selectCols = ['u.id as user_id'];
    if ($hasPhotoPath) $selectCols[] = 'u.photo_path';
    if ($hasPhoto) $selectCols[] = 'u.photo';
    $selectCols[] = 'p.photo_filename';
    $selectCols[] = 'p.nom';
    $selectCols[] = 'p.prenom';
    
    // Récupérer tous les utilisateurs avec leur pré-inscription
    $stmt = $pdo->query("
        SELECT " . implode(', ', $selectCols) . "
        FROM users u
        LEFT JOIN preinscriptions p ON p.user_id = u.id AND p.statut = 'validee'
        WHERE p.photo_filename IS NOT NULL
    ");
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $sourcePath = __DIR__ . '/uploads/preinscriptions/' . $user['photo_filename'];
        
        // Vérifier si la photo source existe
        if (!file_exists($sourcePath)) {
            $errors[] = "Photo source introuvable pour {$user['prenom']} {$user['nom']}: {$user['photo_filename']}";
            continue;
        }
        
        // Vérifier si l'utilisateur a déjà une photo dans uploads/
        $hasExistingPhoto = false;
        if ($hasPhotoPath && !empty($user['photo_path']) && file_exists(__DIR__ . '/' . $user['photo_path'])) {
            $hasExistingPhoto = true;
        } elseif ($hasPhoto && !empty($user['photo']) && file_exists(__DIR__ . '/uploads/' . $user['photo'])) {
            $hasExistingPhoto = true;
        }
        
        if ($hasExistingPhoto) {
            continue; // Photo déjà copiée
        }
        
        // Copier la photo
        $ext = pathinfo($user['photo_filename'], PATHINFO_EXTENSION);
        $newFilename = 'member_' . $user['user_id'] . '_' . time() . '.' . $ext;
        $destPath = __DIR__ . '/uploads/' . $newFilename;
        
        @mkdir(__DIR__ . '/uploads', 0755, true);
        
        if (copy($sourcePath, $destPath)) {
            // Mettre à jour la base de données
            if ($hasPhotoPath) {
                $updateStmt = $pdo->prepare("UPDATE users SET photo_path = ? WHERE id = ?");
                $updateStmt->execute(['uploads/' . $newFilename, $user['user_id']]);
            } elseif ($hasPhoto) {
                $updateStmt = $pdo->prepare("UPDATE users SET photo = ? WHERE id = ?");
                $updateStmt->execute([$newFilename, $user['user_id']]);
            }
            
            $fixed++;
        } else {
            $errors[] = "Erreur copie pour {$user['prenom']} {$user['nom']}";
        }
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Correction Photos - GESTNAV</title>
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
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #004b8d;
            margin-top: 0;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid #dc3545;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #004b8d, #00a0c6);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 1rem;
        }
        .btn:hover {
            filter: brightness(1.1);
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔧 Correction des photos membres</h1>
        
        <?php if ($fixed > 0): ?>
            <div class="success">
                ✅ <strong><?= $fixed ?></strong> photo<?= $fixed > 1 ? 's' : '' ?> copiée<?= $fixed > 1 ? 's' : '' ?> avec succès !
            </div>
        <?php else: ?>
            <div class="success">
                ℹ️ Aucune photo à copier (toutes les photos sont déjà en place).
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <strong>Erreurs rencontrées :</strong>
                <ul style="margin: 0.5rem 0 0 0;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <a href="membres.php" class="btn">← Retour aux membres</a>
    </div>
</body>
</html>
