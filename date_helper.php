<?php
/**
 * Fonctions helper pour le parsing de dates françaises
 * Utilisé notamment pour trier les options de sondages de type "date"
 */

if (!function_exists('parse_french_date')) {
    /**
     * Parse une date française et retourne un timestamp
     * 
     * @param string $text Texte contenant une date française (ex: "dimanche 1 février 2026")
     * @return int Timestamp Unix de la date
     */
    function parse_french_date(string $text): int {
        // Mapping des mois français vers leurs numéros
        $mois_fr = [
            'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 
            'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7,
            'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10,
            'novembre' => 11, 'décembre' => 12, 'decembre' => 12
        ];
        
        // Nettoyer et normaliser le texte
        $text = mb_strtolower(trim($text));
        
        // Extraire les composants avec regex
        // Pattern: (optionnel jour semaine) jour mois année
        if (preg_match('/(\d{1,2})\s+(janvier|février|fevrier|mars|avril|mai|juin|juillet|août|aout|septembre|octobre|novembre|décembre|decembre)\s+(\d{4})/ui', $text, $matches)) {
            $jour = intval($matches[1]);
            $mois = $mois_fr[mb_strtolower($matches[2])] ?? 1;
            $annee = intval($matches[3]);
            
            // Créer un timestamp
            return mktime(0, 0, 0, $mois, $jour, $annee);
        }
        
        // Fallback: retourner 0 (début de l'époque Unix) si parsing échoue
        return 0;
    }
}
