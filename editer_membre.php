<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

// Récupérer les données dynamiquement
$colsStmt = $pdo->query('SHOW COLUMNS FROM users');
$existingCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
$hasEmportPassager = in_array('emport_passager', $existingCols);
$hasQualifRadioIfr = in_array('qualification_radio_ifr', $existingCols);
$hasTypeMembre = in_array('type_membre', $existingCols);

// DEBUG
if (!$hasTypeMembre) {
    // Essayer de créer la colonne si elle n'existe pas
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN type_membre VARCHAR(50) DEFAULT 'club' AFTER actif");
        $hasTypeMembre = true;
    } catch (Exception $e) {
        // Colonne existe peut-être déjà, réessayer la détection
        $colsStmt = $pdo->query('SHOW COLUMNS FROM users');
        $existingCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        $hasTypeMembre = in_array('type_membre', $existingCols);
    }
}

// Récupérer l'ID du membre
$memberId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$memberId) {
    header('Location: membres.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    header('Location: membres.php');
    exit;
}

// Récupérer les machines lâchées du membre
$userMachines = [];
try {
    $stmt = $pdo->prepare("SELECT machine_id FROM user_machines WHERE user_id = ?");
    $stmt->execute([$memberId]);
    $userMachines = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
} catch (Exception $e) {
    error_log("Erreur récupération machines utilisateur: " . $e->getMessage());
}

// Gestion des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!is_admin()) {
        header('Location: acces_refuse.php?message=' . urlencode('La modification des membres est réservée aux administrateurs') . '&redirect=membres.php');
        exit;
    }
    
    $action = $_POST['action'];
    
    try {
        // Traiter les machines lâchées
        if ($action === 'update_machines') {
            $selectedMachines = $_POST['machines'] ?? [];
            $selectedMachines = array_map('intval', array_filter($selectedMachines));
            
            // Supprimer toutes les anciennes sélections
            $deleteStmt = $pdo->prepare("DELETE FROM user_machines WHERE user_id = ?");
            $deleteStmt->execute([$memberId]);
            
            // Ajouter les nouvelles
            if (!empty($selectedMachines)) {
                $stmt = $pdo->prepare("INSERT INTO user_machines (user_id, machine_id) VALUES (?, ?)");
                foreach ($selectedMachines as $machineId) {
                    $stmt->execute([$memberId, $machineId]);
                }
            }
            
            // Recharger les machines après modification
            $stmt = $pdo->prepare("SELECT machine_id FROM user_machines WHERE user_id = ?");
            $stmt->execute([$memberId]);
            $userMachines = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
            
            $_SESSION['success'] = 'Machines lâchées mises à jour avec succès';
            header('Location: editer_membre.php?id=' . $memberId);
            exit;
        }
        
        if ($action === 'update_profile') {
            $nom = trim($_POST['nom']);
            $prenom = trim($_POST['prenom']);
            $email = trim($_POST['email']);
            $telephone = trim($_POST['telephone']);
            $qualification = trim($_POST['qualification']);
            
            if (!$nom || !$prenom) {
                $_SESSION['error'] = 'Le nom et prénom sont obligatoires';
            } else {
                $updates = ['nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'telephone' => $telephone, 'qualification' => $qualification];
                
                if ($hasEmportPassager) $updates['emport_passager'] = isset($_POST['emport_passager']) ? 1 : 0;
                if ($hasQualifRadioIfr) $updates['qualification_radio_ifr'] = isset($_POST['qualification_radio_ifr']) ? 1 : 0;
                if ($hasTypeMembre && isset($_POST['type_membre'])) {
                    $type = in_array($_POST['type_membre'], ['club', 'invite']) ? $_POST['type_membre'] : 'club';
                    $updates['type_membre'] = $type;
                }
                
                // Gestion de la photo
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['photo'];
                    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    
                    if (array_key_exists($ext, $allowed) && $file['size'] <= 5000000) {
                        @mkdir('uploads', 0755, true);
                        $filename = 'member_' . $memberId . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($file['tmp_name'], 'uploads/' . $filename)) {
                            // Vérifier quelle colonne existe pour la photo
                            if (in_array('photo_path', $existingCols)) {
                                $updates['photo_path'] = 'uploads/' . $filename;
                            } elseif (in_array('photo', $existingCols)) {
                                $updates['photo'] = $filename;
                            }
                        }
                    }
                }
                
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($updates)));
                $values = array_values($updates);
                $values[] = $memberId;
                
                $pdo->prepare("UPDATE users SET $set WHERE id = ?")->execute($values);
                
                // Rafraîchir les données
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$memberId]);
                $member = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $_SESSION['success'] = 'Profil mis à jour avec succès';
                header('Location: editer_membre.php?id=' . $memberId);
                exit;
            }
        } elseif ($action === 'update_type') {
            if ($hasTypeMembre) {
                $type = in_array($_POST['type_membre'], ['club', 'invite']) ? $_POST['type_membre'] : 'club';
                $pdo->prepare("UPDATE users SET type_membre = ? WHERE id = ?")->execute([$type, $memberId]);
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$memberId]);
                $member = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $_SESSION['success'] = 'Type de membre mis à jour';
                header('Location: editer_membre.php?id=' . $memberId);
                exit;
            }
        } elseif ($action === 'toggle_actif') {
            $new = $member['actif'] ? 0 : 1;
            $pdo->prepare("UPDATE users SET actif = ? WHERE id = ?")->execute([$new, $memberId]);
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$memberId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $_SESSION['success'] = $new ? 'Membre activé' : 'Membre désactivé';
            header('Location: editer_membre.php?id=' . $memberId);
            exit;
        } elseif ($action === 'update_password') {
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');
            
            if (empty($newPassword)) {
                $_SESSION['error'] = 'Le mot de passe ne peut pas être vide';
            } elseif ($newPassword !== $confirmPassword) {
                $_SESSION['error'] = 'Les mots de passe ne correspondent pas';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hashedPassword, $memberId]);
                $_SESSION['success'] = 'Mot de passe mis à jour avec succès';
            }
            header('Location: editer_membre.php?id=' . $memberId);
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erreur : ' . $e->getMessage();
        header('Location: editer_membre.php?id=' . $memberId);
        exit;
    }
}

require 'header.php';
?>

<style>
.edit-page {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.edit-header {
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

.edit-header h1 {
    font-size: 1.6rem;
    margin: 0;
    letter-spacing: 0.03em;
}

.edit-header-info {
    font-size: 0.95rem;
    opacity: 0.9;
}

.edit-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

.card {
    background: #fff;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border: 1px solid #e8ecf1;
}

.card-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0 0 1.5rem;
    color: #004b8d;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f3f8;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}

.form-group {
    margin-bottom: 0;
}

.form-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 0.4rem;
    color: #333;
}

.form-input, .form-select {
    width: 100%;
    padding: 0.6rem 0.9rem;
    border: 1px solid #d0d7e2;
    border-radius: 0.5rem;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.form-input:focus, .form-select:focus {
    outline: none;
    border-color: #004b8d;
    box-shadow: 0 0 0 3px rgba(0, 75, 141, 0.1);
}

.form-full {
    grid-column: 1 / -1;
}

.form-checkbox {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-top: 0.8rem;
    font-size: 0.9rem;
}

.form-checkbox input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: 0.8rem;
    margin-top: 2rem;
    grid-column: 1 / -1;
}

.btn {
    padding: 0.7rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: #fff;
    flex: 1;
}

.btn-primary:hover {
    filter: brightness(1.1);
    box-shadow: 0 4px 12px rgba(0, 75, 141, 0.3);
}

.btn-secondary {
    background: #f0f3f8;
    color: #666;
    text-decoration: none;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-secondary:hover {
    background: #e7ecf4;
}

.btn-small {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

.photo-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    align-items: start;
}

@media (max-width: 768px) {
    .photo-section {
        grid-template-columns: 1fr;
    }
}

.photo-preview {
    text-align: center;
}

.photo-preview img {
    max-width: 100%;
    max-height: 300px;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.photo-upload {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: #0a8a0a;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.alert-error {
    background: rgba(220, 38, 38, 0.1);
    color: #991b1b;
    border: 1px solid rgba(220, 38, 38, 0.3);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
}

.status-actif {
    background: rgba(16, 185, 129, 0.1);
    color: #0a8a0a;
}

.status-inactif {
    background: rgba(200, 0, 0, 0.1);
    color: #b02525;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
}

.type-club {
    background: rgba(0, 75, 141, 0.1);
    color: #004b8d;
}

.type-invite {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
}

.quick-actions {
    display: flex;
    gap: 0.8rem;
    flex-wrap: wrap;
    padding-top: 1rem;
    border-top: 1px solid #e8ecf1;
}

.action-btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.4rem;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.action-toggle {
    background: rgba(16, 185, 129, 0.1);
    color: #0a8a0a;
}

.action-toggle:hover {
    background: rgba(16, 185, 129, 0.2);
}

.action-crop {
    background: rgba(0, 75, 141, 0.1);
    color: #004b8d;
}

.action-crop:hover {
    background: rgba(0, 75, 141, 0.2);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    padding: 1rem;
    background: #f8fbff;
    border-radius: 0.5rem;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
}

.info-item {
    font-size: 0.9rem;
}

.info-label {
    font-weight: 600;
    color: #004b8d;
    margin-bottom: 0.3rem;
}

.info-value {
    color: #666;
}
</style>

<div class="edit-page">
    <div class="edit-header">
        <div>
            <h1>Édition du profil</h1>
            <div class="edit-header-info"><?= htmlspecialchars($member['prenom'] . ' ' . $member['nom']) ?></div>
        </div>
        <div style="font-size: 2rem;">✏️</div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            ✓ <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            ✕ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="edit-layout">
        <!-- Statut et Actions Rapides -->
        <div class="card">
            <h2 class="card-title">Statut & Actions</h2>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Statut</div>
                    <div class="status-badge <?= $member['actif'] ? 'status-actif' : 'status-inactif' ?>">
                        <?= $member['actif'] ? '✓ Actif' : '✕ Inactif' ?>
                    </div>
                </div>
                
                <?php if ($hasTypeMembre): ?>
                <div class="info-item">
                    <div class="info-label">Type de membre</div>
                    <div class="type-badge <?= ($member['type_membre'] ?? 'club') === 'club' ? 'type-club' : 'type-invite' ?>">
                        <?= ($member['type_membre'] ?? 'club') === 'club' ? '🏢 CLUB' : '👥 INVITE' ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="quick-actions">
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="toggle_actif">
                    <button type="submit" class="action-btn action-toggle">
                        <?= $member['actif'] ? '⏸️ Désactiver' : '▶️ Activer' ?>
                    </button>
                </form>
                
                <?php if (!empty($member['photo_path']) && file_exists($member['photo_path'])): ?>
                    <a href="crop_photo.php?id=<?= $member['id'] ?>&redirect_to=editer_membre.php%3Fid=<?= $member['id'] ?>" class="action-btn action-crop" style="text-decoration: none;">
                        🖼️ Recadrer la photo
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulaire d'édition -->
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            
            <!-- Section 1: Informations Personnelles -->
            <div class="card">
                <h2 class="card-title">Informations personnelles</h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Prénom *</label>
                        <input type="text" name="prenom" class="form-input" value="<?= htmlspecialchars($member['prenom'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nom *</label>
                        <input type="text" name="nom" class="form-input" value="<?= htmlspecialchars($member['nom'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($member['email'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="tel" name="telephone" class="form-input" value="<?= htmlspecialchars($member['telephone'] ?? '') ?>">
                    </div>

                    <div class="form-group form-full">
                        <label class="form-label">Qualification</label>
                        <select name="qualification" class="form-input">
                            <option value="">-- Sélectionner --</option>
                            <option value="Pilote" <?= ($member['qualification'] ?? '') === 'Pilote' ? 'selected' : '' ?>>Pilote</option>
                            <option value="Élève-Pilote" <?= ($member['qualification'] ?? '') === 'Élève-Pilote' ? 'selected' : '' ?>>Élève-Pilote</option>
                            <option value="Instructeur" <?= ($member['qualification'] ?? '') === 'Instructeur' ? 'selected' : '' ?>>Instructeur</option>
                            <option value="Passager" <?= ($member['qualification'] ?? '') === 'Passager' ? 'selected' : '' ?>>Passager</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 2: Catégorie et Qualifications -->
            <div class="card">
                <h2 class="card-title">Catégorie & Qualifications</h2>
                
                <div class="form-grid">
                    <?php if ($hasTypeMembre): ?>
                    <div class="form-group">
                        <label class="form-label">Type de membre</label>
                        <select name="type_membre" class="form-input">
                            <option value="club" <?= ($member['type_membre'] ?? 'club') === 'club' ? 'selected' : '' ?>>🏢 CLUB</option>
                            <option value="invite" <?= ($member['type_membre'] ?? 'club') === 'invite' ? 'selected' : '' ?>>👥 INVITE</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if ($hasEmportPassager): ?>
                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <div class="form-checkbox">
                            <input type="checkbox" id="emport" name="emport_passager" <?= $member['emport_passager'] ? 'checked' : '' ?>>
                            <label for="emport">Emport passager</label>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($hasQualifRadioIfr): ?>
                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <div class="form-checkbox">
                            <input type="checkbox" id="radio" name="qualification_radio_ifr" <?= $member['qualification_radio_ifr'] ? 'checked' : '' ?>>
                            <label for="radio">Qualification Radio IFR</label>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section Photo -->
            <div class="card">
                <h2 class="card-title">Photo de profil</h2>
                
                <div class="photo-section">
                    <div class="photo-preview">
                        <?php 
                        $photoPath = null;
                        
                        // Vérifier photo_path en premier
                        if (!empty($member['photo_path'])) {
                            if (file_exists($member['photo_path'])) {
                                $photoPath = $member['photo_path'];
                            } elseif (file_exists(__DIR__ . '/' . $member['photo_path'])) {
                                $photoPath = $member['photo_path'];
                            }
                        }
                        
                        // Sinon vérifier la colonne photo
                        if (!$photoPath && !empty($member['photo'])) {
                            $possiblePaths = [
                                $member['photo'],
                                'uploads/' . $member['photo'],
                                'uploads/preinscriptions/' . $member['photo']
                            ];
                            foreach ($possiblePaths as $path) {
                                if (file_exists($path)) {
                                    $photoPath = $path;
                                    break;
                                }
                            }
                        }
                        ?>
                        <?php if ($photoPath): ?>
                            <img src="<?= htmlspecialchars($photoPath) ?>" alt="Photo de profil" style="max-width: 200px; border-radius: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <p style="font-size: 0.85rem; color: #666; margin-top: 0.5rem;">Photo actuelle</p>
                        <?php else: ?>
                            <div style="width: 200px; aspect-ratio: 1; background: #f0f3f8; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; color: #999; flex-direction: column; gap: 0.5rem;">
                                <span style="font-size: 3rem;">📷</span>
                                <span>Aucune photo</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="photo-upload">
                        <div>
                            <label class="form-label">Charger une nouvelle photo</label>
                            <input type="file" name="photo" class="form-input" accept="image/*" style="padding: 0.4rem;">
                            <small style="color: #666; font-size: 0.8rem; display: block; margin-top: 0.4rem;">JPG, PNG, GIF - Max 5 MB</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Boutons d'action -->
            <div class="card">
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">💾 Enregistrer les modifications</button>
                    <a href="membres.php" class="btn btn-secondary">← Retour à la liste</a>
                </div>
            </div>
        </form>

        <!-- Section 3: Machines lâchées (formulaire séparé) -->
        <div class="card">
                <h2 class="card-title">✈️ Machines lâchées</h2>
                
                <form method="post">
                    <input type="hidden" name="action" value="update_machines">
                    
                    <?php 
                    // Récupérer les machines du CLUB uniquement
                    $machines = [];
                    try {
                        $colsStmt = $pdo->query('SHOW COLUMNS FROM machines');
                        $machineCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
                        $hasSourceCol = in_array('source', $machineCols);
                        
                        if ($hasSourceCol) {
                            $stmt = $pdo->query("SELECT id, nom, immatriculation FROM machines WHERE actif = 1 AND (source = 'club' OR source IS NULL OR source = '') ORDER BY nom");
                        } else {
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
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; margin-bottom: 1.5rem;">
                            <?php foreach ($machines as $machine): ?>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <input type="checkbox" id="machine_<?= $machine['id'] ?>" name="machines[]" value="<?= $machine['id'] ?>" <?= in_array((int)$machine['id'], $userMachines) ? 'checked' : '' ?> style="cursor: pointer; width: 18px; height: 18px;">
                                    <label for="machine_<?= $machine['id'] ?>" style="cursor: pointer; margin: 0; font-weight: 500; color: #1a1a1a; font-size: 0.9rem;">
                                        <?= htmlspecialchars($machine['nom']) ?> (<?= htmlspecialchars($machine['immatriculation']) ?>)
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">Cochez les machines sur lesquelles le membre est lâché</p>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">💾 Enregistrer les machines</button>
                </form>
            </div>

        <!-- Formulaire de mot de passe (en dehors du formulaire principal) -->
        <div class="card" id="passwordCard">
            <h2 class="card-title">🔐 Modifier le mot de passe</h2>
            
                <div class="photo-section">
                    <div class="photo-preview">
                        <?php 
                        $photoPath = null;
                        $photoUrl = null;
                        
                        // Vérifier photo_path
                        if (!empty($member['photo_path'])) {
                            if (file_exists($member['photo_path'])) {
                                $photoPath = $member['photo_path'];
                                $photoUrl = '/' . ltrim($member['photo_path'], '/');
                            }
                        }
                        
                        // Vérifier photo
                        if (!$photoPath && !empty($member['photo'])) {
                            // Essayer avec le chemin complet
                            if (file_exists($member['photo'])) {
                                $photoPath = $member['photo'];
                                $photoUrl = '/' . ltrim($member['photo'], '/');
                            }
                            // Essayer dans uploads/
                            elseif (file_exists('uploads/' . $member['photo'])) {
                                $photoPath = 'uploads/' . $member['photo'];
                                $photoUrl = '/uploads/' . $member['photo'];
                            }
                        }
                        ?>
                        <?php if ($photoUrl): ?>
                            <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Photo de profil" style="max-width: 200px; border-radius: 0.5rem;">
                            <p style="font-size: 0.85rem; color: #666; margin-top: 0.5rem;">Photo actuelle</p>
                        <?php else: ?>
                            <div style="width: 200px; aspect-ratio: 1; background: #f0f3f8; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; color: #999;">
                                Aucune photo
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group form-full">
                        <label class="form-label">Nouveau mot de passe *</label>
                        <input type="password" id="newPassword" name="new_password" class="form-input" placeholder="Entrez le nouveau mot de passe" required>
                    </div>

                    <div class="form-group form-full">
                        <label class="form-label">Confirmer le mot de passe *</label>
                        <input type="password" id="confirmPassword" name="confirm_password" class="form-input" placeholder="Confirmez le mot de passe" required>
                    </div>
                </div>

                <div class="form-actions" style="gap: 0.8rem;">
                    <button type="submit" class="btn btn-primary" onclick="return validatePasswordForm(event)">🔒 Mettre à jour le mot de passe</button>
                    <button type="button" class="btn btn-secondary" onclick="togglePasswordForm()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePasswordForm() {
    const form = document.getElementById('passwordFormContainer');
    const toggle = document.getElementById('passwordToggle');
    
    if (form.style.display === 'none') {
        form.style.display = 'block';
        toggle.style.display = 'none';
        document.getElementById('newPassword').focus();
    } else {
        form.style.display = 'none';
        toggle.style.display = 'flex';
        document.getElementById('newPassword').value = '';
        document.getElementById('confirmPassword').value = '';
    }
}

function validatePasswordForm(e) {
    const newPassword = document.getElementById('newPassword').value.trim();
    const confirmPassword = document.getElementById('confirmPassword').value.trim();
    
    if (!newPassword) {
        alert('Le mot de passe ne peut pas être vide');
        e.preventDefault();
        return false;
    }
    
    if (newPassword !== confirmPassword) {
        alert('Les mots de passe ne correspondent pas');
        e.preventDefault();
        return false;
    }
    
    return true;
}
</script>

<?php require 'footer.php'; ?>
