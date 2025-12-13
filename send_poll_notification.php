<?php
/**
 * API - Envoyer notification sondage
 * Endpoint pour les administrateurs pour notifier les membres d'un sondage
 */

require_once 'config.php';
require_once 'auth.php';
require_login();

// Vérifier que c'est un admin
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $poll_id = intval($_POST['poll_id'] ?? 0);
    $recipient_type = $_POST['recipient_type'] ?? 'all';

    // Récupérer le sondage
    $stmt = $pdo->prepare("SELECT * FROM polls WHERE id = ? AND creator_id = ?");
    $stmt->execute([$poll_id, $_SESSION['user_id']]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) {
        throw new Exception('Sondage non trouvé');
    }

    // Récupérer les destinataires
    $query = "SELECT id, email, prenom, nom FROM users WHERE 1=1";
    $params = [];

    if ($recipient_type === 'actif') {
        $query .= " AND actif = 1";
    } elseif ($recipient_type === 'club') {
        $query .= " AND type_membre = 'Club'";
    } elseif ($recipient_type === 'invite') {
        $query .= " AND type_membre = 'Invité'";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        throw new Exception('Aucun destinataire trouvé');
    }

    // Construire l'email
    $subject = "🗳️ Nouveau sondage: " . $poll['titre'];
    
    $message = "<h2>" . htmlspecialchars($poll['titre']) . "</h2>";
    if (!empty($poll['description'])) {
        $message .= "<p>" . htmlspecialchars($poll['description']) . "</p>";
    }
    
    $message .= "<p><strong>Nous vous invitons à participer à ce sondage !</strong></p>";
    $message .= "<p><a href='https://gestnav.clubulmevasion.fr/sondages.php' style='display: inline-block; background: #00a0c6; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;'>🗳️ Accéder aux sondages</a></p>";
    
    if ($poll['deadline']) {
        $message .= "<p><em>⏰ Date limite: " . date('d/m/Y à H:i', strtotime($poll['deadline'])) . "</em></p>";
    }

    // Envoyer les emails
    require_once 'mail_helper.php';

    // Enregistrer dans la base de données
    $stmt = $pdo->prepare("INSERT INTO email_logs (subject, message, recipient_count, sender_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$subject, $message, count($users), $_SESSION['user_id']]);
    $email_log_id = $pdo->lastInsertId();

    // Enregistrer les destinataires
    foreach ($users as $user) {
        $stmt = $pdo->prepare("INSERT INTO email_recipients (email_log_id, user_id, email, name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email_log_id, $user['id'], $user['email'], $user['prenom'] . ' ' . $user['nom']]);
    }

    // Envoyer les emails
    $sent_count = 0;
    $errors = [];
    
    foreach ($users as $user) {
        $to = $user['email'];
        $user_message = "<p>Bonjour " . htmlspecialchars($user['prenom']) . ",</p>" . $message;

        $result = gestnav_send_mail($pdo, $to, $subject, $user_message);
        
        if ($result['success']) {
            $sent_count++;
        } else {
            $errors[] = $to . ': ' . $result['error'];
        }
    }

    if ($sent_count > 0) {
        echo json_encode([
            'success' => true,
            'message' => "✅ Notification envoyée à $sent_count membre(s)",
            'count' => $sent_count
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "❌ Aucun email envoyé. Erreurs: " . implode(', ', $errors),
            'count' => 0
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "❌ Erreur: " . $e->getMessage()
    ]);
}
