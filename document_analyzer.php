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
        
        // Chercher d'abord les dates avec contexte (Date:, Invoice Date:, etc.)
        $date_keywords = ['date', 'invoice date', 'bill date', 'facture', 'émission', 'emission'];
        foreach ($date_keywords as $keyword) {
            // Format après mot-clé: Date: DD/MM/YYYY
            if (preg_match('/' . preg_quote($keyword, '/') . '\s*:?\s*(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})/ui', $this->text, $match)) {
                $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad($match[2], 2, '0', STR_PAD_LEFT);
                $year = $match[3];
                if (checkdate($month, $day, $year)) {
                    $dates[] = "$year-$month-$day";
                }
            }
            
            // Format anglais: Invoice Date: MM/DD/YYYY (essayer si DD/MM n'est pas valide)
            if (preg_match('/' . preg_quote($keyword, '/') . '\s*:?\s*(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})/ui', $this->text, $match)) {
                $month = str_pad($match[1], 2, '0', STR_PAD_LEFT);
                $day = str_pad($match[2], 2, '0', STR_PAD_LEFT);
                $year = $match[3];
                if (checkdate($month, $day, $year)) {
                    $dates[] = "$year-$month-$day";
                }
            }
        }
        
        // Format: DD/MM/YYYY ou DD-MM-YYYY ou DD.MM.YYYY
        preg_match_all('/\b(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})\b/', $this->text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($match[2], 2, '0', STR_PAD_LEFT);
            $year = $match[3];
            
            // Valider la date (format français DD/MM/YYYY)
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
        
        // Dates en anglais: "January 15, 2025" ou "15 January 2025"
        $mois_en = [
            'january' => '01', 'february' => '02', 'march' => '03', 'april' => '04',
            'may' => '05', 'june' => '06', 'july' => '07', 'august' => '08',
            'september' => '09', 'october' => '10', 'november' => '11', 'december' => '12',
            'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
            'jun' => '06', 'jul' => '07', 'aug' => '08', 'sep' => '09',
            'oct' => '10', 'nov' => '11', 'dec' => '12'
        ];
        
        foreach ($mois_en as $nom => $num) {
            // Format: January 15, 2025
            preg_match_all('/\b' . $nom . '\s+(\d{1,2}),?\s+(\d{4})\b/ui', $this->text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
                $year = $match[2];
                if (checkdate($num, $day, $year)) {
                    $dates[] = "$year-$num-$day";
                }
            }
            
            // Format: 15 January 2025
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
     * Extrait les montants avec priorité sur TTC
     */
    private function extractAmounts() {
        $amounts = [];
        $ttc_amounts = [];
        
        // Chercher spécifiquement les montants TTC/Total avec contexte
        $ttc_patterns = [
            '/(?:total\s+ttc|montant\s+ttc|ttc|total\s+incl\.?\s+vat|total\s+including\s+tax|amount\s+due)\s*:?\s*([\d\s]+[,\.]\d{2})\s*€/ui',
            '/€?\s*([\d\s]+[,\.]\d{2})\s*(?:ttc|incl\.?\s+vat|including\s+tax)/ui',
        ];
        
        foreach ($ttc_patterns as $pattern) {
            if (preg_match_all($pattern, $this->text, $matches)) {
                foreach ($matches[1] as $match) {
                    $clean = str_replace([' ', ','], ['', '.'], $match);
                    $ttc_amounts[] = floatval($clean);
                }
            }
        }
        
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
        
        // Format: $1234.56 ou USD 1234.56
        preg_match_all('/[\$USD]\s*([\d\s,]+\.\d{2})/', $this->text, $matches);
        foreach ($matches[1] as $match) {
            $clean = str_replace([' ', ','], ['', '.'], $match);
            $amounts[] = floatval($clean);
        }
        
        $this->extracted_data['amounts'] = $amounts;
        
        // Prioriser le montant TTC si trouvé, sinon prendre le maximum
        if (!empty($ttc_amounts)) {
            $this->extracted_data['total_amount'] = max($ttc_amounts);
            $this->extracted_data['is_ttc'] = true;
        } else {
            $this->extracted_data['total_amount'] = !empty($amounts) ? max($amounts) : null;
            $this->extracted_data['is_ttc'] = false;
        }
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
