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
    
    // Si pas de destinataires dans la table dédiée, essayer de récupérer depuis le champ recipients de email_logs
    if (empty($recipients) && !empty($email['recipients'])) {
        // Le champ recipients peut contenir soit une liste d'emails séparés par virgule, soit du JSON
        $recipientsData = $email['recipients'];
        if (is_string($recipientsData)) {
            // Essayer de décoder si c'est du JSON
            $decoded = json_decode($recipientsData, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // C'est du JSON
                foreach ($decoded as $r) {
                    if (is_array($r) && isset($r['email'])) {
                        $recipients[] = [
                            'name' => $r['name'] ?? $r['email'],
                            'email' => $r['email']
                        ];
                    }
                }
            } else {
                // C'est probablement une liste d'emails séparés par virgule
                $emails = array_filter(array_map('trim', explode(',', $recipientsData)));
                foreach ($emails as $emailAddr) {
                    $recipients[] = [
                        'name' => $emailAddr,
                        'email' => $emailAddr
                    ];
                }
            }
        }
    }
    
    // Compter les destinataires
    $recipientCount = count($recipients);
    if ($recipientCount === 0 && !empty($email['recipient_count'])) {
        $recipientCount = (int)$email['recipient_count'];
    }
    
    // Préparer les données pour le frontend
    $response = [
        'id' => $email['id'],
        'subject' => $email['subject'],
        'message' => $email['message'] ?? '',
        'sender' => trim($email['prenom'] . ' ' . $email['nom']),
        'recipient_count' => $recipientCount,
        'recipients' => $recipients,
        'created_at' => $email['created_at']
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur lors de la récupération: ' . $e->getMessage()]);
}
