<?php
/**
 * Migration: Creer table sortie_proposals pour les sorties proposees par les membres
 */

require_once 'config.php';

$pdo = $pdo ?? null;
if (!$pdo instanceof PDO) {
    die('Connexion base de donnees indisponible.');
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sortie_proposals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            titre VARCHAR(255) NOT NULL,
            description TEXT,
            month_proposed VARCHAR(50),
            aerodrome_id INT,
            ulm_base_id INT,
            restaurant_choice VARCHAR(255),
            restaurant_details TEXT,
            activity_details TEXT,
            photo_filename VARCHAR(255),
            status ENUM('en_attente', 'accepte', 'en_preparation', 'validee', 'rejetee') DEFAULT 'en_attente',
            admin_notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (aerodrome_id) REFERENCES aerodromes_fr(id) ON DELETE SET NULL,
            FOREIGN KEY (ulm_base_id) REFERENCES ulm_bases_fr(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        )
    ");
    
    echo "Table sortie_proposals creee avec succes!\n";
    
} catch (Exception $e) {
    echo "Erreur lors de la creation de la table: " . $e->getMessage() . "\n";
    exit(1);
}
?>
