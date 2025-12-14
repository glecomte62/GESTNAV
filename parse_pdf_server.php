<?php
/**
 * Parser PDF côté serveur - Extraction professionnelle
 * Utilise pdftotext (plus fiable que PDF.js)
 */

// Augmenter les limites PHP
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '60');

// Gestionnaire d'erreurs global pour TOUJOURS retourner du JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: $errstr in $errfile:$errline");
    return true; // Ne pas afficher l'erreur
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'error' => 'Erreur serveur: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
});

// Désactiver l'affichage des erreurs pour éviter de corrompre le JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Charger l'autoloader Composer si disponible
$composerLoaded = false;
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $composerLoaded = true;
}

// Charger les nouvelles classes de classification
require_once __DIR__ . '/document_parser.php';
require_once __DIR__ . '/document_analyzer.php';

// Headers AVANT toute sortie
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Log pour debug (dans les logs PHP, pas dans la sortie)
error_log("parse_pdf_server.php - Démarrage");
error_log("Composer autoload: " . ($composerLoaded ? 'OUI' : 'NON'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données (soit FormData, soit JSON avec base64)
$file = null;
$filename = '';
$tmpPath = '';
$cleanupTemp = false;

try {
    // Méthode 1: Texte déjà extrait côté client (PDF.js)
    $jsonInput = file_get_contents('php://input');
    error_log("parse_pdf_server.php - Input length: " . strlen($jsonInput));
    
    $jsonData = json_decode($jsonInput, true);

    if ($jsonData && isset($jsonData['text'])) {
        // Texte pré-extrait envoyé par PDF.js
        error_log("parse_pdf_server.php - Réception texte pré-extrait");
        
        $text = $jsonData['text'];
        $filename = $jsonData['filename'] ?? 'document.pdf';
        
        error_log("Texte reçu: " . strlen($text) . " caractères");
        error_log("Aperçu: " . substr($text, 0, 200));
        
        // Analyser directement le texte
        $analyzer = new DocumentAnalyzer();
        $analysis = $analyzer->analyze($text, $filename);
        
        // Retourner les résultats
        echo json_encode([
            'success' => true,
            'method' => 'client_extraction',
            'text_preview' => substr($text, 0, 500),
            'text_length' => strlen($text),
            'description' => $analysis['description'] ?? '',
            'supplier' => $analysis['supplier'] ?? '',
            'amount' => $analysis['amount'] ?? null,
            'is_ttc' => $analysis['is_ttc'] ?? false,
            'date_iso' => $analysis['date'] ?? null,
            'metadata' => [
                'dates' => $analysis['dates'] ?? [],
                'amounts' => $analysis['amounts'] ?? [],
                'total_amount' => $analysis['amount'] ?? null,
                'is_ttc' => $analysis['is_ttc'] ?? false
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
        
    } else if ($jsonData && isset($jsonData['pdf_base64'])) {
        // Méthode 2: PDF base64 classique
        error_log("parse_pdf_server.php - Réception base64");
        
        // Décoder le base64
        $pdfContent = base64_decode($jsonData['pdf_base64'], true);
        if ($pdfContent === false) {
            throw new Exception("Échec du décodage base64");
        }
        
        $filename = $jsonData['filename'] ?? 'document.pdf';
        
        // Créer un fichier temporaire
        $tmpPath = tempnam(sys_get_temp_dir(), 'pdf_');
        if ($tmpPath === false) {
            throw new Exception("Impossible de créer un fichier temporaire");
        }
        
        $written = file_put_contents($tmpPath, $pdfContent);
        if ($written === false) {
            throw new Exception("Impossible d'écrire le fichier temporaire");
        }
        
        $cleanupTemp = true;
        error_log("parse_pdf_server.php - Fichier temp créé: $tmpPath (" . strlen($pdfContent) . " octets)");
        
    } else if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        // Méthode 2: Upload classique (si ModSecurity désactivé)
        error_log("parse_pdf_server.php - Réception FormData");
        $file = $_FILES['document'];
        $filename = $file['name'];
        $tmpPath = $file['tmp_name'];
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Aucun fichier uploadé',
            'debug' => [
                'json_data' => $jsonData !== null,
                'has_base64' => isset($jsonData['pdf_base64']),
                'has_files' => !empty($_FILES)
            ]
        ]);
        exit;
    }

// TOUT le code d'analyse dans le try/catch
// Extraire le texte du PDF avec les nouvelles classes
$text = '';
$method = 'none';
$extracted_metadata = [];

// Utiliser le nouveau DocumentParser
try {
    error_log("parse_pdf_server.php - Utilisation de DocumentParser");
    $parser = new DocumentParser($tmpPath);
    
    if ($parser->parse()) {
        $text = $parser->getCleanText();
        $method = 'DocumentParser';
        error_log("parse_pdf_server.php - DocumentParser réussi, " . strlen($text) . " caractères extraits");
        
        // Analyser avec DocumentAnalyzer
        $analyzer = new DocumentAnalyzer($text);
        $extracted_metadata = $analyzer->analyze();
        error_log("parse_pdf_server.php - Analyse terminée: " . count($extracted_metadata['dates']) . " dates, " . count($extracted_metadata['amounts']) . " montants");
    }
} catch (Exception $e) {
    error_log("parse_pdf_server.php - Erreur DocumentParser: " . $e->getMessage());
}

// Fallback: Méthode 1: pdftotext (si DocumentParser a échoué)
if (empty($text) && function_exists('exec')) {
    $output = [];
    $return_var = 0;
    @exec("pdftotext -layout -enc UTF-8 " . escapeshellarg($tmpPath) . " -", $output, $return_var);
    if ($return_var === 0 && !empty($output)) {
        $text = implode("\n", $output);
        $method = 'pdftotext';
    }
}

// Fallback: Méthode 2: Fallback avec parser-pdf/pdf-parser (si installé via Composer)
if (empty($text) && class_exists('Smalot\PdfParser\Parser')) {
    try {
        error_log("parse_pdf_server.php - Utilisation de Smalot PDF Parser");
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($tmpPath);
        $text = $pdf->getText();
        $method = 'smalot-parser';
        error_log("parse_pdf_server.php - Smalot réussi, " . strlen($text) . " caractères extraits");
    } catch (Exception $e) {
        error_log("parse_pdf_server.php - Erreur Smalot: " . $e->getMessage());
        // Continuer
    }
}

// Fallback: Méthode 3: Extraction basique avec regex sur le fichier brut
if (empty($text)) {
    $content = file_get_contents($tmpPath);
    // Chercher les streams de texte
    preg_match_all('/\(((?:[^()\\\\]|\\\\.){10,})\)/', $content, $matches);
    if (!empty($matches[1])) {
        $text = implode(' ', $matches[1]);
        $method = 'raw-extraction';
    }
}

// Si on n'a pas utilisé DocumentAnalyzer (fallback), l'utiliser maintenant
if (empty($extracted_metadata) && !empty($text)) {
    try {
        $analyzer = new DocumentAnalyzer($text);
        $extracted_metadata = $analyzer->analyze();
    } catch (Exception $e) {
        error_log("parse_pdf_server.php - Erreur DocumentAnalyzer fallback: " . $e->getMessage());
    }
}

// ANALYSE INTELLIGENTE DU TEXTE
$result = [
    'success' => !empty($text),
    'method' => $method,
    'filename' => $filename,
    'text_length' => strlen($text),
    'invoice_number' => null,
    'amount' => null,
    'date' => null,
    'date_iso' => null,
    'supplier' => null,
    'raw_text' => substr($text, 0, 1000), // Premiers 1000 chars pour debug
    'metadata' => $extracted_metadata
];

if (!empty($text)) {
    // Utiliser les métadonnées extraites en priorité
    if (!empty($extracted_metadata)) {
        // DATE - Utiliser la plus récente trouvée
        if (!empty($extracted_metadata['most_recent_date'])) {
            $result['date_iso'] = $extracted_metadata['most_recent_date'];
            // Convertir en format d'affichage
            if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $extracted_metadata['most_recent_date'], $parts)) {
                $result['date'] = $parts[3] . '/' . $parts[2] . '/' . $parts[1];
            }
        }
        
        // MONTANT - Utiliser le total_amount (priorité TTC)
        if (!empty($extracted_metadata['total_amount'])) {
            $amount = $extracted_metadata['total_amount'];
            $result['amount'] = number_format($amount, 2, ',', ' ') . ' EUR';
            $result['is_ttc'] = $extracted_metadata['is_ttc'] ?? false;
        }
        
        // IMMATRICULATIONS
        if (!empty($extracted_metadata['immatriculations'])) {
            $result['immatriculations'] = $extracted_metadata['immatriculations'];
        }
        
        // EMAILS
        if (!empty($extracted_metadata['emails'])) {
            $result['emails'] = $extracted_metadata['emails'];
        }
    }
    
    // NUMÉRO DE FACTURE (garder la logique existante)
    // D'abord depuis le nom de fichier
    if (preg_match('/([0-9]{8,15})/', $filename, $match)) {
        $result['invoice_number'] = $match[1];
    }
    // Sinon depuis le texte
    if (!$result['invoice_number']) {
        $patterns = [
            '/(?:facture|invoice|bill)\s*(?:n°|numéro|number|#)?\s*:?\s*([A-Z0-9-]{5,20})/i',
            '/n°\s*(?:facture)?\s*:?\s*([A-Z0-9-]{5,20})/i',
            '/\b([0-9]{10,15})\b/'
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $result['invoice_number'] = $match[1];
                break;
            }
        }
    }
    
    // FOURNISSEUR (garder la logique existante)
    $suppliers = [
        'keyyo' => ['Keyyo', ['/keyyo/i', '/manager\.keyyo\.com/i']],
        'starlink' => ['Starlink', ['/starlink/i']],
        'orange' => ['Orange', ['/\borange\b/i']],
        'sfr' => ['SFR', ['/\bsfr\b/i']],
        'free' => ['Free', ['/\bfree\b/i']],
        'bouygues' => ['Bouygues', ['/bouygues/i']],
        'edf' => ['EDF', ['/\bedf\b/i']],
    ];
    
    foreach ($suppliers as $key => $config) {
        foreach ($config[1] as $pattern) {
            if (preg_match($pattern, $text) || preg_match($pattern, $filename)) {
                $result['supplier'] = $config[0];
                break 2;
            }
        }
    }
}

} catch (Exception $e) {
    error_log("parse_pdf_server.php - Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
} finally {
    // Nettoyer le fichier temporaire si créé
    if ($cleanupTemp && $tmpPath && file_exists($tmpPath)) {
        @unlink($tmpPath);
        error_log("parse_pdf_server.php - Fichier temp nettoyé");
    }
}

error_log("parse_pdf_server.php - Résultat: " . json_encode($result));

// Nettoyer le buffer de sortie au cas où
if (ob_get_level()) {
    ob_clean();
}

// Retourner UNIQUEMENT le JSON
echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;
