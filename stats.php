<?php
require_once 'header.php';

// Page publique: accessible même non connecté

// --------- Filtres période ---------
$range = $_GET['range'] ?? 'all'; // all | last12 | year
$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

$dateFilterSqlEvt = '';
$dateFilterSqlSort = '';
$dateParams = [];

if ($range === 'last12') {
	$start = (new DateTime('first day of this month -11 months'))->format('Y-m-d');
	$end   = (new DateTime('last day of this month'))->format('Y-m-d');
	$dateFilterSqlEvt  = ' AND DATE(e.date_evenement) BETWEEN ? AND ? ';
	$dateFilterSqlSort = ' AND DATE(s.date_sortie) BETWEEN ? AND ? ';
	$dateParams = [$start, $end];
} elseif ($range === 'year') {
	if ($year < 2000 || $year > 9999) { $year = (int)date('Y'); }
	$start = sprintf('%04d-01-01', $year);
	$end   = sprintf('%04d-12-31', $year);
	$dateFilterSqlEvt  = ' AND DATE(e.date_evenement) BETWEEN ? AND ? ';
	$dateFilterSqlSort = ' AND DATE(s.date_sortie) BETWEEN ? AND ? ';
	$dateParams = [$start, $end];
}

// Années disponibles
$years_evt = $pdo->query("SELECT DISTINCT YEAR(date_evenement) AS y FROM evenements WHERE date_evenement IS NOT NULL ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
$years_sort= $pdo->query("SELECT DISTINCT YEAR(date_sortie) AS y FROM sorties WHERE date_sortie IS NOT NULL ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
$years = array_values(array_unique(array_map('intval', array_merge($years_evt ?: [], $years_sort ?: []))));
rsort($years);
if (!$years) { $years = [(int)date('Y')]; }

// --------- Helpers internes ---------
function fetch_keypair(PDO $pdo, string $sql, array $params = []): array {
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$rows = $stmt->fetchAll(PDO::FETCH_NUM);
	$out = [];
	foreach ($rows as $r) { if (count($r) >= 2) $out[(string)$r[0]] = (int)$r[1]; }
	return $out;
}

// --------- KPIs globaux ---------
// nb événements
if ($dateFilterSqlEvt) {
	$stmt = $pdo->prepare("SELECT COUNT(*) FROM evenements e WHERE 1=1 " . $dateFilterSqlEvt);
	$stmt->execute($dateParams);
	$nb_evenements = (int)$stmt->fetchColumn();
} else {
	$nb_evenements = (int)$pdo->query("SELECT COUNT(*) FROM evenements")->fetchColumn();
}

// nb sorties
if ($dateFilterSqlSort) {
	$stmt = $pdo->prepare("SELECT COUNT(*) FROM sorties s WHERE 1=1 " . $dateFilterSqlSort);
	$stmt->execute($dateParams);
	$nb_sorties = (int)$stmt->fetchColumn();
} else {
	$nb_sorties = (int)$pdo->query("SELECT COUNT(*) FROM sorties")->fetchColumn();
}

// participations événements (confirmées, accompagnants inclus)
if ($dateFilterSqlEvt) {
	$stmt = $pdo->prepare("SELECT COALESCE(SUM(ei.nb_accompagnants + 1),0)
						   FROM evenement_inscriptions ei
						   JOIN evenements e ON e.id = ei.evenement_id
						   WHERE ei.statut='confirmée' " . $dateFilterSqlEvt);
	$stmt->execute($dateParams);
	$participants_evenements = (int)$stmt->fetchColumn();
} else {
	$participants_evenements = (int)$pdo->query("SELECT COALESCE(SUM(nb_accompagnants + 1),0) FROM evenement_inscriptions WHERE statut='confirmée'")->fetchColumn();
}

// participations sorties (inscriptions)
if ($dateFilterSqlSort) {
	$stmt = $pdo->prepare("SELECT COUNT(*) FROM sortie_inscriptions si
						   JOIN sorties s ON s.id = si.sortie_id
						   WHERE 1=1 " . $dateFilterSqlSort);
	$stmt->execute($dateParams);
	$participants_sorties = (int)$stmt->fetchColumn();
} else {
	$participants_sorties = (int)$pdo->query("SELECT COUNT(*) FROM sortie_inscriptions")->fetchColumn();
}

// --------- Classements par membre ---------
$users = $pdo->query("SELECT id, nom, prenom FROM users WHERE actif=1")->fetchAll(PDO::FETCH_ASSOC);

// sorties par user
if ($dateFilterSqlSort) {
	$stmt = $pdo->prepare("SELECT si.user_id, COUNT(*) AS nb
						   FROM sortie_inscriptions si
						   JOIN sorties s ON s.id = si.sortie_id
						   WHERE 1=1 " . $dateFilterSqlSort . "
						   GROUP BY si.user_id");
	$stmt->execute($dateParams);
	$map_sorties = [];
	foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $r) { $map_sorties[(string)$r[0]] = (int)$r[1]; }
} else {
	$map_sorties = fetch_keypair($pdo, "SELECT user_id, COUNT(*) FROM sortie_inscriptions GROUP BY user_id");
}

// événements par user (accompagnants inclus)
if ($dateFilterSqlEvt) {
	$stmt = $pdo->prepare("SELECT ei.user_id, COALESCE(SUM(ei.nb_accompagnants + 1),0) AS nb
						   FROM evenement_inscriptions ei
						   JOIN evenements e ON e.id = ei.evenement_id
						   WHERE ei.statut='confirmée' " . $dateFilterSqlEvt . "
						   GROUP BY ei.user_id");
	$stmt->execute($dateParams);
	$map_evenements = [];
	foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $r) { $map_evenements[(string)$r[0]] = (int)$r[1]; }
} else {
	$map_evenements = fetch_keypair($pdo, "SELECT user_id, COALESCE(SUM(nb_accompagnants + 1),0) FROM evenement_inscriptions WHERE statut='confirmée' GROUP BY user_id");
}

$classement = [];
foreach ($users as $u) {
	$sid = (string)$u['id'];
	$nb_s = $map_sorties[$sid] ?? 0;
	$nb_e = $map_evenements[$sid] ?? 0;
	$classement[] = [
		'id' => (int)$u['id'],
		'nom' => $u['nom'],
		'prenom' => $u['prenom'],
		'sorties' => (int)$nb_s,
		'evenements' => (int)$nb_e,
		'total' => (int)($nb_s + $nb_e)
	];
}

usort($classement, fn($a,$b) => $b['total'] <=> $a['total']);
$top_global = array_slice($classement, 0, 10);

usort($classement, fn($a,$b) => $b['evenements'] <=> $a['evenements']);
$top_evt = array_slice($classement, 0, 10);

usort($classement, fn($a,$b) => $b['sorties'] <=> $a['sorties']);
$top_sorties = array_slice($classement, 0, 10);

// Meilleur évènement / sortie
if ($dateFilterSqlEvt) {
	$stmt = $pdo->prepare("SELECT e.id, e.titre, COALESCE(SUM(CASE WHEN ei.statut='confirmée' THEN (ei.nb_accompagnants+1) ELSE 0 END),0) AS total
						   FROM evenements e
						   LEFT JOIN evenement_inscriptions ei ON ei.evenement_id = e.id
						   WHERE 1=1 " . $dateFilterSqlEvt . "
						   GROUP BY e.id
						   ORDER BY total DESC
						   LIMIT 1");
	$stmt->execute($dateParams);
	$best_evt = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} else {
	$best_evt = $pdo->query("SELECT e.id, e.titre, COALESCE(SUM(ei.nb_accompagnants + 1),0) AS total
							  FROM evenements e
							  LEFT JOIN evenement_inscriptions ei ON ei.evenement_id = e.id AND ei.statut='confirmée'
							  GROUP BY e.id
							  ORDER BY total DESC
							  LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

if ($dateFilterSqlSort) {
	$stmt = $pdo->prepare("SELECT s.id, s.titre, COUNT(si.id) AS total
						   FROM sorties s
						   LEFT JOIN sortie_inscriptions si ON si.sortie_id = s.id
						   WHERE 1=1 " . $dateFilterSqlSort . "
						   GROUP BY s.id
						   ORDER BY total DESC
						   LIMIT 1");
	$stmt->execute($dateParams);
	$best_sortie = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} else {
	$best_sortie = $pdo->query("SELECT s.id, s.titre, COUNT(si.id) AS total
								FROM sorties s
								LEFT JOIN sortie_inscriptions si ON si.sortie_id = s.id
								GROUP BY s.id
								ORDER BY total DESC
								LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

	// --------- Top destinations (si colonne destination_id disponible) ---------
	$hasDestinationId = false;
	try {
		$cols = $pdo->query('SHOW COLUMNS FROM sorties')->fetchAll(PDO::FETCH_COLUMN, 0);
		$hasDestinationId = in_array('destination_id', $cols, true);
	} catch (Throwable $e) {}

	$top_destinations = [];
	if ($hasDestinationId) {
		if ($dateFilterSqlSort) {
			$stmt = $pdo->prepare("SELECT ad.id, ad.oaci, ad.nom, COUNT(s.id) AS nb
								   FROM sorties s
								   JOIN aerodromes_fr ad ON ad.id = s.destination_id
								   WHERE 1=1 " . $dateFilterSqlSort . "
								   GROUP BY ad.id, ad.oaci, ad.nom
								   ORDER BY nb DESC
								   LIMIT 10");
			$stmt->execute($dateParams);
			$top_destinations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
		} else {
			$top_destinations = $pdo->query("SELECT ad.id, ad.oaci, ad.nom, COUNT(s.id) AS nb
											  FROM sorties s
											  JOIN aerodromes_fr ad ON ad.id = s.destination_id
											  GROUP BY ad.id, ad.oaci, ad.nom
											  ORDER BY nb DESC
											  LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
		}
	}

// --------- Export CSV ---------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="stats_classement.csv"');
	$out = fopen('php://output', 'w');
	fputcsv($out, ['Prenom', 'Nom', 'Sorties', 'Evenements', 'Total']);
	foreach ($top_global as $m) {
		fputcsv($out, [$m['prenom'], $m['nom'], $m['sorties'], $m['evenements'], $m['total']]);
	}
	fclose($out);
	exit;
}
?>
<style>
@keyframes fadeInUp {
	from { opacity: 0; transform: translateY(30px); }
	to { opacity: 1; transform: translateY(0); }
}
@keyframes countUp {
	from { opacity: 0; transform: scale(0.5); }
	to { opacity: 1; transform: scale(1); }
}
@keyframes pulse {
	0%, 100% { transform: scale(1); }
	50% { transform: scale(1.05); }
}
@keyframes shimmer {
	0% { background-position: -1000px 0; }
	100% { background-position: 1000px 0; }
}

.stats-hero {
	background: linear-gradient(135deg, #003a64 0%, #0a548b 50%, #1e6ba8 100%);
	color: white;
	padding: 4rem 0;
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
	background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120"><path d="M0,0 L1200,0 L1200,80 Q900,100 600,80 T0,80 Z" fill="rgba(255,255,255,0.05)"/></svg>') repeat-x bottom;
	opacity: 0.3;
}
.stats-hero h1 {
	font-size: 3.5rem;
	font-weight: 800;
	text-shadow: 0 4px 20px rgba(0,0,0,0.3);
	margin-bottom: 1rem;
	animation: fadeInUp 0.8s ease-out;
}
.stats-hero p {
	font-size: 1.3rem;
	opacity: 0.95;
	animation: fadeInUp 0.8s ease-out 0.2s backwards;
}

.stat-card {
	background: white;
	border-radius: 20px;
	padding: 2rem;
	box-shadow: 0 10px 40px rgba(0,0,0,0.08);
	border: 1px solid rgba(0,0,0,0.05);
	transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
	animation: fadeInUp 0.6s ease-out backwards;
	position: relative;
	overflow: hidden;
}
.stat-card::before {
	content: '';
	position: absolute;
	top: 0;
	left: -100%;
	width: 100%;
	height: 100%;
	background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
	transition: left 0.5s;
}
.stat-card:hover::before {
	left: 100%;
}
.stat-card:hover {
	transform: translateY(-10px);
	box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}
.stat-icon {
	width: 70px;
	height: 70px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 2rem;
	margin-bottom: 1.5rem;
	transition: transform 0.3s;
}
.stat-card:hover .stat-icon {
	transform: scale(1.1) rotate(5deg);
	animation: pulse 1s infinite;
}
.stat-value {
	font-size: 3.5rem;
	font-weight: 800;
	line-height: 1;
	margin-bottom: 0.5rem;
	background: linear-gradient(135deg, #003a64, #1e6ba8);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
	background-clip: text;
	animation: countUp 0.8s ease-out backwards;
}
.stat-label {
	font-size: 0.95rem;
	color: #666;
	text-transform: uppercase;
	letter-spacing: 1px;
	font-weight: 600;
}

.podium-container {
	display: flex;
	align-items: flex-end;
	justify-content: center;
	gap: 2rem;
	padding: 3rem 1rem 1rem;
	position: relative;
}
.podium-item {
	text-align: center;
	animation: fadeInUp 0.8s ease-out backwards;
	position: relative;
}
.podium-item:nth-child(1) { animation-delay: 0.4s; }
.podium-item:nth-child(2) { animation-delay: 0.2s; }
.podium-item:nth-child(3) { animation-delay: 0.6s; }
.podium-avatar {
	width: 100px;
	height: 100px;
	border-radius: 50%;
	margin: 0 auto 1rem;
	border: 5px solid;
	box-shadow: 0 10px 30px rgba(0,0,0,0.2);
	overflow: hidden;
	background: #f0f0f0;
	position: relative;
	transition: transform 0.3s;
}
.podium-item:hover .podium-avatar {
	transform: scale(1.1);
}
.podium-avatar img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}
.podium-base {
	border-radius: 10px 10px 0 0;
	padding: 1.5rem 2rem;
	font-weight: 700;
	color: white;
	position: relative;
	min-width: 140px;
	transition: transform 0.3s;
}
.podium-item:hover .podium-base {
	transform: translateY(-5px);
}
.podium-rank {
	position: absolute;
	top: -20px;
	left: 50%;
	transform: translateX(-50%);
	width: 40px;
	height: 40px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.3rem;
	font-weight: 800;
	box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
.podium-1 .podium-avatar { border-color: #FFD700; }
.podium-1 .podium-base { background: linear-gradient(135deg, #FFD700, #FFA500); height: 180px; }
.podium-1 .podium-rank { background: #FFD700; color: #333; }
.podium-2 .podium-avatar { border-color: #C0C0C0; }
.podium-2 .podium-base { background: linear-gradient(135deg, #C0C0C0, #999); height: 140px; }
.podium-2 .podium-rank { background: #C0C0C0; color: #333; }
.podium-3 .podium-avatar { border-color: #CD7F32; }
.podium-3 .podium-base { background: linear-gradient(135deg, #CD7F32, #8B4513); height: 100px; }
.podium-3 .podium-rank { background: #CD7F32; color: white; }

.chart-card {
	background: white;
	border-radius: 20px;
	padding: 2rem;
	box-shadow: 0 10px 40px rgba(0,0,0,0.08);
	margin-bottom: 2rem;
	animation: fadeInUp 0.6s ease-out backwards;
}
.ranking-item {
	display: flex;
	align-items: center;
	padding: 1rem;
	border-radius: 12px;
	margin-bottom: 0.75rem;
	background: linear-gradient(90deg, rgba(0,58,100,0.03), transparent);
	transition: all 0.3s;
	animation: fadeInUp 0.4s ease-out backwards;
}
.ranking-item:hover {
	background: linear-gradient(90deg, rgba(0,58,100,0.08), transparent);
	transform: translateX(10px);
}
.ranking-number {
	width: 50px;
	height: 50px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.3rem;
	font-weight: 800;
	margin-right: 1.5rem;
	flex-shrink: 0;
}
.ranking-number.gold { background: linear-gradient(135deg, #FFD700, #FFA500); color: #333; }
.ranking-number.silver { background: linear-gradient(135deg, #C0C0C0, #999); color: #333; }
.ranking-number.bronze { background: linear-gradient(135deg, #CD7F32, #8B4513); color: white; }
.ranking-number.other { background: #e0e0e0; color: #666; }
.ranking-bar {
	height: 12px;
	background: linear-gradient(90deg, #003a64, #1e6ba8);
	border-radius: 6px;
	transition: width 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
	box-shadow: 0 2px 8px rgba(0,58,100,0.3);
}

.filter-card {
	background: white;
	border-radius: 15px;
	padding: 1.5rem;
	box-shadow: 0 5px 20px rgba(0,0,0,0.06);
	margin-bottom: 2rem;
}
</style>

<div class="stats-hero">
	<div class="container position-relative">
		<div class="text-center">
			<h1><i class="bi bi-graph-up-arrow"></i> Statistiques du Club</h1>
			<p>Analyse détaillée de l'activité et des performances</p>
			<?php if (isset($_SESSION['user_id'])): ?>
				<div class="mt-3">
					<a href="mes_stats.php" class="btn btn-light btn-lg">
						<i class="bi bi-person-badge"></i> Mes Statistiques Personnelles
					</a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="container">
	<div class="filter-card">
		<div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
			<form method="get" class="d-flex align-items-center gap-3 flex-wrap">
				<div>
					<label class="form-label mb-1 small fw-bold text-uppercase">Période</label>
					<select name="range" class="form-select" onchange="this.form.submit()">
						<option value="all" <?= $range==='all'?'selected':'' ?>>🌍 Tout le temps</option>
						<option value="last12" <?= $range==='last12'?'selected':'' ?>>📅 12 derniers mois</option>
						<option value="year" <?= $range==='year'?'selected':'' ?>>📆 Année spécifique</option>
					</select>
				</div>
				<div>
					<label class="form-label mb-1 small fw-bold text-uppercase">Année</label>
					<select name="y" class="form-select" onchange="this.form.submit()" <?= $range==='year'?'':'disabled' ?>>
						<?php foreach ($years as $yopt): ?>
							<option value="<?= (int)$yopt ?>" <?= ($range==='year' && (int)$yopt===$year)?'selected':'' ?>><?= (int)$yopt ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</form>
			<div class="d-flex gap-2">
				<a class="btn btn-outline-primary" href="stats.php?range=<?= urlencode($range) ?>&y=<?= (int)$year ?>&export=csv">
					<i class="bi bi-download"></i> Export CSV
				</a>
			</div>
		</div>
	</div>

	<div class="row g-4 mb-5" style="animation-delay: 0.2s">
		<div class="col-md-6 col-lg-3" style="animation-delay: 0.2s">
			<div class="stat-card">
				<div class="stat-icon" style="background: linear-gradient(135deg, #FF6B6B, #FF8E8E); color: white;">
					<i class="bi bi-calendar-event"></i>
				</div>
				<div class="stat-value"><?= $nb_evenements ?></div>
				<div class="stat-label">Événements créés</div>
			</div>
		</div>
		<div class="col-md-6 col-lg-3" style="animation-delay: 0.3s">
			<div class="stat-card">
				<div class="stat-icon" style="background: linear-gradient(135deg, #4ECDC4, #44A08D); color: white;">
					<i class="bi bi-people-fill"></i>
				</div>
				<div class="stat-value"><?= $participants_evenements ?></div>
				<div class="stat-label">Participations événements</div>
			</div>
		</div>
		<div class="col-md-6 col-lg-3" style="animation-delay: 0.4s">
			<div class="stat-card">
				<div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
					<i class="bi bi-airplane-fill"></i>
				</div>
				<div class="stat-value"><?= $nb_sorties ?></div>
				<div class="stat-label">Sorties créées</div>
			</div>
		</div>
		<div class="col-md-6 col-lg-3" style="animation-delay: 0.5s">
			<div class="stat-card">
				<div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c); color: white;">
					<i class="bi bi-person-check-fill"></i>
				</div>
				<div class="stat-value"><?= $participants_sorties ?></div>
				<div class="stat-label">Inscriptions sorties</div>
			</div>
		</div>
	</div>

	<?php if ($hasDestinationId && !empty($top_destinations)): ?>
	<div class="chart-card" style="animation-delay: 0.6s">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h2 class="h3 mb-0"><i class="bi bi-geo-alt-fill text-danger"></i> Top 10 Destinations</h2>
			<span class="badge bg-primary">Sorties</span>
		</div>
		<div class="row g-3">
			<?php foreach ($top_destinations as $i => $d): 
				$maxDest = max(array_column($top_destinations, 'nb'));
				$pct = $maxDest > 0 ? ($d['nb'] / $maxDest * 100) : 0;
			?>
			<div class="col-12" style="animation-delay: <?= 0.7 + ($i * 0.1) ?>s">
				<a href="sorties.php?destination_id=<?= (int)$d['id'] ?>" class="text-decoration-none">
					<div class="ranking-item">
						<div class="ranking-number <?= $i==0 ? 'gold' : ($i==1 ? 'silver' : ($i==2 ? 'bronze' : 'other')) ?>">
							<?= $i+1 ?>
						</div>
						<div class="flex-grow-1">
							<div class="d-flex justify-content-between mb-2">
								<strong style="font-size: 1.1rem; color: #212529;"><?= htmlspecialchars(($d['oaci'] ? $d['oaci'].' – ' : '').$d['nom']) ?></strong>
								<span class="badge" style="background: linear-gradient(135deg, #003a64, #1e6ba8); font-size: 1rem;"><?= (int)$d['nb'] ?> sorties</span>
							</div>
							<div class="progress" style="height: 12px; background: #e9ecef;">
								<div class="ranking-bar" style="width: <?= $pct ?>%"></div>
							</div>
						</div>
					</div>
				</a>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if (!empty($top_global) && count($top_global) >= 3): ?>
	<div class="chart-card" style="animation-delay: 0.7s">
		<h2 class="h3 text-center mb-4"><i class="bi bi-trophy-fill text-warning"></i> Podium Général</h2>
		<div class="podium-container">
			<a href="editer_membre.php?id=<?= $top_global[1]['id'] ?>" class="podium-item podium-2 text-decoration-none" style="color: white;">
				<div class="podium-avatar">
					<?php 
					$u2 = $top_global[1];
					$photoU2 = $pdo->prepare('SELECT photo_path FROM users WHERE id = ?');
					$photoU2->execute([$u2['id']]);
					$photoPath2 = $photoU2->fetchColumn() ?: '/assets/img/avatar-placeholder.svg';
					?>
					<img src="<?= htmlspecialchars($photoPath2) ?>" alt="<?= htmlspecialchars($u2['prenom'].' '.$u2['nom']) ?>">
				</div>
				<div class="podium-base">
					<div class="podium-rank">2</div>
					<div style="margin-top: 1rem; font-size: 0.95rem;"><?= htmlspecialchars($u2['prenom']) ?></div>
					<div style="font-size: 0.85rem; opacity: 0.9;"><?= htmlspecialchars($u2['nom']) ?></div>
					<div style="font-size: 1.8rem; margin-top: 0.5rem;"><?= $u2['total'] ?></div>
				</div>
			</a>
			<a href="editer_membre.php?id=<?= $top_global[0]['id'] ?>" class="podium-item podium-1 text-decoration-none" style="color: white;">
				<div class="podium-avatar">
					<?php 
					$u1 = $top_global[0];
					$photoU1 = $pdo->prepare('SELECT photo_path FROM users WHERE id = ?');
					$photoU1->execute([$u1['id']]);
					$photoPath1 = $photoU1->fetchColumn() ?: '/assets/img/avatar-placeholder.svg';
					?>
					<img src="<?= htmlspecialchars($photoPath1) ?>" alt="<?= htmlspecialchars($u1['prenom'].' '.$u1['nom']) ?>">
				</div>
				<div class="podium-base">
					<div class="podium-rank">🏆</div>
					<div style="margin-top: 1rem; font-size: 1.1rem;"><?= htmlspecialchars($u1['prenom']) ?></div>
					<div style="font-size: 0.95rem; opacity: 0.9;"><?= htmlspecialchars($u1['nom']) ?></div>
					<div style="font-size: 2.2rem; margin-top: 0.5rem;"><?= $u1['total'] ?></div>
				</div>
			</a>
			<a href="editer_membre.php?id=<?= $top_global[2]['id'] ?>" class="podium-item podium-3 text-decoration-none" style="color: white;">
				<div class="podium-avatar">
					<?php 
					$u3 = $top_global[2];
					$photoU3 = $pdo->prepare('SELECT photo_path FROM users WHERE id = ?');
					$photoU3->execute([$u3['id']]);
					$photoPath3 = $photoU3->fetchColumn() ?: '/assets/img/avatar-placeholder.svg';
					?>
					<img src="<?= htmlspecialchars($photoPath3) ?>" alt="<?= htmlspecialchars($u3['prenom'].' '.$u3['nom']) ?>">
				</div>
				<div class="podium-base">
					<div class="podium-rank">3</div>
					<div style="margin-top: 1rem; font-size: 0.9rem;"><?= htmlspecialchars($u3['prenom']) ?></div>
					<div style="font-size: 0.8rem; opacity: 0.9;"><?= htmlspecialchars($u3['nom']) ?></div>
					<div style="font-size: 1.6rem; margin-top: 0.5rem;"><?= $u3['total'] ?></div>
				</div>
			</a>
		</div>
	</div>
	<?php endif; ?>

	<div class="row g-3 mb-4">
		<div class="col-lg-6">
			<div class="gn-card">
				<div class="gn-card-header d-flex justify-content-between align-items-center">
					<h3 class="gn-card-title mb-0">Top 10 – Événements</h3>
					<?php if ($best_evt): ?>
						<small class="text-muted">Meilleur évènement : <a href="evenement_inscription_detail.php?id=<?= (int)$best_evt['id'] ?>"><?= htmlspecialchars($best_evt['titre'] ?? 'Évènement') ?></a> (<?= (int)$best_evt['total'] ?>)</small>
					<?php endif; ?>
				</div>
				<div class="p-3">
					<?php if (empty($top_evt)): ?>
						<div class="text-muted">Aucune donnée.</div>
					<?php else: foreach ($top_evt as $i => $m): ?>
						<?php $pct = (array_sum(array_column($top_evt,'evenements'))>0) ? round($m['evenements']/max(1,array_sum(array_column($top_evt,'evenements')))*100) : 0; ?>
						<div class="mb-3">
							<div class="d-flex justify-content-between">
								<strong><?= $i+1 ?>. <?= htmlspecialchars($m['prenom'].' '.$m['nom']) ?></strong>
								<span><?= (int)$m['evenements'] ?></span>
							</div>
							<div class="progress" style="height: 10px;">
								<div class="progress-bar bg-success" role="progressbar" style="width: <?= $pct ?>%"></div>
							</div>
						</div>
					<?php endforeach; endif; ?>
				</div>
			</div>
		</div>
		<div class="col-lg-6">
			<div class="gn-card">
				<div class="gn-card-header d-flex justify-content-between align-items-center">
					<h3 class="gn-card-title mb-0">Top 10 – Sorties</h3>
					<?php if ($best_sortie): ?>
						<small class="text-muted">Meilleure sortie : <a href="sortie_participants.php?id=<?= (int)$best_sortie['id'] ?>"><?= htmlspecialchars($best_sortie['titre'] ?? 'Sortie') ?></a> (<?= (int)$best_sortie['total'] ?>)</small>
					<?php endif; ?>
				</div>
				<div class="p-3">
					<?php if (empty($top_sorties)): ?>
						<div class="text-muted">Aucune donnée.</div>
					<?php else: foreach ($top_sorties as $i => $m): ?>
						<?php $pct = (array_sum(array_column($top_sorties,'sorties'))>0) ? round($m['sorties']/max(1,array_sum(array_column($top_sorties,'sorties')))*100) : 0; ?>
						<div class="mb-3">
							<div class="d-flex justify-content-between">
								<strong><?= $i+1 ?>. <?= htmlspecialchars($m['prenom'].' '.$m['nom']) ?></strong>
								<span><?= (int)$m['sorties'] ?></span>
							</div>
							<div class="progress" style="height: 10px;">
								<div class="progress-bar bg-info" role="progressbar" style="width: <?= $pct ?>%"></div>
							</div>
						</div>
					<?php endforeach; endif; ?>
				</div>
			</div>
		</div>
	</div>

	<div class="chart-card" style="animation-delay: 1.1s">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h2 class="h3 mb-0"><i class="bi bi-bar-chart-fill text-primary"></i> Classement Général</h2>
			<span class="badge bg-primary">Top 10</span>
		</div>
		<div>
			<div class="table-responsive">
				<table class="table table-hover mb-0 align-middle">
					<thead style="background: linear-gradient(135deg, #003a64, #0a548b); color: white;">
						<tr>
							<th style="border:none; padding: 1rem;">#</th>
							<th style="border:none; padding: 1rem;">Membre</th>
							<th style="border:none; padding: 1rem;">Sorties</th>
							<th style="border:none; padding: 1rem;">Événements</th>
							<th style="border:none; padding: 1rem;">Total</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($top_global)): ?>
							<tr><td colspan="5" class="text-center text-muted">Aucune donnée.</td></tr>
						<?php else: foreach ($top_global as $i => $m): ?>
							<?php $pct = (array_sum(array_column($top_global,'total'))>0) ? round($m['total']/max(1,array_sum(array_column($top_global,'total')))*100) : 0; ?>
							<tr>
								<td><?= $i+1 ?></td>
								<td><strong><?= htmlspecialchars($m['prenom'].' '.$m['nom']) ?></strong></td>
								<td><span class="badge bg-info"><?= (int)$m['sorties'] ?></span></td>
								<td><span class="badge bg-success"><?= (int)$m['evenements'] ?></span></td>
								<td style="min-width: 220px;">
									<div class="d-flex align-items-center gap-2">
										<span class="badge bg-primary"><?= (int)$m['total'] ?></span>
										<div class="progress flex-grow-1" style="height: 8px;">
											<div class="progress-bar" role="progressbar" style="width: <?= $pct ?>%"></div>
										</div>
									</div>
								</td>
							</tr>
						<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="text-center text-muted small">Ces statistiques sont calculées sur l'ensemble des données disponibles.</div>
</div>

<?php require_once 'footer.php'; ?>

