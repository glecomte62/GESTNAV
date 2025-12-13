<?php
/**
 * Migration: Create email_history table
 * Description: Adds table to track sent emails with sender info and recipient details
 * Version: 2.0.0
 */

require_once 'config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            sender_id INT,
            sender_name VARCHAR(255),
            recipient_type VARCHAR(50),
            recipient_count INT,
            subject VARCHAR(255),
            message_preview TEXT,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sent_at (sent_at),
            INDEX idx_sender (sender_id),
            INDEX idx_recipient_type (recipient_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ Table email_history créée avec succès!\n";
    echo "Migration appliquée avec succès.\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la création de la table:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
?>
