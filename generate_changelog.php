<?php
/**
 * Script de génération dynamique du changelog
 * Parse CHANGELOG.md et génère le HTML pour changelog.php
 * Usage: php generate_changelog.php > changelog_generated.html
 */

$changelogFile = __DIR__ . '/CHANGELOG.md';

if (!file_exists($changelogFile)) {
    echo "❌ Erreur: CHANGELOG.md not found\n";
    exit(1);
}

$content = file_get_contents($changelogFile);
$lines = explode("\n", $content);

$html = '';
$currentVersion = null;
$currentSection = null;
$items = [];

foreach ($lines as $line) {
    // Version header: ## [1.5.0] - 2025-12-06
    if (preg_match('/^## \[(\d+\.\d+\.\d+)\]\s*-\s*(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
        // Si on a une version précédente, la générer
        if ($currentVersion) {
            $html .= generateVersionBlock($currentVersion, $currentSection, $items);
        }
        
        $currentVersion = [
            'number' => $matches[1],
            'date' => $matches[2]
        ];
        $currentSection = null;
        $items = [];
    }
    // Section header: ### Added, ### Changed, ### Fixed
    elseif (preg_match('/^### (Added|Changed|Fixed)/', $line, $matches)) {
        if ($currentVersion) {
            $currentSection = strtolower($matches[1]);
            $items[$currentSection] = [];
        }
    }
    // List item: - text
    elseif (preg_match('/^- (.+)/', $line, $matches) && $currentVersion && $currentSection) {
        $text = $matches[1];
        // Convertir les backticks en <code>
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $items[$currentSection][] = $text;
    }
}

// Générer la dernière version
if ($currentVersion) {
    $html .= generateVersionBlock($currentVersion, $currentSection, $items);
}

// Sortir le HTML
echo $html;

function generateVersionBlock($version, $lastSection, $items) {
    $html = "\n    <!-- Version " . $version['number'] . " -->\n";
    $html .= "    <div class=\"changelog-version-block\">\n";
    $html .= "        <div class=\"changelog-version-header\">\n";
    $html .= "            <span class=\"version-number\">[" . htmlspecialchars($version['number']) . "]</span>\n";
    $html .= "            <span class=\"version-date\">" . htmlspecialchars($version['date']) . "</span>\n";
    $html .= "        </div>\n\n";
    
    $sections = ['added', 'changed', 'fixed'];
    foreach ($sections as $section) {
        if (isset($items[$section]) && !empty($items[$section])) {
            $sectionLabel = ucfirst($section);
            $html .= "        <div class=\"changelog-section-" . $section . "\">\n";
            $html .= "            <h3 class=\"changelog-section-type\">" . $sectionLabel . "</h3>\n";
            $html .= "            <ul class=\"changelog-items\">\n";
            
            foreach ($items[$section] as $item) {
                $html .= "                <li>" . $item . "</li>\n";
            }
            
            $html .= "            </ul>\n";
            $html .= "        </div>\n\n";
        }
    }
    
    $html .= "    </div>\n";
    return $html;
}
?>
