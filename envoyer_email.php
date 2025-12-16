<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'mail_helper_advanced.php';
require_login();
require_admin();

define('GESTNAV_VERSION', '2.0.0');

// Initialiser la session du brouillon
if (!isset($_SESSION['email_draft'])) {
    $_SESSION['email_draft'] = [
        'step' => 1,
        'subjectType' => 'custom',
        'recipientType' => 'all',
        'specificMembers' => [],
        'subject' => '',
        'message' => '',
        'emailImage' => '',
        'attachments' => [],
        'links' => []
    ];
}

// Récupérer tous les membres
$allMembers = $pdo->prepare("SELECT id, prenom, nom, email, type_membre, actif FROM users ORDER BY nom, prenom");
$allMembers->execute();
$members = $allMembers->fetchAll(PDO::FETCH_ASSOC);

// GESTION DES ACTIONS POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Helper: toujours synchro les destinataires du POST vers la session
    function syncRecipients() {
        error_log("=== syncRecipients() called ===");
        error_log("POST data: " . json_encode($_POST));
        if (!empty($_POST['recipientType'])) {
            $_SESSION['email_draft']['recipientType'] = $_POST['recipientType'];
            error_log("Set recipientType to: " . $_POST['recipientType']);
        }
        if (!empty($_POST['specificMembers'])) {
            $str = $_POST['specificMembers'];
            $members = [];
            if (!empty($str)) {
                $members = array_filter(array_map('intval', explode(',', $str)));
            }
            $_SESSION['email_draft']['specificMembers'] = $members;
            error_log("Set specificMembers to: " . json_encode($members));
        } else {
            error_log("No specificMembers in POST");
        }
        error_log("Session after sync: " . json_encode($_SESSION['email_draft']));
    }
    
    // Appeler la synchro au début de chaque POST
    syncRecipients();
    
    try {
        // PASSER À L'ÉTAPE SUIVANTE
        if ($action === 'next_step') {
            $step = (int)($_POST['step'] ?? 1);
            $subjectType = $_POST['subjectType'] ?? 'custom';
            $recipientType = $_POST['recipientType'] ?? 'all';
            $specificMembersStr = $_POST['specificMembers'] ?? '';
            
            // Convertir la string séparée par virgules en array d'entiers
            $specificMembers = [];
            if (!empty($specificMembersStr)) {
                $specificMembers = array_filter(array_map('intval', explode(',', $specificMembersStr)));
            }
            
            error_log("NEXT_STEP from step $step: recipientType=$recipientType, members=" . json_encode($specificMembers));
            
            $_SESSION['email_draft']['subjectType'] = $subjectType;
            $_SESSION['email_draft']['recipientType'] = $recipientType;
            $_SESSION['email_draft']['specificMembers'] = $specificMembers;
            
            // Sauvegarder aussi subject et message s'ils sont présents dans POST
            if (isset($_POST['subject'])) {
                $_SESSION['email_draft']['subject'] = $_POST['subject'];
            }
            if (isset($_POST['message'])) {
                $_SESSION['email_draft']['message'] = $_POST['message'];
            }
            
            $_SESSION['email_draft']['step'] = min($step + 1, 5);
            
            error_log("After save: members=" . json_encode($_SESSION['email_draft']['specificMembers']));
            
            header('Location: envoyer_email.php');
            exit;
        }
        
        // REVENIR À L'ÉTAPE PRÉCÉDENTE
        if ($action === 'prev_step') {
            $step = (int)($_POST['step'] ?? 1);
            $_SESSION['email_draft']['step'] = max($step - 1, 1);
            // Aussi synchro les recipients en cas de retour
            error_log("PREV_STEP from step $step: syncing recipients...");
            syncRecipients();
            header('Location: envoyer_email.php');
            exit;
        }
        
        // ENVOYER LES NOUVEAUTÉS DE L'APP (DEPUIS changelog.php)
        if ($action === 'send_changelog') {
            $selectedVersion = $_POST['selected_version'] ?? '';
            
            // Lire le fichier changelog.php avec encodage UTF-8
            $changelogPath = __DIR__ . '/changelog.php';
            if (!file_exists($changelogPath)) {
                $_SESSION['error'] = 'Fichier changelog.php introuvable';
                header('Location: envoyer_email.php');
                exit;
            }
            
            $changelogContent = file_get_contents($changelogPath);
            // S'assurer que le contenu est en UTF-8
            if (!mb_check_encoding($changelogContent, 'UTF-8')) {
                $changelogContent = mb_convert_encoding($changelogContent, 'UTF-8', 'auto');
            }
            
            // Extraire toutes les versions disponibles
            preg_match_all('/<!-- Version ([^-]+) -->.*?<span class="version-number">\[([^\]]+)\]<\/span>\s*<span class="version-date">([^<]+)<\/span>/s', $changelogContent, $allVersions, PREG_SET_ORDER);
            
            // Trouver l'index de la version sélectionnée ou prendre la dernière
            $startIndex = 0;
            if (!empty($selectedVersion)) {
                foreach ($allVersions as $idx => $versionData) {
                    if (trim($versionData[2]) === $selectedVersion) {
                        $startIndex = $idx;
                        break;
                    }
                }
            }
            
            // Extraire le contenu depuis la version sélectionnée jusqu'à la première
            if ($startIndex === 0) {
                // Juste la dernière version
                preg_match('/<!-- Version ([^-]+) -->\s*<div class="changelog-version-block">.*?<span class="version-number">\[([^\]]+)\]<\/span>\s*<span class="version-date">([^<]+)<\/span>(.*?)(?=<!-- Version|\z)/s', $changelogContent, $matches);
            } else {
                // Toutes les versions depuis la sélectionnée
                $startVersionId = trim($allVersions[$startIndex][1]);
                $endVersionId = isset($allVersions[$startIndex + 1]) ? trim($allVersions[$startIndex + 1][1]) : null;
                
                if ($endVersionId) {
                    $pattern = '/<!-- Version ' . preg_quote($startVersionId, '/') . ' -->.*?(?=<!-- Version ' . preg_quote($endVersionId, '/') . ')/s';
                } else {
                    $pattern = '/<!-- Version ' . preg_quote($startVersionId, '/') . ' -->.*$/s';
                }
                
                preg_match($pattern, $changelogContent, $multiVersionMatch);
                
                // La première version pour le titre
                $version = trim($allVersions[0][2]);
                $date = trim($allVersions[0][3]);
                
                // Tout le contenu extrait
                $changesBlock = $multiVersionMatch[0] ?? '';
                
                $matches = [
                    $multiVersionMatch[0] ?? '',
                    $startVersionId,
                    $version,
                    $date,
                    $changesBlock
                ];
            }
            
            if (!$matches || count($matches) < 5) {
                $_SESSION['error'] = 'Impossible d\'extraire les nouveautés du changelog';
                header('Location: envoyer_email.php');
                exit;
            }
            
            $version = trim($matches[2]);
            $date = trim($matches[3]);
            $changesBlock = $matches[4];
            
            // S'assurer que les données extraites sont en UTF-8
            $version = mb_convert_encoding($version, 'UTF-8', 'auto');
            $date = mb_convert_encoding($date, 'UTF-8', 'auto');
            $changesBlock = mb_convert_encoding($changesBlock, 'UTF-8', 'auto');
            
            // Extraire les sections (Added, Changed, Fixed)
            $sectionsHtml = '';
            
            // Section Added (✨)
            if (preg_match('/<div class="changelog-section-added">(.*?)<\/div>\s*(?=<div class="changelog-section|$)/s', $changesBlock, $addedMatch)) {
                $addedContent = $addedMatch[1];
                // Extraire les items de la liste
                preg_match('/<ul class="changelog-items">(.*?)<\/ul>/s', $addedContent, $itemsMatch);
                if ($itemsMatch) {
                    $sectionsHtml .= '<h3 style="color: #10b981; margin-top: 1.5rem; margin-bottom: 0.75rem; font-size: 1.15rem;">✨ Nouveautés</h3>';
                    $sectionsHtml .= '<ul style="margin: 0; padding-left: 1.5rem; line-height: 1.8;">';
                    $sectionsHtml .= strip_tags($itemsMatch[1], '<li><strong><code><ul>');
                    $sectionsHtml .= '</ul>';
                }
            }
            
            // Section Changed (🔄)
            if (preg_match('/<div class="changelog-section-changed">(.*?)<\/div>\s*(?=<div class="changelog-section|$)/s', $changesBlock, $changedMatch)) {
                $changedContent = $changedMatch[1];
                preg_match('/<ul class="changelog-items">(.*?)<\/ul>/s', $changedContent, $itemsMatch);
                if ($itemsMatch) {
                    $sectionsHtml .= '<h3 style="color: #f59e0b; margin-top: 1.5rem; margin-bottom: 0.75rem; font-size: 1.15rem;">🔄 Améliorations</h3>';
                    $sectionsHtml .= '<ul style="margin: 0; padding-left: 1.5rem; line-height: 1.8;">';
                    $sectionsHtml .= strip_tags($itemsMatch[1], '<li><strong><code><ul>');
                    $sectionsHtml .= '</ul>';
                }
            }
            
            // Section Fixed (🐛)
            if (preg_match('/<div class="changelog-section-fixed">(.*?)<\/div>\s*(?=<div class="changelog-section|$)/s', $changesBlock, $fixedMatch)) {
                $fixedContent = $fixedMatch[1];
                preg_match('/<ul class="changelog-items">(.*?)<\/ul>/s', $fixedContent, $itemsMatch);
                if ($itemsMatch) {
                    $sectionsHtml .= '<h3 style="color: #ef4444; margin-top: 1.5rem; margin-bottom: 0.75rem; font-size: 1.15rem;">🐛 Corrections</h3>';
                    $sectionsHtml .= '<ul style="margin: 0; padding-left: 1.5rem; line-height: 1.8;">';
                    $sectionsHtml .= strip_tags($itemsMatch[1], '<li><strong><code><ul>');
                    $sectionsHtml .= '</ul>';
                }
            }
            
            // Nettoyer le HTML
            $sectionsHtml = str_replace('<code>', '<code style="background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; color: #d97706;">', $sectionsHtml);
            
            // S'assurer que le HTML des sections est en UTF-8
            $sectionsHtml = mb_convert_encoding($sectionsHtml, 'UTF-8', 'auto');
            
            // Récupérer tous les membres actifs
            $stmt = $pdo->query("SELECT id, email, prenom, nom FROM users WHERE actif = 1 AND email IS NOT NULL AND email != ''");
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($recipients)) {
                $_SESSION['error'] = 'Aucun destinataire trouvé';
                header('Location: envoyer_email.php');
                exit;
            }
            
            $subject = "🚀 Votre application GESTNAV évolue encore... (v$version)";
            // S'assurer que le sujet est en UTF-8
            $subject = mb_encode_mimeheader($subject, 'UTF-8', 'B');
            
            // Construire le message HTML
            $htmlMessage = '<html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 700px; margin: 0 auto;">';
            $htmlMessage .= '<div style="background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; padding: 2rem; border-radius: 12px 12px 0 0; text-align: center;">';
            $htmlMessage .= '<h1 style="margin: 0; font-size: 1.8rem;">🚀 GESTNAV évolue !</h1>';
            $htmlMessage .= '<p style="margin: 0.5rem 0 0; opacity: 0.9; font-size: 1rem;">Version ' . htmlspecialchars($version) . ' • ' . htmlspecialchars($date) . '</p>';
            $htmlMessage .= '</div>';
            
            $htmlMessage .= '<div style="padding: 2rem; background: white; border: 1px solid #e5e7eb; border-top: none;">';
            $htmlMessage .= '<p style="font-size: 1.1rem; margin-bottom: 1.5rem;">Bonjour {{prenom}},</p>';
            $htmlMessage .= '<p style="margin-bottom: 1.5rem;">Nous sommes ravis de vous annoncer que votre application <strong>GESTNAV</strong> vient d\'être mise à jour avec de nouvelles fonctionnalités et améliorations :</p>';
            $htmlMessage .= '<div style="background: #f9fafb; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #00a0c6;">';
            $htmlMessage .= $sectionsHtml;
            $htmlMessage .= '</div>';
            $htmlMessage .= '<p style="margin-top: 2rem;">N\'hésitez pas à vous connecter pour découvrir ces nouveautés !</p>';
            $htmlMessage .= '<p style="text-align: center; margin-top: 2rem;">';
            $htmlMessage .= '<a href="https://gestnav.clubulmevasion.fr" style="display:inline-block;padding:12px 24px;border-radius:6px;background-color:#004b8d;color:#ffffff;text-decoration:none;font-weight:600;">🔗 Accéder à GESTNAV</a>';
            $htmlMessage .= '</p>';
            $htmlMessage .= '</div>';
            
            // Signature
            $htmlMessage .= '<div style="margin-top: 2rem; padding: 1.5rem; text-align: center; background: #f9fafb; border-radius: 0 0 12px 12px;">';
            $logoPath = __DIR__ . '/assets/img/logo.png';
            if (file_exists($logoPath)) {
                $logoData = base64_encode(file_get_contents($logoPath));
                $logoMimeType = mime_content_type($logoPath);
                $htmlMessage .= '<img src="data:' . $logoMimeType . ';base64,' . $logoData . '" style="height: 50px; margin-bottom: 10px;" alt="Logo Club ULM Evasion">';
            }
            $htmlMessage .= '<p style="font-size: 12px; color: #666; margin: 5px 0;">';
            $htmlMessage .= '<strong>Club ULM Evasion</strong><br>';
            $htmlMessage .= 'GESTNAV v' . GESTNAV_VERSION;
            $htmlMessage .= '</p>';
            $htmlMessage .= '</div></body></html>';
            
            // Préparer les headers
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: CLUB ULM EVASION <info@clubulmevasion.fr>\r\n";
            
            // Envoyer les emails
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($recipients as $recipient) {
                $personalizedMessage = str_replace('{{prenom}}', htmlspecialchars($recipient['prenom']), $htmlMessage);
                if (@mail($recipient['email'], $subject, $personalizedMessage, $headers)) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
            
            // Logger l'envoi
            try {
                $pdo->prepare("INSERT INTO email_logs (subject, recipient_count, sender_id, created_at) VALUES (?, ?, ?, NOW())")
                    ->execute([$subject, $successCount, $_SESSION['user_id'] ?? 0]);
                    
                // Logger la communication de changelog
                $pdo->prepare("INSERT INTO changelog_communications (version, sent_at, sender_id, recipient_count) VALUES (?, NOW(), ?, ?)")
                    ->execute([$version, $_SESSION['user_id'] ?? 0, $successCount]);
            } catch (Exception $e) {
                error_log("Warning: email_logs table not found: " . $e->getMessage());
            }
            
            $_SESSION['success'] = "✅ Nouveautés envoyées à $successCount membre(s)" . ($errorCount > 0 ? " ($errorCount erreurs)" : '');
            header('Location: envoyer_email.php');
            exit;
        }
        
        // SAUVEGARDER LE CONTENU (ÉTAPE 3)
        if ($action === 'save_content') {
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            
            // Vérifier que le sujet et le message ne sont pas vides
            if (empty($subject) || empty($message)) {
                $_SESSION['error'] = 'L\'objet et le message sont obligatoires';
                $_SESSION['email_draft']['step'] = 3;
                header('Location: envoyer_email.php');
                exit;
            }
            
            $recipientType = $_POST['recipientType'] ?? 'all';
            $specificMembersStr = $_POST['specificMembers'] ?? '';
            $specificMembers = [];
            if (!empty($specificMembersStr)) {
                $specificMembers = array_filter(array_map('intval', explode(',', $specificMembersStr)));
            }
            
            $_SESSION['email_draft']['subject'] = $subject;
            $_SESSION['email_draft']['message'] = $message;
            $_SESSION['email_draft']['recipientType'] = $recipientType;
            $_SESSION['email_draft']['specificMembers'] = $specificMembers;
            $_SESSION['email_draft']['step'] = 4;
            header('Location: envoyer_email.php');
            exit;
        }
        
        // AJOUTER UNE PHOTO
        if ($action === 'add_email_image') {
            if (isset($_FILES['email_image']) && $_FILES['email_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['email_image'];
                $maxSize = 5 * 1024 * 1024;
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if ($file['size'] > $maxSize) {
                    $_SESSION['error'] = 'Image trop volumineuse (max 5 MB)';
                } elseif (!in_array($file['type'], $allowedTypes)) {
                    $_SESSION['error'] = 'Format non accepté (JPG, PNG, GIF, WebP)';
                } else {
                    @mkdir('uploads/email_images', 0755, true);
                    $fileId = uniqid();
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = $fileId . '.' . $ext;
                    
                    if (move_uploaded_file($file['tmp_name'], 'uploads/email_images/' . $filename)) {
                        $_SESSION['email_draft']['emailImage'] = [
                            'id' => $fileId,
                            'name' => basename($file['name']),
                            'path' => 'uploads/email_images/' . $filename
                        ];
                        $_SESSION['success'] = 'Image ajoutée ✓';
                    }
                }
            }
            header('Location: envoyer_email.php');
            exit;
        }
        
        // SUPPRIMER LA PHOTO
        if ($action === 'remove_email_image') {
            $imagePath = $_SESSION['email_draft']['emailImage']['path'] ?? '';
            if ($imagePath && file_exists($imagePath)) {
                @unlink($imagePath);
            }
            $_SESSION['email_draft']['emailImage'] = '';
            $_SESSION['success'] = 'Image supprimée ✓';
            header('Location: envoyer_email.php');
            exit;
        }
        
        // AJOUTER UNE PIÈCE JOINTE
        if ($action === 'add_attachment') {
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['file'];
                $maxSize = 10 * 1024 * 1024;
                
                if ($file['size'] > $maxSize) {
                    $_SESSION['error'] = 'Fichier trop volumineux (max 10 MB)';
                } else {
                    @mkdir('uploads/email_attachments', 0755, true);
                    $fileId = uniqid();
                    $filename = $fileId . '_' . basename($file['name']);
                    
                    if (move_uploaded_file($file['tmp_name'], 'uploads/email_attachments/' . $filename)) {
                        $_SESSION['email_draft']['attachments'][] = [
                            'id' => $fileId,
                            'name' => basename($file['name']),
                            'path' => 'uploads/email_attachments/' . $filename
                        ];
                        $_SESSION['success'] = 'Pièce jointe ajoutée ✓';
                    }
                }
            }
            header('Location: envoyer_email.php');
            exit;
        }
        
        // SUPPRIMER UNE PIÈCE JOINTE
        if ($action === 'remove_attachment') {
            $attachmentId = $_POST['attachment_id'] ?? '';
            $newAttachments = [];
            foreach ($_SESSION['email_draft']['attachments'] ?? [] as $att) {
                if ($att['id'] !== $attachmentId) {
                    $newAttachments[] = $att;
                } else {
                    if (file_exists($att['path'])) {
                        @unlink($att['path']);
                    }
                }
            }
            $_SESSION['email_draft']['attachments'] = $newAttachments;
            $_SESSION['success'] = 'Pièce jointe supprimée ✓';
            header('Location: envoyer_email.php');
            exit;
        }
        
        // AJOUTER UN LIEN
        if ($action === 'add_link') {
            $linkText = trim($_POST['link_text'] ?? '');
            $linkUrl = trim($_POST['link_url'] ?? '');
            
            if (!$linkText || !$linkUrl) {
                $_SESSION['error'] = 'Texte et URL requis';
            } else {
                $_SESSION['email_draft']['links'][] = [
                    'id' => uniqid(),
                    'text' => $linkText,
                    'url' => $linkUrl
                ];
                $_SESSION['success'] = 'Lien ajouté ✓';
            }
            header('Location: envoyer_email.php');
            exit;
        }
        
        // SUPPRIMER UN LIEN
        if ($action === 'remove_link') {
            $linkId = $_POST['link_id'] ?? '';
            $_SESSION['email_draft']['links'] = array_filter(
                $_SESSION['email_draft']['links'] ?? [],
                fn($link) => $link['id'] !== $linkId
            );
            $_SESSION['success'] = 'Lien supprimé ✓';
            header('Location: envoyer_email.php');
            exit;
        }
        
        // TOGGLE MEMBER
        if ($action === 'toggle_member') {
            header('Content-Type: application/json');
            $memberId = (int)($_POST['member_id'] ?? 0);
            
            if ($memberId > 0) {
                $members_list = $_SESSION['email_draft']['specificMembers'] ?? [];
                if (in_array($memberId, $members_list)) {
                    $members_list = array_filter($members_list, fn($id) => $id !== $memberId);
                } else {
                    $members_list[] = $memberId;
                }
                $_SESSION['email_draft']['specificMembers'] = array_values($members_list);
                echo json_encode(['success' => true, 'count' => count($members_list)]);
            } else {
                echo json_encode(['success' => false]);
            }
            exit;
        }
        
        // ENVOYER L'EMAIL
        if ($action === 'send_email') {
            $subject = trim($_SESSION['email_draft']['subject'] ?? '');
            $message = trim($_SESSION['email_draft']['message'] ?? '');
            $recipientType = $_POST['recipientType'] ?? ($_SESSION['email_draft']['recipientType'] ?? 'all');
            $subjectType = $_SESSION['email_draft']['subjectType'] ?? 'custom';
            
            // Récupérer specificMembers depuis POST ou session
            $specificMembersStr = $_POST['specificMembers'] ?? '';
            $specificMembers = [];
            if (!empty($specificMembersStr)) {
                $specificMembers = array_filter(array_map('intval', explode(',', $specificMembersStr)));
            } else {
                $specificMembers = $_SESSION['email_draft']['specificMembers'] ?? [];
            }
            
            // Double vérification de sécurité (normalement déjà validé à l'étape 3)
            if (empty($subject) || empty($message)) {
                $_SESSION['error'] = 'Erreur: objet ou message manquants. Veuillez revenir à l\'étape 3 et remplir les champs.';
                $_SESSION['email_draft']['step'] = 3;
                header('Location: envoyer_email.php');
                exit;
            }
            
            // Nettoyer le message HTML : supprimer les balises dangereuses mais préserver les styles
            // Approche simple avec regex pour préserver le contenu texte
            $cleanMessage = $message;
            
            // Supprimer les balises et scripts dangereux
            $cleanMessage = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $cleanMessage);
            $cleanMessage = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $cleanMessage);
            $cleanMessage = preg_replace('/<link[^>]*>/is', '', $cleanMessage);
            $cleanMessage = preg_replace('/on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $cleanMessage);
            
            // Supprimer les balises HTML non autorisées tout en gardant le texte
            // Matche les balises non autorisées et remplace par leur contenu
            $allowedTagsPattern = 'b|i|u|br|p|div|span|ol|ul|li|a|strong|em|h1|h2|h3|h4|h5|h6|font';
            $cleanMessage = preg_replace_callback(
                '/<\/?([a-z][a-z0-9]*)[^>]*>/i',
                function($matches) use ($allowedTagsPattern) {
                    $tag = strtolower($matches[1]);
                    // Si c'est une balise autorisée, la garder
                    if (preg_match('/^(' . $allowedTagsPattern . ')$/', $tag)) {
                        return $matches[0];
                    }
                    // Sinon, supprimer la balise mais garder le contenu
                    return '';
                },
                $cleanMessage
            );
            
            $cleanMessage = trim($cleanMessage);
            
            // Ajouter le préfixe au sujet
            $subjectPrefixes = [
                'communication' => '📢 Communication - ',
                'nouveau_membre' => '🎉 Bienvenue - ',
                'custom' => ''
            ];
            $subjectPrefix = $subjectPrefixes[$subjectType] ?? '';
            $finalSubject = $subjectPrefix . $subject;
            
            // Récupérer les destinataires
            $query = "SELECT id, email, prenom, nom FROM users WHERE email IS NOT NULL AND email != ''";
            $params = [];
            
            if ($recipientType === 'club') {
                $query .= " AND type_membre = 'club'";
            } elseif ($recipientType === 'invite') {
                $query .= " AND type_membre = 'invite'";
            } elseif ($recipientType === 'actif') {
                $query .= " AND actif = 1";
            } elseif ($recipientType === 'inactif') {
                $query .= " AND actif = 0";
            } elseif ($recipientType === 'specific') {
                $specificMembers = $_SESSION['email_draft']['specificMembers'] ?? [];
                if (empty($specificMembers)) {
                    $_SESSION['error'] = 'Veuillez sélectionner au moins un membre';
                    header('Location: envoyer_email.php');
                    exit;
                }
                $placeholders = implode(',', array_fill(0, count($specificMembers), '?'));
                $query .= " AND id IN ($placeholders)";
                $params = $specificMembers;
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $recipientData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($recipientData)) {
                $_SESSION['error'] = 'Aucun destinataire trouvé';
                header('Location: envoyer_email.php');
                exit;
            }
            
            // Construire le message HTML
            // (sera construit dans la boucle pour chaque email)
            
            $successCount = 0;
            $emailSender = new EmailSender($pdo);
            
            // Récupérer le chemin de l'image si elle existe
            $imagePath = null;
            if (!empty($_SESSION['email_draft']['emailImage'])) {
                $relativePath = $_SESSION['email_draft']['emailImage']['path'];
                
                // Convertir le chemin relatif en chemin absolu
                $imagePath = __DIR__ . '/' . $relativePath;
                
                if (!file_exists($imagePath)) {
                    $imagePath = null;
                }
            }
            
            // Récupérer les pièces jointes et ajouter l'image si elle existe
            $attachments = [];
            if ($imagePath) {
                // Ajouter l'image comme pièce jointe
                $attachments[] = [
                    'path' => $imagePath,
                    'name' => 'image_' . time() . '.' . pathinfo($imagePath, PATHINFO_EXTENSION)
                ];
            }
            if (!empty($_SESSION['email_draft']['attachments'])) {
                foreach ($_SESSION['email_draft']['attachments'] as $att) {
                    if (isset($att['path']) && file_exists($att['path'])) {
                        $attachments[] = $att;
                    }
                }
            }
            
            foreach ($recipientData as $recipient) {
                // Construire le HTML du mail - SIMPLE, sans images embarquées
                $emailHtml = '<html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">';
                
                // Note si une image a été ajoutée
                if ($imagePath) {
                    $emailHtml .= '<div style="text-align: center; margin-bottom: 20px; padding: 10px; background-color: #f0f0f0; border-radius: 5px;">';
                    $emailHtml .= '<em style="color: #666;">📎 Une image a été jointe à cet email</em>';
                    $emailHtml .= '</div>';
                }
                
                // Ajouter le message
                $emailHtml .= '<div style="margin: 20px 0;">' . $cleanMessage . '</div>';
                
                // Ajouter les liens
                if (!empty($_SESSION['email_draft']['links'])) {
                    $emailHtml .= '<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ccc;"><strong style="color: #004b8d;">Liens utiles :</strong><br>';
                    foreach ($_SESSION['email_draft']['links'] as $link) {
                        $emailHtml .= '<a href="' . htmlspecialchars($link['url']) . '" style="color: #00a0c6; text-decoration: none; display: block; margin: 5px 0;">' . htmlspecialchars($link['text']) . '</a>';
                    }
                    $emailHtml .= '</div>';
                }
                
                // Ajouter le footer
                $emailHtml .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #00a0c6; text-align: center;">';
                $emailHtml .= '<p style="font-size: 12px; color: #666; margin: 5px 0;"><strong>Mail envoyé avec GESTNAV v' . GESTNAV_VERSION . '</strong></p>';
                $emailHtml .= '<p style="font-size: 11px; color: #999; margin: 5px 0;">Gestion des Sorties et Membres - Club ULM Evasion</p>';
                $emailHtml .= '</div></body></html>';
                
                // Envoyer l'email
                if ($emailSender->send($recipient['email'], $finalSubject, $emailHtml, $attachments)) {
                    $successCount++;
                }
            }
            

            
            // Enregistrer dans l'historique
            try {
                $stmt = $pdo->prepare("INSERT INTO email_logs (sender_id, recipient_count, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$_SESSION['user_id'] ?? 0, $successCount, $finalSubject, $cleanMessage]);
                $emailLogId = $pdo->lastInsertId();
                
                // Enregistrer les destinataires
                if ($emailLogId) {
                    $recipientStmt = $pdo->prepare("INSERT INTO email_recipients (email_log_id, user_id, email, name) VALUES (?, ?, ?, ?)");
                    foreach ($recipientData as $recipient) {
                        $recipientStmt->execute([
                            $emailLogId,
                            $recipient['id'] ?? null,
                            $recipient['email'],
                            trim($recipient['prenom'] . ' ' . $recipient['nom'])
                        ]);
                    }
                }
            } catch (Exception $e) {
                error_log("Warning: email_logs table error: " . $e->getMessage());
            }
            
            $_SESSION['email_draft'] = ['step' => 1, 'subjectType' => 'custom', 'recipientType' => 'all', 'specificMembers' => [], 'subject' => '', 'message' => '', 'emailImage' => '', 'attachments' => [], 'links' => []];
            $_SESSION['success'] = "✅ Email envoyé à $successCount destinataire(s)";
            header('Location: envoyer_email.php');
            exit;
        }
        
        // EFFACER LE BROUILLON
        if ($action === 'clear_draft') {
            if (!empty($_SESSION['email_draft']['emailImage'])) {
                $imagePath = $_SESSION['email_draft']['emailImage']['path'] ?? '';
                if ($imagePath && file_exists($imagePath)) @unlink($imagePath);
            }
            foreach ($_SESSION['email_draft']['attachments'] ?? [] as $att) {
                if (file_exists($att['path'])) @unlink($att['path']);
            }
            $_SESSION['email_draft'] = ['step' => 1, 'subjectType' => 'custom', 'recipientType' => 'all', 'specificMembers' => [], 'subject' => '', 'message' => '', 'emailImage' => '', 'attachments' => [], 'links' => []];
            $_SESSION['success'] = 'Brouillon effacé ✓';
            header('Location: envoyer_email.php');
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erreur: ' . $e->getMessage();
        header('Location: envoyer_email.php');
        exit;
    }
}

$draft = $_SESSION['email_draft'];
$currentStep = $draft['step'] ?? 1;
$subjectType = $draft['subjectType'] ?? 'custom';
$recipientType = $draft['recipientType'] ?? 'all';
$specificMembers = $draft['specificMembers'] ?? [];
$subject = $draft['subject'] ?? '';
$message = $draft['message'] ?? '';
$emailImage = $draft['emailImage'] ?? '';
$attachments = $draft['attachments'] ?? [];
$links = $draft['links'] ?? [];

// Récupérer la dernière communication de changelog
$lastChangelogComm = null;
try {
    $stmt = $pdo->query("SELECT version, sent_at, recipient_count FROM changelog_communications ORDER BY sent_at DESC LIMIT 1");
    $lastChangelogComm = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table n'existe pas encore
}

// Extraire toutes les versions du changelog
$availableVersions = [];
$changelogPath = __DIR__ . '/changelog.php';
if (file_exists($changelogPath)) {
    $changelogContent = file_get_contents($changelogPath);
    preg_match_all('/<!-- Version ([^-]+) -->.*?<span class="version-number">\[([^\]]+)\]<\/span>\s*<span class="version-date">([^<]+)<\/span>/s', $changelogContent, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $availableVersions[] = [
            'version' => trim($match[2]),
            'date' => trim($match[3])
        ];
    }
}

// Debug session image
if ($currentStep == 5) {
    error_log("ETAPE 5 - emailImage type: " . gettype($emailImage) . ", value: " . json_encode($emailImage));
}

error_log("PAGE LOAD step=$currentStep, recipientType=$recipientType, members=" . json_encode($specificMembers) . ", recipientCount will be calculated...");

$recipientCount = 0;
if ($recipientType === 'specific') {
    $recipientCount = count($specificMembers);
    error_log("Specific recipients: count=$recipientCount, members=" . json_encode($specificMembers));
} else {
    $query = "SELECT COUNT(*) FROM users WHERE email IS NOT NULL AND email != ''";
    if ($recipientType === 'club') $query .= " AND type_membre = 'club'";
    elseif ($recipientType === 'invite') $query .= " AND type_membre = 'invite'";
    elseif ($recipientType === 'actif') $query .= " AND actif = 1";
    elseif ($recipientType === 'inactif') $query .= " AND actif = 0";
    $recipientCount = (int)$pdo->query($query)->fetchColumn();
}

require 'header.php';
?>

<style>
.email-page { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem 3rem; }
.email-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 2rem; padding: 1.5rem 1.75rem; border-radius: 1.25rem; background: linear-gradient(135deg, #004b8d, #00a0c6); color: #fff; box-shadow: 0 12px 30px rgba(0,0,0,0.25); }
.email-header h1 { font-size: 1.6rem; margin: 0; letter-spacing: 0.03em; text-transform: uppercase; }
.email-header-icon { font-size: 2.4rem; opacity: 0.9; }

.steps-indicator { display: flex; gap: 0.75rem; margin-bottom: 2rem; overflow-x: auto; }
.step { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; border-radius: 0.75rem; background: #f3f4f6; color: #6b7280; font-weight: 600; font-size: 0.85rem; white-space: nowrap; }
.step.active { background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; }
.step.completed { background: #d1fae5; color: #065f46; }
.step-number { display: flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background: rgba(255,255,255,0.3); font-size: 0.75rem; }
.step.completed .step-number { background: #10b981; }

.card { background: #ffffff; border-radius: 1.25rem; padding: 1.75rem 1.5rem; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); border: 1px solid rgba(0, 0, 0, 0.03); margin-bottom: 1.5rem; }
.card-title { font-size: 1.05rem; font-weight: 700; color: #1f2937; margin-bottom: 1rem; }
.form-group { margin-bottom: 1rem; }
.form-label { display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
.form-input, .form-textarea, select { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.75rem; font-family: inherit; font-size: 0.9rem; }
.form-input:focus, .form-textarea:focus, select:focus { outline: none; border-color: #00a0c6; box-shadow: 0 0 0 3px rgba(0,160,198,0.1); }

#messageEditor { width: 100%; min-height: 300px; padding: 12px; border: 1px solid #d1d5db; border-radius: 0.75rem; background: white; font-size: 14px; line-height: 1.5; }
#messageEditor:focus { border-color: #00a0c6; box-shadow: 0 0 0 3px rgba(0,160,198,0.1); }

.btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.9rem; }
.btn-primary { background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; }
.btn-primary:hover:not(:disabled) { filter: brightness(1.08); }
.btn-secondary { background: #f3f4f6; color: #374151; display: inline-block; }
.btn-secondary:hover { background: #e5e7eb; }

.type-buttons { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
.type-btn { padding: 0.75rem; border: 2px solid #e5e7eb; background: white; border-radius: 0.75rem; cursor: pointer; font-weight: 500; transition: all 0.2s; }
.type-btn.active { background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; border-color: #004b8d; }

.members-list { max-height: 400px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 0.75rem; background: white; }
.member-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-bottom: 1px solid #f3f4f6; cursor: pointer; }
.member-item:hover { background: #f9fafb; }
.member-item.selected { background: #f0fbff; border-left: 3px solid #00a0c6; }
.member-checkbox { width: 18px; height: 18px; border: 2px solid #d1d5db; border-radius: 0.35rem; display: flex; align-items: center; justify-content: center; background: white; flex-shrink: 0; font-size: 0.75rem; }
.member-item.selected .member-checkbox { background: #004b8d; border-color: #004b8d; color: white; }
.member-name { font-weight: 600; color: #1f2937; }
.member-email { font-size: 0.8rem; color: #9ca3af; }

.item-card { padding: 0.75rem 1rem; background: #f9fafb; border-radius: 0.75rem; border: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }

.alert { padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; border-left: 4px solid; }
.alert-success { background: rgba(34,197,94,0.1); color: #166534; border-left-color: #22c55e; }
.alert-error { background: rgba(239,68,68,0.1); color: #991b1b; border-left-color: #ef4444; }

.editor-toolbar { display: flex; gap: 5px; margin-bottom: 10px; flex-wrap: wrap; padding: 8px; background: #f9fafb; border-radius: 0.75rem; border: 1px solid #d1d5db; }
.editor-toolbar button { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 0.5rem; cursor: pointer; background: white; font-size: 0.9rem; }
.editor-toolbar button:hover { background: #e5e7eb; }

.file-input-group { display: flex; gap: 1rem; align-items: center; }
.file-input-group input[type="file"] { flex: 1; }
.file-input-group .btn { width: auto; }

.recipient-summary { background: #f0fbff; border-left: 3px solid #00a0c6; padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem; }
</style>

<div class="email-page">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <div class="email-header">
        <div><h1>Envoi d'emails - Étape <?= $currentStep ?>/5</h1></div>
        <div class="email-header-icon">📧</div>
    </div>
    
    <!-- Bouton rapide pour envoyer les nouveautés -->
    <div class="card" style="background: linear-gradient(135deg, rgba(0,75,141,0.05), rgba(0,160,198,0.05)); border: 2px solid #00a0c6;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
            <div style="flex: 1;">
                <h3 style="margin: 0 0 0.5rem 0; color: #004b8d; font-size: 1.1rem;">🚀 Annonce automatique des nouveautés</h3>
                <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem;">Envoyez un email à tous les membres avec les dernières évolutions de GESTNAV (depuis le CHANGELOG)</p>
                <?php if ($lastChangelogComm): ?>
                    <p style="margin: 0; color: #10b981; font-size: 0.85rem; font-weight: 600;">
                        📬 Dernière communication : <strong>v<?= htmlspecialchars($lastChangelogComm['version']) ?></strong> 
                        le <?= date('d/m/Y à H:i', strtotime($lastChangelogComm['sent_at'])) ?> 
                        (<?= $lastChangelogComm['recipient_count'] ?> destinataires)
                    </p>
                <?php else: ?>
                    <p style="margin: 0; color: #f59e0b; font-size: 0.85rem; font-style: italic;">Aucune communication envoyée pour le moment</p>
                <?php endif; ?>
            </div>
            <button type="button" onclick="showChangelogPreview()" class="btn btn-primary" style="white-space: nowrap; width: auto;">📣 Aperçu & Envoi</button>
        </div>
    </div>
    
    <!-- Modal aperçu des nouveautés -->
    <div id="changelogModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; overflow-y: auto; padding: 2rem;">
        <div style="max-width: 900px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div style="background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; padding: 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 1.5rem;">📧 Aperçu de l'email</h2>
                <button onclick="hideChangelogPreview()" style="background: rgba(255,255,255,0.2); border: none; color: white; font-size: 1.5rem; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;">×</button>
            </div>
            
            <div style="padding: 1.5rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.9rem;">
                    📋 Choisir à partir de quelle version générer l'email :
                </label>
                <select id="versionSelector" onchange="updatePreview()" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.75rem; font-size: 0.9rem; background: white;">
                    <?php foreach ($availableVersions as $idx => $versionData): ?>
                        <option value="<?= htmlspecialchars($versionData['version']) ?>" <?= $idx === 0 ? 'selected' : '' ?>>
                            <?php if ($idx === 0): ?>
                                ✨ Dernière version uniquement : <?= htmlspecialchars($versionData['version']) ?> (<?= htmlspecialchars($versionData['date']) ?>)
                            <?php else: ?>
                                📚 Depuis la version <?= htmlspecialchars($versionData['version']) ?> (<?= htmlspecialchars($versionData['date']) ?>) jusqu'à aujourd'hui
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p style="margin: 0.75rem 0 0 0; font-size: 0.8rem; color: #6b7280; font-style: italic;">
                    💡 Si plusieurs versions se sont accumulées, sélectionnez la version de départ pour inclure toutes les nouveautés depuis cette date.
                </p>
            </div>
            
            <div id="changelogPreviewContent" style="padding: 2rem; max-height: 60vh; overflow-y: auto;">
                <div style="text-align: center; padding: 2rem;">
                    <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: #004b8d;"></div>
                    <p style="margin-top: 1rem; color: #6b7280;">Chargement de l'aperçu...</p>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid #e5e7eb; display: flex; gap: 1rem; justify-content: flex-end;">
                <button onclick="hideChangelogPreview()" class="btn btn-secondary">Annuler</button>
                <form method="post" id="sendChangelogForm" style="display: inline;">
                    <input type="hidden" name="action" value="send_changelog">
                    <input type="hidden" name="selected_version" id="selectedVersionInput" value="<?= htmlspecialchars($availableVersions[0]['version'] ?? '') ?>">
                    <button type="submit" class="btn btn-primary">✉️ Confirmer l'envoi</button>
                </form>
            </div>
        </div>
    </div>

    <div class="steps-indicator">
        <div class="step <?= $currentStep >= 1 ? ($currentStep > 1 ? 'completed' : 'active') : '' ?>"><div class="step-number"><?= $currentStep > 1 ? '✓' : '1' ?></div><span>Catégorie</span></div>
        <div class="step <?= $currentStep >= 2 ? ($currentStep > 2 ? 'completed' : 'active') : '' ?>"><div class="step-number"><?= $currentStep > 2 ? '✓' : '2' ?></div><span>Destinataires</span></div>
        <div class="step <?= $currentStep >= 3 ? ($currentStep > 3 ? 'completed' : 'active') : '' ?>"><div class="step-number"><?= $currentStep > 3 ? '✓' : '3' ?></div><span>Contenu</span></div>
        <div class="step <?= $currentStep >= 4 ? ($currentStep > 4 ? 'completed' : 'active') : '' ?>"><div class="step-number"><?= $currentStep > 4 ? '✓' : '4' ?></div><span>Compléments</span></div>
        <div class="step <?= $currentStep === 5 ? 'active' : '' ?>"><div class="step-number">5</div><span>Envoi</span></div>
    </div>

    <?php if ($currentStep === 1): ?>
        <div class="card">
            <div class="card-title">📋 Étape 1: Sélectionnez la catégorie</div>
            <p style="color: #6b7280; margin-bottom: 1.5rem;">Quel type d'email voulez-vous envoyer ?</p>
            <form method="post">
                <input type="hidden" name="action" value="next_step">
                <input type="hidden" name="step" value="1">
                <input type="hidden" name="subjectType" id="categoryInput" value="<?= htmlspecialchars($subjectType) ?>">
                <input type="hidden" name="recipientType" value="<?= htmlspecialchars($recipientType) ?>">
                <div class="type-buttons" id="categoryButtons">
                    <button type="button" class="type-btn <?= $subjectType === 'custom' ? 'active' : '' ?>" data-value="custom">📝 Libre</button>
                    <button type="button" class="type-btn <?= $subjectType === 'communication' ? 'active' : '' ?>" data-value="communication">📢 Communication</button>
                    <button type="button" class="type-btn <?= $subjectType === 'nouveau_membre' ? 'active' : '' ?>" data-value="nouveau_membre">🎉 Nouveau membre</button>
                </div>
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Suivant →</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($currentStep === 2): ?>
        <div class="card">
            <div class="card-title">👥 Étape 2: Sélectionnez les destinataires</div>
            <p style="color: #6b7280; margin-bottom: 1rem;">Total: <span id="recipientCountDisplay"><?= $recipientCount ?></span> personne<span id="recipientCountSuffix"><?= $recipientCount !== 1 ? 's' : '' ?></span></p>
            <form method="post" id="recipientForm" onsubmit="return validateRecipientForm()">
                <input type="hidden" name="action" value="next_step">
                <input type="hidden" name="step" value="2">
                <input type="hidden" name="subjectType" value="<?= htmlspecialchars($subjectType) ?>">
                <input type="hidden" name="recipientType" id="recipientTypeInput" value="<?= htmlspecialchars($recipientType) ?>">
                <input type="hidden" name="specificMembers" id="specificMembersInput" value="<?= implode(',', $specificMembers) ?>">
                <div class="type-buttons" id="recipientButtons">
                    <button type="button" class="type-btn <?= $recipientType === 'all' ? 'active' : '' ?>" data-value="all">Tous</button>
                    <button type="button" class="type-btn <?= $recipientType === 'club' ? 'active' : '' ?>" data-value="club">CLUB</button>
                    <button type="button" class="type-btn <?= $recipientType === 'invite' ? 'active' : '' ?>" data-value="invite">INVITE</button>
                    <button type="button" class="type-btn <?= $recipientType === 'actif' ? 'active' : '' ?>" data-value="actif">Actifs</button>
                    <button type="button" class="type-btn <?= $recipientType === 'inactif' ? 'active' : '' ?>" data-value="inactif">Inactifs</button>
                    <button type="button" class="type-btn <?= $recipientType === 'specific' ? 'active' : '' ?>" data-value="specific">Perso</button>
                </div>
                <!-- Afficher toujours la liste, mais masquée par défaut -->
                <div style="margin: 1.5rem 0; display: <?= $recipientType === 'specific' ? 'block' : 'none' ?>;" id="memberListContainer">
                    <input type="text" id="memberSearch" placeholder="Chercher..." class="form-input" style="margin-bottom: 1rem;">
                    <div class="members-list" id="membersList">
                        <?php foreach ($members as $member): 
                            $isSelected = in_array($member['id'], $specificMembers);
                        ?>
                            <div class="member-item <?= $isSelected ? 'selected' : '' ?>" data-member-id="<?= $member['id'] ?>" data-search="<?= strtolower($member['prenom'] . ' ' . $member['nom'] . ' ' . $member['email']) ?>">
                                <div class="member-checkbox"><?= $isSelected ? '✓' : '' ?></div>
                                <div style="flex: 1;">
                                    <div class="member-name"><?= htmlspecialchars($member['prenom'] . ' ' . $member['nom']) ?></div>
                                    <div class="member-email"><?= htmlspecialchars($member['email']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="validateMembersBtn" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">✓ Valider la sélection</button>
                </div>
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Suivant →</button>
                    <button type="button" class="btn btn-secondary btn-prev" data-step="2">← Retour</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($currentStep === 3): ?>
        <div class="card">
            <div class="card-title">✏️ Étape 3: Rédigez votre message</div>
            <form method="post" id="contentForm">
                <input type="hidden" name="action" value="save_content">
                <input type="hidden" name="recipientType" value="<?= htmlspecialchars($recipientType) ?>">
                <input type="hidden" name="specificMembers" value="<?= implode(',', $specificMembers) ?>">
                <div class="form-group">
                    <label class="form-label">Objet *</label>
                    <input type="text" name="subject" class="form-input" id="subjectInput" placeholder="Saisissez l'objet" value="<?= htmlspecialchars($subject) ?>" required>
                    <div style="margin-top: 0.5rem; padding: 0.75rem; background: #f9fafb; border-radius: 0.5rem; border-left: 3px solid #00a0c6; font-size: 0.9rem;">
                        <strong>Aperçu:</strong> <span id="subjectPreview"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Message *</label>
                    <div class="editor-toolbar" id="editorToolbar">
                        <button type="button" data-command="bold">B</button>
                        <button type="button" data-command="italic" style="font-style: italic;">I</button>
                        <button type="button" data-command="underline" style="text-decoration: underline;">U</button>
                        <button type="button" data-command="insertUnorderedList">≡</button>
                        <select id="colorPicker"><option value="">Couleur</option><option value="#FF0000">🔴 Rouge</option><option value="#0000FF">🔵 Bleu</option><option value="#008000">🟢 Vert</option><option value="#FF8C00">🟠 Orange</option><option value="#9932CC">🟣 Violet</option></select>
                    </div>
                    <div id="messageEditor" contenteditable="true"><?= $message ? $message : '<br>' ?></div>
                    <textarea name="message" id="hiddenMessage" style="display: none;"></textarea>
                </div>
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Suivant →</button>
                    <button type="button" class="btn btn-secondary btn-prev" data-step="3">← Retour</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($currentStep === 4): ?>
        <div class="card">
            <div class="card-title">📎 Étape 4: Ajoutez des compléments</div>
            
            <div style="margin-bottom: 2rem; padding: 1rem; background: #f9fafb; border-radius: 0.75rem;">
                <h3 style="margin: 0 0 0.75rem 0; color: #1f2937;">🖼️ Photo</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_email_image">
                    <input type="hidden" name="recipientType" value="<?= htmlspecialchars($recipientType) ?>">
                    <input type="hidden" name="specificMembers" value="<?= implode(',', $specificMembers) ?>">
                    <div class="file-input-group">
                        <input type="file" name="email_image" accept="image/*">
                        <button type="submit" class="btn btn-secondary">Ajouter</button>
                    </div>
                </form>
                <?php if (!empty($emailImage)): ?>
                    <div class="item-card" style="margin-top: 0.75rem;">
                        <span>✓ <?= htmlspecialchars($emailImage['name']) ?></span>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="remove_email_image">
                            <input type="hidden" name="recipientType" value="<?= htmlspecialchars($recipientType) ?>">
                            <input type="hidden" name="specificMembers" value="<?= implode(',', $specificMembers) ?>">
                            <button type="submit" class="btn btn-secondary">❌</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-bottom: 2rem; padding: 1rem; background: #f9fafb; border-radius: 0.75rem;">
                <h3 style="margin: 0 0 0.75rem 0; color: #1f2937;">📎 Pièces jointes</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_attachment">
                    <input type="hidden" name="recipientType" value="<?= htmlspecialchars($recipientType) ?>">
                    <input type="hidden" name="specificMembers" value="<?= implode(',', $specificMembers) ?>">
                    <div class="file-input-group">
                        <input type="file" name="file">
                        <button type="submit" class="btn btn-secondary">Ajouter</button>
                    </div>
                </form>
                <?php foreach ($attachments as $att): ?>
                    <div class="item-card" style="margin-top: 0.75rem;">
                        <span>📄 <?= htmlspecialchars($att['name']) ?></span>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="remove_attachment">
                            <input type="hidden" name="attachment_id" value="<?= $att['id'] ?>">
                            <input type="hidden" name="recipientType" value="<?= htmlspecialchars($recipientType) ?>">
                            <input type="hidden" name="specificMembers" value="<?= implode(',', $specificMembers) ?>">
                            <button type="submit" class="btn btn-secondary">❌</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-bottom: 2rem; padding: 1rem; background: #f9fafb; border-radius: 0.75rem;">
                <h3 style="margin: 0 0 0.75rem 0; color: #1f2937;">🔗 Liens utiles</h3>
                <form method="post">
                    <input type="hidden" name="action" value="add_link">
                    <input type="hidden" name="recipientType" value="<?= htmlspecialchars($recipientType) ?>">
                    <input type="hidden" name="specificMembers" value="<?= implode(',', $specificMembers) ?>">
                    <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <input type="text" name="link_text" placeholder="Texte" class="form-input">
                        <input type="url" name="link_url" placeholder="https://" class="form-input">
                        <button type="submit" class="btn btn-secondary">➕</button>
                    </div>
                </form>
                <?php foreach ($links as $link): ?>
                    <div class="item-card" style="margin-bottom: 0.5rem;">
                        <div><strong><?= htmlspecialchars($link['text']) ?></strong><br><small style="color: #9ca3af;"><?= htmlspecialchars($link['url']) ?></small></div>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="remove_link">
                            <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                            <input type="hidden" name="recipientType" value="<?= htmlspecialchars($recipientType) ?>">
                            <input type="hidden" name="specificMembers" value="<?= implode(',', $specificMembers) ?>">
                            <button type="submit" class="btn btn-secondary">❌</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <form method="post" style="display: flex; gap: 1rem;">
                <input type="hidden" name="action" value="next_step">
                <input type="hidden" name="step" value="4">
                <input type="hidden" name="subjectType" value="<?= htmlspecialchars($subjectType) ?>">
                <input type="hidden" name="recipientType" value="<?= htmlspecialchars($recipientType) ?>">
                <input type="hidden" name="specificMembers" value="<?= implode(',', $specificMembers) ?>">
                <input type="hidden" name="subject" value="<?= htmlspecialchars($subject) ?>">
                <input type="hidden" name="message" value="<?= htmlspecialchars($message) ?>">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Envoyer →</button>
            </form>
            <button type="button" class="btn btn-secondary btn-prev" data-step="4" style="margin-top: 0.75rem;">← Retour</button>
        </div>
    <?php endif; ?>

    <?php if ($currentStep === 5): ?>
        <div class="card">
            <div class="card-title">✔️ Étape 5: Confirmez et envoyez</div>
            <div style="background: #f9fafb; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 1.5rem;">
                <div style="margin-bottom: 1rem;"><strong style="color: #004b8d;">Objet:</strong><br><?= htmlspecialchars($subject) ?></div>
                <div style="margin-bottom: 1rem;"><strong style="color: #004b8d;">Destinataires:</strong><br><?= $recipientCount ?> personne<?= $recipientCount !== 1 ? 's' : '' ?> (<?= ucfirst($recipientType) ?>)</div>
                <div style="margin-bottom: 1rem;"><strong style="color: #004b8d;">Message:</strong><br><?= nl2br(htmlspecialchars(substr($message, 0, 200))) ?><?= strlen($message) > 200 ? '...' : '' ?></div>
                <?php if (!empty($emailImage)): ?><div style="color: #6b7280;"><strong>🖼️ Photo:</strong> <?= htmlspecialchars($emailImage['name']) ?></div><?php endif; ?>
                <?php if (!empty($attachments)): ?><div style="color: #6b7280;"><strong>📎 Pièces (<?= count($attachments) ?>)</strong></div><?php endif; ?>
                <?php if (!empty($links)): ?><div style="color: #6b7280;"><strong>🔗 Liens (<?= count($links) ?>)</strong></div><?php endif; ?>
            </div>
            <form method="post" style="display: flex; gap: 1rem;">
                <input type="hidden" name="action" value="send_email">
                <input type="hidden" name="recipientType" value="<?= htmlspecialchars($recipientType) ?>">
                <input type="hidden" name="specificMembers" value="<?= implode(',', $specificMembers) ?>">
                <button type="submit" class="btn btn-primary" style="flex: 1;">📤 Envoyer maintenant</button>
            </form>
            <form method="post" style="display: inline; margin-top: 0.75rem;">
                <input type="hidden" name="action" value="prev_step">
                <input type="hidden" name="step" value="5">
                <button type="submit" class="btn btn-secondary">← Modifier</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
// Validation du formulaire destinataires (étape 2)
function validateRecipientForm() {
    const recipientType = document.getElementById('recipientTypeInput').value;
    const specificMembers = document.getElementById('specificMembersInput').value.trim();
    
    if (recipientType === 'specific' && !specificMembers) {
        alert('Veuillez sélectionner au moins un membre');
        return false;
    }
    return true;
}

document.querySelectorAll('#categoryButtons .type-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#categoryButtons .type-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('categoryInput').value = this.dataset.value;
    });
});

// Quand on clique sur un bouton destinataire, juste mettre à jour la valeur et l'affichage
// SANS recharger la page
document.querySelectorAll('#recipientButtons .type-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Mettre à jour l'affichage des boutons
        document.querySelectorAll('#recipientButtons .type-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        // Mettre à jour la valeur cachée
        const selectedValue = this.dataset.value;
        document.getElementById('recipientTypeInput').value = selectedValue;
        
        // Afficher/masquer la liste des membres
        const memberListContainer = document.getElementById('memberListContainer');
        if (memberListContainer) {
            memberListContainer.style.display = selectedValue === 'specific' ? 'block' : 'none';
        }
        
        // NE PAS soumettre le formulaire - rester sur étape 2
        // Le form submit se fera seulement au clic sur "Suivant"
    });
});

const membersList = document.getElementById('membersList');
const memberSearch = document.getElementById('memberSearch');
if (memberSearch) {
    memberSearch.addEventListener('keyup', () => {
        const query = memberSearch.value.toLowerCase();
        membersList.querySelectorAll('.member-item').forEach(item => {
            item.style.display = !query || item.dataset.search.includes(query) ? 'flex' : 'none';
        });
    });
}

// Gérer le clic sur les membres avec délégation d'événements au document
document.addEventListener('click', e => {
    const item = e.target.closest('.member-item');
    if (item && item.closest('#membersList')) {
        const memberId = parseInt(item.dataset.memberId);
        item.classList.toggle('selected');
        item.querySelector('.member-checkbox').textContent = item.classList.contains('selected') ? '✓' : '';
    }
});

// Gérer le bouton "Valider la sélection"
document.getElementById('validateMembersBtn')?.addEventListener('click', () => {
    const membersList = document.getElementById('membersList');
    if (membersList) {
        const selectedItems = membersList.querySelectorAll('.member-item.selected');
        const selectedIds = Array.from(selectedItems).map(item => item.dataset.memberId).join(',');
        const input = document.getElementById('specificMembersInput');
        if (input) {
            input.value = selectedIds;
            
            // Mettre à jour le compteur
            const count = selectedItems.length;
            const countDisplay = document.getElementById('recipientCountDisplay');
            const countSuffix = document.getElementById('recipientCountSuffix');
            if (countDisplay) {
                countDisplay.textContent = count;
                if (countSuffix) {
                    countSuffix.textContent = count !== 1 ? 's' : '';
                }
            }
        }
    }
});

const editor = document.getElementById('messageEditor');
const toolbar = document.getElementById('editorToolbar');
if (toolbar) {
    toolbar.querySelectorAll('button[data-command]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const cmd = btn.dataset.command;
            if (cmd === 'createLink') {
                const url = prompt('URL:');
                if (url) document.execCommand(cmd, false, url);
            } else {
                document.execCommand(cmd);
            }
            editor.focus();
        });
    });
}
document.getElementById('colorPicker')?.addEventListener('change', e => {
    if (e.target.value) {
        editor.focus();
        // Utiliser foreColor qui devrait fonctionner sur la sélection courante
        const selection = window.getSelection();
        if (selection.toString().length > 0) {
            // Il y a une sélection, appliquer la couleur
            document.execCommand('foreColor', false, e.target.value);
        } else {
            // Pas de sélection, afficher un message d'aide
            alert('Sélectionnez d\'abord le texte à colorier');
        }
        e.target.value = '';
    }
});
document.getElementById('contentForm')?.addEventListener('submit', () => {
    document.getElementById('hiddenMessage').value = editor.innerHTML;
});

// Gérer les boutons "Retour" qui créent un formulaire POST pour prev_step
document.querySelectorAll('.btn-prev').forEach(btn => {
    btn.addEventListener('click', function() {
        const step = this.dataset.step;
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'prev_step';
        
        const stepInput = document.createElement('input');
        stepInput.type = 'hidden';
        stepInput.name = 'step';
        stepInput.value = step;
        
        form.appendChild(actionInput);
        form.appendChild(stepInput);
        
        // Ajouter aussi les données des destinataires
        const recipientTypeInput = document.getElementById('recipientTypeInput');
        const specificMembersInput = document.getElementById('specificMembersInput');
        if (recipientTypeInput) {
            const rt = document.createElement('input');
            rt.type = 'hidden';
            rt.name = 'recipientType';
            rt.value = recipientTypeInput.value;
            form.appendChild(rt);
        }
        if (specificMembersInput) {
            const sm = document.createElement('input');
            sm.type = 'hidden';
            sm.name = 'specificMembers';
            sm.value = specificMembersInput.value;
            form.appendChild(sm);
        }
        
        document.body.appendChild(form);
        form.submit();
    });
});

const subjectPrefixes = { 'custom': '', 'communication': '📢 Communication - ', 'nouveau_membre': '🎉 Bienvenue - ' };
function updateSubjectPreview() {
    const type = '<?= $subjectType ?>';
    const prefix = subjectPrefixes[type] || '';
    const subject = document.getElementById('subjectInput')?.value || '';
    const preview = document.getElementById('subjectPreview');
    if (preview) preview.textContent = prefix + subject;
}
document.getElementById('subjectInput')?.addEventListener('input', updateSubjectPreview);
updateSubjectPreview();
</script>

<script>
function showChangelogPreview() {
    document.getElementById('changelogModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    updatePreview();
}

function updatePreview() {
    const versionSelector = document.getElementById('versionSelector');
    const selectedVersion = versionSelector ? versionSelector.value : '';
    
    // Mettre à jour l'input hidden
    document.getElementById('selectedVersionInput').value = selectedVersion;
    
    // Afficher un loader
    document.getElementById('changelogPreviewContent').innerHTML = '<div style="text-align: center; padding: 2rem;"><div style="border: 4px solid #f3f4f6; border-top: 4px solid #004b8d; border-radius: 50%; width: 48px; height: 48px; animation: spin 1s linear infinite; margin: 0 auto;"></div><p style="margin-top: 1rem; color: #6b7280;">Chargement de l\'aperçu...</p></div><style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); }}</style>';
    
    // Charger l'aperçu via fetch
    fetch('preview_changelog_email.php?version=' + encodeURIComponent(selectedVersion))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('changelogPreviewContent').innerHTML = data.html;
            } else {
                document.getElementById('changelogPreviewContent').innerHTML = '<div style="padding: 2rem; text-align: center; color: #ef4444;">Erreur: ' + (data.error || 'Impossible de charger l\'aperçu') + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('changelogPreviewContent').innerHTML = '<div style="padding: 2rem; text-align: center; color: #ef4444;">Erreur de chargement</div>';
        });
}

function hideChangelogPreview() {
    document.getElementById('changelogModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Fermer avec Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideChangelogPreview();
    }
});
</script>

<?php require 'footer.php'; ?>
