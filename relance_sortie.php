<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();
require_once 'mail_helper.php';

$sortie_id = (int)($_GET['sortie_id'] ?? 0);

if ($sortie_id <= 0) {
    header('Location: sorties.php');
    exit;
}

// Récupérer les infos de la sortie
$stmt = $pdo->prepare("SELECT * FROM sorties WHERE id = ?");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sortie) {
    header('Location: sorties.php');
    exit;
}

// Récupérer la destination si elle existe
$destination_label = '';
$hasDestinationId = false;
$hasUlmBaseId = false;
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM sorties LIKE 'destination_id'");
    if ($colCheck && $colCheck->fetch()) {
        $hasDestinationId = true;
    }
    $colCheck2 = $pdo->query("SHOW COLUMNS FROM sorties LIKE 'ulm_base_id'");
    if ($colCheck2 && $colCheck2->fetch()) {
        $hasUlmBaseId = true;
    }
} catch (Throwable $e) {
    $hasDestinationId = false;
    $hasUlmBaseId = false;
}

if ($hasUlmBaseId && !empty($sortie['ulm_base_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT oaci, nom, ville FROM ulm_bases_fr WHERE id = ? LIMIT 1");
        $stmt->execute([$sortie['ulm_base_id']]);
        $rowUlm = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rowUlm) {
            $destination_label = trim(($rowUlm['oaci'] ?? '') . ' – ' . ($rowUlm['nom'] ?? ''));
            if (!empty($rowUlm['ville'])) {
                $destination_label .= ' (' . $rowUlm['ville'] . ')';
            }
        }
    } catch (Throwable $e) { }
}

$sent_count = 0;
$failed_count = 0;

// Récupérer tous les membres actifs du club
try {
    $stmt = $pdo->prepare("SELECT id, prenom, nom, email FROM users WHERE actif = 1 AND email IS NOT NULL AND email != ''");
    $stmt->execute();
    $membres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // URL de base
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $baseUrl = $scheme . $host . $baseDir;
    $sortie_url = htmlspecialchars($baseUrl . '/sortie_detail.php?sortie_id=' . $sortie_id, ENT_QUOTES, 'UTF-8');
    $preinscription_url = htmlspecialchars($baseUrl . '/preinscription_sortie.php?sortie_id=' . $sortie_id, ENT_QUOTES, 'UTF-8');
    
    // Créer UNE seule entrée dans email_logs pour toute la campagne
    $campaign_subject = '🆘 Appel à solidarité : ' . htmlspecialchars($sortie['titre']);
    $email_log_id = null;
    
    try {
        $stmt_campaign = $pdo->prepare("
            INSERT INTO email_logs (sender_id, subject, body_html, body_text, status, created_at)
            VALUES (?, ?, ?, ?, 'sent', NOW())
        ");
        // On utilisera le body_html du premier email comme référence
        $stmt_campaign->execute([
            $_SESSION['user_id'] ?? null,
            $campaign_subject,
            '', // sera mis à jour avec le premier email
            '' // sera mis à jour avec le premier email
        ]);
        $email_log_id = $pdo->lastInsertId();
        
        // Créer la table email_recipients si elle n'existe pas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_recipients (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email_log_id INT NOT NULL,
                name VARCHAR(255),
                email VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (email_log_id) REFERENCES email_logs(id) ON DELETE CASCADE,
                INDEX idx_email_log (email_log_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {
        error_log("Erreur création log campagne: " . $e->getMessage());
    }
    
    $first_email = true;
    
    foreach ($membres as $membre) {
        $full_name = trim($membre['prenom'] . ' ' . $membre['nom']);
        
        $subject = '🆘 Appel à solidarité : ' . htmlspecialchars($sortie['titre']);
        
        $html_body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background-color: #f5f5f5;'>
        <tr>
            <td style='padding: 20px 0;'>
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='600' style='margin: 0 auto; background-color: #ffffff;'>
                    
                    <!-- Header -->
                    <tr>
                        <td style='padding: 30px 40px; background-color: #f59e0b; text-align: center;'>
                            <p style='font-size: 40px; margin: 0 0 10px 0;'>🤝</p>
                            <h2 style='margin: 0; color: #ffffff; font-size: 24px; font-weight: bold;'>Appel à la solidarité du club</h2>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style='padding: 30px 40px; background-color: #f7f9fc;'>
                            <p style='font-size: 16px; line-height: 1.6; color: #333333; margin: 0 0 15px 0;'>
                                Bonjour <strong>" . htmlspecialchars($full_name) . "</strong>,
                            </p>
                            
                            <p style='font-size: 16px; line-height: 1.6; color: #333333; margin: 0 0 15px 0;'>
                                Nous avons besoin de ton aide pour la sortie <strong>" . htmlspecialchars($sortie['titre']) . "</strong> 
                                du <strong>" . htmlspecialchars(date('d/m/Y', strtotime($sortie['date_sortie']))) . "</strong>" .
                                ($destination_label ? " vers <strong>" . htmlspecialchars($destination_label) . "</strong>" : "") . " !
                            </p>
                            
                            <!-- Highlight Box -->
                            <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                                <tr>
                                    <td style='background-color: #fff3cd; border-left: 4px solid #f59e0b; padding: 15px;'>
                                        <p style='margin: 0; font-size: 15px; line-height: 1.5; color: #333333;'>
                                            <strong>🎯 La situation</strong><br>
                                            Nous avons encore des places disponibles, mais nous sommes confrontés à un défi :<br>
                                        </p>
                                        <ul style='margin: 10px 0; padding-left: 20px; color: #333333;'>
                                            <li>Plusieurs <strong>élèves pilotes</strong> souhaitent participer</li>
                                            <li>Certains pilotes n'ont <strong>pas d'emport passager</strong></li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style='font-size: 16px; line-height: 1.6; color: #333333; margin: 15px 0;'>
                                <strong>🙏 Nous faisons appel à ton bon cœur !</strong>
                            </p>
                            
                            <p style='font-size: 16px; line-height: 1.6; color: #333333; margin: 0 0 10px 0;'>
                                Si tu es <strong>qualifié sur Corvus avec emport PAX</strong>, tu pourrais grandement nous aider en acceptant de prendre avec toi :
                            </p>
                            <ul style='margin: 10px 0 15px 0; padding-left: 20px; color: #333333;'>
                                <li>✈️ Un autre pilote (sans emport passager)</li>
                                <li>👨‍🎓 Un élève pilote du club</li>
                            </ul>
                            
                            <p style='font-size: 16px; line-height: 1.6; color: #333333; margin: 0 0 15px 0;'>
                                Ton geste permettrait à <strong>un maximum de personnes de participer</strong> à cette sortie et de renforcer l'esprit d'équipe de notre club !
                            </p>
                            
                            <!-- Pilotes propriétaires -->
                            <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                                <tr>
                                    <td style='background-color: #e0f2fe; border-left: 4px solid #0284c7; padding: 15px;'>
                                        <p style='margin: 0; font-size: 15px; line-height: 1.5; color: #333333;'>
                                            <strong>✈️ Pilotes propriétaires</strong><br>
                                            Si tu possèdes ta propre machine, tu es également le(la) bienvenu(e) ! 
                                            Viens avec ton appareil et emmène l'un des participants qui n'aurait pas de place autrement. 
                                            Ta contribution serait précieuse pour permettre à plus de monde de voler ! 🙏
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Pourquoi c'est important -->
                            <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                                <tr>
                                    <td style='background-color: #e7f7ec; border-left: 4px solid #22c55e; padding: 15px;'>
                                        <p style='margin: 0; font-size: 15px; line-height: 1.5; color: #333333;'>
                                            <strong>💚 Pourquoi c'est important</strong><br>
                                            Chaque vol partagé est une opportunité de transmettre ton expérience, de créer des liens et de faire vivre notre passion commune. 
                                            En acceptant d'emporter un autre membre, tu contribues directement à l'apprentissage et à la cohésion de notre club.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Call to Action -->
                    <tr>
                        <td style='padding: 30px 40px; background-color: #ffffff; text-align: center;'>
                            <p style='font-size: 18px; margin: 0 0 20px 0; color: #333333;'><strong>Intéressé(e) pour aider ?</strong></p>
                            
                            <!-- Bouton 1 -->
                            <table role='presentation' cellspacing='0' cellpadding='0' border='0' align='center' style='margin: 15px auto;'>
                                <tr>
                                    <td align='center' style='border-radius: 30px; background-color: #0066cc;'>
                                        <a href='" . $preinscription_url . "' target='_blank' style='font-size: 17px; font-family: Arial, sans-serif; color: #ffffff; text-decoration: none; padding: 15px 30px; border-radius: 30px; display: inline-block; font-weight: bold;'>
                                            <span style='color: #ffffff;'>📝 Pré-inscription avec préférences</span>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Bouton 2 -->
                            <table role='presentation' cellspacing='0' cellpadding='0' border='0' align='center' style='margin: 15px auto;'>
                                <tr>
                                    <td align='center' style='border-radius: 30px; background-color: #22c55e;'>
                                        <a href='" . $sortie_url . "' target='_blank' style='font-size: 17px; font-family: Arial, sans-serif; color: #ffffff; text-decoration: none; padding: 15px 30px; border-radius: 30px; display: inline-block; font-weight: bold;'>
                                            <span style='color: #ffffff;'>✈️ Voir la sortie</span>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style='margin-top: 20px; font-size: 15px; color: #666666;'>
                                Ou contacte-nous directement si tu as des questions
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style='padding: 20px 40px; background-color: #ffffff; text-align: center; border-top: 1px solid #e6ebf2;'>
                            <p style='margin: 0; font-size: 14px; color: #333333;'>Merci pour ta générosité et ton engagement !</p>
                            <p style='margin: 5px 0 0 0; font-size: 14px; color: #333333;'>L'équipe du Club ULM Evasion</p>
                            <p style='margin: 15px 0 0 0; font-size: 12px; color: #999999;'>
                                Ce mail a été envoyé automatiquement.<br>
                                Pour toute question : <strong>info@clubulmevasion.fr</strong>
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        ";
        
        $text_body = "Bonjour $full_name,\n\n"
                   . "Nous avons besoin de ton aide pour la sortie " . $sortie['titre'] . " du " . date('d/m/Y', strtotime($sortie['date_sortie'])) . " !\n\n"
                   . "LA SITUATION\n"
                   . "Nous avons encore des places disponibles, mais nous sommes confrontés à un défi :\n"
                   . "- Plusieurs élèves pilotes souhaitent participer\n"
                   . "- Certains pilotes n'ont pas d'emport passager\n\n"
                   . "NOUS FAISONS APPEL À TON BON CŒUR !\n\n"
                   . "Si tu es qualifié sur Corvus avec emport PAX, tu pourrais grandement nous aider en acceptant de prendre avec toi :\n"
                   . "- Un autre pilote (sans emport passager)\n"
                   . "- Un élève pilote du club\n\n"
                   . "PILOTES PROPRIÉTAIRES\n"
                   . "Si tu possèdes ta propre machine, tu es également le(la) bienvenu(e) ! Viens avec ton appareil et emmène l'un des participants qui n'aurait pas de place autrement.\n\n"
                   . "Ton geste permettrait à un maximum de personnes de participer à cette sortie et de renforcer l'esprit d'équipe de notre club !\n\n"
                   . "Pré-inscription : " . strip_tags($preinscription_url) . "\n"
                   . "Voir la sortie : " . strip_tags($sortie_url) . "\n\n"
                   . "Merci pour ta générosité et ton engagement !\n\n"
                   . "L'équipe du Club ULM Evasion";
        
        $result = gestnav_send_mail($pdo, $membre['email'], $subject, $html_body, $text_body);
        if ($result['success']) {
            $sent_count++;
            
            // Mettre à jour le body_html/text avec le premier email envoyé
            if ($first_email && $email_log_id) {
                try {
                    $pdo->prepare("UPDATE email_logs SET body_html = ?, body_text = ? WHERE id = ?")
                        ->execute([$html_body, $text_body, $email_log_id]);
                    $first_email = false;
                } catch (Throwable $e) {
                    error_log("Erreur update body: " . $e->getMessage());
                }
            }
            
            // Ajouter le destinataire à la table email_recipients
            if ($email_log_id) {
                try {
                    $pdo->prepare("INSERT INTO email_recipients (email_log_id, name, email) VALUES (?, ?, ?)")
                        ->execute([
                            $email_log_id,
                            trim($membre['prenom'] . ' ' . $membre['nom']),
                            $membre['email']
                        ]);
                } catch (Throwable $e) {
                    error_log("Erreur ajout destinataire: " . $e->getMessage());
                }
            }
        } else {
            $failed_count++;
        }
    }
    
    // Rediriger avec le résultat
    header('Location: sortie_detail.php?sortie_id=' . $sortie_id . '&relance=1&sent=' . $sent_count . '&failed=' . $failed_count);
    exit;
    
} catch (Throwable $e) {
    header('Location: sortie_detail.php?sortie_id=' . $sortie_id . '&relance=0&error=' . urlencode($e->getMessage()));
    exit;
}
