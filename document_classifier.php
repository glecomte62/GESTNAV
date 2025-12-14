<?php
/**
 * Document Classifier - Classification automatique des documents
 * Utilise des règles et patterns pour déterminer la catégorie
 */

class DocumentClassifier {
    private $pdo;
    private $text;
    private $extracted_data;
    private $classification_rules = [];
    
    public function __construct($pdo, $text, $extracted_data = []) {
        $this->pdo = $pdo;
        $this->text = mb_strtolower($text);
        $this->extracted_data = $extracted_data;
        $this->loadRules();
    }
    
    /**
     * Charge les règles de classification depuis la base de données
     */
    private function loadRules() {
        try {
            $stmt = $this->pdo->query("
                SELECT * FROM document_classification_rules 
                WHERE active = 1 
                ORDER BY priority DESC, id ASC
            ");
            $this->classification_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Table n'existe pas encore, utiliser règles par défaut
            $this->classification_rules = $this->getDefaultRules();
        }
    }
    
    /**
     * Règles par défaut (avant création de la table)
     */
    private function getDefaultRules() {
        return [
            [
                'id' => 1,
                'name' => 'Facture',
                'category_name' => 'Factures',
                'keywords' => 'facture,invoice,montant,total,tva,ht,ttc',
                'required_keywords' => 'facture|invoice',
                'priority' => 90,
                'requires_amount' => 1,
                'requires_date' => 1
            ],
            [
                'id' => 2,
                'name' => 'Assurance',
                'category_name' => 'Assurances',
                'keywords' => 'assurance,contrat,police,garantie,sinistre,prime',
                'required_keywords' => 'assurance',
                'priority' => 85,
                'requires_date' => 1
            ],
            [
                'id' => 3,
                'name' => 'Certificat de navigabilité',
                'category_name' => 'Certificats',
                'keywords' => 'navigabilité,certificat,cdb,lapl,certificat médical,certificat de navigabilite',
                'required_keywords' => 'certificat|navigabilité|navigabilite',
                'priority' => 90,
                'requires_date' => 1
            ],
            [
                'id' => 4,
                'name' => 'Carnet de vol',
                'category_name' => 'Carnets de vol',
                'keywords' => 'carnet de vol,log book,heures de vol,vol du',
                'required_keywords' => 'carnet.*vol|log.*book',
                'priority' => 85
            ],
            [
                'id' => 5,
                'name' => 'Manuel',
                'category_name' => 'Manuels',
                'keywords' => 'manuel,mode d\'emploi,instructions,utilisation,guide',
                'required_keywords' => 'manuel|mode.*emploi',
                'priority' => 70
            ],
            [
                'id' => 6,
                'name' => 'Procès-verbal',
                'category_name' => 'Administratif',
                'keywords' => 'procès-verbal,pv,assemblée,réunion,délibération',
                'required_keywords' => 'procès.verbal|assemblée',
                'priority' => 80
            ],
            [
                'id' => 7,
                'name' => 'Révision/Entretien',
                'category_name' => 'Entretien',
                'keywords' => 'révision,entretien,maintenance,contrôle,inspection,visite',
                'required_keywords' => 'révision|entretien|maintenance',
                'priority' => 85,
                'requires_date' => 1
            ]
        ];
    }
    
    /**
     * Classifie le document
     * @return array ['category_id' => int, 'confidence' => float, 'matched_rule' => array]
     */
    public function classify() {
        $best_match = null;
        $best_score = 0;
        
        foreach ($this->classification_rules as $rule) {
            $score = $this->scoreRule($rule);
            
            if ($score > $best_score) {
                $best_score = $score;
                $best_match = $rule;
            }
        }
        
        if ($best_match && $best_score >= 50) {
            // Trouver la catégorie correspondante
            $category_id = $this->findCategoryByName($best_match['category_name'] ?? '');
            
            return [
                'category_id' => $category_id,
                'category_name' => $best_match['category_name'] ?? null,
                'confidence' => $best_score,
                'matched_rule' => $best_match,
                'rule_name' => $best_match['name']
            ];
        }
        
        return [
            'category_id' => null,
            'category_name' => null,
            'confidence' => 0,
            'matched_rule' => null,
            'rule_name' => null
        ];
    }
    
    /**
     * Calcule le score d'une règle
     */
    private function scoreRule($rule) {
        $score = 0;
        $priority = $rule['priority'] ?? 50;
        
        // Vérifier les mots-clés obligatoires
        if (!empty($rule['required_keywords'])) {
            $required = explode('|', $rule['required_keywords']);
            $found_required = false;
            
            foreach ($required as $keyword) {
                if (preg_match('/' . preg_quote($keyword, '/') . '/ui', $this->text)) {
                    $found_required = true;
                    break;
                }
            }
            
            if (!$found_required) {
                return 0; // Règle non applicable
            }
            
            $score += 40; // Points pour mot-clé obligatoire
        }
        
        // Compter les mots-clés présents
        if (!empty($rule['keywords'])) {
            $keywords = explode(',', $rule['keywords']);
            $found_count = 0;
            
            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if (stripos($this->text, $keyword) !== false) {
                    $found_count++;
                }
            }
            
            // Score proportionnel au nombre de mots-clés trouvés
            $keyword_score = min(30, ($found_count / count($keywords)) * 30);
            $score += $keyword_score;
        }
        
        // Vérifier les exigences de données
        if (!empty($rule['requires_amount'])) {
            if (!empty($this->extracted_data['amounts'])) {
                $score += 10;
            } else {
                $score -= 10; // Pénalité si montant requis mais absent
            }
        }
        
        if (!empty($rule['requires_date'])) {
            if (!empty($this->extracted_data['dates'])) {
                $score += 10;
            } else {
                $score -= 5;
            }
        }
        
        if (!empty($rule['requires_immatriculation'])) {
            if (!empty($this->extracted_data['immatriculations'])) {
                $score += 10;
            } else {
                $score -= 5;
            }
        }
        
        // Appliquer la priorité (bonus)
        $score = $score * ($priority / 50);
        
        return max(0, $score);
    }
    
    /**
     * Trouve une catégorie par son nom
     */
    private function findCategoryByName($name) {
        if (empty($name)) {
            return null;
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM document_categories WHERE name LIKE ? LIMIT 1");
            $stmt->execute(['%' . $name . '%']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['id'] : null;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Suggère une machine basée sur l'immatriculation trouvée
     */
    public function suggestMachine() {
        if (empty($this->extracted_data['immatriculations'])) {
            return null;
        }
        
        try {
            foreach ($this->extracted_data['immatriculations'] as $immat) {
                $stmt = $this->pdo->prepare("
                    SELECT id, immatriculation, nom 
                    FROM machines 
                    WHERE immatriculation LIKE ? 
                    AND actif = 1 
                    LIMIT 1
                ");
                $stmt->execute(['%' . $immat . '%']);
                $machine = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($machine) {
                    return $machine;
                }
            }
        } catch (PDOException $e) {
            return null;
        }
        
        return null;
    }
    
    /**
     * Génère des tags de recherche automatiques
     */
    public function generateTags() {
        $tags = [];
        
        // Ajouter les dates
        if (!empty($this->extracted_data['dates'])) {
            foreach ($this->extracted_data['dates'] as $date) {
                $tags[] = date('Y', strtotime($date));
                $tags[] = date('m/Y', strtotime($date));
            }
        }
        
        // Ajouter les immatriculations
        if (!empty($this->extracted_data['immatriculations'])) {
            $tags = array_merge($tags, $this->extracted_data['immatriculations']);
        }
        
        // Ajouter les numéros de série
        if (!empty($this->extracted_data['serial_numbers'])) {
            $tags = array_merge($tags, $this->extracted_data['serial_numbers']);
        }
        
        // Mots-clés importants
        $important_words = ['facture', 'assurance', 'révision', 'entretien', 'certificat', 'manuel'];
        foreach ($important_words as $word) {
            if (stripos($this->text, $word) !== false) {
                $tags[] = $word;
            }
        }
        
        return implode(', ', array_unique($tags));
    }
}
