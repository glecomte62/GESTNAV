<?php
require 'header.php';
require_admin();

$success = false;
$error = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_column'])) {
    try {
        // Ajouter la colonne date_limite_inscription si elle n'existe pas
        $pdo->exec("ALTER TABLE evenements ADD COLUMN date_limite_inscription DATETIME NULL AFTER date_evenement");
        
        $success = true;
        $message = "✓ Colonne 'date_limite_inscription' ajoutée avec succès !";
    } catch (Exception $e) {
        // Vérifier si l'erreur est parce que la colonne existe déjà
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            $success = true;
            $message = "✓ La colonne 'date_limite_inscription' existe déjà.";
        } else {
            $error = true;
            $message = "Erreur : " . $e->getMessage();
        }
    }
}

// Vérifier si la colonne existe
$column_exists = false;
try {
    $result = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='evenements' AND COLUMN_NAME='date_limite_inscription' AND TABLE_SCHEMA=DATABASE()")->fetch();
    $column_exists = $result ? true : false;
} catch (Exception $e) {
    // Erreur silencieuse
}
?>

<div class="container mt-4">
    <h1 class="mb-4">⚙️ Migration - Ajout de date limite</h1>
    
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
            <h3 class="gn-card-title">État de la migration</h3>
        </div>
        <div style="padding: 1.5rem;">
            <p>
                <strong>Colonne 'date_limite_inscription' :</strong>
                <?php if ($column_exists): ?>
                    <span class="badge bg-success">✓ Existe</span>
                <?php else: ?>
                    <span class="badge bg-warning">✗ Manquante</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    
    <?php if (!$column_exists): ?>
        <div class="gn-card mb-4">
            <div class="gn-card-header">
                <h3 class="gn-card-title">Ajouter la colonne</h3>
            </div>
            <div style="padding: 1.5rem;">
                <p>Cette migration ajoute la colonne <code>date_limite_inscription</code> à la table <code>evenements</code>.</p>
                <p>Cela permet de définir une date limite après laquelle les inscriptions ne sont plus possibles.</p>
                
                <form method="POST" class="mt-3">
                    <button type="submit" name="add_column" class="btn btn-warning" 
                            onclick="return confirm('Êtes-vous sûr ? Cette action modifiera le schéma de la base de données.')">
                        <i class="bi bi-lightning-charge-fill"></i> Exécuter la migration
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
