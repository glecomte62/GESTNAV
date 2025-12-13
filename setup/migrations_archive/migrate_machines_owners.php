<?php
require_once 'config.php';
require_once 'auth.php';
// Autoriser exécution avec clé secrète si fournie
$allow = false;
if (isset($_GET['key'])) {
    // Définir une clé dans config.php: $MIGRATE_SECRET_KEY = '...';
    if (!empty($MIGRATE_SECRET_KEY) && hash_equals($MIGRATE_SECRET_KEY, (string)$_GET['key'])) {
        $allow = true;
    }
}
if (!$allow) {
    require_login();
    if (!is_admin()) { die('Accès refusé'); }
}

header('Content-Type: text/plain; charset=utf-8');
echo "Migration runner\n";
echo "Bypass: " . ($allow ? 'ON' : 'OFF') . "\n";
if (isset($_GET['key'])) { echo "Key provided length: " . strlen((string)$_GET['key']) . "\n"; }

echo "Migration: machines_owners + colonnes associées\n";

try {
    // Ajouter colonne source sur machines (club|membre)
    $hasSource = false;
    $stmt = $pdo->query("SHOW COLUMNS FROM machines LIKE 'source'");
    if ($stmt && $stmt->fetch()) { $hasSource = true; }
    if (!$hasSource) {
        $pdo->exec("ALTER TABLE machines ADD COLUMN source ENUM('club','membre') NOT NULL DEFAULT 'club'");
        echo "+ machines.source ajouté\n";
    } else {
        echo "= machines.source existe déjà\n";
    }

    // Créer table machines_owners
    $pdo->exec("CREATE TABLE IF NOT EXISTS machines_owners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        machine_id INT NOT NULL,
        user_id INT NOT NULL,
        role VARCHAR(50) DEFAULT 'propriétaire',
        share_pct DECIMAL(5,2) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_machine_user (machine_id, user_id),
        INDEX idx_user (user_id),
        CONSTRAINT fk_mo_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE,
        CONSTRAINT fk_mo_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "+ machines_owners créée/présente\n";

    // Ajouter is_member_owned et provided_by_user_id sur sortie_machines
    $cols = $pdo->query("SHOW COLUMNS FROM sortie_machines")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('is_member_owned', $cols)) {
        $pdo->exec("ALTER TABLE sortie_machines ADD COLUMN is_member_owned TINYINT(1) NOT NULL DEFAULT 0");
        echo "+ sortie_machines.is_member_owned ajouté\n";
    } else { echo "= sortie_machines.is_member_owned existe déjà\n"; }
    if (!in_array('provided_by_user_id', $cols)) {
        $pdo->exec("ALTER TABLE sortie_machines ADD COLUMN provided_by_user_id INT NULL");
        $pdo->exec("ALTER TABLE sortie_machines ADD CONSTRAINT fk_sm_provided_user FOREIGN KEY (provided_by_user_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "+ sortie_machines.provided_by_user_id ajouté\n";
    } else { echo "= sortie_machines.provided_by_user_id existe déjà\n"; }

    // Ajouter bringing_machine_id sur sortie_inscriptions
    $cols2 = $pdo->query("SHOW COLUMNS FROM sortie_inscriptions")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('bringing_machine_id', $cols2)) {
        $pdo->exec("ALTER TABLE sortie_inscriptions ADD COLUMN bringing_machine_id INT NULL");
        $pdo->exec("ALTER TABLE sortie_inscriptions ADD CONSTRAINT fk_si_bring_machine FOREIGN KEY (bringing_machine_id) REFERENCES machines(id) ON DELETE SET NULL");
        echo "+ sortie_inscriptions.bringing_machine_id ajouté\n";
    } else { echo "= sortie_inscriptions.bringing_machine_id existe déjà\n"; }

    echo "✓ Migration terminée\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Erreur migration: " . $e->getMessage() . "\n";
}
