<?php
require 'header.php';
require_admin();

$success = false;
$error = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schema'])) {
    try {
        // Ajouter la valeur 'en_attente' à l'enum du statut
        $pdo->exec("ALTER TABLE evenement_inscriptions MODIFY statut ENUM('en_attente', 'confirmée', 'annulée') NOT NULL DEFAULT 'en_attente'");
        $success = true;
        $message = "✓ Schéma mis à jour avec succès ! Le statut 'en_attente' a été ajouté.";
    } catch (Exception $e) {
        $error = true;
        $message = "Erreur : " . $e->getMessage();
    }
}

// Ajout idempotent de la colonne cover_filename à evenements
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cover_column'])) {
    try {
        $exists = false;
        try {
            $col = $pdo->query("SHOW COLUMNS FROM evenements LIKE 'cover_filename'");
            if ($col && $col->fetch()) { $exists = true; }
        } catch (Throwable $e) { /* ignore */ }
        if (!$exists) {
            $pdo->exec("ALTER TABLE evenements ADD COLUMN cover_filename VARCHAR(255) NULL AFTER description");
            $success = true;
            $message = "✓ Colonne cover_filename ajoutée à evenements.";
        } else {
            $success = true;
            $message = "ℹ️ La colonne cover_filename existe déjà, aucune action.";
        }
    } catch (Exception $e) {
        $error = true;
        $message = "Erreur : " . $e->getMessage();
    }
}

// Vérifier l'état actuel du schéma
$schema_info = '';
$cover_info = '';
try {
    $result = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='evenement_inscriptions' AND COLUMN_NAME='statut' AND TABLE_SCHEMA=DATABASE()")->fetch();
    if ($result) {
        $schema_info = $result['COLUMN_TYPE'];
    }
    // Vérifier présence cover_filename
    $col = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='evenements' AND COLUMN_NAME='cover_filename' AND TABLE_SCHEMA=DATABASE()")->fetch();
    $cover_info = $col ? 'présente' : 'absente';
} catch (Exception $e) {
    $schema_info = "Impossible de vérifier le schéma";
    $cover_info = "Impossible de vérifier";
}
?>

<div class="container mt-4">
    <h1 class="mb-4">⚙️ Migration - Schéma des événements</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle"></i> <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="gn-card mb-4">
        <div class="gn-card-header">
            <h3 class="gn-card-title">État du schéma</h3>
        </div>
        <div style="padding: 1.5rem;">
            <p><strong>Colonne statut :</strong></p>
            <code style="background: #f5f5f5; padding: 10px; display: block; border-radius: 4px; font-family: monospace;">
                <?= htmlspecialchars($schema_info) ?>
            </code>
            <p class="mt-3"><strong>evenements.cover_filename :</strong> <span class="badge <?= $cover_info==='présente' ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($cover_info) ?></span></p>
        </div>
    </div>
    
    <div class="gn-card mb-4">
        <div class="gn-card-header">
            <h3 class="gn-card-title">Ajout du statut "en attente"</h3>
        </div>
        <div style="padding: 1.5rem;">
            <p>Cette migration ajoute le statut <code>en_attente</code> aux inscriptions aux événements.</p>
            <p>Cela permet aux utilisateurs de recevoir une invitation sans être automatiquement inscrits. Ils doivent cliquer sur le lien "S'inscrire" pour confirmer.</p>
            
            <form method="POST" class="mt-3">
                <button type="submit" name="update_schema" class="btn btn-warning" 
                        onclick="return confirm('Êtes-vous sûr ? Cette action modifiera le schéma de la base de données.')">
                    <i class="bi bi-lightning-charge-fill"></i> Exécuter la migration
                </button>
            </form>
        </div>
    </div>

    <div class="gn-card mb-4">
        <div class="gn-card-header">
            <h3 class="gn-card-title">Ajouter l'image de couverture aux événements</h3>
        </div>
        <div style="padding: 1.5rem;">
            <p>Cette migration ajoute la colonne <code>cover_filename</code> à la table <code>evenements</code> pour stocker le nom de fichier de l'image de couverture.</p>
            <form method="POST" class="mt-3">
                <button type="submit" name="add_cover_column" class="btn btn-primary" 
                        onclick="return confirm('Confirmer l\'ajout de la colonne cover_filename à evenements ?')">
                    <i class="bi bi-image"></i> Ajouter la colonne
                </button>
            </form>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>

