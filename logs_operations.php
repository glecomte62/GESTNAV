<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
if (!is_admin()) {
    header('Location: acces_refuse.php?message=' . urlencode('La consultation des logs est réservée aux administrateurs') . '&redirect=index.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$limit = 200;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(u.email LIKE ? OR ol.nom LIKE ? OR ol.prenom LIKE ? OR ol.action LIKE ? OR ol.details LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($from !== '') {
    $where[] = "ol.created_at >= ?";
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = "ol.created_at <= ?";
    $params[] = $to . ' 23:59:59';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT ol.*, u.email FROM operation_logs ol
        LEFT JOIN users u ON u.id = ol.user_id
        $whereSql
        ORDER BY ol.created_at DESC
        LIMIT $limit";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Libellés lisibles pour les codes d'actions
$actionLabels = [
    'event_register' => "Inscription événement",
    'event_update' => "Inscription événement modifiée",
    'event_cancel' => "Inscription événement annulée",
    'sortie_inscription' => "Inscription sortie",
    'sortie_inscription_duplicate' => "Inscription sortie déjà existante",
    'sortie_create' => "Sortie créée",
    'sortie_update' => "Sortie modifiée",
    'event_update_admin' => "Événement modifié",
    'event_cover_delete' => "Illustration supprimée",
];

include 'header.php';
?>
<div class="container mt-3">
    <h2 class="mb-3">Logs des opérations</h2>

    <form method="get" class="row g-2 mb-3">
        <div class="col-sm-4">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nom, email, action ou détails" class="form-control">
        </div>
        <div class="col-sm-3">
            <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control" placeholder="Depuis">
        </div>
        <div class="col-sm-3">
            <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control" placeholder="Jusqu'au">
        </div>
        <div class="col-sm-2 d-grid">
            <button class="btn btn-primary">Filtrer</button>
        </div>
    </form>

    <div class="table-responsive gn-card">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width: 150px;">Date</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Email</th>
                    <th>Action</th>
                    <th>IP</th>
                    <th>Détails</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td style="white-space: nowrap;"><?= htmlspecialchars($r['created_at']) ?></td>
                    <td><?= htmlspecialchars($r['nom']) ?></td>
                    <td><?= htmlspecialchars($r['prenom']) ?></td>
                    <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                    <td><code><?= htmlspecialchars($actionLabels[$r['action']] ?? $r['action']) ?></code></td>
                    <td><code><?= htmlspecialchars($r['ip_address']) ?></code></td>
                    <td style="max-width: 420px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?= htmlspecialchars($r['details'] ?? '') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center text-muted">Aucun résultat</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-muted" style="font-size:.85rem;">Affichage limité aux <?= $limit ?> derniers enregistrements.</p>
</div>
<?php include 'footer.php'; ?>
