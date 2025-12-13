<?php
/**
 * Installation du système d'emails
 * À exécuter une fois sur le serveur production
 */

require_once 'config.php';
require_once 'auth.php';

// Vérifier que c'est un admin
if (!isset($_SESSION['user_id'])) {
    die('⚠️ Vous devez être connecté');
}

// Récupérer l'utilisateur
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    die('⚠️ Vous devez être administrateur');
}

try {
    // Vérifier si la table existe
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'email_logs'");
    $tableExists = $checkStmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<h2>Création de la table email_logs...</h2>";
        
        $pdo->exec("
            CREATE TABLE email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject VARCHAR(255) NOT NULL,
                recipient_count INT DEFAULT 0,
                sender_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX (created_at),
                INDEX (sender_id)
            )
        ");
        
        echo "<p style='color: green; font-size: 18px;'>✓ Table email_logs créée avec succès!</p>";
    } else {
        echo "<p style='color: green; font-size: 18px;'>✓ La table email_logs existe déjà.</p>";
    }
    
    echo "<p style='margin-top: 20px;'><a href='envoyer_email.php' style='padding: 10px 20px; background: #004b8d; color: white; text-decoration: none; border-radius: 5px;'>Retour au module email</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-size: 16px;'>✕ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit(1);
}
?>
