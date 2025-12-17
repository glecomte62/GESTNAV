<?php
require_once 'config.php';
require_once 'auth.php';

// Vérifier que l'utilisateur est admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: acces_refuse.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Ajouter la colonne machine_id si elle n'existe pas
        $pdo->exec("
            ALTER TABLE documents 
            ADD COLUMN IF NOT EXISTS machine_id INT NULL AFTER category_id,
            ADD FOREIGN KEY IF NOT EXISTS fk_doc_machine (machine_id) REFERENCES machines(id) ON DELETE SET NULL
        ");
        
        // Ajouter un index pour machine_id
        $pdo->exec("ALTER TABLE documents ADD INDEX IF NOT EXISTS idx_machine (machine_id)");
        
        // Ajouter une colonne pour les tags de recherche
        $pdo->exec("ALTER TABLE documents ADD COLUMN IF NOT EXISTS search_tags TEXT NULL");
        
        // Ajouter un index FULLTEXT pour la recherche
        $pdo->exec("ALTER TABLE documents ADD FULLTEXT INDEX IF NOT EXISTS ft_search (title, description, original_filename)");
        
        $message = '✅ Base de données mise à jour avec succès ! Vous pouvez maintenant lier des documents aux machines et utiliser la recherche.';
    } catch (PDOException $e) {
        $message = '❌ Erreur : ' . $e->getMessage();
    }
}

require 'header.php';
?>

<div class="container mt-4">
    <div class="section">
        <div class="section-header">
            <h2>🔧 Mise à jour - Documents par machine</h2>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?= strpos($message, '✅') !== false ? 'alert-success' : 'alert-danger' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <h5>📝 Cette mise à jour va :</h5>
            <ul>
                <li>Ajouter une colonne <code>machine_id</code> pour lier les documents aux machines</li>
                <li>Ajouter une colonne <code>search_tags</code> pour améliorer la recherche</li>
                <li>Créer un index FULLTEXT pour la recherche rapide</li>
            </ul>
        </div>
        
        <form method="POST">
            <button type="submit" class="btn btn-primary btn-lg">
                🚀 Lancer la mise à jour
            </button>
            <a href="documents_admin.php" class="btn btn-secondary btn-lg">
                ← Retour
            </a>
        </form>
    </div>
</div>

<?php require 'footer.php'; ?>
