<?php
require_once 'config.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS connection_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        nom VARCHAR(255) NOT NULL,
        prenom VARCHAR(255) NOT NULL,
        ip_address VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_user_created (user_id, created_at),
        CONSTRAINT fk_connlogs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "Table connection_logs prête.\n";
} catch (Throwable $e) {
    echo "Erreur lors de la création de connection_logs: " . $e->getMessage() . "\n";
}
