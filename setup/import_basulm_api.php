<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Augmenter les limites pour gérer 1249 terrains
ini_set('max_execution_time', '300');        // 5 minutes
ini_set('memory_limit', '256M');             // 256 MB de mémoire
set_time_limit(300);                         // 5 minutes de timeout

require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

// Configuration API BasULM
define('BASULM_API_KEY', '38H0UZMVXXLOGUVP7Z1N');
define('BASULM_API_URL', 'https://basulm.ffplum.fr/getbasulm/get/basulm/listall');

$flash = null;
$import_result = null;

/**
 * Convertit des coordonnées DMS (Degrés Minutes Secondes) en décimales
 * Exemple: "N 48 56 10" -> 48.936111
 * Exemple: "E 004 03 42" -> 4.061667
 */
function convertDMSToDecimal($dms) {
    if (empty($dms)) return null;
    
    // Format attendu: "N 48 56 10" ou "S 12 34 56" ou "E 004 03 42" ou "W 123 45 67"
    $parts = preg_split('/\s+/', trim($dms));
    
    if (count($parts) < 4) return null;
    
    $direction = $parts[0]; // N, S, E, W
    $degrees = (float)$parts[1];
    $minutes = (float)$parts[2];
    $seconds = (float)$parts[3];
    
    // Convertir en décimales
    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
    
    // Inverser le signe pour Sud et Ouest
    if ($direction === 'S' || $direction === 'W') {
        $decimal = -$decimal;
    }
    
    return $decimal;
}

// Import depuis API BasULM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_api') {
    try {
        // Créer la table ulm_bases_fr si elle n'existe pas
        $pdo->exec("CREATE TABLE IF NOT EXISTS ulm_bases_fr (
            id INT AUTO_INCREMENT PRIMARY KEY,
            oaci VARCHAR(8) NOT NULL,
            nom VARCHAR(255) NOT NULL,
            ville VARCHAR(255),
            pays VARCHAR(255),
            lat DOUBLE,
            lon DOUBLE,
            code_basulm VARCHAR(50),
            type_terrain VARCHAR(100),
            statut VARCHAR(100),
            UNIQUE KEY uniq_oaci (oaci),
            INDEX idx_code_basulm (code_basulm),
            INDEX idx_nom (nom),
            INDEX idx_coords (lat, lon)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Appel API BasULM avec le bon header Authorization
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, BASULM_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: api_key ' . BASULM_API_KEY,
            'Accept: application/json',
            'Cache-Control: no-cache'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            throw new RuntimeException('Erreur CURL: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new RuntimeException('API retourne HTTP ' . $httpCode . '. Réponse: ' . substr($response, 0, 500));
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Erreur JSON: ' . json_last_error_msg() . '. Réponse: ' . substr($response, 0, 500));
        }

        // Parser les données selon la structure de l'API BasULM
        $terrains = [];
        if (isset($data['liste'])) {
            // Format BasULM standard
            $terrains = $data['liste'];
        } elseif (isset($data['terrains'])) {
            $terrains = $data['terrains'];
        } elseif (isset($data['features'])) {
            // Format GeoJSON
            foreach ($data['features'] as $feature) {
                $props = $feature['properties'] ?? [];
                $geom = $feature['geometry'] ?? [];
                $coords = $geom['coordinates'] ?? [null, null];
                $terrains[] = array_merge($props, [
                    'lon' => $coords[0],
                    'lat' => $coords[1]
                ]);
            }
        } elseif (is_array($data)) {
            $terrains = $data;
        }
        
        if (empty($terrains)) {
            throw new RuntimeException('Aucun terrain dans la réponse API. Clés disponibles: ' . implode(', ', array_keys($data)));
        }

        // Préparer l'insertion
        $stmt = $pdo->prepare("
            INSERT INTO ulm_bases_fr (oaci, nom, ville, pays, lat, lon, code_basulm, type_terrain, statut) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                nom = VALUES(nom),
                ville = VALUES(ville),
                lat = VALUES(lat),
                lon = VALUES(lon),
                code_basulm = VALUES(code_basulm),
                type_terrain = VALUES(type_terrain),
                statut = VALUES(statut)
        ");

        $count = 0;
        $skipped = 0;

        foreach ($terrains as $terrain) {
            // Extraire les données
            $oaci = strtoupper(trim($terrain['code_terrain'] ?? $terrain['oaci'] ?? $terrain['code'] ?? ''));
            $nom = trim($terrain['toponyme'] ?? $terrain['nom'] ?? $terrain['name'] ?? '');
            $ville = trim($terrain['ville'] ?? $terrain['city'] ?? '');
            $pays = 'FR';
            
            // Parser les coordonnées DMS (Degrés Minutes Secondes)
            $lat = null;
            $lon = null;
            
            if (isset($terrain['latitude']) && is_string($terrain['latitude'])) {
                // Format: "N 48 56 10" ou "S 12 34 56"
                $lat = convertDMSToDecimal($terrain['latitude']);
            } elseif (isset($terrain['lat'])) {
                $lat = (float)$terrain['lat'];
            }
            
            if (isset($terrain['longitude']) && is_string($terrain['longitude'])) {
                // Format: "E 004 03 42" ou "W 123 45 67"
                $lon = convertDMSToDecimal($terrain['longitude']);
            } elseif (isset($terrain['lon'])) {
                $lon = (float)$terrain['lon'];
            }
            
            $code_basulm = trim($terrain['id'] ?? $terrain['code_basulm'] ?? '');
            $type_terrain = trim($terrain['type_terrain'] ?? $terrain['type'] ?? '');
            $statut = trim($terrain['statut'] ?? $terrain['status'] ?? '');

            // Si pas d'OACI mais code BasULM, créer un code temporaire
            if (empty($oaci) && !empty($code_basulm)) {
                $oaci = 'ULM' . substr($code_basulm, 0, 5);
            }

            if (empty($oaci) || empty($nom)) {
                $skipped++;
                continue;
            }

            try {
                $stmt->execute([
                    $oaci,
                    $nom,
                    $ville ?: null,
                    $pays,
                    $lat,
                    $lon,
                    $code_basulm ?: null,
                    $type_terrain ?: null,
                    $statut ?: null
                ]);
                $count++;
            } catch (PDOException $e) {
                error_log("Erreur import terrain $oaci: " . $e->getMessage());
                $skipped++;
            }
        }

        $import_result = [
            'count' => $count,
            'skipped' => $skipped,
            'total' => count($terrains)
        ];

        $flash = [
            'type' => 'success',
            'text' => "✅ Import API réussi: $count terrains importés/mis à jour, $skipped ignorés sur " . count($terrains) . " terrains"
        ];

    } catch (Throwable $e) {
        $flash = [
            'type' => 'error',
            'text' => 'Erreur import API: ' . $e->getMessage()
        ];
    }
}

// Import manuel CSV depuis BasULM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_csv') {
    try {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Fichier CSV manquant ou invalide');
        }

        // Créer la table ulm_bases_fr si elle n'existe pas
        $pdo->exec("CREATE TABLE IF NOT EXISTS ulm_bases_fr (
            id INT AUTO_INCREMENT PRIMARY KEY,
            oaci VARCHAR(8) NOT NULL,
            nom VARCHAR(255) NOT NULL,
            ville VARCHAR(255),
            pays VARCHAR(255),
            lat DOUBLE,
            lon DOUBLE,
            code_basulm VARCHAR(50),
            type_terrain VARCHAR(100),
            statut VARCHAR(100),
            UNIQUE KEY uniq_oaci (oaci),
            INDEX idx_code_basulm (code_basulm),
            INDEX idx_nom (nom),
            INDEX idx_coords (lat, lon)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        if (!$handle) {
            throw new RuntimeException('Impossible de lire le fichier CSV');
        }

        // Détecter le séparateur
        $firstLine = fgets($handle);
        $separator = strpos($firstLine, ';') !== false ? ';' : ',';
        rewind($handle);

        // Préparer l'insertion
        $stmt = $pdo->prepare("
            INSERT INTO ulm_bases_fr (oaci, nom, ville, pays, lat, lon, code_basulm, type_terrain, statut) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                nom = VALUES(nom),
                ville = VALUES(ville),
                lat = VALUES(lat),
                lon = VALUES(lon),
                code_basulm = VALUES(code_basulm),
                type_terrain = VALUES(type_terrain),
                statut = VALUES(statut)
        ");

        $count = 0;
        $skipped = 0;
        $lineNum = 0;

        while (($data = fgetcsv($handle, 1000, $separator)) !== false) {
            $lineNum++;
            
            // Ignorer la ligne d'en-tête
            if ($lineNum === 1 && (stripos($data[0] ?? '', 'oaci') !== false || stripos($data[0] ?? '', 'code') !== false)) {
                continue;
            }

            // Format attendu: code_basulm, oaci, nom, ville, type, statut, lat, lon
            // Ou: oaci, nom, ville, pays, lat, lon
            if (count($data) < 3) {
                $skipped++;
                continue;
            }

            $oaci = strtoupper(trim($data[0] ?? $data[1] ?? ''));
            $nom = trim($data[1] ?? $data[2] ?? '');
            $ville = trim($data[2] ?? $data[3] ?? '');
            $pays = 'FR';
            $lat = isset($data[4]) && is_numeric($data[4]) ? (float)$data[4] : (isset($data[6]) && is_numeric($data[6]) ? (float)$data[6] : null);
            $lon = isset($data[5]) && is_numeric($data[5]) ? (float)$data[5] : (isset($data[7]) && is_numeric($data[7]) ? (float)$data[7] : null);
            $code_basulm = trim($data[0] ?? '');
            $type_terrain = trim($data[4] ?? $data[3] ?? '');
            $statut = trim($data[5] ?? '');

            if (empty($oaci) || empty($nom)) {
                $skipped++;
                continue;
            }

            try {
                $stmt->execute([
                    $oaci,
                    $nom,
                    $ville ?: null,
                    $pays,
                    $lat,
                    $lon,
                    $code_basulm ?: null,
                    $type_terrain ?: null,
                    $statut ?: null
                ]);
                $count++;
            } catch (PDOException $e) {
                error_log("Erreur ligne $lineNum: " . $e->getMessage());
                $skipped++;
            }
        }

        fclose($handle);

        $import_result = [
            'count' => $count,
            'skipped' => $skipped,
            'total' => $lineNum - 1
        ];

        $flash = [
            'type' => 'success',
            'text' => "✅ Import CSV réussi: $count terrains importés/mis à jour, $skipped ignorés"
        ];

    } catch (Throwable $e) {
        $flash = [
            'type' => 'error',
            'text' => 'Erreur import CSV: ' . $e->getMessage()
        ];
    }
}

require 'header.php';
?>

<style>
.import-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.import-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 2rem;
    padding: 1.5rem 1.75rem;
    border-radius: 1.25rem;
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: #fff;
    box-shadow: 0 12px 30px rgba(0,0,0,0.25);
}

.import-header h1 {
    font-size: 1.6rem;
    margin: 0;
    letter-spacing: 0.03em;
}

.card {
    background: #ffffff;
    border-radius: 1.25rem;
    padding: 1.75rem 1.5rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.03);
    margin-bottom: 1.5rem;
}

.alert {
    padding: 1rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid;
}

.alert-success {
    background: rgba(34,197,94,0.1);
    color: #166534;
    border-left-color: #22c55e;
}

.alert-error {
    background: rgba(239,68,68,0.1);
    color: #991b1b;
    border-left-color: #ef4444;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: white;
}

.btn-primary:hover {
    filter: brightness(1.08);
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.info-box {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 0.75rem;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.info-box h3 {
    font-size: 1rem;
    margin: 0 0 0.5rem 0;
    color: #0c4a6e;
}

.info-box p {
    margin: 0.25rem 0;
    font-size: 0.9rem;
    color: #0369a1;
}

.result-box {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1rem;
    margin-top: 1rem;
}

.result-box .stat {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.result-box .stat:last-child {
    border-bottom: none;
}

.result-box .stat strong {
    color: #374151;
}

.result-box .stat span {
    color: #004b8d;
    font-weight: 700;
}
</style>

<div class="import-page">
    <div class="import-header">
        <div>
            <h1>Import Bases ULM</h1>
            <p style="margin: 0.5rem 0 0; opacity: 0.9;">Import automatique depuis l'API BasULM FFPLUM</p>
        </div>
        <div style="font-size: 2.4rem; opacity: 0.9;">✈️</div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['text']) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="font-size: 1.2rem; margin: 0 0 1rem 0; color: #1f2937;">🔄 Import API Automatique</h2>
        
        <div class="info-box">
            <h3>ℹ️ Configuration API</h3>
            <p><strong>Source:</strong> Base de données officielle de la Fédération Française d'ULM</p>
            <p><strong>Endpoint:</strong> <code><?= htmlspecialchars(BASULM_API_URL) ?></code></p>
            <p><strong>Clé API:</strong> <code><?= htmlspecialchars(substr(BASULM_API_KEY, 0, 8)) ?>...</code> <?= strlen(BASULM_API_KEY) === 20 ? '✓' : '⚠️' ?></p>
            <p><strong>Authentification:</strong> <code>Authorization: api_key VOTRE_CLE</code></p>
            <p><strong>Table cible:</strong> <code>ulm_bases_fr</code></p>
        </div>

        <form method="post" style="margin-top: 1.5rem;">
            <input type="hidden" name="action" value="import_api">
            <button type="submit" class="btn btn-primary">
                🔄 Synchroniser depuis l'API BasULM
            </button>
        </form>

        <?php if ($import_result): ?>
            <div class="result-box">
                <h3 style="margin: 0 0 1rem 0; color: #1f2937;">📊 Résultat de l'import</h3>
                <div class="stat">
                    <strong>Terrains reçus</strong>
                    <span><?= $import_result['total'] ?></span>
                </div>
                <div class="stat">
                    <strong>Terrains importés/mis à jour</strong>
                    <span style="color: #22c55e;"><?= $import_result['count'] ?></span>
                </div>
                <div class="stat">
                    <strong>Terrains ignorés</strong>
                    <span style="color: #ef4444;"><?= $import_result['skipped'] ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 1.5rem;">
            <a href="aerodromes_admin.php?tab=ulm_bases" class="btn btn-secondary">
                📋 Voir la liste des bases ULM
            </a>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size: 1.2rem; margin: 0 0 1rem 0; color: #1f2937;">📁 Import CSV (Alternative)</h2>
        
        <div class="info-box">
            <h3>ℹ️ Instructions</h3>
            <p><strong>Source:</strong> Exporter depuis <a href="https://basulm.ffplum.fr/carte-des-terrains.html" target="_blank">BasULM FFPLUM</a></p>
            <p><strong>Format CSV attendu:</strong> code_basulm, oaci, nom, ville, type, statut, lat, lon</p>
            <p><strong>Séparateur:</strong> virgule (,) ou point-virgule (;)</p>
            <p><strong>Table cible:</strong> <code>ulm_bases_fr</code></p>
        </div>

        <form method="post" enctype="multipart/form-data" style="margin-top: 1.5rem;">
            <input type="hidden" name="action" value="import_csv">
            <div style="display: flex; gap: 1rem; align-items: center;">
                <input type="file" name="csv_file" accept=".csv,text/csv" required class="btn btn-secondary">
                <button type="submit" class="btn btn-primary">
                    📤 Importer le fichier CSV
                </button>
            </div>
        </form>

        <?php if ($import_result): ?>
            <div class="result-box">
                <h3 style="margin: 0 0 1rem 0; color: #1f2937;">📊 Résultat de l'import</h3>
                <div class="stat">
                    <strong>Lignes traitées</strong>
                    <span><?= $import_result['total'] ?></span>
                </div>
                <div class="stat">
                    <strong>Terrains importés/mis à jour</strong>
                    <span style="color: #22c55e;"><?= $import_result['count'] ?></span>
                </div>
                <div class="stat">
                    <strong>Lignes ignorées</strong>
                    <span style="color: #ef4444;"><?= $import_result['skipped'] ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 1.5rem;">
            <a href="aerodromes_admin.php?tab=ulm_bases" class="btn btn-secondary">
                📋 Voir la liste des bases ULM
            </a>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size: 1.2rem; margin: 0 0 1rem 0; color: #1f2937;">📖 Comment obtenir les données</h2>
        <ol style="margin: 0; padding-left: 1.5rem; color: #6b7280;">
            <li>Aller sur <a href="https://basulm.ffplum.fr/carte-des-terrains.html" target="_blank" style="color: #00a0c6;">BasULM - Carte des terrains</a></li>
            <li>Se connecter avec votre clé API : <code>38H0UZMVXXLOGUVP7Z1N</code></li>
            <li>Utiliser la fonction d'export CSV du site</li>
            <li>Télécharger le fichier et l'importer ici</li>
        </ol>
        <p style="margin-top: 1rem; color: #6b7280;">
            <strong>Alternative:</strong> Utiliser l'initialisation manuelle dans 
            <a href="aerodromes_admin.php?tab=ulm_bases" style="color: #00a0c6;">Administration des aérodromes</a> 
            qui contient déjà 110+ bases ULM françaises.
        </p>
    </div>
</div>

<?php require 'footer.php'; ?>
