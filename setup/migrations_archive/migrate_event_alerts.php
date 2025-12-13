<?php
/**
 * Migration: système d'alerte email pour sorties/événements publiés
 * Crée les tables nécessaires pour gérer les notifications
 */
require_once 'config.php';

$migrations = [];

// Table: event_alerts (historique des alertes envoyées)
$migrations[] = [
    'name' => 'Create event_alerts table',
    'sql' => "CREATE TABLE IF NOT EXISTS event_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_type ENUM('sortie', 'evenement') NOT NULL,
        event_id INT NOT NULL,
        event_title VARCHAR(255) NOT NULL,
        sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        recipient_count INT DEFAULT 0,
        success_count INT DEFAULT 0,
        failed_count INT DEFAULT 0,
        INDEX idx_event (event_type, event_id),
        INDEX idx_sent_at (sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

// Table: event_alert_optouts (utilisateurs qui se retirent des alertes)
$migrations[] = [
    'name' => 'Create event_alert_optouts table',
    'sql' => "CREATE TABLE IF NOT EXISTS event_alert_optouts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        opted_out_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reason TEXT,
        opt_in_token VARCHAR(64) UNIQUE,
        notes VARCHAR(255),
        INDEX idx_user (user_id),
        INDEX idx_opted_out_at (opted_out_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

// Table: event_alert_logs (log détaillé des envois)
$migrations[] = [
    'name' => 'Create event_alert_logs table',
    'sql' => "CREATE TABLE IF NOT EXISTS event_alert_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alert_id INT NOT NULL,
        user_id INT NOT NULL,
        email VARCHAR(255),
        status ENUM('sent', 'failed', 'skipped') DEFAULT 'failed',
        error_message TEXT,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_alert (alert_id),
        INDEX idx_user (user_id),
        INDEX idx_status (status),
        FOREIGN KEY (alert_id) REFERENCES event_alerts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

// Exécution des migrations
$executed = 0;
$errors = 0;

foreach ($migrations as $migration) {
    try {
        $pdo->exec($migration['sql']);
        echo "✓ {$migration['name']}\n";
        $executed++;
    } catch (Throwable $e) {
        echo "✗ {$migration['name']}: {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\n=== Résumé ===\n";
echo "Exécutées: $executed\n";
echo "Erreurs: $errors\n";
