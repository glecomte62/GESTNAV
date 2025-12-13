<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

header('Content-Type: text/plain; charset=UTF-8');

echo "Migration: Ajuster le champ sorties.statut pour inclure 'en étude'\n";

try {
    // Vérifier le type de la colonne statut
    $col = $pdo->query("SHOW COLUMNS FROM sorties LIKE 'statut'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        echo "ERREUR: Colonne 'statut' introuvable dans 'sorties'\n";
        exit(1);
    }
    $type = $col['Type'] ?? '';
    echo "Type actuel: $type\n";

    $didAlter = false;
    if (stripos($type, 'enum(') === 0) {
        // Si ENUM, vérifier s'il contient 'en étude' ou 'en etude'
        $containsEnEtude = (stripos($type, "'en étude'") !== false) || (stripos($type, "'en etude'") !== false);
        if (!$containsEnEtude) {
            // Essayer d'inspecter les valeurs existantes pour reconstruire l'ENUM proprement
            $vals = [];
            if (preg_match("/enum\((.*)\)/i", $type, $m)) {
                $raw = $m[1];
                $parts = preg_split("/\s*,\s*/", $raw);
                foreach ($parts as $p) {
                    $vals[] = trim($p, "'\" ");
                }
            }
            if (empty($vals)) {
                // Valeurs par défaut si parsing échoue
                $vals = ['prévue','terminée','annulée'];
            }
            // Ajouter 'en étude'
            if (!in_array('en étude', $vals, true) && !in_array('en etude', $vals, true)) {
                $vals[] = 'en étude';
            }
            // Reconstruire la clause ENUM
            $enumSql = 'ENUM(' . implode(',', array_map(function($v){
                return "'" . str_replace("'", "''", $v) . "'";
            }, $vals)) . ')';

            $alter = "ALTER TABLE sorties MODIFY statut $enumSql NOT NULL DEFAULT 'prévue'";
            // Les DDL (ALTER) provoquent des auto-commit en MySQL, ne pas mettre dans une transaction
            $pdo->exec($alter);
            $didAlter = true;
            echo "ALTER effectué: $alter\n";
        }
    } else {
        // Si pas ENUM, s'assurer que la valeur est stockable
        // On force un VARCHAR si nécessaire pour éviter les valeurs vides lors de l'update
        if (stripos($type, 'varchar') === false && stripos($type, 'text') === false) {
            $alter = "ALTER TABLE sorties MODIFY statut VARCHAR(32) NOT NULL DEFAULT 'prévue'";
            $pdo->exec($alter);
            $didAlter = true;
            echo "ALTER effectué: $alter\n";
        }
    }

    // Normaliser les valeurs vides à 'prévue'
    $pdo->exec("UPDATE sorties SET statut = 'prévue' WHERE statut IS NULL OR statut = ''");

    echo "OK: migration terminée." . ($didAlter ? " (structure modifiée)" : " (structure inchangée)") . "\n";
} catch (Throwable $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}
