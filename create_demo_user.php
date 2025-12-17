<?php
/**
 * Script de création d'un utilisateur de démonstration
 * Permet de tester l'application sans impact sur les données réelles
 * 
 * Identifiants :
 * Email: demo@clubulmevasion.fr
 * Mot de passe: Demo2024!
 */

require 'config.php';

try {
    // Vérifier si l'utilisateur demo existe déjà
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['demo@clubulmevasion.fr']);
    
    if ($stmt->fetch()) {
        echo "⚠️ L'utilisateur de démonstration existe déjà !<br>";
        echo "<br>";
        echo "Pour réinitialiser le mot de passe, supprimez d'abord l'utilisateur :<br>";
        echo "<code>DELETE FROM users WHERE email = 'demo@clubulmevasion.fr';</code><br>";
        exit(0);
    }
    
    // Créer l'utilisateur demo
    $password = password_hash('Demo2024!', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users 
        (email, password, nom, prenom, role, statut, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->execute([
        'demo@clubulmevasion.fr',
        $password,
        'DÉMONSTRATION',
        'Compte',
        'member', // Rôle membre standard
        'actif'
    ]);
    
    $demo_user_id = $pdo->lastInsertId();
    
    echo "✅ Utilisateur de démonstration créé avec succès !<br>";
    echo "<br>";
    echo "<div style='background: #e3f2fd; padding: 1rem; border-radius: 6px; border-left: 4px solid #2196f3;'>";
    echo "<strong>📋 Identifiants de connexion :</strong><br>";
    echo "Email : <strong>demo@clubulmevasion.fr</strong><br>";
    echo "Mot de passe : <strong>Demo2024!</strong><br>";
    echo "</div>";
    echo "<br>";
    echo "<div style='background: #fff3e0; padding: 1rem; border-radius: 6px; border-left: 4px solid #ff9800;'>";
    echo "<strong>⚠️ Recommandations :</strong><br>";
    echo "- Ce compte a un rôle 'member' (membre standard)<br>";
    echo "- Il peut consulter toutes les pages accessibles aux membres<br>";
    echo "- Pour limiter l'impact, vous pouvez :<br>";
    echo "  &nbsp;&nbsp;• Créer des données de test séparées<br>";
    echo "  &nbsp;&nbsp;• Ajouter une vérification dans le code pour bloquer certaines actions<br>";
    echo "  &nbsp;&nbsp;• Utiliser un badge visuel 'MODE DÉMO' dans l'interface<br>";
    echo "</div>";
    echo "<br>";
    echo "<div style='background: #f3e5f5; padding: 1rem; border-radius: 6px; border-left: 4px solid #9c27b0;'>";
    echo "<strong>🔒 Sécurité :</strong><br>";
    echo "- Changez régulièrement le mot de passe<br>";
    echo "- Surveillez les actions de ce compte<br>";
    echo "- Supprimez ce fichier après création :<br>";
    echo "  <code>rm create_demo_user.php</code>";
    echo "</div>";
    echo "<br>";
    echo "<a href='login.php' class='btn btn-primary'>Se connecter avec le compte démo</a>";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la création : " . htmlspecialchars($e->getMessage());
    exit(1);
}
?>
