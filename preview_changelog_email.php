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
            $versionNum = trim($versionData[2]);
            // Matcher exactement ou si la sélection est le début (ex: "1.5" match "1.5.0")
            if ($versionNum === $selectedVersion || strpos($versionNum, $selectedVersion . '.') === 0) {
                $startIndex = $idx;
                break;
            }
        }
    }
    
    // Extraire le contenu depuis la version sélectionnée jusqu'à la première
    $allAddedItems = [];
    $allChangedItems = [];
    $allFixedItems = [];
    $itemsByVersion = [];
    
    if ($startIndex === 0) {
        // Juste la dernière version
        $versionsToProcess = [0];
    } else {
        // Toutes les versions depuis la sélectionnée jusqu'à la plus récente
        $versionsToProcess = range(0, $startIndex);
    }
    
    // Collecter les items par version pour avoir une représentation équilibrée
    foreach ($versionsToProcess as $versionIdx) {
        $versionId = trim($allVersions[$versionIdx][1]);
        $versionNumber = trim($allVersions[$versionIdx][2]);
        $versionDate = trim($allVersions[$versionIdx][3]);
        $nextVersionId = isset($allVersions[$versionIdx + 1]) ? trim($allVersions[$versionIdx + 1][1]) : null;
        
        $itemsByVersion[$versionNumber] = ['date' => $versionDate, 'added' => [], 'changed' => [], 'fixed' => []];
        
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
                        $itemsByVersion[$versionNumber]['added'][] = $cleanItem;
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
                        $itemsByVersion[$versionNumber]['changed'][] = $cleanItem;
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
                        $itemsByVersion[$versionNumber]['fixed'][] = $cleanItem;
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
    
    // Créer un résumé avec 1 item principal par version
    $totalAdded = count($allAddedItems);
    $totalChanged = count($allChangedItems);
    $totalFixed = count($allFixedItems);
    
    $highlights = [];
    
    // Pour chaque version, prendre le premier item représentatif
    foreach ($itemsByVersion as $versionNum => $versionData) {
        // Prendre le premier Added, sinon premier Changed, sinon premier Fixed
        $firstItem = null;
        if (!empty($versionData['added'])) {
            $firstItem = $versionData['added'][0];
        } elseif (!empty($versionData['changed'])) {
            $firstItem = $versionData['changed'][0];
        } elseif (!empty($versionData['fixed'])) {
            $firstItem = $versionData['fixed'][0];
        }
        
        if ($firstItem) {
            $parts = explode(':', $firstItem, 2);
            $title = strip_tags($parts[0]);
            $desc = '';
            if (isset($parts[1])) {
                // Prendre la première phrase de description
                $descText = strip_tags($parts[1]);
                $sentences = preg_split('/(?<=[.!?])\s+/', trim($descText), 2);
                $desc = trim($sentences[0]);
            }
            if (!empty(trim($title))) {
                $highlights[] = [
                    'version' => $versionNum,
                    'date' => $versionData['date'],
                    'title' => $title,
                    'desc' => $desc
                ];
            }
        }
    }
    
    // Résumé visuel avec nouveautés
    $sectionsHtml = '<div style="background: linear-gradient(135deg, #e0f2fe, #dbeafe); padding: 1.5rem; border-radius: 10px; border: 2px solid #3b82f6;">';
    $sectionsHtml .= '<p style="margin: 0 0 1rem 0; font-size: 1.2rem; color: #1e3a8a; font-weight: 600; text-align: center;">✨ Nouveautés</p>';
    
    if (!empty($highlights)) {
        foreach ($highlights as $item) {
            $sectionsHtml .= '<div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #bfdbfe;">';
            $sectionsHtml .= '<div style="font-weight: 600; color: #0369a1; font-size: 1rem; margin-bottom: 0.25rem;">';
            $sectionsHtml .= '[' . htmlspecialchars($item['version']) . '] <span style="font-weight: 400; color: #64748b; font-size: 0.85rem;">' . htmlspecialchars($item['date']) . '</span>';
            $sectionsHtml .= '</div>';
            $sectionsHtml .= '<strong style="color: #1e3a8a;">' . htmlspecialchars($item['title']) . '</strong>';
            
            // Afficher la version si plusieurs versions sélectionnées
            if ($nbVersions > 1 && isset($item['version'])) {
                $sectionsHtml .= ' <span style="font-size: 0.8rem; color: #64748b; font-style: italic;">(v' . htmlspecialchars($item['version']) . ')</span>';
            }
            
            if (!empty($item['desc'])) {
                $sectionsHtml .= '<br><span style="color: #1e40af; font-size: 0.95rem;">' . htmlspecialchars($item['desc']) . '</span>';
            }
            $sectionsHtml .= '</div>';
        }
        // Retirer la bordure du dernier élément
        $sectionsHtml = str_replace('border-bottom: 1px solid #bfdbfe;">' . "\n" . '</div>' . "\n" . '</div>', '">' . "\n" . '</div>' . "\n" . '</div>', $sectionsHtml);
    }
    
    if ($totalChanged > 0 || $totalFixed > 0) {
        $sectionsHtml .= '<p style="margin: 1rem 0 0 0; font-size: 0.95rem; color: #1e40af; text-align: center; border-top: 1px solid #bfdbfe; padding-top: 1rem;">';
        $sectionsHtml .= '🔄 + ' . ($totalChanged + $totalFixed) . ' amélioration' . (($totalChanged + $totalFixed) > 1 ? 's' : '') . ' et correction' . (($totalChanged + $totalFixed) > 1 ? 's' : '');
        $sectionsHtml .= '</p>';
    }
    $sectionsHtml .= '</div>';
    
    $sectionsHtml .= '<div style="margin-top: 1.5rem; padding: 1.25rem; background: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">';
    $sectionsHtml .= '<p style="margin: 0; color: #92400e; font-size: 1rem; line-height: 1.6;">';
    $sectionsHtml .= '💡 <strong>Connectez-vous pour découvrir toutes les nouveautés !</strong>';
    $sectionsHtml .= '</p>';
    $sectionsHtml .= '</div>';
    
    // Message d'introduction simplifié
    $introMessage = "Votre application GESTNAV a été mise à jour !";
    
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
    $previewHtml .= '<p style="margin-bottom: 1.5rem; font-size: 1rem; color: #374151;">' . $introMessage . '</p>';
    $previewHtml .= $sectionsHtml;
    $previewHtml .= '<div style="text-align: center; margin-top: 1.5rem; padding: 1rem; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">';
    $previewHtml .= '<p style="margin: 0; color: #6b7280; font-size: 0.9rem;">';
    $previewHtml .= '📋 Pour les curieux, tous les détails techniques sont disponibles sur le ';
    $previewHtml .= '<a href="https://gestnav.clubulmevasion.fr/changelog.php" style="color: #2563eb; font-weight: 500; text-decoration: underline;">changelog</a>';
    $previewHtml .= '</p></div>';
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
