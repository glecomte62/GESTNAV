<?php
require_once 'config.php';
require_once 'auth.php';

// Vérifier que l'utilisateur est admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: acces_refuse.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Créer la table document_categories
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS document_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                icon VARCHAR(50) DEFAULT '📄',
                access_level ENUM('admin_only', 'members', 'public') DEFAULT 'members',
                display_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Créer la table documents
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                filename VARCHAR(255) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                file_size INT NOT NULL,
                file_type VARCHAR(100),
                access_level ENUM('admin_only', 'members', 'public') DEFAULT 'members',
                uploaded_by INT,
                download_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE CASCADE,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_category (category_id),
                INDEX idx_access (access_level),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Insérer des catégories par défaut
        $categories = [
            ['Factures', 'Factures et documents comptables (Starlink, assurances, etc.)', '💰', 'admin_only', 1],
            ['Documents administratifs', 'Statuts, règlement intérieur, procès-verbaux', '📋', 'members', 2],
            ['Contrats & Conventions', 'Contrats d\'assurance, conventions, accords', '📝', 'admin_only', 3],
            ['Rapports d\'activité', 'Bilans annuels, rapports de sorties', '📊', 'members', 4],
            ['Documents techniques', 'Manuels machines, procédures de sécurité', '🔧', 'members', 5],
            ['Formations', 'Supports de formation, tutoriels', '🎓', 'members', 6],
            ['Photos & Médias', 'Photos de sorties, vidéos promotionnelles', '📸', 'public', 7]
        ];

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO document_categories (name, description, icon, access_level, display_order)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($categories as $cat) {
            $stmt->execute($cat);
        }

        // Créer le répertoire uploads/documents si nécessaire
        $upload_dir = __DIR__ . '/uploads/documents';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Créer la table de logs
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS document_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                action VARCHAR(50) NOT NULL,
                document_id INT,
                category_id INT,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
                FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL,
                INDEX idx_user (user_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $message = "✅ Tables créées avec succès ! Catégories par défaut ajoutées.";
    } catch (Exception $e) {
        $error = "❌ Erreur : " . $e->getMessage();
    }
}

require 'header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">📁 Installation GED - Gestion de Documents</h3>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?php echo $message; ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <p>Cette installation va créer :</p>
                    <ul>
                        <li><strong>Table document_categories</strong> : Catégories de documents avec niveaux d'accès</li>
                        <li><strong>Table documents</strong> : Stockage des fichiers uploadés</li>
                        <li><strong>Table document_logs</strong> : Historique de toutes les actions</li>
                        <li><strong>Répertoire uploads/documents/</strong> : Dossier de stockage</li>
                        <li><strong>7 catégories par défaut</strong> avec permissions appropriées</li>
                    </ul>

                    <h5 class="mt-4">Niveaux d'accès :</h5>
                    <ul>
                        <li><span class="badge bg-danger">admin_only</span> : Réservé aux administrateurs</li>
                        <li><span class="badge bg-primary">members</span> : Tous les membres connectés</li>
                        <li><span class="badge bg-success">public</span> : Accessible sans connexion</li>
                    </ul>

                    <form method="POST" class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            🚀 Installer la GED
                        </button>
                    </form>

                    <?php if ($message): ?>
                        <div class="mt-4 text-center">
                            <a href="documents_admin.php" class="btn btn-success me-2">📁 Administration des documents</a>
                            <a href="documents.php" class="btn btn-info">👁️ Vue utilisateur</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
