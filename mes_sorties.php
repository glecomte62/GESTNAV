<?php
require 'header.php';
require_login();

$user_id = $_SESSION['user_id'];
$now = date('Y-m-d H:i:s');

// Vérifier si les colonnes destination_id et ulm_base_id existent
$hasDestinationId = false;
$hasUlmBaseId = false;
try {
    $cols = $pdo->query('SHOW COLUMNS FROM sorties')->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasDestinationId = in_array('destination_id', $cols, true);
    $hasUlmBaseId = in_array('ulm_base_id', $cols, true);
} catch (Throwable $e) {}

// Récupérer les sorties futures sur lesquelles l'utilisateur est inscrit
try {
    $sqlFutures = "
        SELECT 
            s.id,
            s.titre,
            s.description,
            s.details,
            s.date_sortie,
            s.date_fin,
            s.is_multi_day,
            s.destination_oaci,
            s.statut,
            s.repas_prevu,
            s.repas_details,"
            . ($hasDestinationId ? " s.destination_id," : "")
            . ($hasUlmBaseId ? " s.ulm_base_id," : "")
            . ($hasDestinationId ? " ad.nom AS dest_nom, ad.oaci AS dest_oaci," : "")
            . ($hasUlmBaseId ? " ub.nom AS ulm_nom," : "")
            . "
            (SELECT sp.filename FROM sortie_photos sp WHERE sp.sortie_id = s.id ORDER BY sp.created_at DESC LIMIT 1) AS photo_filename,
            si.created_at as inscription_date
        FROM sorties s"
        . ($hasDestinationId ? "\n        LEFT JOIN aerodromes_fr ad ON ad.id = s.destination_id" : "")
        . ($hasUlmBaseId ? "\n        LEFT JOIN ulm_bases_fr ub ON ub.id = s.ulm_base_id" : "")
        . "
        INNER JOIN sortie_inscriptions si ON si.sortie_id = s.id
        WHERE si.user_id = ? AND s.date_sortie >= ?
        ORDER BY s.date_sortie ASC
    ";
    $stmt = $pdo->prepare($sqlFutures);
    $stmt->execute([$user_id, $now]);
    $sorties_futures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sorties_futures = [];
    error_log("Erreur requête sorties futures: " . $e->getMessage());
}

// Récupérer les sorties passées sur lesquelles l'utilisateur était inscrit
try {
    $sqlPassees = "
        SELECT 
            s.id,
            s.titre,
            s.description,
            s.details,
            s.date_sortie,
            s.date_fin,
            s.is_multi_day,
            s.destination_oaci,
            s.statut,
            s.repas_prevu,
            s.repas_details,"
            . ($hasDestinationId ? " s.destination_id," : "")
            . ($hasUlmBaseId ? " s.ulm_base_id," : "")
            . ($hasDestinationId ? " ad.nom AS dest_nom, ad.oaci AS dest_oaci," : "")
            . ($hasUlmBaseId ? " ub.nom AS ulm_nom," : "")
            . "
            (SELECT sp.filename FROM sortie_photos sp WHERE sp.sortie_id = s.id ORDER BY sp.created_at DESC LIMIT 1) AS photo_filename,
            si.created_at as inscription_date
        FROM sorties s"
        . ($hasDestinationId ? "\n        LEFT JOIN aerodromes_fr ad ON ad.id = s.destination_id" : "")
        . ($hasUlmBaseId ? "\n        LEFT JOIN ulm_bases_fr ub ON ub.id = s.ulm_base_id" : "")
        . "
        INNER JOIN sortie_inscriptions si ON si.sortie_id = s.id
        WHERE si.user_id = ? AND s.date_sortie < ?
        ORDER BY s.date_sortie DESC
        LIMIT 20
    ";
    $stmt = $pdo->prepare($sqlPassees);
    $stmt->execute([$user_id, $now]);
    $sorties_passees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sorties_passees = [];
    error_log("Erreur requête sorties passées: " . $e->getMessage());
}

// Fonction pour afficher une sortie
function render_sortie_card($sortie, $isFuture = true) {
    $date_sortie = new DateTime($sortie['date_sortie']);
    $date_str = strftime('%A %d %B %Y à %H:%M', $date_sortie->getTimestamp());
    $date_str = mb_convert_case($date_str, MB_CASE_TITLE, 'UTF-8');
    
    // Déterminer la destination à afficher (priorité à la base ULM)
    $destination_label = '';
    if (!empty($sortie['ulm_nom'])) {
        $destination_label = htmlspecialchars($sortie['ulm_nom']);
    } elseif (!empty($sortie['dest_nom'])) {
        $oaci = !empty($sortie['dest_oaci']) ? htmlspecialchars($sortie['dest_oaci']) . ' – ' : '';
        $destination_label = $oaci . htmlspecialchars($sortie['dest_nom']);
    } elseif (!empty($sortie['destination_oaci'])) {
        $destination_label = htmlspecialchars($sortie['destination_oaci']);
    }
    
    // Badge de statut
    $statutBadge = '';
    $statut = strtolower($sortie['statut'] ?? 'prévue');
    if ($statut === 'prévue' || $statut === 'prevue') {
        $statutBadge = '<span class="badge bg-success">Prévue</span>';
    } elseif ($statut === 'terminée' || $statut === 'terminee') {
        $statutBadge = '<span class="badge bg-secondary">Terminée</span>';
    } elseif ($statut === 'en étude' || $statut === 'en etude') {
        $statutBadge = '<span class="badge bg-warning text-dark">En étude</span>';
    } elseif ($statut === 'annulée' || $statut === 'annulee') {
        $statutBadge = '<span class="badge bg-danger">Annulée</span>';
    }
    
    $photo_url = !empty($sortie['photo_filename']) 
        ? 'uploads/sorties/' . htmlspecialchars($sortie['photo_filename'])
        : 'assets/img/default-sortie.jpg';
    
    ?>
    <div class="col">
        <div class="card h-100 shadow-sm hover-lift" style="border-radius: 1rem; overflow: hidden; transition: transform 0.2s;">
            <div style="height: 200px; overflow: hidden; background: linear-gradient(135deg, #004b8d, #00a0c6);">
                <img src="<?= htmlspecialchars($photo_url) ?>" 
                     alt="<?= htmlspecialchars($sortie['titre']) ?>"
                     style="width: 100%; height: 100%; object-fit: cover;"
                     onerror="this.src='assets/img/default-sortie.jpg'">
            </div>
            
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="card-title mb-0"><?= htmlspecialchars($sortie['titre']) ?></h5>
                    <?= $statutBadge ?>
                </div>
                
                <div class="mb-2">
                    <small class="text-muted">
                        <i class="bi bi-calendar-event me-1"></i>
                        <?= htmlspecialchars($date_str) ?>
                    </small>
                </div>
                
                <?php if ($destination_label): ?>
                <div class="mb-2">
                    <small class="text-muted">
                        <i class="bi bi-geo-alt-fill me-1"></i>
                        <?= $destination_label ?>
                    </small>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($sortie['repas_prevu'])): ?>
                <div class="mb-2">
                    <span class="badge bg-info text-dark">
                        <i class="bi bi-egg-fried"></i> Repas prévu
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($sortie['description'])): ?>
                <p class="card-text text-muted" style="font-size: 0.9rem;">
                    <?= nl2br(htmlspecialchars(mb_substr($sortie['description'], 0, 120))) ?>
                    <?= mb_strlen($sortie['description']) > 120 ? '...' : '' ?>
                </p>
                <?php endif; ?>
                
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="bi bi-clock me-1"></i>
                        Inscrit le <?= date('d/m/Y', strtotime($sortie['inscription_date'])) ?>
                    </small>
                </div>
            </div>
            
            <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                <div class="d-flex gap-2">
                    <a href="sortie_detail.php?id=<?= $sortie['id'] ?>" 
                       class="btn btn-primary flex-grow-1" 
                       style="border-radius: 999px; background: linear-gradient(135deg, #004b8d, #00a0c6); border: none;">
                        <i class="bi bi-eye me-1"></i> Voir les détails
                    </a>
                    <?php if ($isFuture): ?>
                    <a href="download_sortie_ics.php?id=<?= $sortie['id'] ?>" 
                       class="btn btn-outline-primary" 
                       style="border-radius: 999px;"
                       title="Télécharger dans mon calendrier">
                        <i class="bi bi-calendar-plus"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fra');
?>

<style>
    .hover-lift:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.15) !important;
    }
</style>

<div class="container my-4">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0">
                    <i class="bi bi-calendar-check me-2"></i>
                    Mes sorties
                </h1>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Retour
                </a>
            </div>
        </div>
    </div>
    
    <!-- Sorties à venir -->
    <div class="row mb-5">
        <div class="col">
            <h2 class="mb-3">
                <i class="bi bi-calendar-plus me-2"></i>
                Sorties à venir
                <span class="badge bg-primary ms-2"><?= count($sorties_futures) ?></span>
            </h2>
            
            <?php if (empty($sorties_futures)): ?>
                <div class="alert alert-info" style="border-radius: 1rem;">
                    <i class="bi bi-info-circle me-2"></i>
                    Vous n'êtes inscrit à aucune sortie à venir pour le moment.
                    <a href="sorties.php" class="alert-link">Voir les sorties disponibles</a>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($sorties_futures as $sortie): ?>
                        <?php render_sortie_card($sortie, true); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sorties passées -->
    <?php if (!empty($sorties_passees)): ?>
    <div class="row">
        <div class="col">
            <h2 class="mb-3">
                <i class="bi bi-clock-history me-2"></i>
                Sorties passées
                <span class="badge bg-secondary ms-2"><?= count($sorties_passees) ?></span>
            </h2>
            
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($sorties_passees as $sortie): ?>
                    <?php render_sortie_card($sortie, false, false); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
