<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

// Récupérer les données dynamiquement
$colsStmt = $pdo->query('SHOW COLUMNS FROM users');
$existingCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
$hasTypeMembre = in_array('type_membre', $existingCols);

// Créer la colonne si elle n'existe pas
if (!$hasTypeMembre) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN type_membre VARCHAR(50) DEFAULT 'club' AFTER actif");
        $hasTypeMembre = true;
    } catch (Exception $e) {
        // Colonne existe peut-être déjà
        $colsStmt = $pdo->query('SHOW COLUMNS FROM users');
        $existingCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        $hasTypeMembre = in_array('type_membre', $existingCols);
    }
}

// Gestion des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!is_admin()) {
        http_response_code(403);
        die('Accès refusé.');
    }
    
    $action = $_POST['action'];
    $userId = (int)$_POST['user_id'];
    
    if ($action === 'delete_member') {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        $_SESSION['success'] = 'Membre supprimé';
    } elseif ($action === 'create_member') {
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        
        if (!$nom || !$prenom) {
            $_SESSION['error'] = 'Le nom et prénom sont obligatoires';
        } else {
            $email = trim($_POST['email']);
            $telephone = trim($_POST['telephone']);
            $qualification = trim($_POST['qualification']);
            
            $cols = ['nom', 'prenom', 'email', 'telephone', 'qualification', 'actif'];
            $vals = [$nom, $prenom, $email, $telephone, $qualification, 1];
            
            if ($hasTypeMembre) {
                $type = isset($_POST['type_membre']) && in_array($_POST['type_membre'], ['club', 'invite']) ? $_POST['type_membre'] : 'club';
                $cols[] = 'type_membre';
                $vals[] = $type;
            }
            
            $placeholders = implode(',', array_fill(0, count($vals), '?'));
            $colStr = implode(',', $cols);
            $pdo->prepare("INSERT INTO users ($colStr) VALUES ($placeholders)")->execute($vals);
            $_SESSION['success'] = 'Membre créé avec succès';
        }
    }
    
    header('Location: membres.php');
    exit;
}

// Récupérer les filtres et tri
$filterType = isset($_GET['type']) && in_array($_GET['type'], ['club', 'invite', 'all']) ? $_GET['type'] : 'all';
$filterActif = isset($_GET['actif']) && in_array($_GET['actif'], ['actif', 'inactif', 'all']) ? $_GET['actif'] : 'all';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) && in_array($_GET['sort'], ['nom', 'prenom', 'email', 'type_membre', 'actif', 'qualification']) ? $_GET['sort'] : 'nom';
$sortDir = isset($_GET['dir']) && in_array($_GET['dir'], ['asc', 'desc']) ? $_GET['dir'] : 'asc';

// Construire la requête
$query = "SELECT * FROM users WHERE 1";
$params = [];

if ($hasTypeMembre && $filterType !== 'all') {
    $query .= " AND type_membre = ?";
    $params[] = $filterType;
}

if ($filterActif !== 'all') {
    $query .= " AND actif = ?";
    $params[] = ($filterActif === 'actif') ? 1 : 0;
}

if ($searchTerm) {
    $query .= " AND (nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

$query .= " ORDER BY $sortBy $sortDir";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour générer le lien de tri
function getSortLink($column, $currentSort, $currentDir, $getParams) {
    $newDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
    $newParams = array_merge($getParams, ['sort' => $column, 'dir' => $newDir]);
    $queryString = http_build_query($newParams);
    return "membres.php?" . $queryString;
}

function getSortIcon($column, $currentSort, $currentDir) {
    if ($currentSort !== $column) return '';
    return $currentDir === 'asc' ? ' ▲' : ' ▼';
}

require 'header.php';
?>

<style>
.membres-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 2rem;
    padding: 1.5rem 1.75rem;
    border-radius: 1.25rem;
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: #fff;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
}

.page-header h1 {
    font-size: 1.6rem;
    margin: 0;
    letter-spacing: 0.03em;
}

.page-header-subtitle {
    font-size: 0.95rem;
    opacity: 0.9;
}

.header-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-create {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.5);
    color: #fff;
    padding: 0.7rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.btn-create:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.8);
}

.filter-section {
    background: #f8fbff;
    border-radius: 1rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid #e8ecf1;
}

.filter-group {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}

.filter-search {
    flex: 1;
    min-width: 200px;
}

.filter-search input {
    width: 100%;
    padding: 0.7rem 1rem;
    border: 1px solid #d0d7e2;
    border-radius: 0.5rem;
    font-size: 0.9rem;
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 0.6rem 1.2rem;
    border: 2px solid #d0d7e2;
    background: #fff;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    color: #666;
}

.filter-btn:hover {
    border-color: #004b8d;
    color: #004b8d;
}

.filter-btn.active {
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: #fff;
    border-color: #004b8d;
}

.results-info {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 1.5rem;
}

.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: #0a8a0a;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.alert-error {
    background: rgba(220, 38, 38, 0.1);
    color: #991b1b;
    border: 1px solid rgba(220, 38, 38, 0.3);
}

.members-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.members-table th {
    background: #f8fbff;
    padding: 1rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.85rem;
    color: #004b8d;
    border-bottom: 2px solid #e8ecf1;
}

.members-table th a {
    color: #004b8d;
    text-decoration: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    transition: color 0.2s;
}

.members-table th a:hover {
    color: #00a0c6;
}

.members-table td {
    padding: 0.9rem 1rem;
    border-bottom: 1px solid #e8ecf1;
    font-size: 0.9rem;
}

.members-table tr:hover {
    background: #f8fbff;
}

.member-name {
    font-weight: 600;
    color: #004b8d;
}

.member-email {
    color: #666;
    font-size: 0.85rem;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}

.badge-club {
    background: rgba(0, 75, 141, 0.1);
    color: #004b8d;
}

.badge-invite {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
}

.badge-actif {
    background: rgba(16, 185, 129, 0.1);
    color: #0a8a0a;
}

.badge-inactif {
    background: rgba(200, 0, 0, 0.1);
    color: #b02525;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-action {
    padding: 0.4rem 0.8rem;
    border: none;
    border-radius: 0.4rem;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.btn-edit {
    background: rgba(0, 75, 141, 0.1);
    color: #004b8d;
}

.btn-edit:hover {
    background: rgba(0, 75, 141, 0.2);
}

.btn-delete {
    background: rgba(220, 38, 38, 0.1);
    color: #991b1b;
}

.btn-delete:hover {
    background: rgba(220, 38, 38, 0.2);
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #999;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-overlay.active {
    display: flex;
}

.modal {
    background: #fff;
    border-radius: 1rem;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
    font-size: 1.2rem;
    font-weight: 700;
    color: #004b8d;
    margin-bottom: 1rem;
}

.modal-body {
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 0.4rem;
    color: #333;
}

.form-input, .form-select {
    width: 100%;
    padding: 0.6rem 0.9rem;
    border: 1px solid #d0d7e2;
    border-radius: 0.5rem;
    font-size: 0.9rem;
    box-sizing: border-box;
}

.modal-actions {
    display: flex;
    gap: 0.8rem;
}

.btn {
    padding: 0.6rem 1.2rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
    flex: 1;
}

.btn-primary {
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: #fff;
}

.btn-primary:hover {
    filter: brightness(1.1);
}

.btn-secondary {
    background: #f0f3f8;
    color: #666;
}

.btn-secondary:hover {
    background: #e7ecf4;
}
</style>

<div class="membres-page">
    <div class="page-header">
        <div>
            <h1>Gestion des membres</h1>
            <div class="page-header-subtitle">Gérez les membres du club</div>
        </div>
        <div class="header-action">
            <button class="btn-create" onclick="openCreateModal()">➕ Nouveau membre</button>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            ✓ <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            ✕ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="filter-section">
        <form method="get" action="membres.php">
            <div class="filter-group">
                <div class="filter-search">
                    <input type="text" name="search" placeholder="🔍 Chercher par nom, email..." value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
                <div class="filter-buttons">
                    <strong style="font-size: 0.9rem; color: #666;">Type:</strong>
                    <button type="submit" name="type" value="all" class="filter-btn <?= $filterType === 'all' ? 'active' : '' ?>">Tous</button>
                    <?php if ($hasTypeMembre): ?>
                    <button type="submit" name="type" value="club" class="filter-btn <?= $filterType === 'club' ? 'active' : '' ?>">🏢 CLUB</button>
                    <button type="submit" name="type" value="invite" class="filter-btn <?= $filterType === 'invite' ? 'active' : '' ?>">👥 INVITE</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="filter-group" style="margin-top: 0.8rem;">
                <div style="flex: 1;"></div>
                <div class="filter-buttons">
                    <strong style="font-size: 0.9rem; color: #666;">Statut:</strong>
                    <button type="submit" name="actif" value="all" class="filter-btn <?= $filterActif === 'all' ? 'active' : '' ?>">Tous</button>
                    <button type="submit" name="actif" value="actif" class="filter-btn <?= $filterActif === 'actif' ? 'active' : '' ?>">✓ Actif</button>
                    <button type="submit" name="actif" value="inactif" class="filter-btn <?= $filterActif === 'inactif' ? 'active' : '' ?>">✕ Inactif</button>
                </div>
            </div>
        </form>
    </div>

    <div class="results-info">
        📊 <?= count($members) ?> membre(s) trouvé(s)
    </div>

    <!-- Tableau des membres -->
    <?php if (empty($members)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">👥</div>
            <p>Aucun membre trouvé</p>
        </div>
    <?php else: ?>
        <table class="members-table">
            <thead>
                <tr>
                    <th><a href="<?= getSortLink('nom', $sortBy, $sortDir, $_GET) ?>">Nom<?= getSortIcon('nom', $sortBy, $sortDir) ?></a></th>
                    <th><a href="<?= getSortLink('email', $sortBy, $sortDir, $_GET) ?>">Email<?= getSortIcon('email', $sortBy, $sortDir) ?></a></th>
                    <th><a href="<?= getSortLink('type_membre', $sortBy, $sortDir, $_GET) ?>">Type<?= getSortIcon('type_membre', $sortBy, $sortDir) ?></a></th>
                    <th><a href="<?= getSortLink('actif', $sortBy, $sortDir, $_GET) ?>">Statut<?= getSortIcon('actif', $sortBy, $sortDir) ?></a></th>
                    <th><a href="<?= getSortLink('qualification', $sortBy, $sortDir, $_GET) ?>">Qualification<?= getSortIcon('qualification', $sortBy, $sortDir) ?></a></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td>
                            <div class="member-name"><?= htmlspecialchars($member['prenom'] . ' ' . $member['nom']) ?></div>
                            <div class="member-email"><?= htmlspecialchars($member['email'] ?? '-') ?></div>
                        </td>
                        <td><?= htmlspecialchars($member['email'] ?? '-') ?></td>
                        <td>
                            <?php if ($hasTypeMembre): ?>
                                <span class="badge <?= ($member['type_membre'] ?? 'club') === 'club' ? 'badge-club' : 'badge-invite' ?>">
                                    <?= ($member['type_membre'] ?? 'club') === 'club' ? '🏢 CLUB' : '👥 INVITE' ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-club">🏢 CLUB</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $member['actif'] ? 'badge-actif' : 'badge-inactif' ?>">
                                <?= $member['actif'] ? '✓ Actif' : '✕ Inactif' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($member['qualification'] ?? '-') ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="editer_membre.php?id=<?= $member['id'] ?>" class="btn-action btn-edit">✏️ Éditer</a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('⚠️ Êtes-vous sûr?');">
                                    <input type="hidden" name="action" value="delete_member">
                                    <input type="hidden" name="user_id" value="<?= $member['id'] ?>">
                                    <button type="submit" class="btn-action btn-delete">🗑️ Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal Création -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">➕ Créer un nouveau membre</div>
        <form method="post">
            <input type="hidden" name="action" value="create_member">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Prénom *</label>
                    <input type="text" name="prenom" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="nom" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="tel" name="telephone" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Qualification</label>
                    <input type="text" name="qualification" class="form-input" placeholder="Pilote, Élève-Pilote...">
                </div>

                <?php if ($hasTypeMembre): ?>
                <div class="form-group">
                    <label class="form-label">Type de membre</label>
                    <select name="type_membre" class="form-input">
                        <option value="club" selected>🏢 CLUB</option>
                        <option value="invite">👥 INVITE</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Créer le membre</button>
                <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.add('active');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.remove('active');
}

document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) closeCreateModal();
});
</script>

<?php require 'footer.php'; ?>
