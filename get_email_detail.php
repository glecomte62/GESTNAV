<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['error' => 'ID invalide']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            el.*,
            u.nom,
            u.prenom
        FROM email_logs el
        LEFT JOIN users u ON u.id = el.sender_id
        WHERE el.id = ?
    ");
    $stmt->execute([$id]);
    $email = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$email) {
        echo json_encode(['error' => 'Email non trouvé']);
        exit;
    }
    
    // Récupérer les destinataires (avec gestion d'erreur si la table n'existe pas)
    $recipients = [];
    try {
        $recipientsStmt = $pdo->prepare("
            SELECT name, email 
            FROM email_recipients 
            WHERE email_log_id = ? 
            ORDER BY name
        ");
        $recipientsStmt->execute([$id]);
        $recipients = $recipientsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table email_recipients n'existe pas encore
        $recipients = [];
    }
    
    // Préparer les données pour le frontend
    $response = [
        'id' => $email['id'],
        'subject' => $email['subject'],
        'message' => $email['message'] ?? '',
        'sender' => trim($email['prenom'] . ' ' . $email['nom']),
        'recipient_count' => (int)$email['recipient_count'],
        'recipients' => $recipients,
        'created_at' => $email['created_at']
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur lors de la récupération: ' . $e->getMessage()]);
}
