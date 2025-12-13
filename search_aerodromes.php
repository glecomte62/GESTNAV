<?php
require_once 'config.php';
// auth non requis pour la recherche d'aérodromes (données publiques)
// Accessible à tout utilisateur authentifié (pas besoin d'être admin)

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');

// Rechercher dans les aérodromes ET les bases ULM
$results = [];

// 1. Rechercher dans aerodromes_fr ou aerodromes
$table = 'aerodromes_fr';
try {
    $hasAero = false; $hasAeroFr = false; $cntAero = 0; $cntAeroFr = 0;
    $t1 = $pdo->query("SHOW TABLES LIKE 'aerodromes'");
    if ($t1 && $t1->fetch()) { $hasAero = true; }
    $t2 = $pdo->query("SHOW TABLES LIKE 'aerodromes_fr'");
    if ($t2 && $t2->fetch()) { $hasAeroFr = true; }
    if ($hasAero) {
        try { $cntAero = (int)$pdo->query("SELECT COUNT(*) FROM aerodromes")->fetchColumn(); } catch (Throwable $e) {}
    }
    if ($hasAeroFr) {
        try { $cntAeroFr = (int)$pdo->query("SELECT COUNT(*) FROM aerodromes_fr")->fetchColumn(); } catch (Throwable $e) {}
    }
    if ($hasAero && $hasAeroFr) {
        $table = ($cntAeroFr >= $cntAero) ? 'aerodromes_fr' : 'aerodromes';
    } elseif ($hasAero) {
        $table = 'aerodromes';
    } elseif ($hasAeroFr) {
        $table = 'aerodromes_fr';
    }
} catch (Throwable $e) {}

// Aérodromes
if ((isset($hasAero) && $hasAero) || (isset($hasAeroFr) && $hasAeroFr)) {
    $sqlCols = ['id', 'oaci'];
    $hasVille = false; $colVille = null;
    $colNom = 'nom';
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!in_array('nom', $cols, true) && in_array('name', $cols, true)) { $colNom = 'name'; }
        $sqlCols[] = $colNom . ' AS nom';
        if (in_array('ville', $cols, true)) { $hasVille = true; $colVille = 'ville'; }
        elseif (in_array('city', $cols, true)) { $hasVille = true; $colVille = 'city'; }
    } catch (Throwable $e) {}
    if ($hasVille && $colVille) { $sqlCols[] = $colVille; }

    $sql = "SELECT " . implode(',', $sqlCols) . " FROM $table";
    $params = [];
    if ($q !== '') {
        $sql .= " WHERE (oaci LIKE ? OR $colNom LIKE ?" . ($hasVille ? " OR $colVille LIKE ?" : "") . ")";
        $params = ['%' . $q . '%', '%' . $q . '%'];
        if ($hasVille) { $params[] = '%' . $q . '%'; }
    }
    $sql .= " ORDER BY $colNom LIMIT 300";

    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $row['type'] = 'aerodrome';
            $results[] = $row;
        }
    } catch (Throwable $e) {}
}

// 2. Rechercher dans ulm_bases_fr
try {
    $hasUlmBases = false;
    $t3 = $pdo->query("SHOW TABLES LIKE 'ulm_bases_fr'");
    if ($t3 && $t3->fetch()) { $hasUlmBases = true; }
    
    if ($hasUlmBases) {
        $sql = "SELECT id, oaci, nom, ville FROM ulm_bases_fr";
        $params = [];
        if ($q !== '') {
            $sql .= " WHERE (oaci LIKE ? OR nom LIKE ? OR ville LIKE ?)";
            $params = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];
        }
        $sql .= " ORDER BY nom LIMIT 300";
        
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            // Préfixer l'ID avec 'ulm_' pour différencier des aérodromes
            $row['id'] = 'ulm_' . $row['id'];
            $row['type'] = 'ulm_base';
            $results[] = $row;
        }
    }
} catch (Throwable $e) {}

// Limiter le nombre total de résultats
$results = array_slice($results, 0, 500);

echo json_encode(['ok'=>true,'items'=>$results], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
