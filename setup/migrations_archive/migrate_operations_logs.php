<?php
require_once 'config.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS operation_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        nom VARCHAR(255) NOT NULL,
        prenom VARCHAR(255) NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT NULL,
        ip_address VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_user_created (user_id, created_at),
        INDEX idx_action_created (action, created_at),
        CONSTRAINT fk_oplogs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "Table operation_logs prête.\n";
} catch (Throwable $e) {
    echo "Erreur lors de la création de operation_logs: " . $e->getMessage() . "\n";
}
