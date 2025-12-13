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
    $where[] = "(u.email LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($from !== '') {
    $where[] = "cl.created_at >= ?";
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = "cl.created_at <= ?";
    $params[] = $to . ' 23:59:59';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT cl.*, u.email FROM connection_logs cl
        LEFT JOIN users u ON u.id = cl.user_id
        $whereSql
        ORDER BY cl.created_at DESC
        LIMIT $limit";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

include 'header.php';
?>
<div class="container mt-3">
    <h2 class="mb-3">Logs de connexions</h2>

    <form method="get" class="row g-2 mb-3">
        <div class="col-sm-4">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nom, prénom ou email" class="form-control">
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
                    <th>Date</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Email</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['created_at']) ?></td>
                    <td><?= htmlspecialchars($r['nom']) ?></td>
                    <td><?= htmlspecialchars($r['prenom']) ?></td>
                    <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                    <td><code><?= htmlspecialchars($r['ip_address']) ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted">Aucun résultat</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-muted" style="font-size:.85rem;">Affichage limité aux <?= $limit ?> derniers enregistrements.</p>
</div>
<?php include 'footer.php'; ?>
