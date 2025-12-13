<?php
/**
 * Script pour exécuter la migration de la table club_settings
 * À exécuter une seule fois via navigateur : https://gestnav.clubulmevasion.fr/setup/run_migration.php
 */

require_once __DIR__ . '/../config.php';

// Sécurité : vérifier qu'on est admin (si auth.php existe)
if (file_exists(__DIR__ . '/../auth.php')) {
    require_once __DIR__ . '/../auth.php';
    require_login();
    require_admin();
}

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Migration club_settings</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
<div class='container py-5'>
    <div class='card shadow'>
        <div class='card-header bg-primary text-white'>
            <h3 class='mb-0'><i class='bi bi-database-gear'></i> Migration : Table club_settings</h3>
        </div>
        <div class='card-body'>";

// Lire le fichier SQL
$sqlFile = __DIR__ . '/migration_config_to_db.sql';
if (!file_exists($sqlFile)) {
    echo "<div class='alert alert-danger'><strong>Erreur :</strong> Fichier migration_config_to_db.sql introuvable.</div>";
    exit;
}

echo "<p>Lecture du fichier SQL : <code>$sqlFile</code></p>";
$sql = file_get_contents($sqlFile);

echo "<p>Taille du fichier : <strong>" . strlen($sql) . "</strong> octets</p>";

// Nettoyer les commentaires SQL
$sql = preg_replace('/^--.*$/m', '', $sql);
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

// Lire tout le fichier comme une seule requête
// (car il contient CREATE TABLE + INSERT avec ON DUPLICATE KEY)
$sql = trim($sql);

echo "<p>Fichier nettoyé : <strong>" . strlen($sql) . "</strong> octets</p>";
echo "<hr>";

$success = 0;
$errors = 0;

try {
    echo "<div class='mb-2'>";
    echo "<strong>Exécution du script SQL...</strong><br>";
    
    // Exécuter tout le script SQL d'un coup
    $pdo->exec($sql);
    
    echo "<span class='badge bg-success'>✓ Script exécuté avec succès</span>";
    echo "</div>";
    $success = 1;
    
    echo "<hr>";
    echo "<div class='alert alert-success'>";
    echo "<h4>✅ Migration terminée !</h4>";
    echo "<p>Script SQL exécuté avec succès.</p>";
    echo "</div>";
    
    // Vérifier que la table existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'club_settings'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='alert alert-info'>";
        echo "<h5>📊 Table créée avec succès</h5>";
        
        // Compter les paramètres
        $count = $pdo->query("SELECT COUNT(*) FROM club_settings")->fetchColumn();
        echo "<p><strong>$count</strong> paramètre(s) de configuration enregistré(s).</p>";
        
        // Afficher quelques exemples
        $stmt = $pdo->query("SELECT setting_key, setting_value, category FROM club_settings LIMIT 5");
        echo "<table class='table table-sm'>";
        echo "<thead><tr><th>Clé</th><th>Valeur</th><th>Catégorie</th></tr></thead><tbody>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td><code>" . htmlspecialchars($row['setting_key']) . "</code></td>";
            echo "<td>" . htmlspecialchars(substr($row['setting_value'], 0, 50)) . "</td>";
            echo "<td><span class='badge bg-secondary'>" . htmlspecialchars($row['category']) . "</span></td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "<p class='mb-0'>+ " . ($count - 5) . " autres paramètres...</p>";
        echo "</div>";
    }
    
    echo "<div class='alert alert-warning'>";
    echo "<h5>🎉 Prochaines étapes</h5>";
    echo "<ol>";
    echo "<li>Aller sur <a href='../config_generale.php' class='alert-link'>/config_generale.php</a> pour vérifier la configuration</li>";
    echo "<li>Modifier les paramètres de votre club si besoin</li>";
    echo "<li><strong>Supprimer ce fichier</strong> pour des raisons de sécurité : <code>setup/run_migration.php</code></li>";
    echo "</ol>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo " <span class='badge bg-danger'>❌ ERREUR</span>";
    echo "</div>";
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ Erreur lors de la migration</h4>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
    $errors = 1;
}

echo "</div></div></div></body></html>";
