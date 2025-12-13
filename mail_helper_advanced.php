<?php
/**
 * Helper pour l'envoi d'emails via PHPMailer avec SMTP (Brevo, etc.)
 */

require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    private $pdo;
    private $config;
    public $debugInfo = []; // Pour stocker les infos de debug
    
    public function __construct($pdo = null) {
        global $pdo;
        $this->pdo = $pdo ?? $GLOBALS['pdo'] ?? null;
        
        // Charger la config SMTP de la base de données
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query("SELECT * FROM mail_config LIMIT 1");
                $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $this->config = null;
            }
        }
    }
    
    /**
     * Envoyer un email
     * @param string $toEmail Email du destinataire
     * @param string $subject Sujet de l'email
     * @param string $htmlContent Contenu HTML
     * @param array $attachments Tableau des pièces jointes [['path' => '...', 'name' => '...'], ...]
     * @return bool
     */
    public function send($toEmail, $subject, $htmlContent, $attachments = []) {
        $this->debugInfo = []; // Reset debug
        $this->debugInfo[] = "=== ENVOI EMAIL ===";
        $this->debugInfo[] = "Destinataire: $toEmail";
        $this->debugInfo[] = "Nombre de pièces jointes: " . count($attachments);
        
        try {
            $mail = new PHPMailer(true);
            
            // Configurer SMTP
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'] ?? 'smtp-relay.brevo.com';
            $mail->Port = $this->config['smtp_port'] ?? 587;
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp_username'] ?? '';
            $mail->Password = $this->config['smtp_password'] ?? '';
            
            $this->debugInfo[] = "SMTP Host: " . $mail->Host;
            $this->debugInfo[] = "SMTP Port: " . $mail->Port;
            
            $encryption = $this->config['smtp_encryption'] ?? 'tls';
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            
            // Encodage UTF-8
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            
            // Destinataire et expéditeur
            $mail->setFrom($this->config['from_email'] ?? 'info@clubulmevasion.fr', $this->config['from_name'] ?? 'CLUB ULM EVASION');
            $mail->addAddress($toEmail);
            
            if (!empty($this->config['reply_to_email'])) {
                $mail->addReplyTo($this->config['reply_to_email'], $this->config['reply_to_name'] ?? '');
            }
            
            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlContent;
            
            // Ajouter les pièces jointes
            $attachmentCount = 0;
            foreach ($attachments as $att) {
                if (isset($att['path'])) {
                    $filePath = $att['path'];
                    $originalPath = $filePath;
                    
                    // Vérifier si le chemin est absolu
                    if (!is_file($filePath) && !is_absolute_path($filePath)) {
                        $filePath = __DIR__ . '/' . $filePath;
                    }
                    
                    $this->debugInfo[] = "Pièce jointe #" . ($attachmentCount + 1) . ":";
                    $this->debugInfo[] = "  - Chemin original: $originalPath";
                    $this->debugInfo[] = "  - Chemin final: $filePath";
                    $this->debugInfo[] = "  - Existe: " . (file_exists($filePath) ? 'OUI' : 'NON');
                    $this->debugInfo[] = "  - Lisible: " . (is_readable($filePath) ? 'OUI' : 'NON');
                    
                    if (file_exists($filePath) && is_file($filePath) && is_readable($filePath)) {
                        $fileName = $att['name'] ?? basename($filePath);
                        $this->debugInfo[] = "  - Nom fichier: $fileName";
                        $this->debugInfo[] = "  - Taille: " . filesize($filePath) . " octets";
                        $mail->addAttachment($filePath, $fileName);
                        $attachmentCount++;
                        $this->debugInfo[] = "  - ✅ Attachée avec succès";
                    } else {
                        $this->debugInfo[] = "  - ❌ ERREUR: Fichier non accessible";
                    }
                }
            }
            
            $this->debugInfo[] = "Total pièces jointes ajoutées: $attachmentCount";
            
            $result = $mail->send();
            $this->debugInfo[] = $result ? "✅ Email envoyé avec succès" : "❌ Échec de l'envoi";
            
            return $result;
        } catch (Exception $e) {
            $this->debugInfo[] = "❌ ERREUR PHPMailer: " . $e->getMessage();
            error_log("PHPMailer Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Vérifier si un chemin est absolu
 */
function is_absolute_path($path) {
    return $path[0] === '/' || (strlen($path) > 2 && $path[1] === ':');
}
