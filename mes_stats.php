<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

$user_id = $_SESSION['user_id'];

// Récupérer les infos de l'utilisateur
$stmt = $pdo->prepare('SELECT id, nom, prenom, photo_path, photo_metadata FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Période de filtrage
$range = $_GET['range'] ?? 'all';
$year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

$dateFilterEvt = '';
$dateFilterSort = '';
$dateParams = [];

if ($range === 'last12') {
    $start = (new DateTime('first day of this month -11 months'))->format('Y-m-d');
    $end = (new DateTime('last day of this month'))->format('Y-m-d');
    $dateFilterEvt = ' AND DATE(e.date_evenement) BETWEEN ? AND ?';
    $dateFilterSort = ' AND DATE(s.date_sortie) BETWEEN ? AND ?';
    $dateParams = [$start, $end];
} elseif ($range === 'year') {
    if ($year < 2000 || $year > 9999) { $year = (int)date('Y'); }
    $start = sprintf('%04d-01-01', $year);
    $end = sprintf('%04d-12-31', $year);
    $dateFilterEvt = ' AND DATE(e.date_evenement) BETWEEN ? AND ?';
    $dateFilterSort = ' AND DATE(s.date_sortie) BETWEEN ? AND ?';
    $dateParams = [$start, $end];
}

// Années disponibles
$years_evt = $pdo->prepare("SELECT DISTINCT YEAR(e.date_evenement) AS y FROM evenement_inscriptions ei JOIN evenements e ON e.id = ei.evenement_id WHERE ei.user_id = ? AND e.date_evenement IS NOT NULL ORDER BY y DESC");
$years_evt->execute([$user_id]);
$years_e = $years_evt->fetchAll(PDO::FETCH_COLUMN);

$years_sort = $pdo->prepare("SELECT DISTINCT YEAR(s.date_sortie) AS y FROM sortie_assignations sa JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id JOIN sorties s ON s.id = sm.sortie_id WHERE sa.user_id = ? AND s.date_sortie IS NOT NULL ORDER BY y DESC");
$years_sort->execute([$user_id]);
$years_s = $years_sort->fetchAll(PDO::FETCH_COLUMN);

$years = array_values(array_unique(array_map('intval', array_merge($years_e ?: [], $years_s ?: []))));
rsort($years);
if (!$years) { $years = [(int)date('Y')]; }

// Statistiques événements
if ($dateFilterEvt) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ei.evenement_id)
                           FROM evenement_inscriptions ei
                           JOIN evenements e ON e.id = ei.evenement_id
                           WHERE ei.user_id = ? AND ei.statut = 'confirmée' " . $dateFilterEvt);
    $stmt->execute(array_merge([$user_id], $dateParams));
    $nb_evenements = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(ei.nb_accompagnants + 1), 0)
                           FROM evenement_inscriptions ei
                           JOIN evenements e ON e.id = ei.evenement_id
                           WHERE ei.user_id = ? AND ei.statut = 'confirmée' " . $dateFilterEvt);
    $stmt->execute(array_merge([$user_id], $dateParams));
    $total_personnes_evt = (int)$stmt->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT evenement_id) FROM evenement_inscriptions WHERE user_id = ? AND statut = 'confirmée'");
    $stmt->execute([$user_id]);
    $nb_evenements = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(nb_accompagnants + 1), 0) FROM evenement_inscriptions WHERE user_id = ? AND statut = 'confirmée'");
    $stmt->execute([$user_id]);
    $total_personnes_evt = (int)$stmt->fetchColumn();
}

// Statistiques sorties (affectations réelles)
if ($dateFilterSort) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT sm.sortie_id)
                           FROM sortie_assignations sa
                           JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id
                           JOIN sorties s ON s.id = sm.sortie_id
                           WHERE sa.user_id = ? " . $dateFilterSort);
    $stmt->execute(array_merge([$user_id], $dateParams));
    $nb_sorties = (int)$stmt->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT sm.sortie_id) FROM sortie_assignations sa JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id WHERE sa.user_id = ?");
    $stmt->execute([$user_id]);
    $nb_sorties = (int)$stmt->fetchColumn();
}

// Top destinations
$hasDestinationId = false;
try {
    $cols = $pdo->query('SHOW COLUMNS FROM sorties')->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasDestinationId = in_array('destination_id', $cols, true);
} catch (Throwable $e) {}

$top_destinations = [];
if ($hasDestinationId) {
    if ($dateFilterSort) {
        $stmt = $pdo->prepare("SELECT ad.oaci, ad.nom, COUNT(DISTINCT s.id) AS nb
                               FROM sortie_assignations sa
                               JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id
                               JOIN sorties s ON s.id = sm.sortie_id
                               JOIN aerodromes_fr ad ON ad.id = s.destination_id
                               WHERE sa.user_id = ? " . $dateFilterSort . "
                               GROUP BY ad.id, ad.oaci, ad.nom
                               ORDER BY nb DESC
                               LIMIT 5");
        $stmt->execute(array_merge([$user_id], $dateParams));
    } else {
        $stmt = $pdo->prepare("SELECT ad.oaci, ad.nom, COUNT(DISTINCT s.id) AS nb
                               FROM sortie_assignations sa
                               JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id
                               JOIN sorties s ON s.id = sm.sortie_id
                               JOIN aerodromes_fr ad ON ad.id = s.destination_id
                               WHERE sa.user_id = ?
                               GROUP BY ad.id, ad.oaci, ad.nom
                               ORDER BY nb DESC
                               LIMIT 5");
        $stmt->execute([$user_id]);
    }
    $top_destinations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// Dernières activités (affectations réelles)
if ($dateFilterSort) {
    $stmt = $pdo->prepare("SELECT DISTINCT s.id, s.titre, s.date_sortie, 'sortie' AS type
                           FROM sortie_assignations sa
                           JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id
                           JOIN sorties s ON s.id = sm.sortie_id
                           WHERE sa.user_id = ? " . $dateFilterSort . "
                           ORDER BY s.date_sortie DESC
                           LIMIT 10");
    $stmt->execute(array_merge([$user_id], $dateParams));
} else {
    $stmt = $pdo->prepare("SELECT DISTINCT s.id, s.titre, s.date_sortie, 'sortie' AS type
                           FROM sortie_assignations sa
                           JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id
                           JOIN sorties s ON s.id = sm.sortie_id
                           WHERE sa.user_id = ?
                           ORDER BY s.date_sortie DESC
                           LIMIT 10");
    $stmt->execute([$user_id]);
}
$dernieres_sorties = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($dateFilterEvt) {
    $stmt = $pdo->prepare("SELECT e.id, e.titre, e.date_evenement, 'evenement' AS type
                           FROM evenement_inscriptions ei
                           JOIN evenements e ON e.id = ei.evenement_id
                           WHERE ei.user_id = ? AND ei.statut = 'confirmée' " . $dateFilterEvt . "
                           ORDER BY e.date_evenement DESC
                           LIMIT 10");
    $stmt->execute(array_merge([$user_id], $dateParams));
} else {
    $stmt = $pdo->prepare("SELECT e.id, e.titre, e.date_evenement, 'evenement' AS type
                           FROM evenement_inscriptions ei
                           JOIN evenements e ON e.id = ei.evenement_id
                           WHERE ei.user_id = ? AND ei.statut = 'confirmée'
                           ORDER BY e.date_evenement DESC
                           LIMIT 10");
    $stmt->execute([$user_id]);
}
$derniers_evenements = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Fusion et tri par date
$activites = array_merge($dernieres_sorties, $derniers_evenements);
usort($activites, function($a, $b) {
    $date_a = $a['type'] === 'sortie' ? $a['date_sortie'] : $a['date_evenement'];
    $date_b = $b['type'] === 'sortie' ? $b['date_sortie'] : $b['date_evenement'];
    return strtotime($date_b) - strtotime($date_a);
});
$activites = array_slice($activites, 0, 10);

// Position dans le classement général - version simplifiée
$total_user = $nb_sorties + $nb_evenements;

$stmt = $pdo->prepare("
    SELECT COUNT(*) + 1 AS position
    FROM (
        SELECT u.id,
               (SELECT COUNT(DISTINCT sortie_id) FROM sortie_inscriptions WHERE user_id = u.id) +
               (SELECT COUNT(DISTINCT evenement_id) FROM evenement_inscriptions WHERE user_id = u.id AND statut = 'confirmée') AS total
        FROM users u
        WHERE u.actif = 1
    ) AS classement
    WHERE total > ?
");
$stmt->execute([$total_user]);
$position = (int)$stmt->fetchColumn();

require 'header.php';
?>

<style>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.stats-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 3rem 0;
    margin: -2rem -2rem 3rem -2rem;
    position: relative;
    overflow: hidden;
}
.stats-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120"><path d="M0,0 L1200,0 L1200,80 Q900,100 600,80 T0,80 Z" fill="rgba(255,255,255,0.1)"/></svg>') repeat-x bottom;
}
.user-avatar-large {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 5px solid white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    overflow: hidden;
    background: #f0f0f0;
}
.user-avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: all 0.3s;
    animation: fadeInUp 0.6s ease-out backwards;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}
.stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.chart-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
    animation: fadeInUp 0.6s ease-out backwards;
}
.activity-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 0.75rem;
    background: #f8f9fa;
    transition: all 0.3s;
}
.activity-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}
.rank-badge {
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
</style>

<div class="stats-hero">
    <div class="container position-relative">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="user-avatar-large">
                    <?php 
                    $photoPath = $user['photo_path'] ?? '/assets/img/avatar-placeholder.svg';
                    ?>
                    <img src="<?= htmlspecialchars($photoPath) ?>" alt="<?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?>">
                </div>
            </div>
            <div class="col">
                <h1 class="mb-2">Mes Statistiques</h1>
                <p class="mb-0 opacity-75"><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></p>
            </div>
            <div class="col-auto">
                <a href="stats.php" class="btn btn-light">
                    <i class="bi bi-graph-up"></i> Stats du Club
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="bg-white rounded-3 p-3 mb-4 shadow-sm">
        <form method="get" class="d-flex align-items-center gap-3 flex-wrap">
            <div>
                <label class="form-label mb-1 small fw-bold">Période</label>
                <select name="range" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= $range==='all'?'selected':'' ?>>Tout le temps</option>
                    <option value="last12" <?= $range==='last12'?'selected':'' ?>>12 derniers mois</option>
                    <option value="year" <?= $range==='year'?'selected':'' ?>>Année</option>
                </select>
            </div>
            <div>
                <label class="form-label mb-1 small fw-bold">Année</label>
                <select name="y" class="form-select" onchange="this.form.submit()" <?= $range==='year'?'':'disabled' ?>>
                    <?php foreach ($years as $yopt): ?>
                        <option value="<?= (int)$yopt ?>" <?= ($range==='year' && (int)$yopt===$year)?'selected':'' ?>><?= (int)$yopt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3" style="animation-delay: 0.1s">
            <div class="stat-card text-center">
                <div class="mb-2"><i class="bi bi-calendar-event" style="font-size: 2rem; color: #667eea;"></i></div>
                <div class="stat-value"><?= $nb_evenements ?></div>
                <div class="text-muted">Événements</div>
            </div>
        </div>
        <div class="col-md-3" style="animation-delay: 0.2s">
            <div class="stat-card text-center">
                <div class="mb-2"><i class="bi bi-airplane-fill" style="font-size: 2rem; color: #764ba2;"></i></div>
                <div class="stat-value"><?= $nb_sorties ?></div>
                <div class="text-muted">Sorties</div>
            </div>
        </div>
        <div class="col-md-3" style="animation-delay: 0.3s">
            <div class="stat-card text-center">
                <div class="mb-2"><i class="bi bi-people-fill" style="font-size: 2rem; color: #4ECDC4;"></i></div>
                <div class="stat-value"><?= $total_personnes_evt ?></div>
                <div class="text-muted">Personnes (événements)</div>
            </div>
        </div>
        <div class="col-md-3" style="animation-delay: 0.4s">
            <div class="stat-card text-center">
                <div class="mb-2"><i class="bi bi-trophy-fill" style="font-size: 2rem; color: #FFD700;"></i></div>
                <div class="rank-badge">#<?= $position ?></div>
                <div class="text-muted">Classement</div>
            </div>
        </div>
    </div>

    <?php if ($hasDestinationId && !empty($top_destinations)): ?>
    <div class="chart-card" style="animation-delay: 0.5s">
        <h2 class="h4 mb-4"><i class="bi bi-geo-alt-fill text-danger"></i> Mes Destinations Favorites</h2>
        <div class="row g-3">
            <?php foreach ($top_destinations as $i => $dest): 
                $max = max(array_column($top_destinations, 'nb'));
                $pct = $max > 0 ? ($dest['nb'] / $max * 100) : 0;
            ?>
            <div class="col-12">
                <div class="d-flex justify-content-between mb-2">
                    <strong><?= htmlspecialchars(($dest['oaci'] ? $dest['oaci'].' – ' : '').$dest['nom']) ?></strong>
                    <span class="badge bg-primary"><?= (int)$dest['nb'] ?> sorties</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar" style="width: <?= $pct ?>%; background: linear-gradient(90deg, #667eea, #764ba2);"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="chart-card" style="animation-delay: 0.6s">
        <h2 class="h4 mb-4"><i class="bi bi-clock-history"></i> Activités Récentes</h2>
        <?php if (empty($activites)): ?>
            <div class="text-muted text-center py-4">Aucune activité</div>
        <?php else: foreach ($activites as $act): 
            $date = $act['type'] === 'sortie' ? $act['date_sortie'] : $act['date_evenement'];
            $url = $act['type'] === 'sortie' ? 'sortie_detail.php?id='.$act['id'] : 'evenement_detail.php?id='.$act['id'];
            $icon = $act['type'] === 'sortie' ? 'airplane-fill' : 'calendar-event';
            $color = $act['type'] === 'sortie' ? '#764ba2' : '#667eea';
        ?>
        <a href="<?= $url ?>" class="activity-item text-decoration-none">
            <div class="me-3"><i class="bi bi-<?= $icon ?>" style="font-size: 1.5rem; color: <?= $color ?>;"></i></div>
            <div class="flex-grow-1">
                <strong class="text-dark"><?= htmlspecialchars($act['titre']) ?></strong>
                <div class="small text-muted"><?= date('d/m/Y', strtotime($date)) ?></div>
            </div>
            <span class="badge" style="background: <?= $color ?>;"><?= $act['type'] === 'sortie' ? 'Sortie' : 'Événement' ?></span>
        </a>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>
