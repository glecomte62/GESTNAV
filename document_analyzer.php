<?php
/**
 * Document Analyzer - Analyse et extraction d'informations clés
 * Extrait : dates, immatriculations, numéros de série, montants, etc.
 */

class DocumentAnalyzer {
    private $text;
    private $extracted_data = [];
    
    public function __construct($text) {
        $this->text = $text;
    }
    
    /**
     * Analyse complète du document
     */
    public function analyze() {
        $this->extractDates();
        $this->extractImmatriculations();
        $this->extractSerialNumbers();
        $this->extractAmounts();
        $this->extractEmails();
        $this->extractPhones();
        
        return $this->extracted_data;
    }
    
    /**
     * Extrait les dates du document
     */
    private function extractDates() {
        $dates = [];
        
        // Format: DD/MM/YYYY ou DD-MM-YYYY
        preg_match_all('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $this->text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($match[2], 2, '0', STR_PAD_LEFT);
            $year = $match[3];
            
            // Valider la date
            if (checkdate($month, $day, $year)) {
                $dates[] = "$year-$month-$day";
            }
        }
        
        // Format: YYYY-MM-DD
        preg_match_all('/\b(\d{4})-(\d{2})-(\d{2})\b/', $this->text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (checkdate($match[2], $match[3], $match[1])) {
                $dates[] = $match[0];
            }
        }
        
        // Dates en français: "15 janvier 2025"
        $mois = [
            'janvier' => '01', 'février' => '02', 'fevrier' => '02', 'mars' => '03',
            'avril' => '04', 'mai' => '05', 'juin' => '06', 'juillet' => '07',
            'août' => '08', 'aout' => '08', 'septembre' => '09', 'octobre' => '10',
            'novembre' => '11', 'décembre' => '12', 'decembre' => '12'
        ];
        
        foreach ($mois as $nom => $num) {
            preg_match_all('/\b(\d{1,2})\s+' . $nom . '\s+(\d{4})\b/ui', $this->text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
                $year = $match[2];
                if (checkdate($num, $day, $year)) {
                    $dates[] = "$year-$num-$day";
                }
            }
        }
        
        // Dédupliquer et trier
        $dates = array_unique($dates);
        sort($dates);
        
        $this->extracted_data['dates'] = $dates;
        $this->extracted_data['most_recent_date'] = !empty($dates) ? end($dates) : null;
        $this->extracted_data['oldest_date'] = !empty($dates) ? reset($dates) : null;
    }
    
    /**
     * Extrait les immatriculations (format français)
     */
    private function extractImmatriculations() {
        $immatriculations = [];
        
        // Nouveau format: XX-123-XX
        preg_match_all('/\b([A-Z]{2}[\s\-]?\d{3}[\s\-]?[A-Z]{2})\b/', $this->text, $matches);
        foreach ($matches[1] as $match) {
            $clean = preg_replace('/[\s\-]/', '-', strtoupper($match));
            $immatriculations[] = $clean;
        }
        
        // Ancien format: 1234 XX 12
        preg_match_all('/\b(\d{1,4}\s+[A-Z]{2}\s+\d{2,3})\b/', $this->text, $matches);
        foreach ($matches[1] as $match) {
            $immatriculations[] = strtoupper($match);
        }
        
        // Format compact sans espaces: XX123XX
        preg_match_all('/\b([A-Z]{2}\d{3}[A-Z]{2})\b/', $this->text, $matches);
        foreach ($matches[1] as $match) {
            $formatted = substr($match, 0, 2) . '-' . substr($match, 2, 3) . '-' . substr($match, 5, 2);
            $immatriculations[] = $formatted;
        }
        
        $this->extracted_data['immatriculations'] = array_unique($immatriculations);
    }
    
    /**
     * Extrait les numéros de série
     */
    private function extractSerialNumbers() {
        $serials = [];
        
        // Patterns communs pour numéros de série
        $patterns = [
            '/\b(S\/N[\s:]*([A-Z0-9\-]{6,}))\b/i',           // S/N: XXXXX
            '/\b(N°[\s:]*([A-Z0-9\-]{6,}))\b/i',             // N° XXXXX
            '/\b(SN[\s:]*([A-Z0-9\-]{6,}))\b/i',             // SN XXXXX
            '/\b(Serial[\s:]*([A-Z0-9\-]{6,}))\b/i',         // Serial: XXXXX
            '/\b(Numéro de série[\s:]*([A-Z0-9\-]{6,}))\b/i' // Numéro de série: XXXXX
        ];
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $this->text, $matches);
            if (!empty($matches[2])) {
                $serials = array_merge($serials, $matches[2]);
            }
        }
        
        $this->extracted_data['serial_numbers'] = array_unique($serials);
    }
    
    /**
     * Extrait les montants
     */
    private function extractAmounts() {
        $amounts = [];
        
        // Format: 1234,56 € ou 1 234,56€
        preg_match_all('/\b([\d\s]+[,\.]\d{2})\s*€/', $this->text, $matches);
        foreach ($matches[1] as $match) {
            $clean = str_replace([' ', ','], ['', '.'], $match);
            $amounts[] = floatval($clean);
        }
        
        // Format: € 1234.56
        preg_match_all('/€\s*([\d\s]+[,\.]\d{2})/', $this->text, $matches);
        foreach ($matches[1] as $match) {
            $clean = str_replace([' ', ','], ['', '.'], $match);
            $amounts[] = floatval($clean);
        }
        
        $this->extracted_data['amounts'] = $amounts;
        $this->extracted_data['total_amount'] = !empty($amounts) ? max($amounts) : null;
    }
    
    /**
     * Extrait les emails
     */
    private function extractEmails() {
        preg_match_all('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $this->text, $matches);
        $this->extracted_data['emails'] = array_unique($matches[0]);
    }
    
    /**
     * Extrait les numéros de téléphone
     */
    private function extractPhones() {
        $phones = [];
        
        // Format français: 01 23 45 67 89, 01.23.45.67.89, 0123456789
        preg_match_all('/\b0[1-9][\s\.\-]?(\d{2}[\s\.\-]?){4}\b/', $this->text, $matches);
        foreach ($matches[0] as $match) {
            $clean = preg_replace('/[\s\.\-]/', '', $match);
            $phones[] = $clean;
        }
        
        // Format international: +33 1 23 45 67 89
        preg_match_all('/\+33[\s\.\-]?[1-9][\s\.\-]?(\d{2}[\s\.\-]?){4}\b/', $this->text, $matches);
        foreach ($matches[0] as $match) {
            $phones[] = $match;
        }
        
        $this->extracted_data['phones'] = array_unique($phones);
    }
    
    /**
     * Récupère toutes les données extraites
     */
    public function getData() {
        return $this->extracted_data;
    }
    
    /**
     * Récupère une donnée spécifique
     */
    public function get($key, $default = null) {
        return $this->extracted_data[$key] ?? $default;
    }
}
