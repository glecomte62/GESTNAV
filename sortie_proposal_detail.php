<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'config.php';
    require_once 'auth.php';
    require_login();

    $proposal_id = (int)($_GET['id'] ?? 0);
    if (!$proposal_id) header('Location: sortie_proposals_list.php');

    $stmt = $pdo->prepare("
        SELECT sp.*, u.id as user_id, u.prenom, u.nom, u.email, u.telephone, u.photo_path,
               a.oaci, a.nom as aero_nom
        FROM sortie_proposals sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN aerodromes_fr a ON sp.aerodrome_id = a.id
        WHERE sp.id = ?
    ");
    $stmt->execute([$proposal_id]);
    $proposal = $stmt->fetch();

    if (!$proposal) {
        header('Location: sortie_proposals_list.php');
        exit;
    }

    // Calculer distance depuis LFQJ basée sur l'aérodrome (si on a une carte de coordonnées)
    $distance_km = null;
    $flight_time_min = null;

    // Dictionnaire simplifié des distances LFQJ pour les aérodromes communs
    $aerodromes_distances = [
        'LFAC' => 150,  // Auch-Lamothe
        'LFBO' => 187,  // Toulouse-Blagnac
        'LFPG' => 567,  // Paris-Orly
        'LFPB' => 580,  // Paris-Le Bourget
        'LFPD' => 590,  // Paris-Orly CDG
        'LFLY' => 465,  // Lyon-Bron
        'LFML' => 387,  // Marseille-Provence
        'LFNT' => 240,  // Nantes-Atlantique
        'LFRN' => 350,  // Rennes
        'LFPO' => 290,  // Poitiers
        'LFRS' => 110,  // Rochefort-St Agnant
        'LFBX' => 0,    // Bordeaux-Mérignac (LFQJ = Bordeaux)
        'LFQJ' => 0,    // Bordeaux-Mérignac
    ];

    if ($proposal['oaci'] && isset($aerodromes_distances[$proposal['oaci']])) {
        $distance_km = $aerodromes_distances[$proposal['oaci']];
        $flight_time_min = round($distance_km / 1.5); // 90 km/h = distance/1.5
    }

    // Déterminer icône et couleur du statut
    $status_colors = [
        'en_attente' => '#fbbf24',
        'accepte' => '#60a5fa',
        'en_preparation' => '#818cf8',
        'validee' => '#34d399',
        'rejetee' => '#f87171'
    ];

    $status_labels = [
        'en_attente' => 'En attente',
        'accepte' => 'Acceptée',
        'en_preparation' => 'En préparation',
        'validee' => 'Validée',
        'rejetee' => 'Rejetée'
    ];

    $status_icons = [
        'en_attente' => 'hourglass-split',
        'accepte' => 'check-circle',
        'en_preparation' => 'wrench',
        'validee' => 'star-fill',
        'rejetee' => 'x-circle'
    ];
} catch (Throwable $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Erreur</title></head><body>";
    echo "<h1>Erreur dans sortie_proposal_detail.php</h1>";
    echo "<p><strong>" . get_class($e) . ":</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Fichier: " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</body></html>";
    exit;
}

require 'header.php';
?>

<style>
.proposal-detail-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.proposal-breadcrumb {
    margin-bottom: 2rem;
    font-size: 0.9rem;
    color: #6b7280;
}

.proposal-breadcrumb a {
    color: #0066c0;
    text-decoration: none;
}

.proposal-header-detail {
    background: linear-gradient(135deg, #004b8d 0%, #0066c0 100%);
    color: #ffffff;
    padding: 2rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
}

.proposal-header-detail h1 {
    margin: 0 0 1rem;
    font-size: 2rem;
}

.header-meta {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
    font-size: 0.95rem;
}

.header-meta span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.proposal-content {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 2rem;
    margin-bottom: 2rem;
}

.proposal-main {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 2rem;
}

.proposal-photo-large {
    width: 100%;
    height: 300px;
    object-fit: cover;
    border-radius: 0.5rem;
    margin-bottom: 2rem;
}

.section-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1a1a1a;
    margin: 2rem 0 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e5e7eb;
}

.section-title:first-child {
    margin-top: 0;
}

.proposal-description {
    color: #4b5563;
    line-height: 1.8;
    white-space: pre-wrap;
    word-break: break-word;
}

.info-block {
    background: #f9fafb;
    padding: 1.5rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
}

.info-block-title {
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 0.5rem;
}

.info-block-content {
    color: #4b5563;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
}

.proposal-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.card-sidebar {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1.5rem;
}

.card-title {
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 1rem;
    font-size: 0.95rem;
}

.proposer-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.proposer-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 1rem;
    background: #e5e7eb;
}

.proposer-name {
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 0.25rem;
}

.proposer-contact {
    font-size: 0.85rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}

.proposer-contact a {
    color: #0066c0;
    text-decoration: none;
}

.status-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #ffffff;
    text-align: center;
    width: 100%;
    margin-bottom: 1rem;
}

.badges-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.badge-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    text-align: center;
    font-size: 0.85rem;
}

.badge-item .value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #004b8d;
    margin-bottom: 0.25rem;
}

.badge-item .label {
    color: #6b7280;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-item.distance {
    border-color: #dbeafe;
    background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
}

.badge-item.time {
    border-color: #dbeafe;
    background: linear-gradient(135deg, #f0f9ff 0%, #eff6ff 100%);
}

.badge-item.activity {
    border-color: #f3e8ff;
    background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
}

.badge-item.status-badge-mini {
    border-color: #fecaca;
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
}

.dates-info {
    font-size: 0.85rem;
    color: #6b7280;
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .proposal-content {
        grid-template-columns: 1fr;
    }
    
    .proposal-header-detail h1 {
        font-size: 1.5rem;
    }
    
    .header-meta {
        gap: 1rem;
        flex-direction: column;
    }
}
</style>

<div class="proposal-detail-container">
    <div class="proposal-breadcrumb">
        <a href="sortie_proposals_list.php">Sorties Proposees</a> / <?= htmlspecialchars($proposal['titre']) ?>
    </div>

    <div class="proposal-header-detail">
        <h1><?= htmlspecialchars($proposal['titre']) ?></h1>
        <div class="header-meta">
            <span>📅 <?= htmlspecialchars($proposal['month_proposed']) ?></span>
            <?php if ($proposal['oaci']): ?>
                <span>✈️ <?= htmlspecialchars($proposal['oaci']) ?> - <?= htmlspecialchars($proposal['aero_nom']) ?></span>
            <?php endif; ?>
            <span>👤 <?= htmlspecialchars($proposal['prenom'] . ' ' . $proposal['nom']) ?></span>
        </div>
    </div>

    <div class="proposal-content">
        <div class="proposal-main">
            <?php if ($proposal['photo_filename']): ?>
                <img src="uploads/proposals/<?= htmlspecialchars($proposal['photo_filename']) ?>" 
                     alt="<?= htmlspecialchars($proposal['titre']) ?>" 
                     class="proposal-photo-large"
                     loading="lazy"
                     decoding="async">
            <?php endif; ?>

            <div class="section-title">Description</div>
            <div class="proposal-description"><?= htmlspecialchars($proposal['description']) ?></div>

            <?php if (!empty($proposal['restaurant_choice']) || !empty($proposal['restaurant_details'])): ?>
                <div class="section-title">Restaurant</div>
                <div class="info-block">
                    <?php if ($proposal['restaurant_choice']): ?>
                        <div class="info-block-title">Restaurant choisi</div>
                        <div class="info-block-content"><?= htmlspecialchars($proposal['restaurant_choice']) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($proposal['restaurant_details']): ?>
                        <div class="info-block-title" style="margin-top: 1rem;">Details</div>
                        <div class="info-block-content"><?= htmlspecialchars($proposal['restaurant_details']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($proposal['activity_details'])): ?>
                <div class="section-title">Activite sur place</div>
                <div class="info-block">
                    <div class="info-block-content"><?= htmlspecialchars($proposal['activity_details']) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="proposal-sidebar">
            <div class="card-sidebar">
                <div class="card-title">Proposeur</div>
                <div class="proposer-info">
                    <?php 
                    $photo_path = 'uploads/members/default.jpg';
                    if ($proposal['photo_path'] && file_exists(__DIR__ . '/uploads/members/' . $proposal['photo_path'])) {
                        $photo_path = 'uploads/members/' . $proposal['photo_path'];
                    }
                    ?>
                    <img src="<?= $photo_path ?>" alt="<?= htmlspecialchars($proposal['prenom'] . ' ' . $proposal['nom']) ?>" class="proposer-avatar" loading="lazy" decoding="async">
                    <div class="proposer-name"><?= htmlspecialchars($proposal['prenom'] . ' ' . $proposal['nom']) ?></div>
                    <?php if ($proposal['telephone']): ?>
                        <div class="proposer-contact">
                            <a href="tel:<?= htmlspecialchars($proposal['telephone']) ?>"><?= htmlspecialchars($proposal['telephone']) ?></a>
                        </div>
                    <?php endif; ?>
                    <div class="proposer-contact">
                        <a href="mailto:<?= htmlspecialchars($proposal['email']) ?>"><?= htmlspecialchars($proposal['email']) ?></a>
                    </div>
                </div>
            </div>

            <div class="card-sidebar">
                <div class="card-title">Statut & Informations</div>
                <div class="status-badge" style="background-color: <?= $status_colors[$proposal['status']] ?>;">
                    <i class="bi bi-<?= $status_icons[$proposal['status']] ?>"></i> <?= htmlspecialchars($status_labels[$proposal['status']]) ?>
                </div>

                <div class="badges-container">
                    <div class="badge-item distance">
                        <div class="value">
                            <?php 
                            if ($distance_km) {
                                echo $distance_km;
                            } else {
                                echo '?';
                            }
                            ?>
                        </div>
                        <div class="label"><i class="bi bi-compass"></i> km LFQJ</div>
                    </div>

                    <div class="badge-item time">
                        <div class="value">
                            <?php 
                            if ($flight_time_min) {
                                echo ceil($flight_time_min / 60);
                            } else {
                                echo '?';
                            }
                            ?>
                        </div>
                        <div class="label"><i class="bi bi-clock"></i> h vol</div>
                    </div>

                    <div class="badge-item activity">
                        <div class="value">
                            <?php 
                            $has_activity = !empty($proposal['activity_details']);
                            $has_restaurant = !empty($proposal['restaurant_choice']);
                            if ($has_activity && $has_restaurant) {
                                echo '✓ 🍽';
                            } elseif ($has_activity) {
                                echo '✓';
                            } elseif ($has_restaurant) {
                                echo '🍽';
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                        <div class="label">Activités</div>
                    </div>
                </div>

                <div class="dates-info">
                    <strong>Proposee le:</strong><br>
                    <?= date('d/m/Y a H:i', strtotime($proposal['created_at'])) ?>
                    <br><br>
                    <strong>Mise a jour:</strong><br>
                    <?= date('d/m/Y a H:i', strtotime($proposal['updated_at'])) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
