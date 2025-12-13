<?php
/**
 * Migration - Créer tables de sondages
 * Tables: polls, poll_options, poll_votes
 */

require_once 'config.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Table des sondages
    $sql_polls = "CREATE TABLE IF NOT EXISTS `polls` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `titre` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `type` ENUM('date', 'choix_multiple') NOT NULL DEFAULT 'choix_multiple',
        `status` ENUM('ouvert', 'clos') NOT NULL DEFAULT 'ouvert',
        `creator_id` INT NOT NULL,
        `deadline` DATETIME,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_status` (`status`),
        KEY `idx_creator` (`creator_id`),
        FOREIGN KEY (`creator_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql_polls);
    echo "✓ Table 'polls' créée/vérifiée\n";

    // Table des options de sondage
    $sql_options = "CREATE TABLE IF NOT EXISTS `poll_options` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `poll_id` INT NOT NULL,
        `text` VARCHAR(255) NOT NULL,
        `votes` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_poll` (`poll_id`),
        FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql_options);
    echo "✓ Table 'poll_options' créée/vérifiée\n";

    // Table des votes
    $sql_votes = "CREATE TABLE IF NOT EXISTS `poll_votes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `poll_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `option_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_user_poll` (`poll_id`, `user_id`),
        KEY `idx_poll` (`poll_id`),
        KEY `idx_user` (`user_id`),
        KEY `idx_option` (`option_id`),
        FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`option_id`) REFERENCES `poll_options` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql_votes);
    echo "✓ Table 'poll_votes' créée/vérifiée\n";

    echo "\n✅ Migration des sondages terminée avec succès !\n";

} catch (PDOException $e) {
    echo "❌ Erreur lors de la migration : " . $e->getMessage() . "\n";
    exit(1);
}
