<?php
require 'header.php';
require_login();
require_admin();
require_once 'utils/activity_log.php';
// Charger l'événement par son ID
$evenement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($evenement_id <= 0) {
    $error = "Événement non spécifié.";
} else {
    try {
        $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
        $stmt->execute([$evenement_id]);
        $evt = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$evt) {
            $error = "Événement introuvable.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}
// Traiter la mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $titre = $_POST['titre'] ?? '';
    $description = $_POST['description'] ?? '';
    $type = $_POST['type'] ?? 'reunion';
    $date_evenement = $_POST['date_evenement'] ?? '';
    $date_fin = $_POST['date_fin'] ?? '';
    $date_limite = $_POST['date_limite'] ?? '';
    $lieu = $_POST['lieu'] ?? '';
    $adresse = $_POST['adresse'] ?? '';
    $statut = $_POST['statut'] ?? 'prévu';
    
    // Vérifier si c'est un événement multi-jours
    $is_multi_day = 0;
    $date_fin_param = null;
    if (!empty($date_fin) && $date_fin > $date_evenement) {
        $is_multi_day = 1;
        $date_fin_param = $date_fin;
    }

    if (!$titre || !$date_evenement || !$lieu) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        try {
            // Vérifier si la colonne cover_filename existe
            $hasCoverColumn = false;
            try {
                $colCheck = $pdo->query("SHOW COLUMNS FROM evenements LIKE 'cover_filename'");
                if ($colCheck && $colCheck->fetch()) { $hasCoverColumn = true; }
            } catch (Throwable $e) { /* ignore */ }

            // Traiter un upload éventuel
            $new_cover_filename = '';
            if ($hasCoverColumn && !empty($_FILES['cover']['name']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['cover']['tmp_name'];
                $name = basename($_FILES['cover']['name']);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $dir = __DIR__ . '/uploads/events';
                    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                    $safe = 'event_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (@move_uploaded_file($tmp, $dir . '/' . $safe)) {
                        $new_cover_filename = $safe;
                    }
                }
            }

            // Normaliser la date limite en NULL si vide
            $date_limite_param = ($date_limite === '') ? null : $date_limite;

            if ($hasCoverColumn && $new_cover_filename) {
                $upd = $pdo->prepare("UPDATE evenements SET titre = ?, description = ?, type = ?, date_evenement = ?, date_fin = ?, is_multi_day = ?, date_limite_inscription = ?, lieu = ?, adresse = ?, statut = ?, cover_filename = ? WHERE id = ?");
                $upd->execute([$titre, $description, $type, $date_evenement, $date_fin_param, $is_multi_day, $date_limite_param, $lieu, $adresse, $statut, $new_cover_filename, $evenement_id]);
            } else {
                $upd = $pdo->prepare("UPDATE evenements SET titre = ?, description = ?, type = ?, date_evenement = ?, date_fin = ?, is_multi_day = ?, date_limite_inscription = ?, lieu = ?, adresse = ?, statut = ? WHERE id = ?");
                $upd->execute([$titre, $description, $type, $date_evenement, $date_fin_param, $is_multi_day, $date_limite_param, $lieu, $adresse, $statut, $evenement_id]);
            }
            $success = "Événement mis à jour avec succès !";

            // Log opération (édition d'événement par admin)
            gn_log_current_user_operation($pdo, 'event_update_admin', 'Événement modifié');

            // Recharger les données
            $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
            $stmt->execute([$evenement_id]);
            $evt = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

// Traiter la suppression de l'illustration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_cover'])) {
    try {
        // Vérifier la colonne
        $hasCoverColumn = false;
        try {
            $colCheck = $pdo->query("SHOW COLUMNS FROM evenements LIKE 'cover_filename'");
            if ($colCheck && $colCheck->fetch()) { $hasCoverColumn = true; }
        } catch (Throwable $e) { /* ignore */ }
        if ($hasCoverColumn) {
            // Supprimer le fichier si présent
            if (!empty($evt['cover_filename'])) {
                $path = __DIR__ . '/uploads/events/' . $evt['cover_filename'];
                if (is_file($path)) { @unlink($path); }
            }
            // Mettre à jour la base
            $upd = $pdo->prepare("UPDATE evenements SET cover_filename = NULL WHERE id = ?");
            $upd->execute([$evenement_id]);
            $success = "Illustration supprimée.";
            // Log opération (suppression de l'illustration par admin)
            gn_log_current_user_operation($pdo, 'event_cover_delete', 'Illustration supprimée');
            // Recharger les données
            $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
            $stmt->execute([$evenement_id]);
            $evt = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}
            
?>

<div class="container mt-4">
    <a href="evenements_admin.php" class="btn btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
    
    <h1 class="mb-4">✏️ Éditer l'événement</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="gn-card">
        <div class="gn-card-header">
            <h3 class="gn-card-title"><?= htmlspecialchars($evt['titre']) ?></h3>
        </div>
        
        <form method="POST" enctype="multipart/form-data" style="padding: 1.5rem;">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Titre *</label>
                    <input type="text" name="titre" class="form-control" value="<?= htmlspecialchars($evt['titre']) ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Type *</label>
                    <select name="type" class="form-select" required>
                        <option value="reunion" <?= $evt['type'] === 'reunion' ? 'selected' : '' ?>>Réunion</option>
                        <option value="assemblee" <?= $evt['type'] === 'assemblee' ? 'selected' : '' ?>>Assemblée générale</option>
                        <option value="formation" <?= $evt['type'] === 'formation' ? 'selected' : '' ?>>Formation</option>
                        <option value="social" <?= $evt['type'] === 'social' ? 'selected' : '' ?>>Événement social</option>
                        <option value="autre" <?= $evt['type'] === 'autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date et heure de début *</label>
                    <input type="datetime-local" name="date_evenement" class="form-control" 
                           value="<?= date('Y-m-d\TH:i', strtotime($evt['date_evenement'])) ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date et heure de fin</label>
                    <input type="datetime-local" name="date_fin" class="form-control" 
                           value="<?= !empty($evt['date_fin']) ? date('Y-m-d\TH:i', strtotime($evt['date_fin'])) : '' ?>">
                    <small class="form-text text-muted">Pour les événements sur plusieurs jours</small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date limite d'inscription</label>
                    <input type="datetime-local" name="date_limite" class="form-control" 
                           value="<?= $evt['date_limite_inscription'] ? date('Y-m-d\TH:i', strtotime($evt['date_limite_inscription'])) : '' ?>">
                    <small class="form-text text-muted">Au-delà de cette date, l'inscription ne sera plus possible</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Statut *</label>
                    <select name="statut" class="form-select" required>
                        <option value="prévu" <?= $evt['statut'] === 'prévu' ? 'selected' : '' ?>>Prévu</option>
                        <option value="en_cours" <?= $evt['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                        <option value="terminé" <?= $evt['statut'] === 'terminé' ? 'selected' : '' ?>>Terminé</option>
                        <option value="annulé" <?= $evt['statut'] === 'annulé' ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Lieu *</label>
                    <input type="text" name="lieu" class="form-control" value="<?= htmlspecialchars($evt['lieu']) ?>" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($evt['description']) ?></textarea>
            </div>
                <div class="mb-3">
                    <label class="form-label">Image de couverture</label>
                    <?php if (!empty($evt['cover_filename'])): ?>
                        <div class="mb-2">
                            <img src="uploads/events/<?= htmlspecialchars($evt['cover_filename']) ?>" alt="" style="width:200px;height:auto;object-fit:cover;border-radius:.5rem;border:1px solid #e5e7eb;">
                        </div>
                        <div class="mb-2">
                            <button type="submit" name="delete_cover" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i> Supprimer l'illustration
                            </button>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="cover" class="form-control" accept="image/*">
                    <div class="form-text">Formats: jpg, png, webp. L'ancienne image sera remplacée.</div>
                </div>
            
            <div class="mb-3">
                <label class="form-label">Adresse complète</label>
                <textarea name="adresse" class="form-control" rows="2"><?= htmlspecialchars($evt['adresse']) ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <button type="submit" name="update" class="btn btn-primary w-100">
                        <i class="bi bi-check-circle"></i> Mettre à jour
                    </button>
                </div>
                <div class="col-md-6">
                    <a href="evenement_detail.php?id=<?= $evenement_id ?>" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-eye"></i> Voir les inscriptions
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require 'footer.php'; ?>
