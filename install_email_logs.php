<?php
/**
 * Installation de la table email_logs pour l'historique des emails
 */

require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

$success = false;
$error = null;

try {
    // Vérifier si la table existe
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'email_logs'");
    $tableExists = $checkStmt->rowCount() > 0;
    
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT DEFAULT NULL,
                recipient VARCHAR(255) DEFAULT NULL,
                subject VARCHAR(500) NOT NULL,
                body_html TEXT,
                body_text TEXT,
                status ENUM('sent', 'failed', 'pending') DEFAULT 'sent',
                error_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_sender (sender_id),
                INDEX idx_created (created_at),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $success = true;
        $message = "✓ Table email_logs créée avec succès !";
    } else {
        // Vérifier les colonnes existantes
        $columnsStmt = $pdo->query("SHOW COLUMNS FROM email_logs");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missingColumns = [];
        $requiredColumns = ['sender_id', 'recipient', 'subject', 'body_html', 'body_text', 'status', 'created_at'];
        
        foreach ($requiredColumns as $col) {
            if (!in_array($col, $columns)) {
                $missingColumns[] = $col;
            }
        }
        
        if (!empty($missingColumns)) {
            // Ajouter les colonnes manquantes
            if (in_array('recipient', $missingColumns)) {
                $pdo->exec("ALTER TABLE email_logs ADD COLUMN recipient VARCHAR(255) DEFAULT NULL AFTER sender_id");
            }
            if (in_array('body_html', $missingColumns)) {
                $pdo->exec("ALTER TABLE email_logs ADD COLUMN body_html TEXT AFTER subject");
            }
            if (in_array('body_text', $missingColumns)) {
                $pdo->exec("ALTER TABLE email_logs ADD COLUMN body_text TEXT AFTER body_html");
            }
            if (in_array('status', $missingColumns)) {
                $pdo->exec("ALTER TABLE email_logs ADD COLUMN status ENUM('sent', 'failed', 'pending') DEFAULT 'sent' AFTER body_text");
            }
            if (in_array('error_message', $missingColumns)) {
                $pdo->exec("ALTER TABLE email_logs ADD COLUMN error_message TEXT AFTER status");
            }
            
            $success = true;
            $message = "✓ Table email_logs mise à jour avec " . count($missingColumns) . " colonne(s) ajoutée(s) !";
        } else {
            $success = true;
            $message = "✓ La table email_logs existe déjà avec toutes les colonnes nécessaires.";
        }
    }
    
} catch (Exception $e) {
    $error = "Erreur lors de l'installation : " . $e->getMessage();
}

require 'header.php';
?>

<style>
.install-page {
    max-width: 800px;
    margin: 2rem auto;
    padding: 2rem;
}
.card {
    background: #ffffff;
    border-radius: 1.25rem;
    padding: 2rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}
.success-message {
    background: #e7f7ec;
    border-left: 4px solid #22c55e;
    padding: 1.5rem;
    border-radius: 0.75rem;
    margin: 1.5rem 0;
    color: #166534;
}
.error-message {
    background: #fde8e8;
    border-left: 4px solid #ef4444;
    padding: 1.5rem;
    border-radius: 0.75rem;
    margin: 1.5rem 0;
    color: #991b1b;
}
.btn {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: white;
    text-decoration: none;
    border-radius: 999px;
    font-weight: 600;
    margin-top: 1rem;
}
.btn:hover {
    filter: brightness(1.1);
}
</style>

<div class="install-page">
    <div class="card">
        <h1>Installation de la table email_logs</h1>
        
        <?php if ($success): ?>
            <div class="success-message">
                <strong style="font-size: 1.2rem;">✓ Installation réussie</strong><br>
                <p style="margin: 0.5rem 0 0 0;"><?= htmlspecialchars($message) ?></p>
            </div>
            
            <a href="historique_emails.php" class="btn">
                📧 Voir l'historique des emails
            </a>
        <?php elseif ($error): ?>
            <div class="error-message">
                <strong style="font-size: 1.2rem;">✗ Erreur d'installation</strong><br>
                <p style="margin: 0.5rem 0 0 0;"><?= htmlspecialchars($error) ?></p>
            </div>
            
            <a href="historique_emails.php" class="btn">
                ← Retour
            </a>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
