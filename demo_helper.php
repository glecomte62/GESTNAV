<?php
/**
 * Helper pour la gestion du compte de démonstration
 * 
 * Fonctions utilitaires pour détecter et gérer le compte démo
 */

/**
 * Vérifie si l'utilisateur actuel est le compte de démonstration
 */
function is_demo_user() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Vérifier par email (plus fiable)
    if (isset($_SESSION['email'])) {
        return $_SESSION['email'] === 'demo@clubulmevasion.fr';
    }
    
    // Vérification par ID si nécessaire
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user && $user['email'] === 'demo@clubulmevasion.fr';
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Affiche un bandeau d'avertissement pour le mode démo
 */
function show_demo_banner() {
    if (!is_demo_user()) {
        return;
    }
    ?>
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0.75rem 1rem; text-align: center; font-weight: 500; border-bottom: 3px solid #5a67d8; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <i class="bi bi-eye"></i> MODE DÉMONSTRATION - Vos actions ne modifieront pas les données réelles
    </div>
    <?php
}

/**
 * Bloque une action si l'utilisateur est en mode démo
 * @param string $message Message à afficher si bloqué
 * @param string $redirect_url URL de redirection (optionnel)
 */
function block_demo_action($message = "Cette action n'est pas disponible en mode démonstration.", $redirect_url = null) {
    if (!is_demo_user()) {
        return false; // Pas en mode démo, continuer normalement
    }
    
    if ($redirect_url) {
        $_SESSION['demo_message'] = $message;
        header("Location: $redirect_url");
        exit;
    } else {
        die("<div style='max-width: 600px; margin: 100px auto; padding: 2rem; background: #fff3e0; border-radius: 8px; border-left: 4px solid #ff9800;'>
                <h3 style='color: #f57c00; margin-top: 0;'><i class='bi bi-lock'></i> Action bloquée</h3>
                <p style='color: #666;'>$message</p>
                <a href='javascript:history.back()' class='btn btn-secondary'>Retour</a>
             </div>");
    }
}

/**
 * Affiche un message de démonstration si présent
 */
function show_demo_message() {
    if (isset($_SESSION['demo_message'])) {
        echo '<div class="alert alert-warning"><i class="bi bi-lock"></i> ' . htmlspecialchars($_SESSION['demo_message']) . '</div>';
        unset($_SESSION['demo_message']);
    }
}

/**
 * Protège une action destructive (suppression, modification importante)
 * Retourne true si l'action doit être bloquée
 */
function is_demo_protected() {
    return is_demo_user();
}
?>
