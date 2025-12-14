<?php
/**
 * Document Parser - Extraction de texte et métadonnées depuis différents formats
 * Supporte : PDF, Images (OCR), DOCX, TXT
 */

class DocumentParser {
    private $file_path;
    private $file_type;
    private $extracted_text = '';
    private $metadata = [];
    
    public function __construct($file_path, $file_type = null) {
        $this->file_path = $file_path;
        $this->file_type = $file_type ?? mime_content_type($file_path);
    }
    
    /**
     * Parse le document et extrait le texte
     */
    public function parse() {
        if (!file_exists($this->file_path)) {
            throw new Exception("Fichier introuvable: " . $this->file_path);
        }
        
        // Selon le type de fichier
        if (strpos($this->file_type, 'pdf') !== false) {
            return $this->parsePDF();
        } elseif (strpos($this->file_type, 'image') !== false) {
            return $this->parseImage();
        } elseif (strpos($this->file_type, 'wordprocessing') !== false || 
                  strpos($this->file_type, 'msword') !== false ||
                  pathinfo($this->file_path, PATHINFO_EXTENSION) === 'docx') {
            return $this->parseDOCX();
        } elseif (strpos($this->file_type, 'text') !== false) {
            return $this->parseText();
        }
        
        return false;
    }
    
    /**
     * Parse un PDF
     */
    private function parsePDF() {
        // Méthode 1 : pdftotext (le plus rapide et précis)
        if ($this->isPdftotextAvailable()) {
            $output = [];
            $temp_file = tempnam(sys_get_temp_dir(), 'pdf_text_');
            exec("pdftotext -layout " . escapeshellarg($this->file_path) . " " . escapeshellarg($temp_file) . " 2>&1", $output, $return_var);
            
            if ($return_var === 0 && file_exists($temp_file)) {
                $this->extracted_text = file_get_contents($temp_file);
                unlink($temp_file);
                
                if (!empty(trim($this->extracted_text))) {
                    return true;
                }
            }
        }
        
        // Méthode 2: PdfParser library (PHP pur)
        if (class_exists('Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($this->file_path);
                $this->extracted_text = $pdf->getText();
                
                // Extraire les métadonnées
                $details = $pdf->getDetails();
                $this->metadata['title'] = $details['Title'] ?? '';
                $this->metadata['author'] = $details['Author'] ?? '';
                $this->metadata['creation_date'] = $details['CreationDate'] ?? '';
                
                if (!empty(trim($this->extracted_text))) {
                    return true;
                }
            } catch (Exception $e) {
                // Continuer vers méthode suivante
            }
        }
        
        // Méthode 3 : Extraction avec décompression FlateDecode
        $text = $this->extractWithDecompression();
        if (!empty($text)) {
            $this->extracted_text = $text;
            return true;
        }
        
        // Méthode 4 : Extraction brute (fallback sans dépendances)
        $text = $this->extractRawPDFText();
        if (!empty($text)) {
            $this->extracted_text = $text;
            return true;
        }
        
        // Méthode 5 : Convertir en images puis OCR
        return $this->parsePDFAsImages();
    }
    
    /**
     * Extraction PDF avec décompression des streams FlateDecode
     */
    private function extractWithDecompression() {
        $content = @file_get_contents($this->file_path);
        if ($content === false || strlen($content) < 100) {
            return '';
        }
        
        $text = '';
        
        // Trouver tous les streams compressés avec FlateDecode
        preg_match_all('/stream\s*\n(.*?)\nendstream/s', $content, $streams);
        
        foreach ($streams[1] as $stream) {
            // Essayer de décompresser avec zlib
            if (function_exists('gzuncompress')) {
                $decompressed = @gzuncompress($stream);
                if ($decompressed !== false) {
                    // Extraire le texte du stream décompressé
                    if (preg_match_all('/\(([^\)]+)\)\s*Tj/s', $decompressed, $matches)) {
                        foreach ($matches[1] as $match) {
                            $decoded = $this->decodePDFString($match);
                            $text .= $decoded . ' ';
                        }
                    }
                    // Aussi chercher dans les tableaux de texte
                    if (preg_match_all('/\[\s*\(([^\)]+)\)\s*\]/s', $decompressed, $matches)) {
                        foreach ($matches[1] as $match) {
                            $decoded = $this->decodePDFString($match);
                            $text .= $decoded . ' ';
                        }
                    }
                }
            }
            
            // Essayer aussi avec gzinflate
            if (function_exists('gzinflate') && empty($text)) {
                $decompressed = @gzinflate($stream);
                if ($decompressed !== false) {
                    if (preg_match_all('/\(([^\)]+)\)\s*Tj/s', $decompressed, $matches)) {
                        foreach ($matches[1] as $match) {
                            $decoded = $this->decodePDFString($match);
                            $text .= $decoded . ' ';
                        }
                    }
                }
            }
        }
        
        // Nettoyer
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Extraction brute du texte PDF (fallback sans dépendances)
     * Méthode basique qui extrait les chaînes de texte visibles
     */
    private function extractRawPDFText() {
        $content = @file_get_contents($this->file_path);
        if ($content === false || strlen($content) < 100) {
            return '';
        }
        
        $text = '';
        $found_text = false;
        
        // Méthode 1: Extraction agressive de tout texte entre parenthèses (plus permissive)
        if (preg_match_all('/\(([^\)]{2,})\)/s', $content, $matches)) {
            foreach ($matches[1] as $match) {
                // Décoder les caractères échappés
                $decoded = $this->decodePDFString($match);
                // Moins restrictif: accepter même les courtes chaînes
                if (strlen(trim($decoded)) > 0) {
                    $text .= $decoded . ' ';
                    $found_text = true;
                }
            }
        }
        
        // Méthode 2: Extraction des objets texte BT...ET
        if (preg_match_all('/BT\s+(.*?)\s+ET/s', $content, $matches)) {
            foreach ($matches[1] as $match) {
                // Extraire le texte des commandes Tj et TJ
                if (preg_match_all('/\(([^\)]+)\)\s*T[jJ]/s', $match, $textMatches)) {
                    foreach ($textMatches[1] as $textMatch) {
                        $decoded = $this->decodePDFString($textMatch);
                        $text .= $decoded . ' ';
                        $found_text = true;
                    }
                }
                // Tableau de texte
                if (preg_match_all('/\[\s*\(([^\)]+)\)\s*\]/s', $match, $arrayMatches)) {
                    foreach ($arrayMatches[1] as $arrayMatch) {
                        $decoded = $this->decodePDFString($arrayMatch);
                        $text .= $decoded . ' ';
                        $found_text = true;
                    }
                }
            }
        }
        
        // Méthode 3: Extraction hexadécimale <...>
        if (preg_match_all('/<([0-9A-Fa-f]+)>/s', $content, $hexMatches)) {
            foreach ($hexMatches[1] as $hex) {
                if (strlen($hex) % 2 == 0) {
                    $decoded = '';
                    for ($i = 0; $i < strlen($hex); $i += 2) {
                        $byte = hexdec(substr($hex, $i, 2));
                        if ($byte >= 32 && $byte < 127) {
                            $decoded .= chr($byte);
                        }
                    }
                    if (strlen($decoded) > 0) {
                        $text .= $decoded . ' ';
                        $found_text = true;
                    }
                }
            }
        }
        
        // Si aucun texte trouvé, essayer extraction ultra-basique
        if (!$found_text) {
            // Chercher toutes les chaînes ASCII imprimables de 4+ caractères
            if (preg_match_all('/[\x20-\x7E]{4,}/', $content, $asciiMatches)) {
                $text = implode(' ', $asciiMatches[0]);
                $found_text = strlen($text) > 50;
            }
        }
        
        // Nettoyer le texte
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return strlen($text) > 20 ? $text : '';
    }
    
    /**
     * Décode une chaîne PDF (gère les échappements)
     */
    private function decodePDFString($str) {
        // Décoder les séquences d'échappement
        $str = str_replace('\\n', "\n", $str);
        $str = str_replace('\\r', "\r", $str);
        $str = str_replace('\\t', "\t", $str);
        $str = str_replace('\\(', '(', $str);
        $str = str_replace('\\)', ')', $str);
        $str = str_replace('\\\\', '\\', $str);
        
        // Décoder les séquences octales (\000)
        $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function($m) {
            return chr(octdec($m[1]));
        }, $str);
        
        return $str;
    }
    
    /**
     * Parse une image avec OCR
     */
    private function parseImage() {
        // Tesseract OCR
        if ($this->isTesseractAvailable()) {
            $temp_file = tempnam(sys_get_temp_dir(), 'ocr_text_');
            exec("tesseract " . escapeshellarg($this->file_path) . " " . escapeshellarg($temp_file) . " -l fra 2>&1", $output, $return_var);
            
            if (file_exists($temp_file . '.txt')) {
                $this->extracted_text = file_get_contents($temp_file . '.txt');
                unlink($temp_file . '.txt');
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Parse un PDF en le convertissant en images puis OCR
     */
    private function parsePDFAsImages() {
        // ImageMagick + Tesseract
        if ($this->isImageMagickAvailable() && $this->isTesseractAvailable()) {
            $temp_dir = sys_get_temp_dir() . '/pdf_ocr_' . uniqid();
            mkdir($temp_dir);
            
            // Convertir PDF en images
            exec("convert -density 300 " . escapeshellarg($this->file_path) . " -quality 100 " . escapeshellarg($temp_dir . '/page.png') . " 2>&1");
            
            // OCR sur chaque page
            $text_parts = [];
            $pages = glob($temp_dir . '/page*.png');
            
            foreach ($pages as $page) {
                $temp_file = tempnam(sys_get_temp_dir(), 'ocr_');
                exec("tesseract " . escapeshellarg($page) . " " . escapeshellarg($temp_file) . " -l fra 2>&1");
                
                if (file_exists($temp_file . '.txt')) {
                    $text_parts[] = file_get_contents($temp_file . '.txt');
                    unlink($temp_file . '.txt');
                }
            }
            
            // Nettoyer
            array_map('unlink', $pages);
            rmdir($temp_dir);
            
            if (!empty($text_parts)) {
                $this->extracted_text = implode("\n\n", $text_parts);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Parse un document DOCX
     */
    private function parseDOCX() {
        if (!class_exists('ZipArchive')) {
            return false;
        }
        
        $zip = new ZipArchive;
        if ($zip->open($this->file_path) === true) {
            $xml_content = $zip->getFromName('word/document.xml');
            $zip->close();
            
            if ($xml_content) {
                // Extraire le texte du XML
                $xml = simplexml_load_string($xml_content);
                if ($xml) {
                    $namespaces = $xml->getNamespaces(true);
                    $text_parts = [];
                    
                    foreach ($xml->xpath('//w:t') as $text) {
                        $text_parts[] = (string)$text;
                    }
                    
                    $this->extracted_text = implode(' ', $text_parts);
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Parse un fichier texte simple
     */
    private function parseText() {
        $this->extracted_text = file_get_contents($this->file_path);
        return true;
    }
    
    /**
     * Récupère le texte extrait
     */
    public function getText() {
        return $this->extracted_text;
    }
    
    /**
     * Récupère les métadonnées
     */
    public function getMetadata() {
        return $this->metadata;
    }
    
    /**
     * Nettoie et normalise le texte
     */
    public function getCleanText() {
        $text = $this->extracted_text;
        
        // Supprimer les caractères de contrôle
        $text = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text);
        
        // Normaliser les espaces
        $text = preg_replace('/\s+/u', ' ', $text);
        
        // Trim
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Vérifications de disponibilité des outils
     */
    private function isPdftotextAvailable() {
        exec('which pdftotext 2>&1', $output, $return_var);
        return $return_var === 0;
    }
    
    private function isTesseractAvailable() {
        exec('which tesseract 2>&1', $output, $return_var);
        return $return_var === 0;
    }
    
    private function isImageMagickAvailable() {
        exec('which convert 2>&1', $output, $return_var);
        return $return_var === 0;
    }
}
