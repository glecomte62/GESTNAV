<?php
/**
 * Helpers pour le système d'alertes email
 */

function gestnav_event_alert_is_opted_out(PDO $pdo, int $user_id): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM event_alert_optouts WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

function gestnav_generate_optout_token(): string {
    return bin2hex(random_bytes(32));
}

function gestnav_send_event_alert(PDO $pdo, string $event_type, int $event_id, array $event_data, string $event_url): array {
    require_once __DIR__ . '/../mail_helper.php';
    
    $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'alert_id' => 0];

    try {
        // Créer l'enregistrement d'alerte
        $stmt = $pdo->prepare("INSERT INTO event_alerts (event_type, event_id, event_title) VALUES (?, ?, ?)");
        $stmt->execute([$event_type, $event_id, $event_data['titre'] ?? 'Sans titre']);
        $alert_id = (int)$pdo->lastInsertId();
        $result['alert_id'] = $alert_id;

        // Récupérer tous les utilisateurs actifs
        $stmt = $pdo->prepare("SELECT id, email, prenom, nom FROM users WHERE email IS NOT NULL AND email != '' ORDER BY prenom, nom");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $recipient_count = count($users);

        // Envoyer les alertes
        foreach ($users as $user) {
            $user_id = (int)$user['id'];
            $email = (string)$user['email'];

            // Vérifier l'opt-out
            if (gestnav_event_alert_is_opted_out($pdo, $user_id)) {
                $log_stmt = $pdo->prepare("INSERT INTO event_alert_logs (alert_id, user_id, email, status) VALUES (?, ?, ?, 'skipped')");
                $log_stmt->execute([$alert_id, $user_id, $email]);
                $result['skipped']++;
                continue;
            }

            // Générer le token de désinscription
            $optout_token = gestnav_generate_optout_token();

            // Construire les URLs
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $baseUrl = $scheme . $host . $baseDir;

            $optout_url = htmlspecialchars($baseUrl . '/event_alert_optout.php?token=' . urlencode($optout_token), ENT_QUOTES, 'UTF-8');
            $full_event_url = htmlspecialchars($event_url, ENT_QUOTES, 'UTF-8');

            // Construire l'email
            $full_name = trim($user['prenom'] . ' ' . $user['nom']);
            $subject = '🔔 Nouvelle sortie/événement : ' . htmlspecialchars($event_data['titre']);

            $date_sortie = !empty($event_data['date_sortie']) ? htmlspecialchars($event_data['date_sortie']) : 'À définir';
            $destination = !empty($event_data['destination_label']) ? htmlspecialchars($event_data['destination_label']) : 'À définir';

            $html_body = "
                <html>
                <head><meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #222; }
                    .container { max-width: 600px; margin: 0 auto; }
                    .header { background: linear-gradient(135deg, #004b8d, #00a0c6); color: #ffffff; padding: 30px 20px; border-radius: 8px 8px 0 0; text-align: center; }
                    .header h2 { margin: 0; font-size: 1.8rem; }
                    .header p { margin: 5px 0 0; opacity: 0.9; }
                    .content { padding: 30px 20px; background: #f7f9fc; }
                    .event-card { background: #ffffff; border-left: 5px solid #00a0c6; padding: 20px; margin: 20px 0; border-radius: 6px; }
                    .event-card strong { color: #004b8d; }
                    .btn { display: inline-block; padding: 14px 24px; border-radius: 6px; font-weight: 600; text-decoration: none; margin: 8px 8px 8px 0; }
                    .btn-primary { background: linear-gradient(135deg, #004b8d, #00a0c6); color: #ffffff; }
                    .actions { text-align: center; margin: 25px 0; }
                    .footer { background: #ffffff; padding: 20px; border-radius: 0 0 8px 8px; color: #666; font-size: 12px; }
                    .optout-link { color: #999; text-decoration: none; font-size: 11px; }
                </style>
                </head>
                <body>
                <div class='container'>
                    <div class='header'>
                        <h2>🔔 Nouvelle sortie/événement</h2>
                        <p>Vous êtes invité à participer !</p>
                    </div>
                    <div class='content'>
                        <p>Bonjour <strong>$full_name</strong>,</p>
                        <p>Une nouvelle sortie/événement intéressante vient d'être publiée sur la plateforme !</p>
                        <div class='event-card'>
                            <p><strong>✈️ " . htmlspecialchars($event_data['titre']) . "</strong></p>
                            <p style='margin: 10px 0;'><strong>📅 Date :</strong> $date_sortie</p>
                            <p style='margin: 10px 0;'><strong>📍 Destination :</strong> $destination</p>
                            " . (!empty($event_data['description']) ? "<p style='margin: 10px 0;'>" . htmlspecialchars(substr($event_data['description'], 0, 150)) . "...</p>" : "") . "
                        </div>
                        <div class='actions'>
                            <a href='$full_event_url' class='btn btn-primary' style='color: #ffffff !important;'>👁️ Voir la sortie</a>
                        </div>
                    </div>
                    <div class='footer'>
                        <p style='margin: 0 0 10px;'>Vous avez changé d'avis et ne souhaitez plus recevoir ces alertes ?</p>
                        <a href='$optout_url' class='optout-link'>Cliquez ici pour vous désinscrire des alertes</a>
                        <p style='margin: 15px 0 0;'>Ce message a été envoyé automatiquement. Veuillez ne pas répondre directement.<br>
                        Pour toute question : <strong>info@clubulmevasion.fr</strong></p>
                    </div>
                </div>
                </body>
                </html>
            ";

            $text_body = "Bonjour $full_name,\n\n"
                       . "Une nouvelle sortie/événement intéressante vient d'être publiée !\n\n"
                       . "✈️ " . $event_data['titre'] . "\n"
                       . "📅 Date : $date_sortie\n"
                       . "📍 Destination : $destination\n\n"
                       . "Pour consulter les détails et vous inscrire, cliquez ici :\n"
                       . strip_tags($full_event_url) . "\n\n"
                       . "Vous ne souhaitez plus recevoir ces alertes ?\n"
                       . strip_tags($optout_url) . "\n\n"
                       . "À bientôt,\nLe club ULM";

            // Envoyer l'email
            $mail_result = gestnav_send_mail($pdo, $email, $subject, $html_body, $text_body);

            if ($mail_result['success']) {
                $log_stmt = $pdo->prepare("INSERT INTO event_alert_logs (alert_id, user_id, email, status) VALUES (?, ?, ?, 'sent')");
                $log_stmt->execute([$alert_id, $user_id, $email]);
                $result['sent']++;
            } else {
                $log_stmt = $pdo->prepare("INSERT INTO event_alert_logs (alert_id, user_id, email, status, error_message) VALUES (?, ?, ?, 'failed', ?)");
                $log_stmt->execute([$alert_id, $user_id, $email, $mail_result['error'] ?? 'Unknown error']);
                $result['failed']++;
            }
        }

        // Mettre à jour les stats de l'alerte
        $update_stmt = $pdo->prepare("UPDATE event_alerts SET recipient_count = ?, success_count = ?, failed_count = ? WHERE id = ?");
        $update_stmt->execute([$recipient_count, $result['sent'], $result['failed'], $alert_id]);

    } catch (Throwable $e) {
        error_log("Erreur gestnav_send_event_alert: " . $e->getMessage());
    }

    return $result;
}
