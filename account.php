<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

// Récupérer l'ID utilisateur depuis la session
$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    header('Location: login.php');
    exit;
}

// Vérifier quelles colonnes existent
$colsStmt = $pdo->query('SHOW COLUMNS FROM users');
$existingCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
$hasEmportPassager = in_array('emport_passager', $existingCols);
$hasQualifRadioIfr = in_array('qualification_radio_ifr', $existingCols);

// Si les colonnes n'existent pas, les créer
if (!$hasEmportPassager || !$hasQualifRadioIfr) {
    try {
        if (!$hasEmportPassager) {
            $pdo->exec("ALTER TABLE users ADD COLUMN emport_passager TINYINT(1) DEFAULT 0 AFTER qualification");
            error_log("Colonne emport_passager créée");
        }
        if (!$hasQualifRadioIfr) {
            $pdo->exec("ALTER TABLE users ADD COLUMN qualification_radio_ifr TINYINT(1) DEFAULT 0 AFTER emport_passager");
            error_log("Colonne qualification_radio_ifr créée");
        }
    } catch (Exception $e) {
        error_log("Erreur création colonnes: " . $e->getMessage());
    }
}

// Récupérer les machines lâchées de l'utilisateur
$userMachines = [];
$machinesSaved = false;
try {
    $stmt = $pdo->prepare("SELECT machine_id FROM user_machines WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $userMachines = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
} catch (Exception $e) {
    error_log("Erreur récupération machines utilisateur: " . $e->getMessage());
}

// Traitement POST unifié
$flash = null;
$machinesError = null;

// Traiter les machines lâchées
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Traiter les machines lâchées
    $selectedMachines = $_POST['machines'] ?? [];
    // Convertir en entiers et filtrer
    $selectedMachines = array_map('intval', array_filter($selectedMachines));
    
    $machinesSaved = false;
    try {
        // Supprimer toutes les anciennes sélections
        $deleteStmt = $pdo->prepare("DELETE FROM user_machines WHERE user_id = ?");
        $deleteStmt->execute([$user_id]);
        
        // Ajouter les nouvelles
        if (!empty($selectedMachines)) {
            $stmt = $pdo->prepare("INSERT INTO user_machines (user_id, machine_id) VALUES (?, ?)");
            foreach ($selectedMachines as $machineId) {
                $stmt->execute([$user_id, $machineId]);
            }
        }
        
        // Recharger les machines après modification
        $stmt = $pdo->prepare("SELECT machine_id FROM user_machines WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $userMachines = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
        $machinesSaved = true;
    } catch (Exception $e) {
        $machinesSaved = false;
        $machinesError = $e->getMessage();
    }
}

// Découverte des colonnes pour s'adapter au schéma
$usersColumns = [];
$hasPasswordHash = false;
try {
  $colsStmt = $pdo->query('SHOW COLUMNS FROM users');
  $usersColumns = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
  $hasPasswordHash = in_array('password_hash', $usersColumns, true);
} catch (Throwable $e) {}

// Charger les infos actuelles
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: logout.php'); exit; }

// Définir photoPath dès le départ
$photoPath = $user['photo_path'] ?? null;

// Initialiser les variables utilisateur pour le formulaire
$nom = $user['nom'] ?? '';
$prenom = $user['prenom'] ?? '';
$email = $user['email'] ?? '';
$telephone = $user['telephone'] ?? '';
$qualification = $user['qualification'] ?? '';
$emport_passager = (int)($user['emport_passager'] ?? 0);
$qualification_radio_ifr = (int)($user['qualification_radio_ifr'] ?? 0);

// Traitement POST (suite du bloc unifié)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? $user['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? $user['prenom'] ?? '');
    $email = trim($_POST['email'] ?? $user['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? $user['telephone'] ?? '');
    $qualification = trim($_POST['qualification'] ?? $user['qualification'] ?? '');
    $emport_passager = isset($_POST['emport_passager']) ? 1 : 0;
    $qualification_radio_ifr = isset($_POST['qualification_radio_ifr']) ? 1 : 0;

    // Traitement de l'upload photo
    if (!empty($_FILES['photo']['name'])) {
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        if ($_FILES['photo']['size'] > $maxFileSize) {
            $flash = ['type' => 'error', 'text' => 'Photo trop volumineuse (max 5MB).'];
        } else {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($_FILES['photo']['type'], $allowedMimes)) {
                $flash = ['type' => 'error', 'text' => 'Format non autorisé. JPG, PNG, GIF ou WebP acceptés.'];
            } else {
                $uploadsDir = __DIR__ . '/uploads/members';
                if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0755, true);
                
                $ext = match($_FILES['photo']['type']) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                    default => 'jpg'
                };
                
                $photoPath = "uploads/members/member_{$user_id}.{$ext}";
                $photoFullPath = __DIR__ . '/' . $photoPath;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $photoFullPath)) {
                    $pdo->prepare('UPDATE users SET photo_path=? WHERE id=?')->execute([$photoPath, $user_id]);
                    $flash = ['type' => 'success', 'text' => 'Photo mise à jour.'];
                } else {
                    $flash = ['type' => 'error', 'text' => 'Erreur lors de l\'upload de la photo.'];
                }
            }
        }
    }

    // Mise à jour des infos de base + annuaire
    if (!$flash) {
        try {
            $updateFields = ['nom=?', 'prenom=?', 'email=?', 'telephone=?', 'qualification=?'];
            $updateParams = [$nom, $prenom, $email, $telephone, $qualification];
            
            // Ajouter les champs optionnels s'ils existent
            if ($hasEmportPassager) {
                $updateFields[] = 'emport_passager=?';
                $updateParams[] = $emport_passager;
            }
            if ($hasQualifRadioIfr) {
                $updateFields[] = 'qualification_radio_ifr=?';
                $updateParams[] = $qualification_radio_ifr;
            }
            
            $updateParams[] = $user_id;
            
            $updateSql = 'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id=?';
            error_log("DEBUG - SQL: $updateSql");
            error_log("DEBUG - Params: " . json_encode($updateParams));
            
            $stmt = $pdo->prepare($updateSql);
            $result = $stmt->execute($updateParams);
            error_log("DEBUG - Execute result: " . ($result ? 'true' : 'false'));
            error_log("DEBUG - Rows affected: " . $stmt->rowCount());
        } catch (Exception $e) {
            error_log("Erreur UPDATE users: " . $e->getMessage());
            $flash = ['type' => 'error', 'text' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()];
        }
    }

    // Changement de mot de passe (optionnel)
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($hasPasswordHash && ($current !== '' || $new !== '' || $confirm !== '')) {
        if ($new === '' || $confirm === '' || $new !== $confirm) {
            $flash = ['type' => 'error', 'text' => 'La confirmation du mot de passe ne correspond pas.'];
        } else {
            // Vérifier le mot de passe actuel si disponible
            $ok = true;
            if (!empty($user['password_hash'])) {
                $ok = password_verify($current, $user['password_hash']);
            }
            if (!$ok) {
                $flash = ['type' => 'error', 'text' => 'Mot de passe actuel incorrect.'];
            } else {
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$newHash, $user_id]);
                $flash = ['type' => 'success', 'text' => 'Mot de passe mis à jour.'];
            }
        }
    }

    if (!$flash) {
        if ($machinesSaved) {
            $flash = ['type' => 'success', 'text' => 'Machines et profil mis à jour.'];
        } else {
            $flash = ['type' => 'success', 'text' => 'Profil mis à jour.'];
        }
    }

    // Recharger les données après mise à jour
    // Aussi recharger les infos des colonnes au cas où elles ont été créées
    $colsStmt = $pdo->query('SHOW COLUMNS FROM users');
    $existingCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
    $hasEmportPassager = in_array('emport_passager', $existingCols);
    $hasQualifRadioIfr = in_array('qualification_radio_ifr', $existingCols);
    
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $photoPath = $user['photo_path'] ?? null;  // Mettre à jour photoPath aussi
    $nom = $user['nom'] ?? '';
    $prenom = $user['prenom'] ?? '';
    $email = $user['email'] ?? '';
    $telephone = $user['telephone'] ?? '';
    $qualification = $user['qualification'] ?? '';
    $emport_passager = $hasEmportPassager ? (int)($user['emport_passager'] ?? 0) : 0;
    $qualification_radio_ifr = $hasQualifRadioIfr ? (int)($user['qualification_radio_ifr'] ?? 0) : 0;
}

require 'header.php';
?>

<style>
.account-page {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.account-header {
    background: linear-gradient(135deg, #004b8d 0%, #0066c0 100%);
    color: #ffffff;
    padding: 3rem 2rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
    text-align: center;
    box-shadow: 0 4px 16px rgba(0, 75, 141, 0.2);
}

.account-header h1 {
    margin: 0 0 0.5rem;
    font-size: 2rem;
    font-weight: 700;
}

.account-header p {
    margin: 0;
    opacity: 0.95;
    font-size: 0.95rem;
}

.flash {
    margin: 1.5rem 0;
    padding: 1rem;
    border-radius: 0.75rem;
    font-weight: 500;
}

.flash.success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

.flash.error {
    background: #fee2e2;
    color: #7f1d1d;
    border-left: 4px solid #ef4444;
}

.account-container {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 2rem;
}

.profile-sidebar {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    height: fit-content;
}

.profile-photo-container {
    text-align: center;
    margin-bottom: 1.5rem;
}

.profile-photo {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    border: 4px solid #e5e7eb;
    object-fit: cover;
    display: inline-block;
    margin-bottom: 1rem;
}

.profile-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 0.25rem;
}

.profile-qualification {
    display: inline-block;
    background: linear-gradient(135deg, #004b8d 0%, #0066c0 100%);
    color: #ffffff;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.upload-button {
    display: inline-block;
    background: linear-gradient(135deg, #004b8d 0%, #0066c0 100%);
    color: #ffffff;
    padding: 0.6rem 1.2rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    width: 100%;
    text-align: center;
    margin-bottom: 0.5rem;
}

.upload-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 75, 141, 0.3);
    text-decoration: none;
    color: #ffffff;
}

.crop-button {
    display: inline-block;
    background: #f3f4f6;
    color: #1a1a1a;
    padding: 0.6rem 1.2rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
    cursor: pointer;
    width: 100%;
    text-align: center;
}

.crop-button:hover {
    background: #e5e7eb;
    text-decoration: none;
    color: #1a1a1a;
}

.account-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
}

.account-card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #004b8d;
    margin: 0 0 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.form-grid.full {
    grid-template-columns: 1fr;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-group input,
.form-group select {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.95rem;
    font-family: inherit;
    transition: all 0.2s ease;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #0066c0;
    box-shadow: 0 0 0 3px rgba(0, 102, 192, 0.1);
}

.form-help {
    color: #6b7280;
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.button-group {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    justify-content: flex-end;
}

.btn-save {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
    padding: 0.75rem 2rem;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.95rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    color: #ffffff;
}

.btn-cancel {
    background: #f3f4f6;
    color: #1a1a1a;
    padding: 0.75rem 2rem;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.95rem;
    border: 1px solid #d1d5db;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-cancel:hover {
    background: #e5e7eb;
    text-decoration: none;
    color: #1a1a1a;
}

@media (max-width: 768px) {
    .account-container {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .account-header {
        padding: 2rem 1.5rem;
    }
    
    .account-header h1 {
        font-size: 1.5rem;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .button-group button,
    .button-group a {
        width: 100%;
    }
}
</style>

<div class="account-page">
    <div class="account-header">
        <h1>👤 Mon Compte</h1>
        <p>Gérez vos informations personnelles et votre mot de passe</p>
    </div>

    <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] ?>"><?= htmlspecialchars($flash['text']) ?></div>
    <?php endif; ?>

    <div class="account-container">
        <!-- Sidebar Profil -->
        <div class="profile-sidebar">
            <div class="profile-photo-container">
                <?php 
                    $photoPath = $user['photo_path'] ?? null;
                    $offsetX = 0;
                    $offsetY = 0;
                    if (!empty($user['photo_metadata'])) {
                        $meta = json_decode($user['photo_metadata'], true);
                        $offsetX = $meta['offsetX'] ?? 0;
                        $offsetY = $meta['offsetY'] ?? 0;
                    }
                    if ($photoPath && file_exists(__DIR__ . '/' . $photoPath)) {
                        echo '<div style="width:150px; height:150px; border-radius:50%; overflow:hidden; border:4px solid #e5e7eb; position:relative; background:#f0f0f0; margin:0 auto;">';
                        echo '<img src="' . htmlspecialchars($photoPath) . '" style="position:absolute; width:230px; height:230px; top:50%; left:50%; transform:translate(calc(-50% + ' . $offsetX . 'px), calc(-50% + ' . $offsetY . 'px)); object-fit:cover;">';
                        echo '</div>';
                    } else {
                        echo '<img src="/assets/img/avatar-placeholder.svg" class="profile-photo">';
                    }
                ?>
            </div>
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div class="profile-name"><?= htmlspecialchars($user['prenom'] ?? '') ?> <?= htmlspecialchars($user['nom'] ?? '') ?></div>
                <?php if (!empty($user['qualification'])): ?>
                    <div class="profile-qualification"><?= htmlspecialchars($user['qualification']) ?></div>
                <?php endif; ?>
                <?php if (!empty($user['email'])): ?>
                    <div style="font-size: 0.9rem; color: #6b7280; margin-top: 0.5rem;"><?= htmlspecialchars($user['email']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulaire -->
        <div>
            <!-- Section Informations Personnelles -->
            <div class="account-card">
                <h2 class="account-card-title">📋 Informations Personnelles</h2>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Prénom</label>
                            <input type="text" name="prenom" required value="<?= htmlspecialchars($prenom) ?>">
                        </div>
                        <div class="form-group">
                            <label>Nom</label>
                            <input type="text" name="nom" required value="<?= htmlspecialchars($nom) ?>">
                        </div>
                    </div>

                    <div class="form-grid full">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" name="telephone" placeholder="+33 6 12 34 56 78" value="<?= htmlspecialchars($telephone) ?>">
                        </div>
                        <div class="form-group">
                            <label>Qualification</label>
                            <select name="qualification">
                                <option value="">— Non renseigné —</option>
                                <option value="Pilote" <?= $qualification === 'Pilote' ? 'selected' : '' ?>>Pilote</option>
                                <option value="Élève-Pilote" <?= $qualification === 'Élève-Pilote' ? 'selected' : '' ?>>Élève-Pilote</option>
                            </select>
                        </div>
                    </div>

                    <!-- Section Qualifications Pilote -->
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                        <h3 style="font-size: 0.95rem; font-weight: 600; color: #1a1a1a; margin-bottom: 1rem;">✈️ Qualifications Pilote</h3>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <input type="checkbox" id="emport_passager" name="emport_passager" value="1" <?= $emport_passager ? 'checked' : '' ?> style="cursor: pointer; width: 18px; height: 18px;">
                                <label for="emport_passager" style="cursor: pointer; margin: 0; font-weight: 500; color: #1a1a1a;">Emport Passager</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <input type="checkbox" id="qualification_radio_ifr" name="qualification_radio_ifr" value="1" <?= $qualification_radio_ifr ? 'checked' : '' ?> style="cursor: pointer; width: 18px; height: 18px;">
                                <label for="qualification_radio_ifr" style="cursor: pointer; margin: 0; font-weight: 500; color: #1a1a1a;">Qualification Radio IFR</label>
                            </div>
                        </div>
                        <p class="form-help" style="margin-top: 0.75rem;">Cochez les qualifications que vous possédez</p>
                    </div>

                    <!-- Section Machines Lâchées -->
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                        <h3 style="font-size: 0.95rem; font-weight: 600; color: #1a1a1a; margin-bottom: 1rem;">✈️ Machines lâchées</h3>
                        <?php 
                        // Récupérer les machines du CLUB uniquement (pas les propriétaires)
                        $machines = [];
                        try {
                            // D'abord, vérifier si la colonne 'source' existe
                            $colsStmt = $pdo->query('SHOW COLUMNS FROM machines');
                            $machineCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
                            $hasSourceCol = in_array('source', $machineCols);
                            
                            if ($hasSourceCol) {
                                // Si colonne source existe, filtrer par source='club'
                                $stmt = $pdo->query("SELECT id, nom, immatriculation FROM machines WHERE actif = 1 AND (source = 'club' OR source IS NULL OR source = '') ORDER BY nom");
                            } else {
                                // Sinon, récupérer les machines qui n'ont pas de propriétaire
                                $stmt = $pdo->query("
                                    SELECT m.id, m.nom, m.immatriculation FROM machines m
                                    LEFT JOIN machines_owners mo ON m.id = mo.machine_id
                                    WHERE m.actif = 1 AND mo.machine_id IS NULL
                                    ORDER BY m.nom
                                ");
                            }
                            $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            error_log("Erreur récupération machines: " . $e->getMessage());
                        }
                        ?>
                        <?php if (empty($machines)): ?>
                            <p style="color: #999; font-size: 0.9rem;">Aucune machine disponible</p>
                        <?php else: ?>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                                <?php foreach ($machines as $machine): ?>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <input type="checkbox" id="machine_<?= $machine['id'] ?>" name="machines[]" value="<?= $machine['id'] ?>" <?= in_array((int)$machine['id'], $userMachines) ? 'checked' : '' ?> style="cursor: pointer; width: 18px; height: 18px;">
                                        <label for="machine_<?= $machine['id'] ?>" style="cursor: pointer; margin: 0; font-weight: 500; color: #1a1a1a; font-size: 0.9rem;">
                                            <?= htmlspecialchars($machine['nom']) ?> (<?= htmlspecialchars($machine['immatriculation']) ?>)
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="form-help" style="margin-top: 0.75rem;">Cochez les machines sur lesquelles vous êtes lâché</p>
                        <?php endif; ?>
                    </div>

                    <!-- Section Photo de Profil -->
                    <div style="margin-top: 2rem;">
                        <h2 class="account-card-title">📷 Photo de Profil</h2>
                        <div class="form-group">
                            <label>Mettre à jour votre photo</label>
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-help">JPG, PNG, GIF ou WebP (max 5MB)</div>
                        </div>
                        <?php if ($photoPath && file_exists(__DIR__ . '/' . $photoPath)): ?>
                            <a href="crop_photo.php" class="crop-button">
                                <i class="bi bi-arrow-resize"></i> Centrer la photo
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Section Mot de Passe -->
                    <div style="margin-top: 2rem;">
                        <h2 class="account-card-title">🔐 Mot de Passe</h2>
                        <div class="form-grid full">
                            <div class="form-group">
                                <label>Mot de passe actuel</label>
                                <input type="password" name="current_password" autocomplete="current-password">
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nouveau mot de passe</label>
                                <input type="password" name="new_password" autocomplete="new-password">
                            </div>
                            <div class="form-group">
                                <label>Confirmer le mot de passe</label>
                                <input type="password" name="confirm_password" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="form-help">Laissez vide pour ne pas modifier votre mot de passe</div>
                    </div>

                    <!-- Boutons d'Action -->
                    <div class="button-group">
                        <a href="index.php" class="btn-cancel">Annuler</a>
                        <button type="submit" class="btn-save">
                            <i class="bi bi-check-circle"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
