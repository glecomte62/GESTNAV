<?php
// Exécution publique temporaire (pas d'auth) pour débloquer la migration
require_once 'config.php';
// S'assurer que $pdo est disponible
if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo "Erreur: connexion base de données indisponible (pdo).\n";
    exit;
}
header('Content-Type: text/plain; charset=utf-8');

echo "Migration: ajout repas_prevu et repas_details sur sorties\n";
try {
    $cols = $pdo->query('SHOW COLUMNS FROM sorties')->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('repas_prevu', $cols)) {
        $pdo->exec("ALTER TABLE sorties ADD COLUMN repas_prevu TINYINT(1) NOT NULL DEFAULT 0 AFTER details");
        echo "+ Ajout sorties.repas_prevu\n";
    } else { echo "= sorties.repas_prevu déjà présent\n"; }
    if (!in_array('repas_details', $cols)) {
        $pdo->exec("ALTER TABLE sorties ADD COLUMN repas_details TEXT NULL AFTER repas_prevu");
        echo "+ Ajout sorties.repas_details\n";
    } else { echo "= sorties.repas_details déjà présent\n"; }
    echo "✓ Terminé\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Erreur migration: " . $e->getMessage() . "\n";
}
