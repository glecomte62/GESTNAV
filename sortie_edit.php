<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();
require_once 'utils/activity_log.php';

// Dossier d'upload des photos (à adapter si besoin)
$uploadDir = __DIR__ . '/uploads/sorties';
$uploadUrl = 'uploads/sorties';

// S'assurer que le dossier existe
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

// Récupération de l'ID éventuel
$sortie_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors    = [];
$photos    = [];
$sortie    = null;
$machines  = $pdo->query("SELECT * FROM machines WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
// Charger la liste des aérodromes pour destination (détection table + ville/city)
$aerodromes = [];
try {
    // Choisir la table la plus fournie entre aerodromes et aerodromes_fr pour rester cohérent avec l'endpoint
    $adTable = 'aerodromes_fr';
    try {
        $hasA = false; $hasAF = false; $cntA = 0; $cntAF = 0;
        $t1 = $pdo->query("SHOW TABLES LIKE 'aerodromes'");
        if ($t1 && $t1->fetch()) { $hasA = true; }
        $t2 = $pdo->query("SHOW TABLES LIKE 'aerodromes_fr'");
        if ($t2 && $t2->fetch()) { $hasAF = true; }
        if ($hasA)  { try { $cntA  = (int)$pdo->query("SELECT COUNT(*) FROM aerodromes")->fetchColumn(); } catch (Throwable $e3) {} }
        if ($hasAF) { try { $cntAF = (int)$pdo->query("SELECT COUNT(*) FROM aerodromes_fr")->fetchColumn(); } catch (Throwable $e4) {} }
        if ($hasA && $hasAF) {
            $adTable = ($cntAF >= $cntA) ? 'aerodromes_fr' : 'aerodromes';
        } elseif ($hasA) {
            $adTable = 'aerodromes';
        } elseif ($hasAF) {
            $adTable = 'aerodromes_fr';
        }
    } catch (Throwable $e2) {}
    $colsAd = $pdo->query('SHOW COLUMNS FROM ' . $adTable)->fetchAll(PDO::FETCH_COLUMN, 0);
    $colVille = in_array('ville', $colsAd, true) ? 'ville' : (in_array('city', $colsAd, true) ? 'city' : null);
    $colNom = in_array('nom', $colsAd, true) ? 'nom' : (in_array('name', $colsAd, true) ? 'name' : 'nom');
    $selectCols = 'id, oaci, ' . $colNom . ' AS nom' . ($colVille ? (', ' . $colVille . ' AS ville') : '');
    $aerodromes = $pdo->query('SELECT ' . $selectCols . ' FROM ' . $adTable . ' ORDER BY ' . $colNom)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $aerodromes = []; }

// Charger les bases ULM
$ulm_bases = [];
try {
    $ulm_bases = $pdo->query('SELECT id, oaci, nom, ville FROM ulm_bases_fr ORDER BY nom')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { 
    $ulm_bases = [];
}
// Détecter si les colonnes destination_id et ulm_base_id existent dans sorties
$hasDestinationId = false;
$hasUlmBaseId = false;
try {
    $cols = $pdo->query('SHOW COLUMNS FROM sorties')->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasDestinationId = in_array('destination_id', $cols, true);
    $hasUlmBaseId = in_array('ulm_base_id', $cols, true);
} catch (Throwable $e) {}
$selected_machines = [];
// Détecter colonnes repas
$hasRepasPrevus = false;
$hasRepasDetails = false;
// Détecter colonnes multi-jours
$hasDateFin = false;
$hasIsMultiDay = false;
try {
    $colsAll = $pdo->query('SHOW COLUMNS FROM sorties')->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasRepasPrevus = in_array('repas_prevu', $colsAll, true);
    $hasRepasDetails = in_array('repas_details', $colsAll, true);
    $hasDateFin = in_array('date_fin', $colsAll, true);
    $hasIsMultiDay = in_array('is_multi_day', $colsAll, true);
} catch (Throwable $e) {}

// Si édition : charger la sortie + machines + photos
if ($sortie_id > 0) {
    $selectSortie = "SELECT s.*";
    if ($hasDestinationId) $selectSortie .= ", s.destination_id";
    if ($hasUlmBaseId) $selectSortie .= ", s.ulm_base_id";
    $selectSortie .= " FROM sorties s WHERE s.id = ?";
    
    $stmt = $pdo->prepare($selectSortie);
    $stmt->execute([$sortie_id]);
    $sortie = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sortie) {
        die("Sortie introuvable.");
    }

    // Machines liées
    $stmtM = $pdo->prepare("SELECT machine_id FROM sortie_machines WHERE sortie_id = ?");
    $stmtM->execute([$sortie_id]);
    $rows = $stmtM->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $selected_machines[] = (int)$row['machine_id'];
    }

    // Photos
    $stmtP = $pdo->prepare("SELECT * FROM sortie_photos WHERE sortie_id = ? ORDER BY created_at DESC");
    $stmtP->execute([$sortie_id]);
    $photos = $stmtP->fetchAll(PDO::FETCH_ASSOC);
}

// TRAITEMENT FORMULAIRE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sortie_id = isset($_POST['sortie_id']) ? (int)$_POST['sortie_id'] : 0;

    $date_sortie = $_POST['date_sortie'] ?? '';
    $date_fin    = $_POST['date_fin'] ?? '';
    $is_multi_day = isset($_POST['is_multi_day']) ? (int)$_POST['is_multi_day'] : 0;
    $titre       = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $details     = trim($_POST['details'] ?? '');
    $statut      = $_POST['statut'] ?? 'prévue';
    
    // Gérer destination (aérodrome ou base ULM)
    $destination_raw = trim($_POST['destination_id'] ?? '');
    $destination_id = null;
    $ulm_base_id = null;
    
    if (!empty($destination_raw)) {
        if (strpos($destination_raw, 'ulm_') === 0) {
            // Base ULM: extraire l'ID
            $ulm_base_id = (int)substr($destination_raw, 4);
        } else {
            // Aérodrome classique
            $destination_id = (int)$destination_raw;
        }
    }
    
    $machinesPost = $_POST['machines'] ?? [];
    $repas_prevu = isset($_POST['repas_prevu']) ? (int)$_POST['repas_prevu'] : 0;
    $repas_details = trim($_POST['repas_details'] ?? '');

    if ($titre === '') {
        $errors[] = "Le nom de la sortie est obligatoire.";
    }
    if ($date_sortie === '') {
        $errors[] = "La date/heure de la sortie est obligatoire.";
    }
    if (($hasDateFin || $hasIsMultiDay) && $is_multi_day) {
        if ($date_fin === '') {
            $errors[] = "La date/heure de fin est obligatoire pour une sortie sur plusieurs jours.";
        } elseif (strtotime($date_fin) < strtotime($date_sortie)) {
            $errors[] = "La date de fin doit être postérieure à la date de début.";
        }
    } else {
        // Si non multi-jours, ignorer date_fin
        $date_fin = '';
        $is_multi_day = 0;
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($sortie_id > 0) {
                // UPDATE
                if ($hasDestinationId || $hasUlmBaseId) {
                    $updateSql = "UPDATE sorties SET date_sortie = ?, titre = ?, description = ?, details = ?, statut = ?";
                    $params = [$date_sortie, $titre, $description, $details, $statut];
                    
                    if ($hasDestinationId) {
                        $updateSql .= ", destination_id = ?";
                        $params[] = $destination_id ?: null;
                    }
                    if ($hasUlmBaseId) {
                        $updateSql .= ", ulm_base_id = ?";
                        $params[] = $ulm_base_id ?: null;
                    }
                    if ($hasDateFin) {
                        $updateSql .= ", date_fin = ?";
                        $params[] = $date_fin ?: null;
                    }
                    if ($hasIsMultiDay) {
                        $updateSql .= ", is_multi_day = ?";
                        $params[] = $is_multi_day ? 1 : 0;
                    }
                    if ($hasRepasPrevus) {
                        $updateSql .= ", repas_prevu = ?";
                        $params[] = $repas_prevu ? 1 : 0;
                    }
                    if ($hasRepasDetails) {
                        $updateSql .= ", repas_details = ?";
                        $params[] = $repas_details;
                    }
                    $updateSql .= " WHERE id = ?";
                    $params[] = $sortie_id;
                    
                    $stmt = $pdo->prepare($updateSql);
                    $stmt->execute($params);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE sorties
                        SET date_sortie = ?, titre = ?, description = ?, details = ?, statut = ?" . ($hasDateFin ? ", date_fin = ?" : "") . ($hasIsMultiDay ? ", is_multi_day = ?" : "") . ($hasRepasPrevus ? ", repas_prevu = ?" : "") . ($hasRepasDetails ? ", repas_details = ?" : "") . "
                        WHERE id = ?
                    ");
                    $params = [$date_sortie, $titre, $description, $details, $statut];
                    if ($hasDateFin) { $params[] = $date_fin ?: null; }
                    if ($hasIsMultiDay) { $params[] = $is_multi_day ? 1 : 0; }
                    if ($hasRepasPrevus) { $params[] = $repas_prevu ? 1 : 0; }
                    if ($hasRepasDetails) { $params[] = $repas_details; }
                    $params[] = $sortie_id;
                    $stmt->execute($params);
                }

                // Machines
                $stmtDel = $pdo->prepare("DELETE FROM sortie_machines WHERE sortie_id = ?");
                $stmtDel->execute([$sortie_id]);
                if (!empty($machinesPost)) {
                    $stmtIns = $pdo->prepare("INSERT INTO sortie_machines (sortie_id, machine_id) VALUES (?, ?)");
                    foreach ($machinesPost as $mid) {
                        $stmtIns->execute([$sortie_id, (int)$mid]);
                    }
                }

            } else {
                // INSERT
                if ($hasDestinationId || $hasUlmBaseId) {
                    $insertCols = "date_sortie";
                    $insertVals = "?";
                    $params = [$date_sortie];
                    
                    if ($hasDateFin) {
                        $insertCols .= ", date_fin";
                        $insertVals .= ", ?";
                        $params[] = $date_fin ?: null;
                    }
                    
                    $insertCols .= ", titre, description, details, statut";
                    $insertVals .= ", ?, ?, ?, ?";
                    $params = array_merge($params, [$titre, $description, $details, $statut]);
                    
                    if ($hasIsMultiDay) {
                        $insertCols .= ", is_multi_day";
                        $insertVals .= ", ?";
                        $params[] = $is_multi_day ? 1 : 0;
                    }
                    
                    if ($hasDestinationId) {
                        $insertCols .= ", destination_id";
                        $insertVals .= ", ?";
                        $params[] = $destination_id ?: null;
                    }
                    
                    if ($hasUlmBaseId) {
                        $insertCols .= ", ulm_base_id";
                        $insertVals .= ", ?";
                        $params[] = $ulm_base_id ?: null;
                    }
                    
                    $insertCols .= ", created_by";
                    $insertVals .= ", ?";
                    $params[] = $_SESSION['user_id'];
                    
                    if ($hasRepasPrevus) {
                        $insertCols .= ", repas_prevu";
                        $insertVals .= ", ?";
                        $params[] = $repas_prevu ? 1 : 0;
                    }
                    if ($hasRepasDetails) {
                        $insertCols .= ", repas_details";
                        $insertVals .= ", ?";
                        $params[] = $repas_details;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO sorties ($insertCols) VALUES ($insertVals)");
                    $stmt->execute($params);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO sorties (date_sortie, " . ($hasDateFin ? "date_fin, " : "") . "titre, description, details, statut, " . ($hasIsMultiDay ? "is_multi_day, " : "") . "created_by" . ($hasRepasPrevus ? ", repas_prevu" : "") . ($hasRepasDetails ? ", repas_details" : "") . ")
                        VALUES (?, " . ($hasDateFin ? "?, " : "") . "?, ?, ?, ?, " . ($hasIsMultiDay ? "?, " : "") . "?" . ($hasRepasPrevus ? ",?" : "") . ($hasRepasDetails ? ",?" : "") . ")
                    ");
                    $params = [$date_sortie];
                    if ($hasDateFin) { $params[] = $date_fin ?: null; }
                    $params = array_merge($params, [$titre, $description, $details, $statut]);
                    if ($hasIsMultiDay) { $params[] = $is_multi_day ? 1 : 0; }
                    $params[] = $_SESSION['user_id'];
                    if ($hasRepasPrevus) { $params[] = $repas_prevu ? 1 : 0; }
                    if ($hasRepasDetails) { $params[] = $repas_details; }
                    $stmt->execute($params);
                }
                $sortie_id = (int)$pdo->lastInsertId();

                // Machines
                if (!empty($machinesPost)) {
                    $stmtIns = $pdo->prepare("INSERT INTO sortie_machines (sortie_id, machine_id) VALUES (?, ?)");
                    foreach ($machinesPost as $mid) {
                        $stmtIns->execute([$sortie_id, (int)$mid]);
                    }
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Une erreur est survenue lors de l'enregistrement de la sortie.";
        }

        // Gestion des suppressions de photos
        if (!empty($_POST['delete_photos']) && is_array($_POST['delete_photos'])) {
            $idsToDelete = array_map('intval', $_POST['delete_photos']);
            if (!empty($idsToDelete)) {
                $in  = implode(',', array_fill(0, count($idsToDelete), '?'));
                $sql = "SELECT * FROM sortie_photos WHERE sortie_id = ? AND id IN ($in)";
                $stmt = $pdo->prepare($sql);
                $params = array_merge([$sortie_id], $idsToDelete);
                $stmt->execute($params);
                $photosToDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Suppression fichiers
                foreach ($photosToDelete as $p) {
                    $file = $uploadDir . '/' . $p['filename'];
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }

                // Suppression DB
                $sqlDel = "DELETE FROM sortie_photos WHERE sortie_id = ? AND id IN ($in)";
                $stmtDel = $pdo->prepare($sqlDel);
                $stmtDel->execute($params);
            }
        }

        // Gestion des uploads de nouvelles photos
        if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
            $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            foreach ($_FILES['photos']['name'] as $idx => $name) {
                if ($_FILES['photos']['error'][$idx] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $tmpName = $_FILES['photos']['tmp_name'][$idx];
                $origName = basename($name);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedExt, true)) {
                    continue;
                }

                $newName = 'sortie_' . $sortie_id . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                $dest = $uploadDir . '/' . $newName;

                if (move_uploaded_file($tmpName, $dest)) {
                    $stmtP = $pdo->prepare("
                        INSERT INTO sortie_photos (sortie_id, filename)
                        VALUES (?, ?)
                    ");
                    $stmtP->execute([$sortie_id, $newName]);
                }
            }
        }

        // Si tout va bien et pas d'erreurs, tracer l'opération puis redirection vers la liste
        if (empty($errors)) {
            $action = (isset($_POST['sortie_id']) && $_POST['sortie_id']) ? 'sortie_update' : 'sortie_create';
            $details = $action === 'sortie_update' ? 'Sortie modifiée' : 'Sortie créée';
            gn_log_current_user_operation($pdo, $action, $details);

            $flag = isset($_POST['sortie_id']) && $_POST['sortie_id'] ? 'success=1' : 'created=1';
            header('Location: sorties.php?' . $flag . '&focus=' . $sortie_id);
            exit;
        }
    }

    // Si erreurs, on reconstruit $sortie + machines sélectionnées pour réafficher le formulaire
    $sortie = [
        'id'          => $sortie_id,
        'date_sortie' => $date_sortie,
        'date_fin'    => $date_fin,
        'is_multi_day'=> $is_multi_day,
        'titre'       => $titre,
        'description' => $description,
        'details'     => $details,
        'statut'      => $statut,
        'repas_prevu' => $repas_prevu,
        'repas_details' => $repas_details,
    ];
    $selected_machines = array_map('intval', $machinesPost);

    if ($sortie_id > 0) {
        // Recharger les photos si on est en édition
        $stmtP = $pdo->prepare("SELECT * FROM sortie_photos WHERE sortie_id = ? ORDER BY created_at DESC");
        $stmtP->execute([$sortie_id]);
        $photos = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Préparation valeurs pour le formulaire
$is_edit = ($sortie_id > 0);
$page_title = $is_edit ? "Modifier une sortie" : "Créer une sortie";

// Pour le champ datetime-local
$datetime_value = '';
if (!empty($sortie['date_sortie'])) {
    $dt = new DateTime($sortie['date_sortie']);
    $datetime_value = $dt->format('Y-m-d\\TH:i');
}
$datetime_end_value = '';
if (!empty($sortie['date_fin'])) {
    try { $df = new DateTime($sortie['date_fin']); $datetime_end_value = $df->format('Y-m-d\\TH:i'); } catch (Throwable $e) {}
}
$current_statut = $sortie['statut'] ?? 'prévue';
$current_destination = isset($sortie['destination_id']) ? (int)$sortie['destination_id'] : 0;
$current_ulm_base = isset($sortie['ulm_base_id']) ? (int)$sortie['ulm_base_id'] : 0;

$current_dest_value = '';
if ($current_ulm_base > 0) {
    $current_dest_value = 'ulm_' . $current_ulm_base;
} elseif ($current_destination > 0) {
    $current_dest_value = (string)$current_destination;
}

// Récupération éventuelle des photos déjà faites (si pas déjà fait)
if ($is_edit && empty($photos)) {
    $stmtP = $pdo->prepare("SELECT * FROM sortie_photos WHERE sortie_id = ? ORDER BY created_at DESC");
    $stmtP->execute([$sortie_id]);
    $photos = $stmtP->fetchAll(PDO::FETCH_ASSOC);
}
// À partir d'ici, on peut envoyer du HTML
require 'header.php';
?>

<style>
    .sortie-edit-page {
        max-width: 1000px;
        margin: 0 auto;
        padding: 2rem 1rem 3rem;
    }
    .sortie-edit-header {
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
    .sortie-edit-header h1 {
        font-size: 1.6rem;
        margin: 0;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    .sortie-edit-header p {
        margin: 0.25rem 0 0;
        opacity: 0.9;
        font-size: 0.95rem;
    }
    .sortie-edit-header-icon {
        font-size: 2.4rem;
        opacity: 0.9;
    }
    .card {
        background: #ffffff;
        border-radius: 1.25rem;
        padding: 1.75rem 1.5rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.03);
        margin-bottom: 1.5rem;
    }
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 1rem;
        gap: .75rem;
    }
    .card-title {
        font-size: 1.15rem;
        font-weight: 600;
        margin: 0;
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }
    .card-subtitle {
        font-size: 0.85rem;
        color: #666;
        margin: 0.15rem 0 0;
    }
    .badge-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.2rem 0.6rem;
        border-radius: 999px;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        background: rgba(0, 75, 141, 0.08);
        color: #004b8d;
        font-weight: 600;
    }
    .btn-primary-gestnav {
        border: none;
        border-radius: 999px;
        padding: 0.55rem 1.3rem;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        background: linear-gradient(135deg, #004b8d, #00a0c6);
        color: #fff;
        box-shadow: 0 8px 16px rgba(0, 75, 141, 0.35);
        transition: transform 0.1s ease, box-shadow 0.1s ease, filter 0.1s ease;
    }
    .btn-primary-gestnav:hover {
        filter: brightness(1.05);
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(0, 75, 141, 0.4);
    }
    .btn-secondary-link {
        border: none;
        padding: 0.4rem 0.8rem;
        border-radius: 999px;
        background: transparent;
        font-size: 0.8rem;
        color: #fff;
        cursor: pointer;
        text-decoration: underline;
    }
    .sortie-edit-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
        gap: 1.5rem;
    }
    @media (max-width: 900px) {
        .sortie-edit-grid {
            grid-template-columns: 1fr;
        }
    }
    .form-group {
        margin-bottom: 0.75rem;
    }
    .form-group label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: #333;
    }
    .form-group small {
        display: block;
        font-size: 0.75rem;
        color: #777;
        margin-top: 0.1rem;
    }
    .form-control,
    textarea,
    select {
        width: 100%;
        border-radius: 0.75rem;
        border: 1px solid #d0d7e2;
        padding: 0.6rem 0.9rem;
        font-size: 0.9rem;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        background: #f9fbff;
        resize: vertical;
    }
    .form-control:focus,
    textarea:focus,
    select:focus {
        border-color: #00a0c6;
        box-shadow: 0 0 0 3px rgba(0, 160, 198, 0.2);
        background: #ffffff;
    }
    .machines-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 1.25rem;
        font-size: 0.9rem;
    }
    .machines-list label {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        cursor: pointer;
    }
    .flash-error {
        margin-bottom: 1rem;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        background: #fde8e8;
        color: #b02525;
        font-size: 0.85rem;
    }
    .photos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 0.75rem;
    }
    .photo-item {
        border-radius: 0.75rem;
        border: 1px solid #e4e9f2;
        padding: 0.4rem;
        font-size: 0.8rem;
        background: #f9fbff;
    }
    .photo-item img {
        width: 100%;
        display: block;
        border-radius: 0.5rem;
        margin-bottom: 0.3rem;
        object-fit: cover;
        max-height: 120px;
    }
    .photo-item label {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        cursor: pointer;
    }
    #destSearchBtn { cursor: pointer; }
</style>

<div class="sortie-edit-page">
    <div class="sortie-edit-header">
        <div>
            <h1><?= htmlspecialchars($page_title) ?></h1>
            <p>Complétez les informations détaillées de la sortie et ajoutez des photos si besoin.</p>
        </div>
        <div class="sortie-edit-header-icon">🛩️</div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="flash-error">
            <?php foreach ($errors as $e): ?>
                <div>• <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="sortieEditForm">
        <input type="hidden" name="sortie_id" value="<?= htmlspecialchars($sortie_id) ?>">

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">
                        Informations de la sortie
                        <span class="badge-pill"><?= $is_edit ? 'Édition' : 'Nouvelle' ?></span>
                    </h2>
                    <p class="card-subtitle">
                        Date, nom, description courte et état de la sortie.
                    </p>
                </div>
                <button type="button" class="btn-secondary-link" onclick="window.location.href='sorties.php'">
                    Retour à la liste
                </button>
            </div>

            <div class="sortie-edit-grid">
                <div>
                    <div class="form-group">
                        <label for="date_sortie">Date & heure</label>
                        <input
                            type="datetime-local"
                            id="date_sortie"
                            name="date_sortie"
                            class="form-control"
                            required
                            value="<?= htmlspecialchars($datetime_value) ?>"
                        >
                    </div>
                    <?php if ($hasDateFin || $hasIsMultiDay): ?>
                    <div class="form-group">
                        <label for="is_multi_day">Sortie sur plusieurs jours</label>
                        <select id="is_multi_day" name="is_multi_day" class="form-control">
                            <option value="0" <?= !empty($sortie['is_multi_day']) ? '' : 'selected' ?>>Non</option>
                            <option value="1" <?= !empty($sortie['is_multi_day']) ? 'selected' : '' ?>>Oui</option>
                        </select>
                        <small>Activez pour renseigner une période (date de début et de fin).</small>
                    </div>
                    <div class="form-group" id="date_fin_group" style="display: <?= (!empty($sortie['is_multi_day'])) ? 'block' : 'none' ?>;">
                        <label for="date_fin">Date & heure de fin</label>
                        <input
                            type="datetime-local"
                            id="date_fin"
                            name="date_fin"
                            class="form-control"
                            value="<?= htmlspecialchars($datetime_end_value) ?>"
                        >
                        <small>Doit être postérieure à la date de début.</small>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="titre">Nom de la sortie</label>
                        <input
                            type="text"
                            id="titre"
                            name="titre"
                            class="form-control"
                            required
                            placeholder="Ex : Nav côtière, Vol local LFQJ, Baptêmes..."
                            value="<?= htmlspecialchars($sortie['titre'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="description">Description courte</label>
                        <input
                            type="text"
                            id="description"
                            name="description"
                            class="form-control"
                            placeholder="Court résumé visible dans la liste des sorties"
                            value="<?= htmlspecialchars($sortie['description'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="statut">Statut</label>
                        <select id="statut" name="statut" class="form-control">
                            <option value="prévue"    <?= $current_statut === 'prévue'    ? 'selected' : '' ?>>Prévue</option>
                            <option value="terminée"  <?= $current_statut === 'terminée'  ? 'selected' : '' ?>>Terminée</option>
                            <option value="annulée"   <?= $current_statut === 'annulée'   ? 'selected' : '' ?>>Annulée</option>
                            <option value="en étude"  <?= $current_statut === 'en étude'  ? 'selected' : '' ?>>En étude (admin)</option>
                        </select>
                        <small>Le statut « En étude » permet de préparer une sortie avant publication. Visible et modifiable uniquement par les administrateurs.</small>
                    </div>
                    <div class="form-group">
                        <label for="destination_id">Destination (aérodrome ou base ULM)</label>
                        <?php if (($aerodromes || $ulm_bases) && ($hasDestinationId || $hasUlmBaseId)): ?>
                            <div class="input-group mb-2">
                                <button class="btn btn-outline-secondary" type="button" id="destSearchBtn" title="Rechercher" aria-label="Rechercher">
                                    <span id="destSearchSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                                    <i class="bi bi-search"></i>
                                </button>
                                <input type="text" id="destSearch" class="form-control" placeholder="Rechercher OACI, nom ou ville…" aria-label="Champ de recherche destination">
                            </div>
                            <div id="destSearchInfo" class="text-muted" style="font-size:.8rem;margin-top:-.25rem;margin-bottom:.5rem;">Tapez pour rechercher (OACI, nom, ville)…</div>
                            <select id="destination_id" name="destination_id" class="form-control">
                                <option value="">— Sélectionner —</option>
                                <?php if (!empty($aerodromes)): ?>
                                    <optgroup label="🛩️ Aérodromes">
                                        <?php foreach ($aerodromes as $ad): ?>
                                            <?php $sel = ((string)$current_dest_value === (string)$ad['id']) ? 'selected' : ''; ?>
                                            <option value="<?= (int)$ad['id'] ?>" <?= $sel ?>><?= htmlspecialchars(($ad['oaci']?($ad['oaci'].' – '):'').$ad['nom'].(isset($ad['ville']) && $ad['ville']?(' ('.$ad['ville'].')') : '')) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if (!empty($ulm_bases)): ?>
                                    <optgroup label="🪂 Bases ULM">
                                        <?php foreach ($ulm_bases as $ulm): ?>
                                            <?php 
                                            $ulm_value = 'ulm_' . (string)$ulm['id'];
                                            $sel = ($current_dest_value === $ulm_value) ? 'selected' : ''; 
                                            ?>
                                            <option value="ulm_<?= (int)$ulm['id'] ?>" <?= $sel ?>><?= htmlspecialchars(($ulm['oaci']?($ulm['oaci'].' – '):'').$ulm['nom'].(isset($ulm['ville']) && $ulm['ville']?(' ('.$ulm['ville'].')') : '')) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <small>Choisissez la destination pour vos statistiques et le suivi.</small>
                        <?php else: ?>
                            <input type="text" class="form-control" value="" placeholder="Destination (colonnes destination_id/ulm_base_id absentes)">
                            <small class="text-muted">Les colonnes de destination n'existent pas dans `sorties`. Contactez l'admin pour les ajouter.</small>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <div class="form-group">
                        <label for="details">Détails / briefing</label>
                        <textarea
                            id="details"
                            name="details"
                            rows="8"
                            class="form-control"
                            placeholder="Itinéraire, altitudes, fréquences, contraintes météo, remarques…"
                        ><?= htmlspecialchars($sortie['details'] ?? '') ?></textarea>
                    </div>

                    <?php if ($hasRepasPrevus || $hasRepasDetails): ?>
                    <div class="form-group">
                        <label>Repas prévu</label>
                        <select name="repas_prevu" class="form-control">
                            <option value="0" <?= !empty($sortie['repas_prevu']) ? '' : 'selected' ?>>Non</option>
                            <option value="1" <?= !empty($sortie['repas_prevu']) ? 'selected' : '' ?>>Oui</option>
                        </select>
                        <small>Indiquez si un repas est prévu pendant la sortie.</small>
                    </div>
                    <div class="form-group">
                        <label for="repas_details">Lieu/infos repas (lien cliquable)</label>
                        <textarea id="repas_details" name="repas_details" rows="3" class="form-control" placeholder="Ex: Restaurant Le Spot – https://lespot.example.com"><?= htmlspecialchars($sortie['repas_details'] ?? '') ?></textarea>
                        <small>Vous pouvez coller une URL, elle sera affichée comme lien cliquable sur la page de détail.</small>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Machines utilisées</label>
                        <?php if ($machines): ?>
                            <div class="machines-list">
                                <?php foreach ($machines as $m): ?>
                                    <?php $checked = in_array((int)$m['id'], $selected_machines, true) ? 'checked' : ''; ?>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="machines[]"
                                            value="<?= $m['id'] ?>"
                                            <?= $checked ?>
                                        >
                                        <?= htmlspecialchars($m['nom']) ?>
                                        (<?= htmlspecialchars($m['immatriculation']) ?>)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <small>Aucune machine active. Ajoutez des machines dans le module dédié.</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Photos de la sortie</h2>
                    <p class="card-subtitle">
                        Ajoutez quelques photos pour illustrer la sortie (affichées dans les futures pages de détail).
                    </p>
                </div>
            </div>

            <?php if ($is_edit && !empty($photos)): ?>
                <div class="form-group">
                    <label>Photos existantes</label>
                    <div class="photos-grid">
                        <?php foreach ($photos as $p): ?>
                            <div class="photo-item">
                                <img src="<?= $uploadUrl . '/' . htmlspecialchars($p['filename']) ?>" alt="">
                                <label>
                                    <input type="checkbox" name="delete_photos[]" value="<?= $p['id'] ?>">
                                    Supprimer cette photo
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="photos">Ajouter des photos</label>
                <input
                    type="file"
                    id="photos"
                    name="photos[]"
                    class="form-control"
                    multiple
                    accept="image/*"
                >
                <small>Formats acceptés : JPG, PNG, GIF, WebP. Taille limitée par la config de ton hébergement.</small>
            </div>
        </div>

                <div style="text-align:right;">
                        <button type="submit" class="btn-primary-gestnav" id="sortieEditSubmit">
                <?= $is_edit ? 'Enregistrer les modifications' : 'Créer la sortie' ?>
            </button>
        </div>
    </form>
</div>

<?php require 'footer.php'; ?>
<script>
(function(){
    var form = document.getElementById('sortieEditForm');
    var btn = document.getElementById('sortieEditSubmit');
    if (!form || !btn) return;
    form.addEventListener('submit', function(){
        btn.disabled = true;
        btn.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>');
        var txt = btn.textContent;
        btn.textContent = 'Envoi en cours…';
        btn.dataset.original = txt;
    });
})();

// Filtre destination basé sur une source JSON fiable (conserve les optgroups)
(function(){
    var input = document.getElementById('destSearch');
    var btn = document.getElementById('destSearchBtn');
    var select = document.getElementById('destination_id');
    var info = document.getElementById('destSearchInfo');
    var spinner = document.getElementById('destSearchSpinner');
    if (!input || !select) return;
    
    // Sauvegarder l'HTML d'origine du select (avec aérodromes ET bases ULM)
    var originalSelectHTML = select.innerHTML;
    var placeholderText = '— Sélectionner —';
    
    function render(list){
        var current = select.value;
        while (select.firstChild) select.removeChild(select.firstChild);
        var opt0 = document.createElement('option');
        opt0.value = '';
        opt0.text = placeholderText;
        select.appendChild(opt0);
        
        // Aérodromes (résultats de recherche)
        if (list && list.length > 0) {
            var optGroupAero = document.createElement('optgroup');
            optGroupAero.label = '🛩️ Aérodromes';
            list.forEach(function(a){
                var opt = document.createElement('option');
                opt.value = String(a.id);
                var label = ((a.oaci||'') ? (a.oaci + ' – ') : '') + (a.nom||'');
                if (a.ville || a.city) {
                    label += ' (' + (a.ville||a.city) + ')';
                }
                opt.text = label;
                if (opt.value === current) opt.selected = true;
                optGroupAero.appendChild(opt);
            });
            select.appendChild(optGroupAero);
        }
        
        // Réinsérer les bases ULM depuis l'HTML d'origine
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = originalSelectHTML;
        var ulmOptgroup = tempDiv.querySelector('optgroup[label*="ULM"]');
        if (ulmOptgroup) {
            var clonedUlm = ulmOptgroup.cloneNode(true);
            // Restaurer la sélection si c'était une base ULM
            Array.from(clonedUlm.querySelectorAll('option')).forEach(function(opt){
                if (opt.value === current) opt.selected = true;
            });
            select.appendChild(clonedUlm);
        }
    }
    
    function queryServer(q){
        var url = 'search_aerodromes.php?q=' + encodeURIComponent(q||'');
        if (spinner) spinner.classList.remove('d-none');
        if (btn) btn.disabled = true;
        if (info) info.textContent = 'Recherche…';
        fetch(url, { credentials: 'include' })
            .then(function(r){ return r.json(); })
            .then(function(json){
                if (json && json.ok) {
                    render(json.items);
                    var n = Array.isArray(json.items) ? json.items.length : 0;
                    // Auto-sélectionner le 1er résultat si une requête non vide a été faite
                    if ((q||'').trim() !== '' && n > 0) {
                        select.selectedIndex = 1; // 0 = placeholder
                    }
                    if (info) {
                        var sample = '';
                        if (n > 0) {
                            var a0 = json.items[0];
                            if (a0) {
                                sample = ' — ex: ' + ((a0.oaci? (a0.oaci + ' – ') : '') + (a0.nom||'') + (a0.ville? (' ('+a0.ville+')') : ''));
                            }
                        }
                        info.textContent = n ? (n + ' résultat' + (n>1?'s':'') + sample) : 'Aucun résultat';
                    }
                } else {
                    if (info) info.textContent = 'Erreur de recherche';
                }
            })
            .catch(function(){ if (info) info.textContent = 'Erreur de recherche'; })
            .finally(function(){ if (spinner) spinner.classList.add('d-none'); if (btn) btn.disabled = false; });
    }
    // Initial render (serveur, vide = liste initiale)
    queryServer('');
    var debounce;
    input.addEventListener('input', function(){
        clearTimeout(debounce);
        var q = input.value;
        debounce = setTimeout(function(){ queryServer(q); }, 150);
    });
    input.addEventListener('keydown', function(ev){ if (ev.key === 'Enter') { ev.preventDefault(); queryServer(input.value); }});
    if (btn) {
        btn.addEventListener('click', function(){
            input.focus();
            var q = input.value;
            queryServer(q);
        });
        btn.addEventListener('keydown', function(ev){
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                input.focus();
                queryServer(input.value);
            }
        });
    }
})();
</script>
<script>
// Toggle affichage de la date de fin quand "Sortie sur plusieurs jours" change
(function(){
    var sel = document.getElementById('is_multi_day');
    var grp = document.getElementById('date_fin_group');
    var fin = document.getElementById('date_fin');
    if (!sel || !grp || !fin) return;
    function update(){
        var on = (sel.value === '1');
        grp.style.display = on ? 'block' : 'none';
        fin.required = on;
    }
    sel.addEventListener('change', update);
    update();
})();
</script>
