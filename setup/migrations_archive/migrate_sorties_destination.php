<?php
require_once 'config.php';
require_once 'auth.php';
require_admin();

header('Content-Type: text/plain; charset=utf-8');

echo "Migration: ajout de sorties.destination_id -> aerodromes_fr(id)\n";

try {
    $pdo->beginTransaction();

    // Vérifier existence colonne
    $cols = $pdo->query('SHOW COLUMNS FROM sorties')->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasDest = in_array('destination_id', $cols, true);

    if (!$hasDest) {
        // Ajouter colonne destination_id
        $pdo->exec('ALTER TABLE sorties ADD COLUMN destination_id INT NULL');
        echo "✓ Colonne destination_id ajoutée\n";
    } else {
        echo "• Colonne destination_id déjà présente\n";
    }

    // Vérifier index et contrainte
    // Créer index si manquant
    $idx = $pdo->query("SHOW INDEX FROM sorties WHERE Key_name='idx_sorties_destination_id'");
    if (!$idx || !$idx->fetch()) {
        $pdo->exec('CREATE INDEX idx_sorties_destination_id ON sorties(destination_id)');
        echo "✓ Index créé\n";
    } else {
        echo "• Index déjà présent\n";
    }

    // Ajouter contrainte FK si possible, uniquement si absente
    try {
        $fkExists = false;
        $check = $pdo->prepare("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='sorties' AND COLUMN_NAME='destination_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
        $check->execute();
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['CONSTRAINT_NAME'])) {
            $fkExists = true;
            echo "• Contrainte FK déjà présente (" . $row['CONSTRAINT_NAME'] . ")\n";
        }
        if (!$fkExists) {
            // utiliser un nom unique pour éviter les collisions
            $fkName = 'fk_sorties_destination_'.date('YmdHis');
            $pdo->exec("ALTER TABLE sorties ADD CONSTRAINT $fkName FOREIGN KEY (destination_id) REFERENCES aerodromes_fr(id) ON UPDATE CASCADE ON DELETE SET NULL");
            echo "✓ Contrainte FK ajoutée ($fkName)\n";
        }
    } catch (Throwable $e) {
        echo "! Impossible d'ajouter la contrainte FK (" . $e->getMessage() . ")\n";
    }

    $pdo->commit();
    echo "Migration terminée.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo "Erreur migration: " . $e->getMessage() . "\n";
}
