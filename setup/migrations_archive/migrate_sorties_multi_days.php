<?php
require_once __DIR__ . '/config.php';
// Rendre exécutable même sans login
if (!isset($pdo) || !$pdo) {
    die('Erreur: connexion DB non initialisée.');
}
header('Content-Type: text/plain; charset=utf-8');

echo "Migration: sorties multi-jours (date_fin, is_multi_day)\n";

function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `" . $table . "` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

// IMPORTANT: ne pas utiliser de transaction autour des DDL (ALTER TABLE) sous MySQL
try {
    // Ajouter colonne date_fin
    if (!columnExists($pdo, 'sorties', 'date_fin')) {
        $pdo->exec("ALTER TABLE `sorties` ADD COLUMN `date_fin` DATETIME NULL AFTER `date_sortie`");
        echo "+ Ajout sorties.date_fin\n";
    } else {
        echo "= sorties.date_fin existe déjà\n";
    }

    // Ajouter colonne is_multi_day
    if (!columnExists($pdo, 'sorties', 'is_multi_day')) {
        $pdo->exec("ALTER TABLE `sorties` ADD COLUMN `is_multi_day` TINYINT(1) NOT NULL DEFAULT 0 AFTER `statut`");
        echo "+ Ajout sorties.is_multi_day\n";
    } else {
        echo "= sorties.is_multi_day existe déjà\n";
    }

    echo "✓ Terminé\n";
} catch (Throwable $e) {
    echo "✗ Échec: " . $e->getMessage() . "\n";
}
