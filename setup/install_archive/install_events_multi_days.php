<?php
/**
 * Migration pour ajouter le support des événements multi-jours
 * Ajoute les colonnes date_fin et is_multi_day à la table evenements
 */
require '../../header.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_sql'])) {
    try {
        // SQL pour ajouter les nouvelles colonnes
        $sql_queries = [
            // Ajouter la colonne date_fin
            "ALTER TABLE evenements 
             ADD COLUMN date_fin DATETIME NULL DEFAULT NULL AFTER date_evenement",
            
            // Ajouter la colonne is_multi_day
            "ALTER TABLE evenements 
             ADD COLUMN is_multi_day TINYINT(1) NOT NULL DEFAULT 0 AFTER date_fin",
            
            // Ajouter un index sur date_fin
            "ALTER TABLE evenements 
             ADD INDEX idx_date_fin (date_fin)"
        ];
        
        foreach ($sql_queries as $query) {
            try {
                $pdo->exec($query);
            } catch (PDOException $e) {
                // Ignorer les erreurs si la colonne existe déjà
                if (strpos($e->getMessage(), 'Duplicate column name') === false && 
                    strpos($e->getMessage(), 'Duplicate key name') === false) {
                    throw $e;
                }
            }
        }
        
        $success = "Migration réussie ! Les colonnes 'date_fin' et 'is_multi_day' ont été ajoutées à la table evenements.";
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Événements Multi-jours</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1>Installation - Support des Événements Multi-jours</h1>
    
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
    
    <div class="card mt-4">
        <div class="card-header">
            <h3>Migration de la base de données</h3>
        </div>
        <div class="card-body">
            <p>Cette migration va ajouter les colonnes suivantes à la table <code>evenements</code> :</p>
            <ul>
                <li><strong>date_fin</strong> (DATETIME NULL) : Date et heure de fin de l'événement pour les événements multi-jours</li>
                <li><strong>is_multi_day</strong> (TINYINT(1)) : Indicateur booléen pour identifier les événements sur plusieurs jours</li>
            </ul>
            
            <p class="text-muted">
                <strong>Note :</strong> Pour les événements existants, ces champs resteront vides (événements d'un jour).
                Pour créer un événement multi-jours, il suffira de renseigner la date de fin.
            </p>
            
            <form method="POST">
                <button type="submit" name="execute_sql" class="btn btn-primary">
                    Exécuter la migration
                </button>
                <a href="../../evenements_admin.php" class="btn btn-secondary">Retour</a>
            </form>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h4>Fonctionnalités ajoutées</h4>
        </div>
        <div class="card-body">
            <h5>Pour les administrateurs :</h5>
            <ul>
                <li>Possibilité de définir une date de fin pour les événements</li>
                <li>Détection automatique des événements multi-jours (si date_fin > date_evenement)</li>
                <li>Affichage de la période "Du XX/XX/XXXX au YY/YY/YYYY" pour les événements multi-jours</li>
            </ul>
            
            <h5>Pour les membres :</h5>
            <ul>
                <li>Affichage clair de la durée des événements</li>
                <li>Meilleure visibilité des événements sur plusieurs jours</li>
            </ul>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
