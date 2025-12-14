<?php
// Chargement de PHPMailer (version sans Composer)
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Récupère la configuration SMTP active.
 *
 * @param PDO $pdo
 * @return array|null
 */
function gestnav_get_mail_config(PDO $pdo): ?array
{
    $stmt = $pdo->query("SELECT * FROM mail_config WHERE active = 1 LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    return $config ?: null;
}

/**
 * Envoie un e-mail en utilisant la configuration SMTP de GestNav.
 *
 * @param PDO $pdo
 * @param string|array $to       Destinataire(s)
 * @param string $subject        Sujet
 * @param string $html_body      Corps HTML
 * @param string|null $text_body Version texte (facultative)
 * @param array $attachments     Pièces jointes ['filename' => 'content'] ou options (cc, bcc, reply_to…)
 *
 * @return array ['success' => bool, 'error' => string|null]
 */
function gestnav_send_mail(PDO $pdo, $to, string $subject, string $html_body, ?string $text_body = null, array $attachments = []): array
{
    $config = gestnav_get_mail_config($pdo);

    if (!$config) {
        return [
            'success' => false,
            'error'   => "Configuration SMTP inactive ou non trouvée."
        ];
    }

    $mail = new PHPMailer(true);

    // IMPORTANT : encodage correct pour les accents
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';

    try {
        // ---- CONFIG SMTP ----
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->Port       = (int)$config['smtp_port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_username'];
        $mail->Password   = $config['smtp_password'];

        // Chiffrement
        $enc = $config['smtp_encryption'] ?? 'tls';
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false; // Aucune sécurité
        }

        // ---- EXPÉDITEUR ----
        $fromEmail = $config['from_email'];
        $fromName  = $config['from_name'] ?: $config['from_email'];

        $mail->setFrom($fromEmail, $fromName);

        // Reply-To
        $replyToEmail = $attachments['reply_to_email'] ?? $config['reply_to_email'] ?? $fromEmail;
        $replyToName  = $attachments['reply_to_name'] ?? $config['reply_to_name'] ?? $fromName;

        if ($replyToEmail) {
            $mail->addReplyTo($replyToEmail, $replyToName);
        }

        // ---- FONCTION POUR AJOUTER LES DESTINATAIRES ----
        $addRecipients = function ($list, callable $adder) {
            if (!$list) return;

            if (is_string($list)) {
                $adder($list, '');
            } elseif (is_array($list)) {
                foreach ($list as $email => $name) {
                    if (is_int($email)) {
                        $adder($name, '');
                    } else {
                        $adder($email, $name);
                    }
                }
            }
        };

        // To
        $addRecipients($to, function ($email, $name) use ($mail) {
            $mail->addAddress($email, $name);
        });

        // CC
        if (isset($attachments['cc'])) {
            $addRecipients($attachments['cc'], function ($email, $name) use ($mail) {
                $mail->addCC($email, $name);
            });
        }

        // BCC
        if (isset($attachments['bcc'])) {
            $addRecipients($attachments['bcc'], function ($email, $name) use ($mail) {
                $mail->addBCC($email, $name);
            });
        }

        // ---- CONTENU ----
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;

        // Version texte lisible (évite duplication du contenu HTML)
        if ($text_body === null) {
            $text_body = "Un nouveau message vous attend dans GestNav.";
        }

        $mail->AltBody = $text_body;

        // ---- PIÈCES JOINTES ----
        // Supporte ['filename.ext' => 'content'] pour les pièces jointes inline
        foreach ($attachments as $key => $value) {
            // Ignorer les options (cc, bcc, reply_to...)
            if (in_array($key, ['cc', 'bcc', 'reply_to_email', 'reply_to_name'])) {
                continue;
            }
            
            // Si c'est un nom de fichier avec du contenu
            if (is_string($key) && is_string($value)) {
                $mail->addStringAttachment($value, $key);
            }
        }

        // ---- ENVOI ----
        $mail->send();

        // ---- LOG DANS email_logs ----
        try {
            // Extraire les destinataires pour le log
            $recipients = [];
            if (is_string($to)) {
                $recipients[] = $to;
            } elseif (is_array($to)) {
                foreach ($to as $email => $name) {
                    $recipients[] = is_int($email) ? $name : $email;
                }
            }
            $recipient_emails = implode(', ', $recipients);
            
            // Enregistrer dans email_logs
            $sender_id = $_SESSION['user_id'] ?? null;
            $stmtLog = $pdo->prepare("INSERT INTO email_logs (sender_id, recipient_email, subject, message_html, message_text, status, created_at) VALUES (?, ?, ?, ?, ?, 'sent', NOW())");
            $stmtLog->execute([
                $sender_id,
                $recipient_emails,
                $subject,
                $html_body,
                $text_body
            ]);
        } catch (PDOException $e) {
            // Si la table n'existe pas, on ignore l'erreur (l'email est quand même envoyé)
            error_log("Impossible d'enregistrer l'email dans email_logs: " . $e->getMessage());
        }

        return [
            'success' => true,
            'error'   => null
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error'   => "Erreur d'envoi : " . $mail->ErrorInfo
        ];
    }
}
