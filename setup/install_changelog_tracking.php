<?php
/**
 * Script d'installation de la table de suivi des communications de changelog
 * Permet de savoir quelle version a été communiquée et quand
 */

require_once __DIR__ . '/../config.php';

try {
    // Créer la table changelog_communications
    $sql = "CREATE TABLE IF NOT EXISTS changelog_communications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        version VARCHAR(20) NOT NULL,
        sent_at DATETIME NOT NULL,
        sender_id INT,
        recipient_count INT DEFAULT 0,
        INDEX idx_version (version),
        INDEX idx_sent_at (sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    
    echo "✅ Table changelog_communications créée avec succès\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la création de la table : " . $e->getMessage() . "\n";
    exit(1);
}
