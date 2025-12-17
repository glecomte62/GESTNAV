<?php
/**
 * Script de mise à jour de la table event_comments
 * Ajoute le champ parent_id pour permettre les réponses
 * 
 * À exécuter si la table event_comments existe déjà sans le champ parent_id
 */

require 'config.php';

try {
    // Vérifier si la table existe
    $check = $pdo->query("SHOW TABLES LIKE 'event_comments'");
    if ($check->rowCount() == 0) {
        echo "❌ La table event_comments n'existe pas.<br>";
        echo "Veuillez d'abord exécuter install_event_comments.php<br>";
        exit(1);
    }
    
    // Vérifier si la colonne parent_id existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM event_comments LIKE 'parent_id'");
    if ($stmt->rowCount() > 0) {
        echo "✅ La colonne parent_id existe déjà !<br>";
        echo "Aucune mise à jour nécessaire.<br>";
        exit(0);
    }
    
    // Ajouter la colonne parent_id
    $pdo->exec("ALTER TABLE event_comments 
                ADD COLUMN parent_id INT DEFAULT NULL AFTER user_id,
                ADD FOREIGN KEY (parent_id) REFERENCES event_comments(id) ON DELETE CASCADE,
                ADD INDEX idx_parent_id (parent_id)");
    
    echo "✅ Mise à jour réussie !<br>";
    echo "<br>";
    echo "Modifications apportées :<br>";
    echo "- Ajout de la colonne parent_id (pour les réponses aux commentaires)<br>";
    echo "- Ajout de la clé étrangère vers event_comments(id)<br>";
    echo "- Ajout de l'index sur parent_id<br>";
    echo "<br>";
    echo "✅ La fonctionnalité de réponses aux commentaires est maintenant active !<br>";
    echo "<br>";
    echo "⚠️ Supprimez ce fichier après l'exécution :<br>";
    echo "<code>rm update_event_comments_add_replies.php</code>";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la mise à jour : " . htmlspecialchars($e->getMessage());
    exit(1);
}
?>
