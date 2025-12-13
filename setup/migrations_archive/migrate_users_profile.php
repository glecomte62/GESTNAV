<?php
/**
 * Migration: Add profile fields to users table
 * - photo_path (avatar/profile picture)
 * - qualification (Pilote, Elève-Pilote, etc.)
 * - telephone (phone number)
 */
require_once 'config.php';

try {
    $pdo = $pdo ?? null;
    if (!$pdo instanceof PDO) {
        die('Erreur: Connexion PDO indisponible.');
    }

    // 1. Check if columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'photo_path'");
    $hasPhoto = (bool)$stmt->fetch();

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'qualification'");
    $hasQualification = (bool)$stmt->fetch();

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'telephone'");
    $hasTelephone = (bool)$stmt->fetch();

    $changes = [];

    // 2. Add photo_path if missing
    if (!$hasPhoto) {
        $pdo->exec("ALTER TABLE users ADD COLUMN photo_path VARCHAR(255) NULL COMMENT 'Path to user profile photo'");
        $changes[] = "✓ Colonne 'photo_path' ajoutée";
    } else {
        $changes[] = "• Colonne 'photo_path' déjà présente";
    }

    // 3. Add qualification if missing
    if (!$hasQualification) {
        $pdo->exec("ALTER TABLE users ADD COLUMN qualification VARCHAR(100) NULL COMMENT 'Qualification: Pilote, Elève-Pilote, etc.'");
        $changes[] = "✓ Colonne 'qualification' ajoutée";
    } else {
        $changes[] = "• Colonne 'qualification' déjà présente";
    }

    // 4. Add telephone if missing
    if (!$hasTelephone) {
        $pdo->exec("ALTER TABLE users ADD COLUMN telephone VARCHAR(20) NULL COMMENT 'Numéro de téléphone'");
        $changes[] = "✓ Colonne 'telephone' ajoutée";
    } else {
        $changes[] = "• Colonne 'telephone' déjà présente";
    }

    // 5. Display results
    echo "<!DOCTYPE html>\n<html>\n<head><meta charset='UTF-8'><title>Migration Users</title></head>\n<body>\n";
    echo "<h2>Migration: Ajout des colonnes de profil utilisateur</h2>\n";
    echo "<ul>\n";
    foreach ($changes as $msg) {
        echo "<li>$msg</li>\n";
    }
    echo "</ul>\n";
    echo "<p><strong>Statut:</strong> Migration terminée avec succès ✓</p>\n";
    echo "</body>\n</html>\n";

} catch (Exception $e) {
    die("Erreur migration: " . htmlspecialchars($e->getMessage()));
}
?>
