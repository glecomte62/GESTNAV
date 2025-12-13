<?php
/**
 * migrate_ulm_bases.php
 * Crée la table ulm_bases_fr et la peuple avec les bases ULM françaises
 */
require_once 'config.php';
require_once 'auth.php';

// Vérifier si on est en CLI ou en web avec admin
if (php_sapi_name() === 'cli') {
    // CLI OK
} else {
    require_login();
    require_admin();
}

try {
    // Créer la table ulm_bases_fr si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS ulm_bases_fr (
        id INT AUTO_INCREMENT PRIMARY KEY,
        oaci VARCHAR(8) NOT NULL UNIQUE,
        nom VARCHAR(255) NOT NULL,
        ville VARCHAR(255),
        pays VARCHAR(255),
        lat DOUBLE,
        lon DOUBLE,
        INDEX idx_oaci (oaci),
        INDEX idx_nom (nom),
        INDEX idx_coords (lat, lon)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Données des bases ULM françaises (coordonnées GPS)
    $ulm_bases = [
        ['LFLS', 'Sarlat-Périgueux', 'Sarlat', 'FR', 44.9267, 1.2806],
        ['LFTZ', 'Limoges-Bellegarde', 'Limoges', 'FR', 45.8622, 1.1870],
        ['LFBE', 'Brive-Vallée de la Dordogne', 'Brive', 'FR', 45.3017, 1.4680],
        ['LFBC', 'Bergerac', 'Bergerac', 'FR', 44.8244, 0.6072],
        ['LFBO', 'Bordeaux-Mérignac', 'Bordeaux', 'FR', 44.8281, -0.7155],
        ['LFRN', 'Nantes Atlantique', 'Nantes', 'FR', 47.1547, -1.6118],
        ['LFRS', 'Rennes Saint-Jacques', 'Rennes', 'FR', 48.0694, -1.7259],
        ['LFOP', 'Paris-Orly', 'Paris', 'FR', 48.7258, 2.3759],
        ['LFPG', 'Paris-Charles de Gaulle', 'Paris', 'FR', 49.0097, 2.5479],
        ['LFPB', 'Paris-Le Bourget', 'Paris', 'FR', 48.9697, 2.4423],
        ['LFPY', 'Pontoise-Cormeilles', 'Pontoise', 'FR', 49.0202, 2.0319],
        ['LFPN', 'Pontoise-Néville', 'Pontoise', 'FR', 49.0178, 2.0261],
        ['LFLY', 'Lyon-Bron', 'Lyon', 'FR', 45.7242, 4.9216],
        ['LFLL', 'Lyon-Saint Exupéry', 'Lyon', 'FR', 45.7261, 5.0911],
        ['LFMD', 'Marseille-Provence', 'Marseille', 'FR', 43.4413, 5.2147],
        ['LFTB', 'Toulon-Hyères', 'Toulon', 'FR', 43.0973, 6.1424],
        ['LFJL', 'Aix-en-Provence', 'Aix-en-Provence', 'FR', 43.6189, 5.2268],
        ['LFNF', 'Nice-Côte d\'Azur', 'Nice', 'FR', 43.6647, 7.2155],
        ['LFJG', 'Grenoble-Isère', 'Grenoble', 'FR', 45.3639, 5.3340],
        ['LFLR', 'La Roche', 'La Roche-sur-Foron', 'FR', 46.0742, 6.3069],
        ['LFSE', 'Saint-Étienne', 'Saint-Étienne', 'FR', 45.5308, 4.2736],
        ['LFTP', 'Tarbes-Lourdes', 'Tarbes', 'FR', 43.1939, 0.0040],
        ['LFLF', 'Figari', 'Figari', 'FR', 41.5122, 9.0936],
        ['LFKJ', 'Ajaccio', 'Ajaccio', 'FR', 41.9231, 8.8048],
        ['LFKB', 'Bastia', 'Bastia', 'FR', 42.5544, 9.2910],
    ];

    $stmt = $pdo->prepare("INSERT INTO ulm_bases_fr (oaci, nom, ville, pays, lat, lon) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nom=VALUES(nom), ville=VALUES(ville), lat=VALUES(lat), lon=VALUES(lon)");
    
    foreach ($ulm_bases as $base) {
        $stmt->execute($base);
    }

    echo php_sapi_name() === 'cli' 
        ? "✅ Table ulm_bases_fr créée/mise à jour avec succès (" . count($ulm_bases) . " bases)\n"
        : "<div class='alert alert-success'>✅ Table ulm_bases_fr créée/mise à jour avec succès (" . count($ulm_bases) . " bases)</div>";

} catch (Throwable $e) {
    echo php_sapi_name() === 'cli'
        ? "❌ Erreur: " . $e->getMessage() . "\n"
        : "<div class='alert alert-danger'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
