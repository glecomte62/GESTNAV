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

<div class="container py-4">
	<div class="d-flex align-items-center justify-content-between mb-4">
		<h1 class="m-0">Statistiques du Club</h1>
		<div class="d-flex align-items-center gap-3">
			<form method="get" class="d-flex align-items-center gap-2">
				<select name="range" class="form-select form-select-sm" onchange="this.form.submit()">
					<option value="all" <?= $range==='all'?'selected':'' ?>>Tout le temps</option>
					<option value="last12" <?= $range==='last12'?'selected':'' ?>>12 derniers mois</option>
					<option value="year" <?= $range==='year'?'selected':'' ?>>Année</option>
				</select>
				<select name="y" class="form-select form-select-sm" onchange="this.form.submit()" <?= $range==='year'?'':'disabled' ?> >
					<?php foreach ($years as $yopt): ?>
						<option value="<?= (int)$yopt ?>" <?= ($range==='year' && (int)$yopt===$year)?'selected':'' ?>><?= (int)$yopt ?></option>
					<?php endforeach; ?>
				</select>
				<a class="btn btn-sm btn-outline-secondary" href="stats.php?range=<?= urlencode($range) ?>&y=<?= (int)$year ?>&export=csv">
					<i class="bi bi-download"></i> Export CSV
				</a>
			</form>
			<span class="text-muted small">Généré le <?= date('d/m/Y \à H:i') ?></span>
		</div>
	</div>

	<div class="row g-3 mb-4">
		<div class="col-md-3">
			<div class="gn-card h-100">
				<div class="gn-card-header"><h3 class="gn-card-title">Événements</h3></div>
				<div class="p-3">
					<div class="display-5 fw-bold mb-1"><?= $nb_evenements ?></div>
					<small class="text-muted">créés</small>
				</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="gn-card h-100">
				<div class="gn-card-header"><h3 class="gn-card-title">Participations (événements)</h3></div>
				<div class="p-3">
					<div class="display-5 fw-bold mb-1"><?= $participants_evenements ?></div>
					<small class="text-muted">personnes confirmées</small>
				</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="gn-card h-100">
				<div class="gn-card-header"><h3 class="gn-card-title">Sorties</h3></div>
				<div class="p-3">
					<div class="display-5 fw-bold mb-1"><?= $nb_sorties ?></div>
					<small class="text-muted">créées</small>
				</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="gn-card h-100">
				<div class="gn-card-header"><h3 class="gn-card-title">Participations (sorties)</h3></div>
				<div class="p-3">
					<div class="display-5 fw-bold mb-1"><?= $participants_sorties ?></div>
					<small class="text-muted">inscriptions</small>
				</div>
			</div>
		</div>
	</div>

	<?php if ($hasDestinationId): ?>
	<div class="gn-card mb-4">
		<div class="gn-card-header d-flex justify-content-between align-items-center">
			<h3 class="gn-card-title mb-0">Top 10 – Destinations des sorties</h3>
			<small class="text-muted">Basé sur le champ destination</small>
		</div>
		<div class="p-3">
			<?php if (empty($top_destinations)): ?>
				<div class="text-muted">Aucune donnée.</div>
			<?php else: foreach ($top_destinations as $i => $d): ?>
				<div class="mb-3">
					<div class="d-flex justify-content-between">
						<strong><?= $i+1 ?>. <?= htmlspecialchars(($d['oaci']?($d['oaci'].' – '):'').$d['nom']) ?></strong>
						<span><?= (int)$d['nb'] ?></span>
					</div>
					<div class="progress" style="height: 10px;">
						<?php $sum = array_sum(array_map(fn($x)=> (int)$x['nb'], $top_destinations)); $pct = $sum>0 ? round(((int)$d['nb'])/max(1,$sum)*100) : 0; ?>
						<div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pct ?>%"></div>
					</div>
				</div>
			<?php endforeach; endif; ?>
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

	<div class="gn-card mb-4">
		<div class="gn-card-header">
			<h3 class="gn-card-title">Top 10 – Classement général</h3>
		</div>
		<div class="p-0">
			<div class="table-responsive">
				<table class="table table-hover mb-0 align-middle">
					<thead class="table-light">
						<tr>
							<th>#</th>
							<th>Nom</th>
							<th>Sorties</th>
							<th>Événements</th>
							<th>Total</th>
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

