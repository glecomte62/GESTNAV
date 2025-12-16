<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

header('Content-Type: application/json');

try {
    $selectedVersion = $_GET['version'] ?? '';
    
    // Lire le fichier changelog.php
    $changelogPath = __DIR__ . '/changelog.php';
    if (!file_exists($changelogPath)) {
        echo json_encode(['success' => false, 'error' => 'Fichier changelog.php introuvable']);
        exit;
    }
    
    $changelogContent = file_get_contents($changelogPath);
    
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
    $allAddedItems = [];
    $allChangedItems = [];
    $allFixedItems = [];
    
    if ($startIndex === 0) {
        // Juste la dernière version
        $versionsToProcess = [0];
    } else {
        // Toutes les versions depuis la sélectionnée jusqu'à la plus récente
        $versionsToProcess = range(0, $startIndex);
    }
    
    // Collecter tous les items de toutes les versions
    foreach ($versionsToProcess as $versionIdx) {
        $versionId = trim($allVersions[$versionIdx][1]);
        $nextVersionId = isset($allVersions[$versionIdx + 1]) ? trim($allVersions[$versionIdx + 1][1]) : null;
        
        // Extraire le bloc de cette version
        if ($nextVersionId) {
            $pattern = '/<!-- Version ' . preg_quote($versionId, '/') . ' -->.*?(?=<!-- Version ' . preg_quote($nextVersionId, '/') . ')/s';
        } else {
            $pattern = '/<!-- Version ' . preg_quote($versionId, '/') . ' -->.*$/s';
        }
        
        if (preg_match($pattern, $changelogContent, $versionBlock)) {
            $block = $versionBlock[0];
            
            // Extraire Added
            if (preg_match('/<div class="changelog-section-added">.*?<ul class="changelog-items">(.*?)<\/ul>/s', $block, $addedMatch)) {
                preg_match_all('/<li>(.*?)<\/li>/s', $addedMatch[1], $items);
                foreach ($items[1] as $item) {
                    $cleanItem = strip_tags($item, '<strong><code>');
                    if (!empty(trim($cleanItem))) {
                        $allAddedItems[] = $cleanItem;
                    }
                }
            }
            
            // Extraire Changed
            if (preg_match('/<div class="changelog-section-changed">.*?<ul class="changelog-items">(.*?)<\/ul>/s', $block, $changedMatch)) {
                preg_match_all('/<li>(.*?)<\/li>/s', $changedMatch[1], $items);
                foreach ($items[1] as $item) {
                    $cleanItem = strip_tags($item, '<strong><code>');
                    if (!empty(trim($cleanItem))) {
                        $allChangedItems[] = $cleanItem;
                    }
                }
            }
            
            // Extraire Fixed
            if (preg_match('/<div class="changelog-section-fixed">.*?<ul class="changelog-items">(.*?)<\/ul>/s', $block, $fixedMatch)) {
                preg_match_all('/<li>(.*?)<\/li>/s', $fixedMatch[1], $items);
                foreach ($items[1] as $item) {
                    $cleanItem = strip_tags($item, '<strong><code>');
                    if (!empty(trim($cleanItem))) {
                        $allFixedItems[] = $cleanItem;
                    }
                }
            }
        }
    }
    
    $version = trim($allVersions[0][2]);
    $date = trim($allVersions[0][3]);
    
    // Déterminer la plage de versions pour le message
    $versionRange = '';
    if ($startIndex > 0) {
        $versionRange = ' (versions ' . trim($allVersions[$startIndex][2]) . ' à ' . $version . ')';
    }
    
    // Compter les destinataires
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE actif = 1 AND email IS NOT NULL AND email != ''");
    $recipientCount = (int)$stmt->fetchColumn();
    
    // Construire les sections HTML avec tous les items collectés
    $sectionsHtml = '';
    
    // Section Added (✨)
    if (!empty($allAddedItems)) {
        $sectionsHtml .= '<h3 style="color: #10b981; margin-top: 1.5rem; margin-bottom: 0.75rem; font-size: 1.15rem;">✨ Nouveautés</h3>';
        $sectionsHtml .= '<ul style="margin: 0; padding-left: 1.5rem; line-height: 1.8;">';
        foreach ($allAddedItems as $item) {
            $sectionsHtml .= '<li>' . $item . '</li>';
        }
        $sectionsHtml .= '</ul>';
    }
    
    // Section Changed (🔄)
    if (!empty($allChangedItems)) {
        $sectionsHtml .= '<h3 style="color: #f59e0b; margin-top: 1.5rem; margin-bottom: 0.75rem; font-size: 1.15rem;">🔄 Améliorations</h3>';
        $sectionsHtml .= '<ul style="margin: 0; padding-left: 1.5rem; line-height: 1.8;">';
        foreach ($allChangedItems as $item) {
            $sectionsHtml .= '<li>' . $item . '</li>';
        }
        $sectionsHtml .= '</ul>';
    }
    
    // Section Fixed (🐛)
    if (!empty($allFixedItems)) {
        $sectionsHtml .= '<h3 style="color: #ef4444; margin-top: 1.5rem; margin-bottom: 0.75rem; font-size: 1.15rem;">🐛 Corrections</h3>';
        $sectionsHtml .= '<ul style="margin: 0; padding-left: 1.5rem; line-height: 1.8;">';
        foreach ($allFixedItems as $item) {
            $sectionsHtml .= '<li>' . $item . '</li>';
        }
        $sectionsHtml .= '</ul>';
    }
    
    // Nettoyer le HTML
    $sectionsHtml = str_replace('<code>', '<code style="background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; color: #d97706;">', $sectionsHtml);
    
    // Message d'introduction personnalisé selon le nombre de versions
    $introMessage = '';
    if ($startIndex > 0) {
        $nbVersions = $startIndex + 1;
        $introMessage = "Nous avons le plaisir de vous présenter <strong>$nbVersions nouvelle" . ($nbVersions > 1 ? 's' : '') . " version" . ($nbVersions > 1 ? 's' : '') . "</strong> de GESTNAV ! Voici un récapitulatif de toutes les évolutions apportées depuis la version " . trim($allVersions[$startIndex][2]) . " :";
    } else {
        $introMessage = "Nous sommes ravis de vous annoncer que votre application <strong>GESTNAV</strong> vient d'être mise à jour avec de nouvelles fonctionnalités et améliorations :";
    }
    
    // Construire l'aperçu HTML
    $previewHtml = '<div style="font-family: Arial, sans-serif; line-height: 1.6;">';
    $previewHtml .= '<div style="background: #f0fbff; border-left: 3px solid #00a0c6; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">';
    $previewHtml .= '<strong style="color: #004b8d;">📧 Sujet :</strong> 🚀 Votre application GESTNAV évolue encore...' . $versionRange . '<br>';
    $previewHtml .= '<strong style="color: #004b8d;">👥 Destinataires :</strong> ' . $recipientCount . ' membre(s) actif(s)<br>';
    $previewHtml .= '<strong style="color: #004b8d;">📅 Version actuelle :</strong> ' . htmlspecialchars($version) . ' • ' . htmlspecialchars($date);
    if ($startIndex > 0) {
        $previewHtml .= '<br><strong style="color: #10b981;">📦 Versions incluses :</strong> ' . trim($allVersions[$startIndex][2]) . ' → ' . $version . ' (' . ($startIndex + 1) . ' version' . ($startIndex > 0 ? 's' : '') . ')';
    }
    $previewHtml .= '</div>';
    
    $previewHtml .= '<div style="border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">';
    $previewHtml .= '<div style="background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; padding: 2rem; text-align: center;">';
    $previewHtml .= '<h1 style="margin: 0; font-size: 1.8rem;">🚀 GESTNAV évolue !</h1>';
    $previewHtml .= '<p style="margin: 0.5rem 0 0; opacity: 0.9; font-size: 1rem;">Version ' . htmlspecialchars($version) . ' • ' . htmlspecialchars($date) . '</p>';
    $previewHtml .= '</div>';
    
    $previewHtml .= '<div style="padding: 2rem; background: white;">';
    $previewHtml .= '<p style="font-size: 1.1rem; margin-bottom: 1.5rem;">Bonjour <strong style="color: #00a0c6;">[Prénom]</strong>,</p>';
    $previewHtml .= '<p style="margin-bottom: 1.5rem;">' . $introMessage . '</p>';
    $previewHtml .= '<div style="background: #f9fafb; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #00a0c6;">';
    $previewHtml .= $sectionsHtml;
    $previewHtml .= '</div>';
    $previewHtml .= '<p style="margin-top: 2rem;">N\'hésitez pas à vous connecter pour découvrir ces nouveautés !</p>';
    $previewHtml .= '<p style="text-align: center; margin-top: 2rem;">';
    $previewHtml .= '<a href="https://gestnav.clubulmevasion.fr" style="display:inline-block;padding:12px 24px;border-radius:6px;background-color:#004b8d;color:#ffffff;text-decoration:none;font-weight:600;">🔗 Accéder à GESTNAV</a>';
    $previewHtml .= '</p>';
    $previewHtml .= '</div>';
    
    $previewHtml .= '<div style="padding: 1.5rem; text-align: center; background: #f9fafb; border-top: 1px solid #e5e7eb;">';
    $previewHtml .= '<p style="font-size: 12px; color: #666; margin: 5px 0;">';
    $previewHtml .= '<strong>Club ULM Evasion</strong><br>';
    $previewHtml .= 'GESTNAV v' . GESTNAV_VERSION;
    $previewHtml .= '</p>';
    $previewHtml .= '</div>';
    $previewHtml .= '</div>';
    $previewHtml .= '</div>';
    
    echo json_encode(['success' => true, 'html' => $previewHtml]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
