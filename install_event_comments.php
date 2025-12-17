<?php
/**
 * Script d'installation de la table event_comments
 * Pour la gestion des commentaires sur les événements
 * 
 * Exécuter ce fichier une seule fois pour créer la table
 */

require 'config.php';

try {
    // Création de la table event_comments
    $sql = "CREATE TABLE IF NOT EXISTS event_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        user_id INT NOT NULL,
        parent_id INT DEFAULT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT NULL,
        FOREIGN KEY (event_id) REFERENCES evenements(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES event_comments(id) ON DELETE CASCADE,
        INDEX idx_event_id (event_id),
        INDEX idx_parent_id (parent_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    
    echo "✅ Table event_comments créée avec succès!<br>";
    echo "<br>";
    echo "Structure de la table:<br>";
    echo "- id: Identifiant unique du commentaire<br>";
    echo "- event_id: ID de l'événement (lien avec evenements)<br>";
    echo "- user_id: ID de l'utilisateur (lien avec users)<br>";
    echo "- parent_id: ID du commentaire parent (NULL si commentaire principal)<br>";
    echo "- comment: Texte du commentaire<br>";
    echo "- created_at: Date de création<br>";
    echo "- updated_at: Date de modification (optionnel)<br>";
    echo "<br>";
    echo "✅ Installation terminée!<br>";
    echo "<br>";
    echo "⚠️ IMPORTANT: Pour des raisons de sécurité, supprimez ce fichier après l'installation:<br>";
    echo "<code>rm install_event_comments.php</code>";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la création de la table: " . htmlspecialchars($e->getMessage());
    exit(1);
}
?>
