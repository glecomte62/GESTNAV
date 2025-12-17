<?php
require '../../header.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_sql'])) {
    try {
        // Vérifier si la colonne existe déjà
        $check = $pdo->query("SHOW COLUMNS FROM evenements LIKE 'url'");
        if ($check->fetch()) {
            $info = "ℹ️ La colonne 'url' existe déjà dans la table evenements.";
        } else {
            // Ajouter la colonne url
            $pdo->exec("ALTER TABLE evenements ADD COLUMN url VARCHAR(500) NULL AFTER adresse");
            $success = "✓ Colonne 'url' ajoutée avec succès à la table evenements !";
        }
    } catch (Exception $e) {
        $error = "✗ Erreur : " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <h1 class="mb-4">⚙️ Installation - Champ URL pour les événements</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($info)): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <i class="bi bi-info-circle"></i> <?= $info ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="gn-card mb-4">
        <div class="gn-card-header">
            <h3 class="gn-card-title">Ajout du champ URL</h3>
        </div>
        <div style="padding: 1.5rem;">
            <p>Cette migration ajoute un champ <code>url</code> à la table <code>evenements</code>.</p>
            <p>Ce champ permet d'ajouter un lien externe (site web, formulaire, etc.) pour chaque événement.</p>
            
            <form method="POST" class="mt-3">
                <button type="submit" name="execute_sql" class="btn btn-primary">
                    <i class="bi bi-lightning-charge-fill"></i> Exécuter la migration
                </button>
            </form>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="../../evenements_admin.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Retour aux événements
        </a>
    </div>
</div>

<?php require '../../footer.php'; ?>
