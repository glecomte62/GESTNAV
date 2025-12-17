<?php
require_once 'config.php';
require_once 'auth.php';

$is_logged_in = is_logged_in();
$is_admin = is_admin();

// Fonction pour logger une action
function log_document_action($pdo, $action, $document_id = null, $category_id = null, $details = '') {
    if (!isset($_SESSION['user_id'])) return;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO document_logs (user_id, action, document_id, category_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $document_id,
            $category_id,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        // Ignorer les erreurs de log
    }
}

// Vérifier si les colonnes machine_id et search_tags existent
$has_machine_support = false;
try {
    $pdo->query("SELECT machine_id, search_tags FROM documents LIMIT 1");
    $has_machine_support = true;
} catch (PDOException $e) {
    // Les colonnes n'existent pas encore
}

// Récupérer les machines si disponible
$machines = [];
if ($has_machine_support) {
    $stmt = $pdo->query("SELECT id, immatriculation, nom, type FROM machines WHERE actif = 1 ORDER BY immatriculation");
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les catégories accessibles
if ($is_admin) {
    $stmt = $pdo->query("
        SELECT c.*, COUNT(d.id) as doc_count 
        FROM document_categories c
        LEFT JOIN documents d ON c.id = d.category_id
        GROUP BY c.id
        ORDER BY c.display_order, c.name
    ");
} elseif ($is_logged_in) {
    $stmt = $pdo->query("
        SELECT c.*, COUNT(d.id) as doc_count 
        FROM document_categories c
        LEFT JOIN documents d ON c.id = d.category_id AND d.access_level IN ('members', 'public')
        WHERE c.access_level IN ('members', 'public')
        GROUP BY c.id
        ORDER BY c.display_order, c.name
    ");
} else {
    $stmt = $pdo->query("
        SELECT c.*, COUNT(d.id) as doc_count 
        FROM document_categories c
        LEFT JOIN documents d ON c.id = d.category_id AND d.access_level = 'public'
        WHERE c.access_level = 'public'
        GROUP BY c.id
        ORDER BY c.display_order, c.name
    ");
}
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtres
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$filter_machine = isset($_GET['machine']) ? (int)$_GET['machine'] : 0;

// Gérer le téléchargement
if (isset($_GET['download'])) {
    $doc_id = (int)$_GET['download'];
    
    // Vérifier les droits d'accès
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doc) {
        $can_access = false;
        if ($is_admin) {
            $can_access = true;
        } elseif ($doc['access_level'] === 'public') {
            $can_access = true;
        } elseif ($doc['access_level'] === 'members' && $is_logged_in) {
            $can_access = true;
        }
        
        if ($can_access) {
            // Incrémenter le compteur
            $pdo->prepare("UPDATE documents SET download_count = download_count + 1 WHERE id = ?")->execute([$doc_id]);
            
            // Logger
            log_document_action($pdo, 'document_download', $doc_id, $doc['category_id'], 
                "Téléchargement: " . $doc['original_filename']);
            
            // Télécharger
            $file_path = __DIR__ . '/uploads/documents/' . $doc['filename'];
            if (file_exists($file_path)) {
                header('Content-Type: ' . $doc['file_type']);
                header('Content-Disposition: attachment; filename="' . $doc['original_filename'] . '"');
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
                exit;
            }
        }
    }
}

// Construire la requête des documents
$where = ["1=1"];
$params = [];

// Filtres d'accès
if ($is_admin) {
    // Admin voit tout
} elseif ($is_logged_in) {
    $where[] = "d.access_level IN ('members', 'public')";
} else {
    $where[] = "d.access_level = 'public'";
}

// Recherche
if ($search_query && $has_machine_support) {
    $where[] = "(d.title LIKE ? OR d.description LIKE ? OR d.original_filename LIKE ? OR d.search_tags LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
} elseif ($search_query) {
    $where[] = "(d.title LIKE ? OR d.description LIKE ? OR d.original_filename LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Filtre catégorie
if ($filter_category > 0) {
    $where[] = "d.category_id = ?";
    $params[] = $filter_category;
}

// Filtre machine
if ($filter_machine > 0 && $has_machine_support) {
    $where[] = "d.machine_id = ?";
    $params[] = $filter_machine;
}

// Requête finale
if ($has_machine_support) {
    $sql = "
        SELECT d.*, c.name as category_name, c.icon as category_icon,
               m.immatriculation as machine_immat, m.nom as machine_nom
        FROM documents d
        LEFT JOIN document_categories c ON d.category_id = c.id
        LEFT JOIN machines m ON d.machine_id = m.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY d.created_at DESC
    ";
} else {
    $sql = "
        SELECT d.*, c.name as category_name, c.icon as category_icon
        FROM documents d
        LEFT JOIN document_categories c ON d.category_id = c.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY d.created_at DESC
    ";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Logger la consultation de catégorie
if ($filter_category > 0 && $is_logged_in) {
    log_document_action($pdo, 'category_view', null, $filter_category, "Consultation de catégorie");
}

require 'header.php';
?>

<style>
.docs-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.search-hero {
    background: linear-gradient(135deg, #004b8d 0%, #00a0c6 100%);
    color: white;
    padding: 3rem 2rem;
    border-radius: 1rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.search-hero h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.search-box {
    position: relative;
    max-width: 800px;
    margin: 0 auto;
}

.search-box input {
    width: 100%;
    padding: 1rem 1rem 1rem 3.5rem;
    border-radius: 50px;
    border: none;
    font-size: 1.1rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.search-box i {
    position: absolute;
    left: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.5rem;
    color: #6b7280;
}

.filters-bar {
    background: white;
    padding: 1.5rem;
    border-radius: 1rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
}

.filter-chips {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    align-items: center;
}

.chip {
    padding: 0.5rem 1.25rem;
    border-radius: 50px;
    border: 2px solid #e5e7eb;
    background: white;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 500;
    text-decoration: none;
    color: #374151;
}

.chip:hover {
    border-color: #00a0c6;
    background: #f0f9ff;
    color: #00a0c6;
}

.chip.active {
    background: linear-gradient(135deg, #004b8d 0%, #00a0c6 100%);
    border-color: #004b8d;
    color: white;
}

.chip .count {
    opacity: 0.7;
    font-size: 0.85rem;
    margin-left: 0.25rem;
}

.docs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.doc-card {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    transition: all 0.3s;
    border: 2px solid transparent;
}

.doc-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    border-color: #00a0c6;
}

.doc-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.doc-icon {
    font-size: 3rem;
    flex-shrink: 0;
}

.doc-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.doc-filename {
    font-size: 0.85rem;
    color: #6b7280;
    font-family: monospace;
}

.doc-description {
    color: #4b5563;
    margin-bottom: 1rem;
    line-height: 1.5;
}

.doc-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
}

.badge-category {
    background: #dbeafe;
    color: #1e40af;
}

.badge-machine {
    background: #fef3c7;
    color: #92400e;
}

.badge-access {
    background: #f3e8ff;
    color: #6b21a8;
}

.doc-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.tag {
    padding: 0.25rem 0.75rem;
    background: #f3f4f6;
    color: #374151;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 500;
}

.doc-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.doc-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: #6b7280;
}

.btn-download {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #004b8d 0%, #00a0c6 100%);
    color: white;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-download:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(0,160,198,0.3);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #6b7280;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.results-count {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
}

.clear-filters {
    color: #00a0c6;
    text-decoration: none;
    font-weight: 500;
}

.clear-filters:hover {
    text-decoration: underline;
}
</style>

<div class="docs-container">
    <!-- Hero de recherche -->
    <div class="search-hero">
        <h1>📚 Bibliothèque de documents</h1>
        <p style="font-size: 1.1rem; opacity: 0.9; margin-bottom: 2rem;">
            Retrouvez tous vos documents techniques, manuels et ressources
        </p>
        
        <form method="GET" class="search-box">
            <i class="bi bi-search"></i>
            <input 
                type="text" 
                name="search" 
                placeholder="Rechercher par titre, description, nom de fichier<?= $has_machine_support ? ', tags...' : '...' ?>" 
                value="<?= htmlspecialchars($search_query) ?>"
                autofocus
            >
            <?php if ($filter_category): ?>
                <input type="hidden" name="category" value="<?= $filter_category ?>">
            <?php endif; ?>
            <?php if ($filter_machine): ?>
                <input type="hidden" name="machine" value="<?= $filter_machine ?>">
            <?php endif; ?>
        </form>
    </div>

    <!-- Filtres par catégorie -->
    <?php if (count($categories) > 0): ?>
    <div class="filters-bar">
        <div class="filter-chips">
            <strong style="color: #6b7280; margin-right: 0.5rem;">📂 Catégories:</strong>
            <a href="?<?= $search_query ? 'search=' . urlencode($search_query) : '' ?><?= $filter_machine ? '&machine=' . $filter_machine : '' ?>" 
               class="chip <?= !$filter_category ? 'active' : '' ?>">
                Toutes <span class="count">(<?= array_sum(array_column($categories, 'doc_count')) ?>)</span>
            </a>
            <?php foreach ($categories as $cat): ?>
                <?php if ($cat['doc_count'] > 0): ?>
                <a href="?category=<?= $cat['id'] ?><?= $search_query ? '&search=' . urlencode($search_query) : '' ?><?= $filter_machine ? '&machine=' . $filter_machine : '' ?>" 
                   class="chip <?= $filter_category == $cat['id'] ? 'active' : '' ?>">
                    <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?> 
                    <span class="count">(<?= $cat['doc_count'] ?>)</span>
                </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtres par machine -->
    <?php if ($has_machine_support && count($machines) > 0): ?>
    <div class="filters-bar">
        <div class="filter-chips">
            <strong style="color: #6b7280; margin-right: 0.5rem;">✈️ Machines:</strong>
            <a href="?<?= $search_query ? 'search=' . urlencode($search_query) : '' ?><?= $filter_category ? '&category=' . $filter_category : '' ?>" 
               class="chip <?= !$filter_machine ? 'active' : '' ?>">
                Toutes
            </a>
            <?php foreach ($machines as $machine): ?>
                <a href="?machine=<?= $machine['id'] ?><?= $search_query ? '&search=' . urlencode($search_query) : '' ?><?= $filter_category ? '&category=' . $filter_category : '' ?>" 
                   class="chip <?= $filter_machine == $machine['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($machine['immatriculation']) ?> - <?= htmlspecialchars($machine['nom']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- En-tête des résultats -->
    <?php if ($search_query || $filter_category || $filter_machine): ?>
    <div class="results-header">
        <div class="results-count">
            📊 <?= count($documents) ?> document<?= count($documents) > 1 ? 's' : '' ?> trouvé<?= count($documents) > 1 ? 's' : '' ?>
        </div>
        <a href="documents.php" class="clear-filters">✕ Effacer les filtres</a>
    </div>
    <?php endif; ?>

    <!-- Grille de documents -->
    <?php if (count($documents) > 0): ?>
    <div class="docs-grid">
        <?php foreach ($documents as $doc): ?>
            <div class="doc-card">
                <div class="doc-header">
                    <div class="doc-icon">
                        <?php
                        $ext = pathinfo($doc['original_filename'], PATHINFO_EXTENSION);
                        echo match(strtolower($ext)) {
                            'pdf' => '📕',
                            'doc', 'docx' => '📘',
                            'xls', 'xlsx' => '📗',
                            'ppt', 'pptx' => '📙',
                            'jpg', 'jpeg', 'png', 'gif' => '🖼️',
                            'zip', 'rar' => '📦',
                            'mp4', 'avi', 'mov' => '🎬',
                            default => '📄'
                        };
                        ?>
                    </div>
                    <div style="flex: 1;">
                        <div class="doc-title"><?= htmlspecialchars($doc['title']) ?></div>
                        <div class="doc-filename"><?= htmlspecialchars($doc['original_filename']) ?></div>
                    </div>
                </div>

                <?php if ($doc['description']): ?>
                <div class="doc-description">
                    <?= nl2br(htmlspecialchars($doc['description'])) ?>
                </div>
                <?php endif; ?>

                <div class="doc-meta">
                    <span class="meta-badge badge-category">
                        <?= $doc['category_icon'] ?> <?= htmlspecialchars($doc['category_name']) ?>
                    </span>
                    
                    <?php if ($has_machine_support && !empty($doc['machine_immat'])): ?>
                    <span class="meta-badge badge-machine">
                        ✈️ <?= htmlspecialchars($doc['machine_immat']) ?>
                    </span>
                    <?php endif; ?>

                    <?php if ($is_admin): ?>
                    <span class="meta-badge badge-access">
                        <?= $doc['access_level'] === 'admin_only' ? '🔒 Admin' : ($doc['access_level'] === 'members' ? '👥 Membres' : '🌐 Public') ?>
                    </span>
                    <?php endif; ?>
                </div>

                <?php if ($has_machine_support && !empty($doc['search_tags'])): ?>
                <div class="doc-tags">
                    <?php 
                    $tags = array_map('trim', explode(',', $doc['search_tags']));
                    foreach ($tags as $tag): 
                        if ($tag):
                    ?>
                        <span class="tag">🏷️ <?= htmlspecialchars($tag) ?></span>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                <?php endif; ?>

                <div class="doc-footer">
                    <div class="doc-stats">
                        <span>📅 <?= date('d/m/Y', strtotime($doc['created_at'])) ?></span>
                        <span>📦 <?= round($doc['file_size'] / 1024, 1) ?> KB</span>
                        <span>⬇️ <?= $doc['download_count'] ?></span>
                    </div>
                    <a href="?download=<?= $doc['id'] ?>" class="btn-download">
                        <i class="bi bi-download"></i> Télécharger
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <h3>Aucun document trouvé</h3>
        <p>
            <?php if ($search_query || $filter_category || $filter_machine): ?>
                Essayez de modifier vos critères de recherche
            <?php else: ?>
                Aucun document n'est disponible pour le moment
            <?php endif; ?>
        </p>
        <?php if ($search_query || $filter_category || $filter_machine): ?>
            <a href="documents.php" class="btn-download" style="margin-top: 1rem;">
                Voir tous les documents
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
