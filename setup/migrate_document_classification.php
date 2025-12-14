<?php
/**
 * Migration : Table des règles de classification automatique des documents
 * 
 * Usage: php setup/migrate_document_classification.php
 * Ou visiter: https://gestnav.clubulmevasion.fr/setup/migrate_document_classification.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Si accès via navigateur, vérifier les droits admin
if (php_sapi_name() !== 'cli') {
    require_login();
    if (!is_admin()) {
        die("❌ Accès refusé. Vous devez être administrateur.");
    }
    echo "<pre>";
}

echo "🚀 Migration : Règles de classification automatique des documents\n";
echo "==================================================================\n\n";

try {
    // Créer la table des règles de classification
    $sql = "CREATE TABLE IF NOT EXISTS `document_classification_rules` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `category_name` VARCHAR(255) NULL,
        `keywords` TEXT NULL COMMENT 'Mots-clés séparés par des virgules',
        `required_keywords` TEXT NULL COMMENT 'Mots-clés obligatoires (regex, séparés par |)',
        `priority` INT DEFAULT 50 COMMENT 'Priorité de la règle (0-100)',
        `requires_amount` TINYINT(1) DEFAULT 0,
        `requires_date` TINYINT(1) DEFAULT 0,
        `requires_immatriculation` TINYINT(1) DEFAULT 0,
        `active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Table 'document_classification_rules' créée\n\n";
    
    // Insérer les règles par défaut
    $default_rules = [
        [
            'name' => 'Facture',
            'category_name' => 'Factures',
            'keywords' => 'facture,invoice,montant,total,tva,ht,ttc,à payer,due',
            'required_keywords' => 'facture|invoice',
            'priority' => 90,
            'requires_amount' => 1,
            'requires_date' => 1
        ],
        [
            'name' => 'Assurance',
            'category_name' => 'Assurances',
            'keywords' => 'assurance,contrat,police,garantie,sinistre,prime,assuré,assureur',
            'required_keywords' => 'assurance',
            'priority' => 85,
            'requires_date' => 1
        ],
        [
            'name' => 'Certificat de navigabilité',
            'category_name' => 'Certificats',
            'keywords' => 'navigabilité,certificat,cdb,lapl,certificat médical,aptitude,navigabilite',
            'required_keywords' => 'certificat.*(navigabilit[ée]|m[ée]dical)|navigabilit[ée]',
            'priority' => 90,
            'requires_date' => 1
        ],
        [
            'name' => 'Carnet de vol',
            'category_name' => 'Carnets de vol',
            'keywords' => 'carnet de vol,log book,heures de vol,vol du,temps de vol',
            'required_keywords' => 'carnet.*vol|log.*book',
            'priority' => 85
        ],
        [
            'name' => 'Manuel technique',
            'category_name' => 'Manuels',
            'keywords' => 'manuel,mode d\'emploi,instructions,utilisation,guide,notice,technical manual',
            'required_keywords' => 'manuel|mode.*emploi|guide.*utilisation',
            'priority' => 70
        ],
        [
            'name' => 'Procès-verbal',
            'category_name' => 'Administratif',
            'keywords' => 'procès-verbal,pv,assemblée,réunion,délibération,ag,ca',
            'required_keywords' => 'proc[èe]s.verbal|assembl[ée]e',
            'priority' => 80
        ],
        [
            'name' => 'Révision/Entretien',
            'category_name' => 'Entretien',
            'keywords' => 'révision,entretien,maintenance,contrôle,inspection,visite,vérification,revision',
            'required_keywords' => 'r[ée]vision|entretien|maintenance|inspection',
            'priority' => 85,
            'requires_date' => 1
        ],
        [
            'name' => 'Devis',
            'category_name' => 'Factures',
            'keywords' => 'devis,estimation,proposition,offre,quote',
            'required_keywords' => 'devis|estimation.*prix|proposition.*commerciale',
            'priority' => 80,
            'requires_amount' => 1
        ],
        [
            'name' => 'Bon de commande',
            'category_name' => 'Factures',
            'keywords' => 'bon de commande,commande,purchase order,order',
            'required_keywords' => 'bon.*commande|purchase.*order',
            'priority' => 80
        ],
        [
            'name' => 'Notice pilote',
            'category_name' => 'Manuels',
            'keywords' => 'notice pilote,flight manual,poh,pilot operating handbook,consignes',
            'required_keywords' => 'notice.*pilote|flight.*manual|poh',
            'priority' => 85
        ]
    ];
    
    $count = 0;
    foreach ($default_rules as $rule) {
        // Vérifier si la règle existe déjà
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM document_classification_rules WHERE name = ?");
        $stmt->execute([$rule['name']]);
        
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("
                INSERT INTO document_classification_rules 
                (name, category_name, keywords, required_keywords, priority, requires_amount, requires_date, requires_immatriculation)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $rule['name'],
                $rule['category_name'],
                $rule['keywords'],
                $rule['required_keywords'],
                $rule['priority'],
                $rule['requires_amount'] ?? 0,
                $rule['requires_date'] ?? 0,
                $rule['requires_immatriculation'] ?? 0
            ]);
            $count++;
        }
    }
    
    echo "✅ $count règle(s) de classification ajoutée(s)\n\n";
    
    // Afficher les règles
    echo "📋 Règles de classification actives :\n";
    echo "===================================\n";
    
    $stmt = $pdo->query("SELECT * FROM document_classification_rules WHERE active = 1 ORDER BY priority DESC");
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rules as $rule) {
        echo sprintf("   %-30s [Priorité: %3d] → %s\n", 
            $rule['name'], 
            $rule['priority'],
            $rule['category_name']
        );
    }
    
    echo "\n✅ Migration terminée avec succès !\n\n";
    
    echo "🎯 Prochaines étapes :\n";
    echo "   1. Les documents uploadés seront automatiquement analysés\n";
    echo "   2. La catégorie sera suggérée en fonction du contenu\n";
    echo "   3. Vous pouvez ajuster les règles dans documents_admin.php\n\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
    echo "<br><br><a href='../documents_admin.php' style='padding: 10px 20px; background: #004b8d; color: white; text-decoration: none; border-radius: 5px;'>← Retour aux documents</a>";
}
