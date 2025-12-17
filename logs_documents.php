<?php
require_once 'config.php';
require_once 'auth.php';

// Vérifier que l'utilisateur est admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: acces_refuse.php');
    exit;
}

// Vérifier que les tables existent
try {
    $pdo->query("SELECT 1 FROM document_logs LIMIT 1");
} catch (PDOException $e) {
    die("⚠️ Les tables de documents n'existent pas encore. Veuillez exécuter <a href='install_documents.php'>install_documents.php</a> d'abord.");
}

// Filtres
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_user = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$filter_days = isset($_GET['days']) ? (int)$_GET['days'] : 7;

// Construire la requête
$where = ["1=1"];
$params = [];

if ($filter_action) {
    $where[] = "dl.action = ?";
    $params[] = $filter_action;
}

if ($filter_user) {
    $where[] = "dl.user_id = ?";
    $params[] = $filter_user;
}

if ($filter_days > 0) {
    $where[] = "dl.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params[] = $filter_days;
}

$sql = "
    SELECT dl.*, 
           u.prenom, u.nom, u.email,
           d.title as document_title, d.original_filename,
           c.name as category_name, c.icon as category_icon
    FROM document_logs dl
    LEFT JOIN users u ON dl.user_id = u.id
    LEFT JOIN documents d ON dl.document_id = d.id
    LEFT JOIN document_categories c ON dl.category_id = c.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY dl.created_at DESC
    LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les utilisateurs actifs
$users = $pdo->query("
    SELECT DISTINCT u.id, u.prenom, u.nom 
    FROM users u
    INNER JOIN document_logs dl ON u.id = dl.user_id
    ORDER BY u.nom, u.prenom
")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats = $pdo->query("
    SELECT 
        action,
        COUNT(*) as count,
        COUNT(DISTINCT user_id) as users,
        MAX(created_at) as last_action
    FROM document_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL $filter_days DAY)
    GROUP BY action
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

require 'header.php';
?>

<style>
.logs-page {
    max-width: 1600px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.filters-section {
    background: white;
    padding: 1.5rem;
    border-radius: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #00a0c6;
}

.stat-label {
    font-size: 0.85rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #004b8d;
}

.stat-meta {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 0.25rem;
}

.logs-table {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    overflow: hidden;
}

.logs-table table {
    width: 100%;
    border-collapse: collapse;
}

.logs-table th {
    background: #f9fafb;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #e5e7eb;
    position: sticky;
    top: 0;
}

.logs-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f3f4f6;
}

.logs-table tr:hover {
    background: #f9fafb;
}

.action-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}

.action-upload { background: #d1fae5; color: #065f46; }
.action-download { background: #dbeafe; color: #1e40af; }
.action-delete { background: #fee2e2; color: #991b1b; }
.action-update { background: #fef3c7; color: #92400e; }
.action-view { background: #f3e8ff; color: #6b21a8; }
.action-create { background: #d1fae5; color: #065f46; }

.user-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.85rem;
}

.details-text {
    font-size: 0.9rem;
    color: #4b5563;
}

.ip-address {
    font-family: monospace;
    font-size: 0.85rem;
    color: #6b7280;
}
</style>

<div class="logs-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>📊 Logs d'activité - Documents</h1>
        <a href="documents_admin.php" class="btn btn-outline-primary">← Retour à la GED</a>
    </div>

    <!-- Filtres -->
    <div class="filters-section">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Type d'action</label>
                <select name="action" class="form-select">
                    <option value="">Toutes les actions</option>
                    <option value="document_upload" <?= $filter_action === 'document_upload' ? 'selected' : '' ?>>📤 Upload</option>
                    <option value="document_download" <?= $filter_action === 'document_download' ? 'selected' : '' ?>>📥 Téléchargement</option>
                    <option value="document_delete" <?= $filter_action === 'document_delete' ? 'selected' : '' ?>>🗑️ Suppression</option>
                    <option value="category_create" <?= $filter_action === 'category_create' ? 'selected' : '' ?>>➕ Création catégorie</option>
                    <option value="category_update" <?= $filter_action === 'category_update' ? 'selected' : '' ?>>✏️ Modification catégorie</option>
                    <option value="category_delete" <?= $filter_action === 'category_delete' ? 'selected' : '' ?>>🗑️ Suppression catégorie</option>
                    <option value="category_view" <?= $filter_action === 'category_view' ? 'selected' : '' ?>>👁️ Consultation catégorie</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Utilisateur</label>
                <select name="user" class="form-select">
                    <option value="">Tous les utilisateurs</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Période</label>
                <select name="days" class="form-select">
                    <option value="1" <?= $filter_days == 1 ? 'selected' : '' ?>>Dernières 24h</option>
                    <option value="7" <?= $filter_days == 7 ? 'selected' : '' ?>>7 derniers jours</option>
                    <option value="30" <?= $filter_days == 30 ? 'selected' : '' ?>>30 derniers jours</option>
                    <option value="90" <?= $filter_days == 90 ? 'selected' : '' ?>>90 derniers jours</option>
                    <option value="0" <?= $filter_days == 0 ? 'selected' : '' ?>>Tout l'historique</option>
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">🔍 Filtrer</button>
            </div>
        </form>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <?php foreach ($stats as $stat): ?>
            <div class="stat-card">
                <div class="stat-label">
                    <?php
                    $action_labels = [
                        'document_upload' => '📤 Uploads',
                        'document_download' => '📥 Téléchargements',
                        'document_delete' => '🗑️ Suppressions',
                        'category_create' => '➕ Catégories créées',
                        'category_update' => '✏️ Catégories modifiées',
                        'category_delete' => '🗑️ Catégories supprimées',
                        'category_view' => '👁️ Consultations'
                    ];
                    echo $action_labels[$stat['action']] ?? $stat['action'];
                    ?>
                </div>
                <div class="stat-value"><?= $stat['count'] ?></div>
                <div class="stat-meta">
                    <?= $stat['users'] ?> utilisateur<?= $stat['users'] > 1 ? 's' : '' ?>
                    · Dernier: <?= date('d/m à H:i', strtotime($stat['last_action'])) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tableau des logs -->
    <div class="logs-table">
        <table>
            <thead>
                <tr>
                    <th>Date/Heure</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                    <th>Document/Catégorie</th>
                    <th>Détails</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 3rem;">
                            Aucun log trouvé pour ces critères
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap;">
                                <strong><?= date('d/m/Y', strtotime($log['created_at'])) ?></strong><br>
                                <small style="color: #6b7280;"><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                            </td>
                            
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?= strtoupper(substr($log['prenom'] ?? '?', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars(($log['prenom'] ?? 'Système') . ' ' . ($log['nom'] ?? '')) ?></strong><br>
                                        <small style="color: #6b7280;"><?= htmlspecialchars($log['email'] ?? '') ?></small>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <?php
                                $action_class = match(true) {
                                    str_contains($log['action'], 'upload') || str_contains($log['action'], 'create') => 'action-create',
                                    str_contains($log['action'], 'download') => 'action-download',
                                    str_contains($log['action'], 'delete') => 'action-delete',
                                    str_contains($log['action'], 'update') => 'action-update',
                                    str_contains($log['action'], 'view') => 'action-view',
                                    default => 'action-view'
                                };
                                
                                $action_icon = match(true) {
                                    str_contains($log['action'], 'upload') => '📤',
                                    str_contains($log['action'], 'download') => '📥',
                                    str_contains($log['action'], 'delete') => '🗑️',
                                    str_contains($log['action'], 'create') => '➕',
                                    str_contains($log['action'], 'update') => '✏️',
                                    str_contains($log['action'], 'view') => '👁️',
                                    default => '📄'
                                };
                                ?>
                                <span class="action-badge <?= $action_class ?>">
                                    <?= $action_icon ?> <?= str_replace('_', ' ', $log['action']) ?>
                                </span>
                            </td>
                            
                            <td>
                                <?php if ($log['document_title']): ?>
                                    <strong><?= htmlspecialchars($log['document_title']) ?></strong><br>
                                    <small style="color: #6b7280;"><?= htmlspecialchars($log['original_filename']) ?></small>
                                <?php elseif ($log['category_name']): ?>
                                    <?= $log['category_icon'] ?> <strong><?= htmlspecialchars($log['category_name']) ?></strong>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <span class="details-text"><?= htmlspecialchars($log['details']) ?></span>
                            </td>
                            
                            <td>
                                <span class="ip-address"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></span><br>
                                <small style="color: #9ca3af;" title="<?= htmlspecialchars($log['user_agent'] ?? '') ?>">
                                    <?php
                                    $ua = $log['user_agent'] ?? '';
                                    if (str_contains($ua, 'Chrome')) echo '🌐 Chrome';
                                    elseif (str_contains($ua, 'Firefox')) echo '🦊 Firefox';
                                    elseif (str_contains($ua, 'Safari')) echo '🧭 Safari';
                                    elseif (str_contains($ua, 'Edge')) echo '🌊 Edge';
                                    else echo '🖥️ Browser';
                                    ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (count($logs) >= 500): ?>
        <div class="alert alert-info mt-3">
            ℹ️ Affichage limité aux 500 dernières entrées. Utilisez les filtres pour affiner votre recherche.
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
