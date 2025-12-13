<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

// Déterminer l'onglet actif (aerodromes ou ulm_bases)
$tab = trim($_GET['tab'] ?? $_POST['tab'] ?? 'aerodromes');
if (!in_array($tab, ['aerodromes', 'ulm_bases'], true)) {
    $tab = 'aerodromes';
}

// Détection de la table à utiliser selon l'onglet
if ($tab === 'ulm_bases') {
    $table = 'ulm_bases_fr';
} else {
    $table = 'aerodromes_fr';
    try {
        $t = $pdo->query("SHOW TABLES LIKE 'aerodromes'");
        if ($t && $t->fetch()) { $table = 'aerodromes'; }
    } catch (Throwable $e) { /* default */ }
}

// Colonnes connues minimales
$knownCols = ['id','oaci','nom'];
$cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Throwable $e) { $cols = $knownCols; }

$hasLat = in_array('lat', $cols, true) || in_array('latitude', $cols, true);
$hasLon = in_array('lon', $cols, true) || in_array('longitude', $cols, true);
$hasIata = in_array('iata', $cols, true);
$hasPays = in_array('pays', $cols, true) || in_array('country', $cols, true);
$hasVille = in_array('ville', $cols, true) || in_array('city', $cols, true);

$flash = null;
$import_result = null;

// Normaliser OACI (uppercase, sans espaces)
function norm_oaci($v) { return strtoupper(trim((string)$v)); }

// Création / édition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $oaci = norm_oaci($_POST['oaci'] ?? '');
    $nom  = trim($_POST['nom'] ?? '');
    $iata = trim($_POST['iata'] ?? '');
    $lat  = trim($_POST['lat'] ?? ($_POST['latitude'] ?? ''));
    $lon  = trim($_POST['lon'] ?? ($_POST['longitude'] ?? ''));
    $pays = trim($_POST['pays'] ?? ($_POST['country'] ?? ''));
    $ville= trim($_POST['ville'] ?? ($_POST['city'] ?? ''));

    if ($oaci === '' || $nom === '') {
        $flash = ['type'=>'error','text'=>'OACI et Nom sont obligatoires.'];
    } else {
        try {
            if ($id > 0) {
                // UPDATE
                $set = ['oaci = ?','nom = ?'];
                $vals = [$oaci, $nom];
                if ($hasIata) { $set[] = 'iata = ?'; $vals[] = ($iata ?: null); }
                if ($hasLat)  { $c = in_array('lat', $cols, true) ? 'lat' : 'latitude'; $set[] = "$c = ?"; $vals[] = ($lat !== '' ? $lat : null); }
                if ($hasLon)  { $c = in_array('lon', $cols, true) ? 'lon' : 'longitude'; $set[] = "$c = ?"; $vals[] = ($lon !== '' ? $lon : null); }
                if ($hasPays) { $c = in_array('pays', $cols, true) ? 'pays' : 'country'; $set[] = "$c = ?"; $vals[] = ($pays !== '' ? $pays : null); }
                if ($hasVille){ $c = in_array('ville', $cols, true) ? 'ville' : 'city'; $set[] = "$c = ?"; $vals[] = ($ville !== '' ? $ville : null); }
                $vals[] = $id;
                $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $set) . ' WHERE id = ?';
                $pdo->prepare($sql)->execute($vals);
                $flash = ['type'=>'success','text'=>($tab === 'ulm_bases' ? 'Base ULM mise à jour.' : 'Aérodrome mis à jour.')];
            } else {
                // INSERT
                $cNames = ['oaci','nom'];
                $place  = ['?','?'];
                $vals   = [$oaci, $nom];
                if ($hasIata) { $cNames[]='iata'; $place[]='?'; $vals[] = ($iata ?: null); }
                if ($hasLat)  { $cNames[]=(in_array('lat',$cols,true)?'lat':'latitude'); $place[]='?'; $vals[] = ($lat !== '' ? $lat : null); }
                if ($hasLon)  { $cNames[]=(in_array('lon',$cols,true)?'lon':'longitude'); $place[]='?'; $vals[] = ($lon !== '' ? $lon : null); }
                if ($hasPays) { $cNames[]=(in_array('pays',$cols,true)?'pays':'country'); $place[]='?'; $vals[] = ($pays !== '' ? $pays : null); }
                if ($hasVille){ $cNames[]=(in_array('ville',$cols,true)?'ville':'city'); $place[]='?'; $vals[] = ($ville !== '' ? $ville : null); }
                $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cNames) . ') VALUES (' . implode(',', $place) . ')';
                $pdo->prepare($sql)->execute($vals);
                $flash = ['type'=>'success','text'=>($tab === 'ulm_bases' ? 'Base ULM ajoutée.' : 'Aérodrome ajouté.')];
            }
            header('Location: aerodromes_admin.php?tab=' . urlencode($tab));
            exit;
        } catch (Throwable $e) {
            $flash = ['type'=>'error','text'=>'Erreur: ' . $e->getMessage()];
        }
    }
}

// Suppression
if (is_admin() && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare('DELETE FROM ' . $table . ' WHERE id = ?')->execute([$id]);
        header('Location: aerodromes_admin.php?tab=' . urlencode($tab) . '&deleted=1');
        exit;
    } catch (Throwable $e) {
        $flash = ['type'=>'error','text'=>'Suppression impossible: ' . $e->getMessage()];
    }
}

// Initialiser les bases ULM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'init_ulm' && is_admin()) {
    try {
        // Créer la table ulm_bases_fr
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

        // Données des bases ULM françaises (enrichies)
        $ulm_bases = [
            // Nouvelle-Aquitaine
            ['LFBC', 'Bergerac-Roumanière', 'Bergerac', 'FR', 44.8244, 0.6072],
            ['LFBE', 'Brive-Souillac', 'Brive-la-Gaillarde', 'FR', 45.0396, 1.4856],
            ['LFBZ', 'Biarritz-Anglet-Bayonne', 'Biarritz', 'FR', 43.4696, -1.5227],
            ['LFBO', 'Bordeaux-Mérignac', 'Bordeaux', 'FR', 44.8281, -0.7155],
            ['LFBX', 'Périgueux-Bassillac', 'Périgueux', 'FR', 45.1981, 0.8156],
            ['LFDX', 'Royan-Médis', 'Royan', 'FR', 45.6278, -0.9725],
            ['LFIB', 'Angoulême-Brie-Champniers', 'Angoulême', 'FR', 45.7294, 0.2214],
            ['LFIP', 'Poitiers-Biard', 'Poitiers', 'FR', 46.5894, 0.3069],
            ['LFBI', 'Bordeaux-Léognan-Saucats', 'Bordeaux', 'FR', 44.7030, -0.5954],
            ['LFCR', 'Rochefort-Saint-Agnant', 'Rochefort', 'FR', 45.8878, -0.9831],
            ['LFBG', 'Cognac-Châteaubernard', 'Cognac', 'FR', 45.6583, -0.3175],
            
            // Occitanie
            ['LFBT', 'Tarbes-Lourdes-Pyrénées', 'Tarbes', 'FR', 43.1786, -0.0064],
            ['LFCI', 'Albi-Le Séquestre', 'Albi', 'FR', 43.9139, 2.1130],
            ['LFCK', 'Castres-Mazamet', 'Castres', 'FR', 43.5564, 2.2889],
            ['LFCL', 'Montauban', 'Montauban', 'FR', 44.0256, 1.3778],
            ['LFCM', 'Millau-Larzac', 'Millau', 'FR', 43.9893, 3.1830],
            ['LFCR', 'Rodez-Marcillac', 'Rodez', 'FR', 44.4079, 2.4827],
            ['LFDB', 'Montpellier-Méditerranée', 'Montpellier', 'FR', 43.5762, 3.9629],
            ['LFMT', 'Toulouse-Blagnac', 'Toulouse', 'FR', 43.6291, 1.3638],
            ['LFMK', 'Carcassonne', 'Carcassonne', 'FR', 43.2160, 2.3060],
            ['LFMP', 'Perpignan-Rivesaltes', 'Perpignan', 'FR', 42.7404, 2.8707],
            ['LFNB', 'Béziers-Cap d\'Agde', 'Béziers', 'FR', 43.3235, 3.3539],
            
            // Auvergne-Rhône-Alpes
            ['LFHP', 'Annecy-Meythet', 'Annecy', 'FR', 45.9308, 6.1064],
            ['LFHN', 'Chambéry-Savoie', 'Chambéry', 'FR', 45.6381, 5.8803],
            ['LFLB', 'Chambéry-Challes-les-Eaux', 'Chambéry', 'FR', 45.5611, 5.9758],
            ['LFHG', 'Grenoble-Alpes-Isère', 'Grenoble', 'FR', 45.3629, 5.3294],
            ['LFLS', 'Grenoble-Le Versoud', 'Grenoble', 'FR', 45.2203, 5.8456],
            ['LFLL', 'Lyon-Saint Exupéry', 'Lyon', 'FR', 45.7256, 5.0811],
            ['LFLY', 'Lyon-Bron', 'Lyon', 'FR', 45.7272, 4.9443],
            ['LFHV', 'Valence-Chabeuil', 'Valence', 'FR', 44.9216, 4.9699],
            ['LFSE', 'Saint-Étienne-Bouthéon', 'Saint-Étienne', 'FR', 45.5406, 4.2964],
            ['LFHX', 'Châteauroux-Déols', 'Châteauroux', 'FR', 46.8603, 1.7211],
            ['LFLP', 'Annemasse', 'Annemasse', 'FR', 46.1920, 6.2682],
            
            // Provence-Alpes-Côte d\'Azur
            ['LFMD', 'Cannes-Mandelieu', 'Cannes', 'FR', 43.5420, 6.9535],
            ['LFMN', 'Nice-Côte d\'Azur', 'Nice', 'FR', 43.6584, 7.2159],
            ['LFMV', 'Avignon-Provence', 'Avignon', 'FR', 43.9073, 4.9018],
            ['LFMF', 'Fréjus-Saint-Raphaël', 'Fréjus', 'FR', 43.4175, 6.7357],
            ['LFTH', 'Toulon-Hyères', 'Toulon', 'FR', 43.0973, 6.1460],
            ['LFTW', 'Nîmes-Alès-Camargue-Cévennes', 'Nîmes', 'FR', 43.7574, 4.4163],
            ['LFMY', 'Salon-de-Provence', 'Salon-de-Provence', 'FR', 43.6064, 5.1093],
            ['LFKF', 'Fayence', 'Fayence', 'FR', 43.6054, 6.6963],
            
            // Corse
            ['LFKJ', 'Ajaccio-Napoléon Bonaparte', 'Ajaccio', 'FR', 41.9236, 8.8029],
            ['LFKB', 'Bastia-Poretta', 'Bastia', 'FR', 42.5527, 9.4837],
            ['LFKC', 'Calvi-Sainte-Catherine', 'Calvi', 'FR', 42.5244, 8.7930],
            ['LFKO', 'Propriano', 'Propriano', 'FR', 41.6606, 8.8897],
            ['LFKS', 'Solenzara', 'Solenzara', 'FR', 41.9244, 9.4060],
            
            // Pays de la Loire
            ['LFOU', 'Cholet-Le Pontreau', 'Cholet', 'FR', 47.0821, -0.8770],
            ['LFRN', 'Rennes-Saint-Jacques', 'Rennes', 'FR', 48.0695, -1.7348],
            ['LFRS', 'Nantes-Atlantique', 'Nantes', 'FR', 47.1532, -1.6108],
            ['LFRB', 'Brest-Bretagne', 'Brest', 'FR', 48.4478, -4.4185],
            ['LFRQ', 'Quimper-Cornouaille', 'Quimper', 'FR', 47.9750, -4.1678],
            ['LFRT', 'Saint-Brieuc-Armor', 'Saint-Brieuc', 'FR', 48.5378, -2.8544],
            ['LFRD', 'Dinard-Pleurtuit-Saint-Malo', 'Dinard', 'FR', 48.5877, -2.0799],
            ['LFRH', 'Lorient-Lann-Bihoué', 'Lorient', 'FR', 47.7606, -3.4400],
            ['LFRC', 'Cherbourg-Maupertus', 'Cherbourg', 'FR', 49.6501, -1.4703],
            ['LFAV', 'Valenciennes-Denain', 'Valenciennes', 'FR', 50.3257, 3.4613],
            
            // Grand Est
            ['LFST', 'Strasbourg-Entzheim', 'Strasbourg', 'FR', 48.5382, 7.6282],
            ['LFSB', 'Bâle-Mulhouse-Freiburg', 'Mulhouse', 'FR', 47.5896, 7.5299],
            ['LFSG', 'Épinal-Mirecourt', 'Épinal', 'FR', 48.3250, 6.0698],
            ['LFJY', 'Metz-Nancy-Lorraine', 'Metz', 'FR', 48.9821, 6.2513],
            ['LFSR', 'Reims-Champagne', 'Reims', 'FR', 49.3100, 4.0500],
            ['LFQA', 'Colmar-Houssen', 'Colmar', 'FR', 48.1099, 7.3590],
            
            // Hauts-de-France
            ['LFAB', 'Amiens-Glisy', 'Amiens', 'FR', 49.8730, 2.3872],
            ['LFAQ', 'Albert-Bray', 'Albert', 'FR', 49.9715, 2.6976],
            ['LFAT', 'Le Touquet-Côte d\'Opale', 'Le Touquet', 'FR', 50.5174, 1.6206],
            ['LFQQ', 'Lille-Lesquin', 'Lille', 'FR', 50.5636, 3.0895],
            ['LFOH', 'Le Havre-Octeville', 'Le Havre', 'FR', 49.5339, 0.0881],
            
            // Île-de-France
            ['LFPB', 'Paris-Le Bourget', 'Paris', 'FR', 48.9694, 2.4414],
            ['LFPN', 'Toussus-le-Noble', 'Toussus-le-Noble', 'FR', 48.7519, 2.1062],
            ['LFPO', 'Paris-Orly', 'Paris', 'FR', 48.7233, 2.3794],
            ['LFPG', 'Paris-Charles de Gaulle', 'Paris', 'FR', 49.0097, 2.5479],
            ['LFPT', 'Pontoise-Cormeilles', 'Pontoise', 'FR', 49.0966, 2.0408],
            ['LFPX', 'Château-Thierry-Belleau', 'Château-Thierry', 'FR', 49.0675, 3.3572],
            ['LFPM', 'Melun-Villaroche', 'Melun', 'FR', 48.6047, 2.6711],
            ['LFPV', 'Vélizy-Villacoublay', 'Vélizy', 'FR', 48.7753, 2.2014],
            
            // Normandie
            ['LFRC', 'Cherbourg-Maupertus', 'Cherbourg', 'FR', 49.6501, -1.4703],
            ['LFRK', 'Caen-Carpiquet', 'Caen', 'FR', 49.1733, -0.4500],
            ['LFRD', 'Deauville-Normandie', 'Deauville', 'FR', 49.3653, 0.1543],
            ['LFOK', 'Évreux-Fauville', 'Évreux', 'FR', 49.0287, 1.2198],
        ];

        $stmt = $pdo->prepare("INSERT INTO ulm_bases_fr (oaci, nom, ville, pays, lat, lon) VALUES (?, ?, ?, ?, ?, ?) 
                              ON DUPLICATE KEY UPDATE nom=VALUES(nom), ville=VALUES(ville), lat=VALUES(lat), lon=VALUES(lon)");
        
        foreach ($ulm_bases as $base) {
            $stmt->execute($base);
        }

        $flash = ['type'=>'success','text'=>'✅ Table ULM initialisée avec ' . count($ulm_bases) . ' bases'];
        header('Location: aerodromes_admin.php?tab=ulm_bases');
        exit;
    } catch (Throwable $e) {
        $flash = ['type'=>'error','text'=>'Erreur création table ULM: ' . $e->getMessage()];
    }
}

// Import CSV (oaci,nom,ville,pays,lat,lon) — crée/alimente la table détectée, en s'adaptant aux colonnes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_csv' && is_admin()) {
    try {
        // S'assurer qu'une table existe: tenter d'abord la table détectée ($table), sinon créer aerodromes_fr minimale
        $targetTable = $table;
        try {
            $pdo->query("SHOW COLUMNS FROM $targetTable");
        } catch (Throwable $e) {
            $targetTable = 'aerodromes_fr';
            $pdo->exec("CREATE TABLE IF NOT EXISTS aerodromes_fr (
                id INT AUTO_INCREMENT PRIMARY KEY,
                oaci VARCHAR(8) NOT NULL,
                nom VARCHAR(255) NULL,
                ville VARCHAR(255) NULL,
                pays VARCHAR(255) NULL,
                lat DOUBLE NULL,
                lon DOUBLE NULL,
                UNIQUE KEY uniq_oaci (oaci)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        // Récupérer les colonnes disponibles et leur nullabilité
        $colsTarget = [];
        $colsInfo = [];
        try {
            $colsInfo = $pdo->query("SHOW COLUMNS FROM $targetTable")->fetchAll(PDO::FETCH_ASSOC);
            $colsTarget = array_map(function($c){ return $c['Field']; }, $colsInfo);
        } catch (Throwable $e) { $colsTarget = []; $colsInfo = []; }
        $notNull = [];
        foreach ($colsInfo as $ci) { $notNull[$ci['Field']] = (strtoupper($ci['Null'] ?? '') !== 'YES'); }
        $colOaci = 'oaci';
        $colNom  = 'nom';
        $colVille= in_array('ville', $colsTarget, true) ? 'ville' : (in_array('city',$colsTarget,true)?'city':null);
        $colPays = in_array('pays', $colsTarget, true) ? 'pays' : (in_array('country',$colsTarget,true)?'country':null);
        $colLat  = in_array('lat', $colsTarget, true) ? 'lat' : (in_array('latitude',$colsTarget,true)?'latitude':null);
        $colLon  = in_array('lon', $colsTarget, true) ? 'lon' : (in_array('longitude',$colsTarget,true)?'longitude':null);

        if (!isset($_FILES['csv']) || ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload CSV manquant ou invalide.');
        }
        $tmp = $_FILES['csv']['tmp_name'];
        $fh = fopen($tmp, 'r');
        if (!$fh) throw new RuntimeException('Impossible de lire le CSV.');

        // Détecter séparateur ; ou ,
        $firstLine = fgets($fh);
        $sep = (strpos($firstLine, ';') !== false) ? ';' : ',';
        rewind($fh);

        // Construire dynamiquement l'INSERT/UPSERT selon colonnes disponibles
        $insertCols = [$colOaci, $colNom];
        $placeholders = ['?','?'];
        $onDup = ['nom=VALUES(nom)'];
        if ($colVille) { $insertCols[] = $colVille; $placeholders[]='?'; $onDup[] = "$colVille=VALUES($colVille)"; }
        if ($colPays) { $insertCols[] = $colPays; $placeholders[]='?'; $onDup[] = "$colPays=VALUES($colPays)"; }
        if ($colLat)  { $insertCols[] = $colLat;  $placeholders[]='?'; $onDup[] = "$colLat=VALUES($colLat)"; }
        if ($colLon)  { $insertCols[] = $colLon;  $placeholders[]='?'; $onDup[] = "$colLon=VALUES($colLon)"; }
        $sqlIns = 'INSERT INTO ' . $targetTable . ' (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $placeholders) . ')';
        // Toujours ajouter ON DUPLICATE KEY UPDATE (s'appliquera si une contrainte existe)
        if (!empty($onDup)) {
            $sqlIns .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $onDup);
        }
        $ins = $pdo->prepare($sqlIns);
        $count = 0; $skipped = 0;
        while (($row = fgetcsv($fh, 0, $sep)) !== false) {
            if (count($row) < 2) { $skipped++; continue; }
            // En-têtes ?
            if ($count === 0 && preg_match('/oaci/i', $row[0])) { $skipped++; continue; }
            $oaci = strtoupper(trim($row[0] ?? ''));
            $nom  = trim($row[1] ?? '');
            $ville= trim($row[2] ?? '');
            $pays = trim($row[3] ?? '');
            $lat  = ($row[4] ?? '') !== '' ? (float)$row[4] : null;
            $lon  = ($row[5] ?? '') !== '' ? (float)$row[5] : null;
            if ($oaci === '') { $skipped++; continue; }
            $vals = [$oaci, ($nom !== '' ? $nom : ($notNull[$colNom] ?? false ? '' : null))];
            if ($colVille) { $vals[] = ($ville !== '' ? $ville : (($notNull[$colVille] ?? false) ? '' : null)); }
            if ($colPays) { $vals[] = ($pays !== '' ? $pays : (($notNull[$colPays] ?? false) ? '' : null)); }
            if ($colLat)  { $vals[] = $lat; }
            if ($colLon)  { $vals[] = $lon; }
            try {
                $ins->execute($vals);
            } catch (PDOException $ex) {
                // Fallback en cas de 1062: effectuer un UPDATE par oaci
                if ((int)$ex->errorInfo[1] === 1062) {
                    $setParts = ['nom = ?'];
                    $updVals = [($nom !== '' ? $nom : (($notNull[$colNom] ?? false) ? '' : null))];
                    if ($colVille) { $setParts[] = "$colVille = ?"; $updVals[] = ($ville !== '' ? $ville : (($notNull[$colVille] ?? false) ? '' : null)); }
                    if ($colPays) { $setParts[] = "$colPays = ?"; $updVals[] = ($pays !== '' ? $pays : (($notNull[$colPays] ?? false) ? '' : null)); }
                    if ($colLat)  { $setParts[] = "$colLat = ?";  $updVals[] = $lat; }
                    if ($colLon)  { $setParts[] = "$colLon = ?";  $updVals[] = $lon; }
                    $updVals[] = $oaci;
                    $pdo->prepare('UPDATE ' . $targetTable . ' SET ' . implode(', ', $setParts) . ' WHERE ' . $colOaci . ' = ?')->execute($updVals);
                } else {
                    throw $ex;
                }
            }
            $count++;
        }
        fclose($fh);
        $import_result = [ 'count' => $count, 'skipped' => $skipped ];
        $flash = ['type'=>'success','text'=>"Import effectué: $count lignes insérées/mises à jour, $skipped ignorées."];
        // Mettre à jour les infos colonnes selon la table cible
        $table = $targetTable;
        $cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN, 0);
        $hasLat = in_array('lat', $cols, true) || in_array('latitude', $cols, true);
        $hasLon = in_array('lon', $cols, true) || in_array('longitude', $cols, true);
        $hasPays = in_array('pays', $cols, true) || in_array('country', $cols, true);
        $hasVille = in_array('ville', $cols, true) || in_array('city', $cols, true);
    } catch (Throwable $e) {
        $flash = ['type'=>'error','text'=>'Erreur import: ' . $e->getMessage()];
    }
}

// Import automatique depuis OurAirports (pays FR)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_ourairports' && is_admin()) {
    try {
        $targetTable = $table;
        // Vérifier/Créer la table cible minimale si absente
        try {
            $pdo->query("SHOW COLUMNS FROM $targetTable");
        } catch (Throwable $e) {
            $targetTable = 'aerodromes_fr';
            $pdo->exec("CREATE TABLE IF NOT EXISTS aerodromes_fr (
                id INT AUTO_INCREMENT PRIMARY KEY,
                oaci VARCHAR(8) NOT NULL,
                nom VARCHAR(255) NULL,
                ville VARCHAR(255) NULL,
                pays VARCHAR(255) NULL,
                lat DOUBLE NULL,
                lon DOUBLE NULL,
                UNIQUE KEY uniq_oaci (oaci)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        // Colonnes de la table cible + nullabilité
        $colsTarget = [];
        $colsInfo = [];
        try {
            $colsInfo = $pdo->query("SHOW COLUMNS FROM $targetTable")->fetchAll(PDO::FETCH_ASSOC);
            $colsTarget = array_map(function($c){ return $c['Field']; }, $colsInfo);
        } catch (Throwable $e) { $colsTarget = []; $colsInfo = []; }
        $notNull = [];
        foreach ($colsInfo as $ci) { $notNull[$ci['Field']] = (strtoupper($ci['Null'] ?? '') !== 'YES'); }
        $colOaci = 'oaci';
        $colNom  = 'nom';
        $colVille= in_array('ville', $colsTarget, true) ? 'ville' : (in_array('city',$colsTarget,true)?'city':null);
        $colPays = in_array('pays', $colsTarget, true) ? 'pays' : (in_array('country',$colsTarget,true)?'country':null);
        $colLat  = in_array('lat', $colsTarget, true) ? 'lat' : (in_array('latitude',$colsTarget,true)?'latitude':null);
        $colLon  = in_array('lon', $colsTarget, true) ? 'lon' : (in_array('longitude',$colsTarget,true)?'longitude':null);

        // Télécharger le CSV OurAirports
        $url = 'https://ourairports.com/data/airports.csv';
        $csv = @file_get_contents($url);
        if ($csv === false || strlen($csv) < 1024) {
            throw new RuntimeException('Téléchargement OurAirports échoué ou fichier inattendu.');
        }
        // Parser CSV en mémoire
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $csv);
        rewind($fh);
        // Lire en-tête
        $headers = fgetcsv($fh);
        if (!$headers || count($headers) < 10) {
            throw new RuntimeException('En-têtes OurAirports invalides.');
        }
        // Indices utiles
        $idxType = array_search('type', $headers);
        $idxName = array_search('name', $headers);
        $idxLat  = array_search('latitude_deg', $headers);
        $idxLon  = array_search('longitude_deg', $headers);
        $idxCountry = array_search('iso_country', $headers);
        $idxMunicipality = array_search('municipality', $headers);
        $idxGps = array_search('gps_code', $headers);
        $idxIdent = array_search('ident', $headers);
        if ($idxType===false || $idxName===false || $idxLat===false || $idxLon===false || $idxCountry===false || $idxGps===false || $idxIdent===false) {
            throw new RuntimeException('Colonnes OurAirports manquantes.');
        }

        // Préparer INSERT/UPSERT
        $insertCols = [$colOaci, $colNom];
        $placeholders = ['?','?'];
        $onDup = ['nom=VALUES(nom)'];
        if ($colVille) { $insertCols[] = $colVille; $placeholders[]='?'; $onDup[] = "$colVille=VALUES($colVille)"; }
        if ($colPays) { $insertCols[] = $colPays; $placeholders[]='?'; $onDup[] = "$colPays=VALUES($colPays)"; }
        if ($colLat)  { $insertCols[] = $colLat;  $placeholders[]='?'; $onDup[] = "$colLat=VALUES($colLat)"; }
        if ($colLon)  { $insertCols[] = $colLon;  $placeholders[]='?'; $onDup[] = "$colLon=VALUES($colLon)"; }
        $sqlIns = 'INSERT INTO ' . $targetTable . ' (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $placeholders) . ')';
        // Toujours ajouter ON DUPLICATE KEY UPDATE (si contrainte, mettra à jour)
        if (!empty($onDup)) {
            $sqlIns .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $onDup);
        }
        $ins = $pdo->prepare($sqlIns);

        $count = 0; $skipped = 0;
        // Inclure également heliports et aéroports fermés pour couvrir les cas manquants (ex: Arras)
        $typesOk = ['small_airport','medium_airport','large_airport','heliport','closed'];
        while (($row = fgetcsv($fh)) !== false) {
            $country = $row[$idxCountry] ?? '';
            if (strtoupper($country) !== 'FR') { $skipped++; continue; }
            $type = $row[$idxType] ?? '';
            if (!in_array($type, $typesOk, true)) { $skipped++; continue; }
            $gps = trim((string)($row[$idxGps] ?? ''));
            $ident = trim((string)($row[$idxIdent] ?? ''));
            $oaci = $gps !== '' ? strtoupper($gps) : strtoupper($ident);
            // Accepter tout code OACI 4 lettres pour FR (certains enregistrements peuvent ne pas commencer par LF)
            if ($oaci === '' || !preg_match('/^[A-Z0-9]{4}$/', $oaci)) { $skipped++; continue; }
            $name = trim((string)($row[$idxName] ?? ''));
            $municipality = trim((string)($row[$idxMunicipality] ?? ''));
            $lat = ($row[$idxLat] !== '' ? (float)$row[$idxLat] : null);
            $lon = ($row[$idxLon] !== '' ? (float)$row[$idxLon] : null);

            $vals = [$oaci, ($name !== '' ? $name : (($notNull[$colNom] ?? false) ? '' : null))];
            if ($colVille) { $vals[] = ($municipality !== '' ? $municipality : (($notNull[$colVille] ?? false) ? '' : null)); }
            if ($colPays) { $vals[] = (($notNull[$colPays] ?? false) ? 'France' : 'France'); }
            if ($colLat)  { $vals[] = $lat; }
            if ($colLon)  { $vals[] = $lon; }
            try {
                $ins->execute($vals);
            } catch (PDOException $ex) {
                // Fallback UPDATE si 1062
                if ((int)$ex->errorInfo[1] === 1062) {
                    $setParts = ['nom = ?'];
                    $updVals = [($name !== '' ? $name : (($notNull[$colNom] ?? false) ? '' : null))];
                    if ($colVille) { $setParts[] = "$colVille = ?"; $updVals[] = ($municipality !== '' ? $municipality : (($notNull[$colVille] ?? false) ? '' : null)); }
                    if ($colPays) { $setParts[] = "$colPays = ?"; $updVals[] = (($notNull[$colPays] ?? false) ? 'France' : 'France'); }
                    if ($colLat)  { $setParts[] = "$colLat = ?";  $updVals[] = $lat; }
                    if ($colLon)  { $setParts[] = "$colLon = ?";  $updVals[] = $lon; }
                    $updVals[] = $oaci;
                    $pdo->prepare('UPDATE ' . $targetTable . ' SET ' . implode(', ', $setParts) . ' WHERE ' . $colOaci . ' = ?')->execute($updVals);
                } else {
                    throw $ex;
                }
            }
            $count++;
        }
        fclose($fh);
        $import_result = [ 'count' => $count, 'skipped' => $skipped ];
        $flash = ['type'=>'success','text'=>"OurAirports: $count lignes FR importées, $skipped ignorées."];

        // rafraîchir contexte table/colonnes
        $table = $targetTable;
        $cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN, 0);
        $hasLat = in_array('lat', $cols, true) || in_array('latitude', $cols, true);
        $hasLon = in_array('lon', $cols, true) || in_array('longitude', $cols, true);
        $hasPays = in_array('pays', $cols, true) || in_array('country', $cols, true);
        $hasVille = in_array('ville', $cols, true) || in_array('city', $cols, true);
    } catch (Throwable $e) {
        $flash = ['type'=>'error','text'=>'Erreur import OurAirports: ' . $e->getMessage()];
    }
}

// Liste (avec recherche simple)
$q = trim($_GET['q'] ?? '');
$params = [];
$where = '';
if ($q !== '') {
    $where = 'WHERE (oaci LIKE ? OR nom LIKE ?)';
    $params = ['%' . $q . '%','%' . $q . '%'];
}
$sql = 'SELECT * FROM ' . $table . ' ' . $where . ' ORDER BY nom';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Form pour édition
$edit = null;
if (isset($_GET['id'])) {
    $eid = (int)$_GET['id'];
    $st = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = ?');
    $st->execute([$eid]);
    $edit = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

require 'header.php';
?>
<div class="container mt-4" style="max-width:1000px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Administration des aérodromes et bases ULM</h1>
        <div class="text-muted">Table: <code><?= htmlspecialchars($table) ?></code></div>
    </div>

    <!-- Onglets -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $tab === 'aerodromes' ? 'active' : '' ?>" href="aerodromes_admin.php?tab=aerodromes">
                <i class="bi bi-airplane-fill me-1"></i> Aérodromes
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $tab === 'ulm_bases' ? 'active' : '' ?>" href="aerodromes_admin.php?tab=ulm_bases">
                <i class="bi bi-airplane me-1"></i> Bases ULM
            </a>
        </li>
    </ul>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type']==='error'?'danger':'success' ?>"><?= htmlspecialchars($flash['text']) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form class="row g-2" method="get">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="col-sm-6">
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Recherche (OACI, nom)" class="form-control">
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary">Rechercher</button>
                </div>
                <div class="col-auto">
                    <a href="aerodromes_admin.php?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-outline-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5">Liste</h2>
                    <?php if (!$list): ?>
                        <p class="text-muted">Aucun aérodrome.</p>
                    <?php else: ?>
                        <div style="max-height:520px;overflow:auto;">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:80px;">OACI</th>
                                    <th>Nom</th>
                                    <?php if ($hasIata): ?><th style="width:80px;">IATA</th><?php endif; ?>
                                    <?php if ($hasVille): ?><th>Ville</th><?php endif; ?>
                                    <?php if ($hasPays): ?><th>Pays</th><?php endif; ?>
                                    <th style="width:140px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list as $row): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($row['oaci'] ?? '') ?></code></td>
                                    <td><?= htmlspecialchars($row['nom'] ?? '') ?></td>
                                    <?php if ($hasIata): ?><td><?= htmlspecialchars($row['iata'] ?? '') ?></td><?php endif; ?>
                                    <?php if ($hasVille): ?><td><?= htmlspecialchars($row['ville'] ?? ($row['city'] ?? '')) ?></td><?php endif; ?>
                                    <?php if ($hasPays): ?><td><?= htmlspecialchars($row['pays'] ?? ($row['country'] ?? '')) ?></td><?php endif; ?>
                                    <td class="text-end">
                                        <a href="aerodromes_admin.php?tab=<?= htmlspecialchars($tab) ?>&id=<?= (int)($row['id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">Éditer</a>
                                        <a href="aerodromes_admin.php?tab=<?= htmlspecialchars($tab) ?>&delete=<?= (int)($row['id'] ?? 0) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?');">Supprimer</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?php 
                        if ($edit) {
                            echo $tab === 'ulm_bases' ? 'Modifier une base ULM' : 'Modifier un aérodrome';
                        } else {
                            echo $tab === 'ulm_bases' ? 'Ajouter une base ULM' : 'Ajouter un aérodrome';
                        }
                    ?></h2>
                    <form method="post">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                        <?php if ($edit): ?>
                            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-2">
                            <label class="form-label">OACI *</label>
                            <input type="text" name="oaci" class="form-control" required value="<?= htmlspecialchars($edit['oaci'] ?? '') ?>" maxlength="6">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Nom *</label>
                            <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($edit['nom'] ?? '') ?>">
                        </div>
                        <?php if ($hasIata): ?>
                        <div class="mb-2">
                            <label class="form-label">IATA</label>
                            <input type="text" name="iata" class="form-control" value="<?= htmlspecialchars($edit['iata'] ?? '') ?>" maxlength="3">
                        </div>
                        <?php endif; ?>
                        <?php if ($hasVille): ?>
                        <div class="mb-2">
                            <label class="form-label">Ville</label>
                            <input type="text" name="<?= in_array('ville',$cols,true)?'ville':'city' ?>" class="form-control" value="<?= htmlspecialchars(($edit['ville'] ?? ($edit['city'] ?? ''))) ?>">
                        </div>
                        <?php endif; ?>
                        <?php if ($hasPays): ?>
                        <div class="mb-2">
                            <label class="form-label">Pays</label>
                            <input type="text" name="<?= in_array('pays',$cols,true)?'pays':'country' ?>" class="form-control" value="<?= htmlspecialchars(($edit['pays'] ?? ($edit['country'] ?? ''))) ?>">
                        </div>
                        <?php endif; ?>
                        <?php if ($hasLat): ?>
                        <div class="mb-2">
                            <label class="form-label">Latitude</label>
                            <input type="text" name="<?= in_array('lat',$cols,true)?'lat':'latitude' ?>" class="form-control" value="<?= htmlspecialchars(($edit['lat'] ?? ($edit['latitude'] ?? ''))) ?>">
                        </div>
                        <?php endif; ?>
                        <?php if ($hasLon): ?>
                        <div class="mb-3">
                            <label class="form-label">Longitude</label>
                            <input type="text" name="<?= in_array('lon',$cols,true)?'lon':'longitude' ?>" class="form-control" value="<?= htmlspecialchars(($edit['lon'] ?? ($edit['longitude'] ?? ''))) ?>">
                        </div>
                        <?php endif; ?>
                        <div class="text-end">
                            <button class="btn btn-primary" type="submit">Enregistrer</button>
                            <?php if ($edit): ?>
                                <a href="aerodromes_admin.php?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-outline-secondary">Annuler</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">Importer un CSV OACI</h2>
                    <p class="text-muted" style="margin-bottom:.5rem;">Colonnes attendues: <code>oaci,nom,ville,pays,lat,lon</code> (UTF‑8, séparateur virgule ou point‑virgule).</p>
                    <form method="post" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="action" value="import_csv">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                        <input type="file" name="csv" accept=".csv,text/csv" class="form-control" required style="max-width:420px;">
                        <button class="btn btn-outline-primary">Importer</button>
                    </form>
                    <?php if ($import_result): ?>
                        <div class="alert alert-info mt-2 mb-0">Lignes traitées: <?= (int)$import_result['count'] ?>, ignorées: <?= (int)$import_result['skipped'] ?>.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($tab === 'aerodromes'): ?>
            <div class="card mt-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">Importer automatiquement (OurAirports, France)</h2>
                    <p class="text-muted" style="margin-bottom:.5rem;">Télécharge et importe les aérodromes français depuis OurAirports (<code>airports.csv</code>). Types acceptés: small/medium/large airport, heliport, closed. Les codes OACI à 4 caractères sont acceptés.</p>
                    <form method="post" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="action" value="import_ourairports">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                        <button class="btn btn-outline-success">Importer OurAirports (FR)</button>
                        <a class="btn btn-link" target="_blank" href="https://ourairports.com/data/airports.csv">Voir la source</a>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($tab === 'ulm_bases'): ?>
            <div class="card mt-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">Import BasULM (API FFPLUM)</h2>
                    <p class="text-muted" style="margin-bottom:.5rem;">Importer automatiquement les terrains ULM depuis la base de données officielle de la FFPLUM.</p>
                    <div class="d-flex align-items-center gap-2">
                        <a href="import_basulm_api.php" class="btn btn-success">
                            <i class="bi bi-cloud-download"></i> Import API BasULM
                        </a>
                    </div>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">Initialiser les bases ULM (Manuel)</h2>
                    <p class="text-muted" style="margin-bottom:.5rem;">Crée une liste manuelle de bases ULM françaises avec leurs coordonnées GPS.</p>
                    <form method="post" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="action" value="init_ulm">
                        <button class="btn btn-outline-info">Initialiser la liste ULM</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require 'footer.php'; ?>
