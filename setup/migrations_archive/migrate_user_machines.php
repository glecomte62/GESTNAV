<?php
/**
 * Migration: Créer la table user_machines
 * Cette table stocke les machines club sur lesquelles chaque utilisateur est lâché
 */

require_once 'config.php';

try {
    // Vérifier si la table existe déjà
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'user_machines'");
    $tableExists = $checkStmt->rowCount() > 0;

    if ($tableExists) {
        echo "✅ La table user_machines existe déjà\n";
        
        // Vérifier les colonnes
        $colsStmt = $pdo->query("SHOW COLUMNS FROM user_machines");
        $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        echo "📋 Colonnes: " . implode(", ", $columns) . "\n";
    } else {
        echo "🔨 Création de la table user_machines...\n";
        
        $pdo->exec("
            CREATE TABLE user_machines (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                machine_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_machine (user_id, machine_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_machine_id (machine_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "✅ Table user_machines créée avec succès\n";
        
        // Vérifier les colonnes
        $colsStmt = $pdo->query("SHOW COLUMNS FROM user_machines");
        $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        echo "📋 Colonnes: " . implode(", ", $columns) . "\n";
    }
    
    echo "\n✅ Migration terminée avec succès\n";

} catch (Exception $e) {
    echo "\n❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>
