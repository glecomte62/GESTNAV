<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

// Initialisation
$edit_machine = null;

// Ajout / édition
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $nom = trim($_POST['nom']);
    $immatriculation = trim($_POST['immatriculation']);
    $type = trim($_POST['type']);
    $actif = isset($_POST['actif']) ? 1 : 0;
    // Source (club|membre) si la colonne existe
    $hasSourceCol = false;
    try {
        $c = $pdo->query("SHOW COLUMNS FROM machines LIKE 'source'");
        if ($c && $c->fetch()) { $hasSourceCol = true; }
    } catch (Throwable $e) { $hasSourceCol = false; }
    $source = 'club';
    if ($hasSourceCol) {
        $srcIn = strtolower(trim($_POST['source'] ?? 'club'));
        $source = in_array($srcIn, ['club','membre'], true) ? $srcIn : 'club';
    }

    if ($id) {
        // Update
        if ($hasSourceCol) {
            $stmt = $pdo->prepare("UPDATE machines SET nom=?, immatriculation=?, type=?, actif=?, source=? WHERE id=?");
            $stmt->execute([$nom, $immatriculation, $type, $actif, $source, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE machines SET nom=?, immatriculation=?, type=?, actif=? WHERE id=?");
            $stmt->execute([$nom, $immatriculation, $type, $actif, $id]);
        }
        $machineId = (int)$id;
    } else {
        // Insert
        if ($hasSourceCol) {
            $stmt = $pdo->prepare("INSERT INTO machines (nom, immatriculation, type, actif, source) VALUES (?,?,?,?,?)");
            $stmt->execute([$nom, $immatriculation, $type, $actif, $source]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO machines (nom, immatriculation, type, actif) VALUES (?,?,?,?)");
            $stmt->execute([$nom, $immatriculation, $type, $actif]);
        }
        $machineId = (int)$pdo->lastInsertId();
    }

    // Gestion upload photo éventuelle (compression + redimensionnement en WebP)
    $uploadFlag = '';
    if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $maxSize = 5 * 1024 * 1024; // 5 Mo
        if (($_FILES['photo']['size'] ?? 0) > $maxSize) {
            $uploadFlag = 'too_large';
        } else {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $uploadFlag = 'bad_type';
        } else {
            $dir = __DIR__ . '/uploads/machines';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            // Nettoyage anciens formats
            foreach (array_merge($allowed, ['webp']) as $e) {
                $old = $dir . '/machine_' . $machineId . '.' . $e;
                if (file_exists($old)) { @unlink($old); }
            }

            $tmp = $_FILES['photo']['tmp_name'];
            $img = null;
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $img = @imagecreatefromjpeg($tmp);
            } elseif ($ext === 'png') {
                $img = @imagecreatefrompng($tmp);
            } elseif ($ext === 'webp') {
                if (function_exists('imagecreatefromwebp')) {
                    $img = @imagecreatefromwebp($tmp);
                } else {
                    // fallback: move as-is if webp not supported
                    if (@move_uploaded_file($tmp, $dir . '/machine_' . $machineId . '.webp')) {
                        $uploadFlag = 'ok';
                    }
                }
            }

            if ($img) {
                $w = imagesx($img); $h = imagesy($img);
                // Recadrage centré carré
                $side = min($w, $h);
                $srcX = (int)floor(($w - $side) / 2);
                $srcY = (int)floor(($h - $side) / 2);
                $square = imagecreatetruecolor($side, $side);
                imagecopy($square, $img, 0, 0, $srcX, $srcY, $side, $side);
                imagedestroy($img);
                $img = $square;
                $w = $side; $h = $side;
                $maxW = 1280; $maxH = 1280;
                $scale = min($maxW / max(1,$w), $maxH / max(1,$h), 1.0);
                $newW = (int)floor($w * $scale); $newH = (int)floor($h * $scale);
                $dst = imagecreatetruecolor($newW, $newH);
                imagecopyresampled($dst, $img, 0,0,0,0, $newW,$newH, $w,$h);
                $target = $dir . '/machine_' . $machineId . '.webp';
                if (function_exists('imagewebp')) {
                    if (@imagewebp($dst, $target, 85)) { $uploadFlag = 'ok'; }
                } else {
                    // si imagewebp indisponible, enregistrer en JPEG
                    if (@imagejpeg($dst, $dir . '/machine_' . $machineId . '.jpg', 85)) { $uploadFlag = 'ok'; }
                }
                imagedestroy($dst);
                imagedestroy($img);
            }
        }
        }
    }

    $redirect = 'machines.php?success=1' . ($uploadFlag ? ('&upload=' . $uploadFlag) : '');
    header('Location: ' . $redirect);
    exit;
}

// Suppression
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    try {
        // Supprimer les dépendances éventuelles pour éviter les erreurs SQL
        // Associations propriétaires
        $pdo->prepare("DELETE FROM machines_owners WHERE machine_id = ?")->execute([$id]);
        // Associations aux sorties
        $pdo->prepare("DELETE FROM sortie_machines WHERE machine_id = ?")->execute([$id]);

        // Supprimer la photo associée si présente
        $dir = __DIR__ . '/uploads/machines';
        foreach (['jpg','jpeg','png','webp'] as $e) {
            $p = $dir . '/machine_' . $id . '.' . $e;
            if (file_exists($p)) { @unlink($p); }
        }

        // Supprimer la machine
        $stmt = $pdo->prepare("DELETE FROM machines WHERE id=?");
        $stmt->execute([$id]);
        header('Location: machines.php?deleted=1');
        exit;
    } catch (Throwable $ex) {
        // En cas d'erreur, revenir avec un message lisible
        $msg = urlencode('Suppression impossible: ' . ($ex->getMessage() ?? 'erreur inconnue'));
        header('Location: machines.php?error=' . $msg);
        exit;
    }
}

// Toggle actif/inactif
if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    $stmt = $pdo->prepare("SELECT actif FROM machines WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $new = $row['actif'] ? 0 : 1;
        $up = $pdo->prepare("UPDATE machines SET actif=? WHERE id=?");
        $up->execute([$new, $id]);
    }
    header('Location: machines.php?success=1');
    exit;
}

// Préparation édition
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM machines WHERE id=?");
    $stmt->execute([$id]);
    $edit_machine = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Liste des machines + séparation club / propriétaires
$stmt = $pdo->query("SELECT * FROM machines ORDER BY actif DESC, nom ASC");
$allMachines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Détecter colonne `source`
$machinesCols = [];
try { $machinesCols = $pdo->query("SHOW COLUMNS FROM machines")->fetchAll(PDO::FETCH_COLUMN, 0); } catch (Throwable $e) { $machinesCols = []; }
$hasSource = in_array('source', $machinesCols, true);

// Récupérer propriétaires pour toutes les machines
$ownersByMachine = [];
try {
    $rs = $pdo->query("SELECT mo.machine_id, u.prenom, u.nom FROM machines_owners mo JOIN users u ON u.id = mo.user_id ORDER BY u.nom, u.prenom");
    foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mid = (int)$row['machine_id'];
        if (!isset($ownersByMachine[$mid])) $ownersByMachine[$mid] = [];
        $ownersByMachine[$mid][] = trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? ''));
    }
} catch (Throwable $e) {
    $ownersByMachine = [];
}

$machinesClub = [];
$machinesMember = [];
foreach ($allMachines as $m) {
    $mid = (int)$m['id'];
    $isMember = ($hasSource && ($m['source'] ?? 'club') === 'membre') || isset($ownersByMachine[$mid]);
    if ($isMember) $machinesMember[] = $m; else $machinesClub[] = $m;
}

// Helper local pour récupérer l'URL de la photo machine (ou placeholder)
function gestnav_machine_photo_url(int $id): string {
    $relBase = 'uploads/machines';
    $absBase = __DIR__ . '/uploads/machines';
    foreach (['jpg','jpeg','png','webp'] as $ext) {
        $abs = $absBase . '/machine_' . $id . '.' . $ext;
        if (file_exists($abs)) {
            return $relBase . '/machine_' . $id . '.' . $ext;
        }
    }
    return 'assets/img/machine-placeholder.svg';
}

require 'header.php';

?>

<style>
    /* Styles spécifiques à la page machines */
    .machines-page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 2rem 1rem 3rem;
    }

    .machines-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 2rem;
        padding: 1.5rem 1.75rem;
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #004b8d, #00a0c6);
        color: #fff;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
    }

    .machines-header h1 {
        font-size: 1.6rem;
        margin: 0;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    .machines-header p {
        margin: 0.2rem 0 0;
        opacity: 0.9;
        font-size: 0.95rem;
    }

    .machines-header-icon {
        font-size: 2.4rem;
        opacity: 0.9;
    }

    .machines-layout {
        display: grid;
        grid-template-columns: minmax(0, 360px) minmax(0, 1fr);
        gap: 1.75rem;
        align-items: flex-start;
    }

    .machines-main {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2.5rem;
    }

    .section-separator {
        padding-top: 2rem;
        border-top: 2px solid #e6ebf2;
        margin-top: 0.5rem;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        margin-bottom: 1.25rem;
    }

    .section-header h2 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: #004b8d;
    }

    .section-header .section-icon {
        font-size: 1.6rem;
    }

    .section-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.3rem 0.8rem;
        background: rgba(0, 75, 141, 0.1);
        color: #004b8d;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-left: auto;
    }

    .empty-state {
        text-align: center;
        padding: 2rem 1rem;
        color: #888;
        font-style: italic;
    }

    @media (max-width: 900px) {
        .machines-layout {
            grid-template-columns: 1fr;
        }
    }

    .card {
        background: #ffffff;
        border-radius: 1.25rem;
        padding: 1.75rem 1.5rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.03);
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 1rem;
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
        margin: 0;
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

    .machines-form .form-group {
        margin-bottom: 0.85rem;
    }

    .machines-form label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 0.2rem;
        color: #333;
    }

    .machines-form input[type="text"],
    .machines-form input[type="email"],
    .machines-form select {
        width: 100%;
        border-radius: 999px;
        border: 1px solid #d0d7e2;
        padding: 0.6rem 0.9rem;
        font-size: 0.9rem;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        background: #f9fbff;
    }

    .machines-form input[type="text"]:focus,
    .machines-form select:focus {
        border-color: #00a0c6;
        box-shadow: 0 0 0 3px rgba(0, 160, 198, 0.2);
        background: #ffffff;
    }

    .machines-form .form-check {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
        font-size: 0.9rem;
    }

    .machines-form .form-check input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    .machines-form .form-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        margin-top: 1.1rem;
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
        padding: 0;
        background: none;
        color: #888;
        font-size: 0.8rem;
        cursor: pointer;
        text-decoration: underline;
    }

    .machines-table-title { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:0.5rem; }
    .machines-table-title h2 { margin:0; font-size:1.05rem; }
    .machines-table-title span { font-size:0.8rem; color:#666; }
    .machines-flash { margin-top:0.75rem; font-size:0.85rem; color:#0a8a0a; }

    /* Grille de cartes machines */
    .machine-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem; }
    .machine-grid.members-grid { grid-template-columns: repeat(2, 1fr); }
    .machine-card { border:1px solid #e6ebf2; border-radius:1rem; overflow:hidden; background:#fff; box-shadow:0 6px 16px rgba(0,0,0,0.06); display:flex; flex-direction:column; height:100%; }
    .machine-card .machine-img { width:100%; aspect-ratio:16/9; object-fit:cover; background:#f2f6fc; }
    .machine-card .machine-body { padding:1rem; display:flex; flex-direction:column; gap:0.5rem; flex-grow:1; }
    .machine-name { font-weight:700; font-size:1.05rem; margin:0; }
    .machine-meta { color:#666; font-size:0.9rem; }
    .badge-status { display:inline-flex; align-items:center; padding:0.25rem 0.6rem; border-radius:999px; font-size:0.75rem; font-weight:700; width:fit-content; }
    .badge-status.actif { background:rgba(0,150,0,0.1); color:#0a8a0a; }
    .badge-status.inactif { background:rgba(200,0,0,0.06); color:#b02525; }
    .machine-actions { display:flex; justify-content:flex-start; align-items:center; padding:0.9rem 1rem; border-top:1px solid #eef2f7; gap:0.6rem; flex-wrap:wrap; }
    .btn-link { display:inline-flex; align-items:center; gap:0.35rem; padding:0.5rem 0.85rem; border-radius:999px; font-weight:600; text-decoration:none; font-size:0.85rem; flex-shrink:0; }
    .btn-edit { color:#004b8d; background:rgba(0,75,141,0.08); }
    .btn-edit:hover { background:rgba(0,75,141,0.12); }
    .btn-delete { color:#b02525; background:rgba(176,37,37,0.08); }
    .btn-delete:hover { background:rgba(176,37,37,0.12); }
    .btn-toggle-on { color:#0a8a0a; background:rgba(0,150,0,0.1); }
    .btn-toggle-on:hover { background:rgba(0,150,0,0.15); }
    .btn-toggle-off { color:#555; background:#f0f3f8; }
    .btn-toggle-off:hover { background:#e7ecf4; }
    .machine-card.inactive { opacity:0.8; }
    
    @media (max-width: 1024px) {
        .machine-grid.members-grid { grid-template-columns: repeat(2, 1fr); }
    }
    
    @media (max-width: 768px) {
        .machine-grid, .machine-grid.members-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="machines-page">
    <div class="machines-header">
        <div>
            <h1>Gestion des machines ULM</h1>
            <p>Ajoutez, modifiez ou désactivez les machines disponibles pour les sorties du club.</p>
        </div>
        <div class="machines-header-icon">
            ✈️
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert" style="margin-bottom:1rem;">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div>Machine enregistrée avec succès.</div>
        </div>
        <?php if (isset($_GET['upload']) && $_GET['upload'] === 'ok'): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert" style="margin-top:-0.5rem; margin-bottom:1rem;">
            <i class="bi bi-image-fill me-2"></i>
            <div>Photo importée et optimisée.</div>
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['upload']) && $_GET['upload'] === 'too_large'): ?>
        <div class="alert alert-warning d-flex align-items-center" role="alert" style="margin-bottom:1rem;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div>La photo n’a pas été importée: fichier trop volumineux (max 5 Mo).</div>
        </div>
        <?php endif; ?>
    <?php elseif (isset($_GET['deleted'])): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert" style="margin-bottom:1rem;">
            <i class="bi bi-trash-fill me-2"></i>
            <div>Machine supprimée.</div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert" style="margin-bottom:1rem;">
            <i class="bi bi-x-circle-fill me-2"></i>
            <div><?= htmlspecialchars($_GET['error']) ?></div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['upload']) && $_GET['upload'] === 'bad_type'): ?>
        <div class="alert alert-warning d-flex align-items-center" role="alert" style="margin-bottom:1rem;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div>Type de fichier non autorisé. Formats acceptés: JPG, PNG, WebP.</div>
        </div>
    <?php endif; ?>

    <div class="machines-layout">
        <!-- Formulaire en sidebar -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">
                        <?= $edit_machine ? 'Modifier une machine' : 'Nouvelle machine' ?>
                        <span class="badge-pill">Flotte ULM</span>
                    </h2>
                    <p class="card-subtitle">
                        <?= $edit_machine
                            ? 'Mettez à jour les informations de la machine sélectionnée.'
                            : 'Déclarez une nouvelle machine disponible pour les membres du club.' ?>
                    </p>
                </div>
            </div>

            <form method="post" class="machines-form" enctype="multipart/form-data" id="ajout">
                <input type="hidden" name="id" value="<?= htmlspecialchars($edit_machine['id'] ?? '') ?>">

                <div class="form-group">
                    <label for="nom">Nom de la machine</label>
                    <input
                        type="text"
                        id="nom"
                        name="nom"
                        required
                        value="<?= htmlspecialchars($edit_machine['nom'] ?? '') ?>"
                        placeholder="Ex : Savannah, MCR, SkyRanger…"
                    >
                </div>

                <div class="form-group">
                    <label for="immatriculation">Immatriculation</label>
                    <input
                        type="text"
                        id="immatriculation"
                        name="immatriculation"
                        required
                        value="<?= htmlspecialchars($edit_machine['immatriculation'] ?? '') ?>"
                        placeholder="Ex : F-JXYZ"
                    >
                </div>

                <div class="form-group">
                    <label for="type">Type / catégorie</label>
                    <input
                        type="text"
                        id="type"
                        name="type"
                        required
                        value="<?= htmlspecialchars($edit_machine['type'] ?? '') ?>"
                        placeholder="Multiaxe, Pendulaire, Autogire…"
                    >
                </div>

                <div class="form-group">
                    <div class="form-check">
                        <input
                            type="checkbox"
                            id="actif"
                            name="actif"
                            <?= isset($edit_machine['actif']) ? ($edit_machine['actif'] ? 'checked' : '') : 'checked' ?>
                        >
                        <label for="actif">Machine active et disponible aux réservations</label>
                    </div>
                </div>

                <?php if ($hasSource): ?>
                <div class="form-group">
                    <label for="source">Catégorie</label>
                    <?php $currentSource = strtolower($edit_machine['source'] ?? 'club'); if (!in_array($currentSource, ['club','membre'], true)) $currentSource = 'club'; ?>
                    <select id="source" name="source">
                        <option value="club" <?= $currentSource==='club' ? 'selected' : '' ?>>Flotte du club</option>
                        <option value="membre" <?= $currentSource==='membre' ? 'selected' : '' ?>>Machine propriétaire (membre)</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="photo">Photo de la machine (JPG, PNG, WebP)</label>
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                    <?php if ($edit_machine):
                        $absBase = __DIR__ . '/uploads/machines';
                        $p = '';
                        foreach (['jpg','jpeg','png','webp'] as $e) {
                            $abs = $absBase . '/machine_' . (int)$edit_machine['id'] . '.' . $e;
                            if (file_exists($abs)) { $p = 'uploads/machines/machine_' . (int)$edit_machine['id'] . '.' . $e; break; }
                        }
                        if ($p): ?>
                        <div style="margin-top:0.5rem; font-size:0.85rem; color:#666;">Aperçu actuel :</div>
                        <img src="<?= htmlspecialchars($p) ?>" alt="Aperçu machine" style="max-width:100%; border-radius:0.5rem; border:1px solid #e6ebf2;">
                    <?php endif; endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary-gestnav" id="submitBtn">
                        <?= $edit_machine ? 'Enregistrer les modifications' : 'Ajouter la machine' ?>
                    </button>

                    <?php if ($edit_machine): ?>
                        <a href="machines.php" class="btn-secondary-link">Annuler l’édition</a>
                    <?php endif; ?>
                </div>

            </form>
        </div>

        <!-- Contenu principal avec 2 sections -->
        <div class="machines-main">
            <!-- Section 1 : Flotte du club -->
            <div>
                <div class="section-header">
                    <div class="section-icon">🏢</div>
                    <h2>Flotte du club</h2>
                    <span class="section-badge"><?= count($machinesClub) ?> machine(s)</span>
                </div>
                <?php if (empty($machinesClub)): ?>
                    <div class="empty-state">
                        <p>Aucune machine de flotte club.</p>
                        <p style="font-size:0.9rem; color:#999;"><a href="#ajout" style="color:#004b8d; text-decoration:none; font-weight:600;">Ajouter une machine →</a></p>
                    </div>
                <?php else: ?>
                    <div class="machine-grid">
                        <?php foreach ($machinesClub as $m): $photo = gestnav_machine_photo_url((int)$m['id']); ?>
                            <div class="machine-card <?= $m['actif'] ? '' : 'inactive' ?>">
                                <img class="machine-img" src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($m['nom']) ?>" loading="lazy" width="640" height="360">
                                <div class="machine-body">
                                    <div class="machine-name"><?= htmlspecialchars($m['nom']) ?></div>
                                    <div class="machine-meta">
                                        <?= htmlspecialchars($m['immatriculation']) ?> — <?= htmlspecialchars($m['type']) ?>
                                    </div>
                                    <div>
                                        <?php if ($m['actif']): ?>
                                            <span class="badge-status actif">Active</span>
                                        <?php else: ?>
                                            <span class="badge-status inactif">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="machine-actions">
                                    <a class="btn-link btn-edit" href="machines.php?edit=<?= $m['id'] ?>">✏️ Éditer</a>
                                    <?php if ($m['actif']): ?>
                                        <a class="btn-link btn-toggle-off" href="machines.php?toggle=<?= $m['id'] ?>" onclick="return confirm('Désactiver cette machine ?');">⏸️ Désactiver</a>
                                    <?php else: ?>
                                        <a class="btn-link btn-toggle-on" href="machines.php?toggle=<?= $m['id'] ?>">▶️ Activer</a>
                                    <?php endif; ?>
                                    <a class="btn-link btn-delete" href="machines.php?delete=<?= $m['id'] ?>" onclick="return confirm('Supprimer cette machine ?');">🗑️ Supprimer</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section 2 : Machines propriétaires -->
            <div class="section-separator">
                <div class="section-header">
                    <div class="section-icon">👤</div>
                    <h2>Machines propriétaires</h2>
                    <span class="section-badge"><?= count($machinesMember) ?> machine(s)</span>
                </div>
                <?php if (empty($machinesMember)): ?>
                    <div class="empty-state">
                        <p>Aucune machine déclarée par des membres.</p>
                    </div>
                <?php else: ?>
                    <div class="machine-grid members-grid">
                        <?php foreach ($machinesMember as $m): $photo = gestnav_machine_photo_url((int)$m['id']); $mid=(int)$m['id']; $owners=$ownersByMachine[$mid] ?? []; ?>
                            <div class="machine-card <?= $m['actif'] ? '' : 'inactive' ?>">
                                <img class="machine-img" src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($m['nom']) ?>" loading="lazy" width="640" height="360">
                                <div class="machine-body">
                                    <div class="machine-name"><?= htmlspecialchars($m['nom']) ?></div>
                                    <div class="machine-meta">
                                        <?= htmlspecialchars($m['immatriculation']) ?> — <?= htmlspecialchars($m['type']) ?>
                                    </div>
                                    <div style="display:flex; gap:.35rem; flex-wrap:wrap; margin-top:.25rem;">
                                        <span class="badge-pill" style="background:rgba(156,39,176,0.10);color:#6a1b9a;border:1px solid rgba(156,39,176,0.25);">Machine membre</span>
                                        <?php if (!empty($owners)): ?>
                                            <span class="badge-pill" style="background:#f2f6fc;color:#004b8d;">Propriétaire<?= count($owners)>1?'s':'' ?>: <?= htmlspecialchars(implode(', ', $owners)) ?></span>
                                        <?php endif; ?>
                                        <?php if ($m['actif']): ?>
                                            <span class="badge-status actif">Active</span>
                                        <?php else: ?>
                                            <span class="badge-status inactif">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="machine-actions">
                                    <a class="btn-link btn-edit" href="machines.php?edit=<?= $m['id'] ?>">✏️ Éditer</a>
                                    <?php if ($m['actif']): ?>
                                        <a class="btn-link btn-toggle-off" href="machines.php?toggle=<?= $m['id'] ?>" onclick="return confirm('Désactiver cette machine ?');">⏸️ Désactiver</a>
                                    <?php else: ?>
                                        <a class="btn-link btn-toggle-on" href="machines.php?toggle=<?= $m['id'] ?>">▶️ Activer</a>
                                    <?php endif; ?>
                                    <a class="btn-link btn-delete" href="machines.php?delete=<?= $m['id'] ?>" onclick="return confirm('Supprimer cette machine ?');">🗑️ Supprimer</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Afficher type et taille du fichier sélectionné
(function(){
    var input = document.getElementById('photo');
    var info = document.getElementById('photoInfo');
    if (!input || !info) return;
    input.addEventListener('change', function(){
        if (this.files && this.files[0]) {
            var f = this.files[0];
            var mb = (f.size/1024/1024).toFixed(2);
            info.textContent = 'Fichier: ' + (f.type || 'inconnu') + ' — ' + mb + ' Mo';
            if (f.size > (5*1024*1024)) {
                info.style.color = '#b02525';
                info.textContent += ' (dépassé: max 5 Mo)';
            } else {
                info.style.color = '#666';
            }
        } else {
            info.textContent = '';
        }
    });
})();

// Spinner léger sur submit
(function(){
    var form = document.getElementById('ajout');
    var btn = document.getElementById('submitBtn');
    if (!form || !btn) return;
    form.addEventListener('submit', function(){
        btn.disabled = true;
        var original = btn.textContent;
        btn.dataset.original = original;
        btn.textContent = 'Envoi en cours…';
        btn.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>');
    });
})();
</script>

<?php require 'footer.php'; ?>
