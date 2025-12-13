<?php
/**
 * Installation de la table email_logs via interface web
 */
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

$output = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // Vérifier si la table existe déjà
        $checkStmt = $pdo->query("SHOW TABLES LIKE 'email_logs'");
        $tableExists = $checkStmt->rowCount() > 0;
        
        if (!$tableExists) {
            $output[] = "⏳ Création de la table email_logs...";
            
            $pdo->exec("
                CREATE TABLE email_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    subject VARCHAR(255) NOT NULL,
                    message TEXT NULL,
                    recipient_count INT DEFAULT 0,
                    sender_id INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX (created_at),
                    INDEX (sender_id)
                )
            ");
            
            $output[] = "✅ Table email_logs créée avec succès!";
            $success = true;
        } else {
            $output[] = "ℹ️ La table email_logs existe déjà.";
            
            // Vérifier si la colonne message existe
            $checkCol = $pdo->query("SHOW COLUMNS FROM email_logs LIKE 'message'");
            if ($checkCol->rowCount() === 0) {
                $output[] = "⏳ Ajout de la colonne 'message'...";
                $pdo->exec("ALTER TABLE email_logs ADD COLUMN message TEXT NULL AFTER subject");
                $output[] = "✅ Colonne 'message' ajoutée!";
                $success = true;
            } else {
                $output[] = "✅ La colonne 'message' existe déjà.";
                $success = true;
            }
        }
        
        // Créer la table email_recipients
        $output[] = "";
        $checkRecipients = $pdo->query("SHOW TABLES LIKE 'email_recipients'");
        $recipientsExists = $checkRecipients->rowCount() > 0;
        
        if (!$recipientsExists) {
            $output[] = "⏳ Création de la table email_recipients...";
            
            $pdo->exec("
                CREATE TABLE email_recipients (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email_log_id INT NOT NULL,
                    user_id INT,
                    email VARCHAR(255) NOT NULL,
                    name VARCHAR(255),
                    FOREIGN KEY (email_log_id) REFERENCES email_logs(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX (email_log_id),
                    INDEX (user_id)
                )
            ");
            
            $output[] = "✅ Table email_recipients créée!";
        } else {
            $output[] = "✅ La table email_recipients existe déjà.";
        }
        
        $output[] = "";
        $output[] = "🎉 Migration terminée avec succès!";
        
    } catch (Exception $e) {
        $output[] = "❌ Erreur: " . $e->getMessage();
        $success = false;
    }
}

require 'header.php';
?>

<style>
.install-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.install-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 2rem;
    padding: 1.5rem 1.75rem;
    border-radius: 1.25rem;
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: #fff;
    box-shadow: 0 12px 30px rgba(0,0,0,0.25);
}

.install-header h1 {
    font-size: 1.6rem;
    margin: 0;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.install-header-icon {
    font-size: 2.4rem;
    opacity: 0.9;
}

.card {
    background: #ffffff;
    border-radius: 1.25rem;
    padding: 1.75rem 1.5rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.03);
    margin-bottom: 1.5rem;
}

.card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.info-box {
    background: #f0f9ff;
    border-left: 4px solid #0284c7;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
}

.output-box {
    background: #1f2937;
    color: #e5e7eb;
    padding: 1.25rem;
    border-radius: 0.75rem;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    line-height: 1.6;
    margin-top: 1.5rem;
}

.output-box div {
    margin: 0.25rem 0;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: white;
    width: 100%;
}

.btn-primary:hover {
    filter: brightness(1.08);
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
    text-decoration: none;
    display: inline-block;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.alert-success {
    background: rgba(34,197,94,0.1);
    color: #166534;
    border-left: 4px solid #22c55e;
    padding: 1rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
}
</style>

<div class="install-page">
    <div class="install-header">
        <div>
            <h1>Installation Table email_logs</h1>
        </div>
        <div class="install-header-icon">🛠️</div>
    </div>

    <div class="card">
        <div class="card-title">📋 Installation</div>
        
        <div class="info-box">
            <strong>ℹ️ À propos</strong><br>
            Cette migration va créer ou mettre à jour la table <code>email_logs</code> qui permet de conserver l'historique des emails envoyés depuis l'application.
        </div>

        <?php if ($success): ?>
            <div class="alert-success">
                <strong>✅ Installation réussie!</strong><br>
                La table email_logs est maintenant prête à l'emploi.
            </div>
        <?php endif; ?>

        <?php if (!empty($output)): ?>
            <div class="output-box">
                <?php foreach ($output as $line): ?>
                    <div><?= htmlspecialchars($line) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" style="margin-top: 2rem;">
            <button type="submit" name="install" class="btn btn-primary">
                🚀 Exécuter la migration
            </button>
        </form>

        <div style="margin-top: 1rem; text-align: center;">
            <a href="historique_emails.php" class="btn btn-secondary">
                ← Retour à l'historique
            </a>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
