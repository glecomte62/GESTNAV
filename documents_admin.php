<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'document_parser.php';
require_once 'document_analyzer.php';
require_once 'document_classifier.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: acces_refuse.php');
    exit;
}

$message = '';
$error = '';

function log_document_action($pdo, $action, $document_id = null, $category_id = null, $details = '') {
    $stmt = $pdo->prepare("
        INSERT INTO document_logs (user_id, action, document_id, category_id, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action,
        $document_id,
        $category_id,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

// Traiter les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'save_category') {
            $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $icon = trim($_POST['icon']);
            $access_level = $_POST['access_level'];
            $display_order = (int)$_POST['display_order'];

            if ($category_id > 0) {
                $stmt = $pdo->prepare("UPDATE document_categories SET name = ?, description = ?, icon = ?, access_level = ?, display_order = ? WHERE id = ?");
                $stmt->execute([$name, $description, $icon, $access_level, $display_order, $category_id]);
                log_document_action($pdo, 'category_update', null, $category_id, "Catégorie modifiée: $name");
                $message = "✅ Catégorie modifiée";
            } else {
                $stmt = $pdo->prepare("INSERT INTO document_categories (name, description, icon, access_level, display_order) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $icon, $access_level, $display_order]);
                $new_id = $pdo->lastInsertId();
                log_document_action($pdo, 'category_create', null, $new_id, "Catégorie créée: $name");
                $message = "✅ Catégorie créée";
            }
        }

        if (isset($_POST['action']) && $_POST['action'] === 'delete_category') {
            $category_id = (int)$_POST['category_id'];
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE category_id = ?");
            $stmt->execute([$category_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Impossible : la catégorie contient des documents");
            }
            $stmt = $pdo->prepare("DELETE FROM document_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            log_document_action($pdo, 'category_delete', null, $category_id, "Catégorie supprimée");
            $message = "✅ Catégorie supprimée";
        }

        if (isset($_POST['action']) && $_POST['action'] === 'upload_document') {
            $category_id = (int)$_POST['category_id'];
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $access_level = $_POST['access_level'];

            $has_machine_support = false;
            try {
                $pdo->query("SELECT machine_id, search_tags FROM documents LIMIT 1");
                $has_machine_support = true;
            } catch (PDOException $e) {}

            $machine_id = null;
            $search_tags = '';
            $document_date = null;
            if ($has_machine_support) {
                $machine_id = isset($_POST['machine_id']) && $_POST['machine_id'] > 0 ? (int)$_POST['machine_id'] : null;
                $search_tags = isset($_POST['search_tags']) ? trim($_POST['search_tags']) : '';
                $document_date = isset($_POST['document_date']) && !empty($_POST['document_date']) ? $_POST['document_date'] : null;
            }

            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Erreur upload");
            }

            $file = $_FILES['file'];
            $original_filename = basename($file['name']);
            $file_size = $file['size'];
            $file_type = $file['type'];
            $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;

            $upload_dir = __DIR__ . '/uploads/documents';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $destination = $upload_dir . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new Exception("Erreur sauvegarde");
            }

            // ========================================
            // CLASSIFICATION AUTOMATIQUE
            // ========================================
            $auto_classification = null;
            $extracted_metadata = [];
            $suggested_machine = null;
            $auto_tags = '';
            
            try {
                // 1. Parser le document
                $parser = new DocumentParser($destination, $file_type);
                if ($parser->parse()) {
                    $extracted_text = $parser->getCleanText();
                    
                    // 2. Analyser et extraire les métadonnées
                    $analyzer = new DocumentAnalyzer($extracted_text);
                    $extracted_metadata = $analyzer->analyze();
                    
                    // 3. Classifier le document
                    $classifier = new DocumentClassifier($pdo, $extracted_text, $extracted_metadata);
                    $auto_classification = $classifier->classify();
                    
                    // 4. Suggérer une machine si immatriculation trouvée
                    $suggested_machine = $classifier->suggestMachine();
                    
                    // 5. Générer des tags automatiques
                    $auto_tags = $classifier->generateTags();
                    
                    // Si aucun tag manuel, utiliser les tags auto
                    if (empty($search_tags) && !empty($auto_tags)) {
                        $search_tags = $auto_tags;
                    }
                    
                    // Si catégorie suggérée avec bonne confiance et aucune catégorie choisie
                    if ($auto_classification['confidence'] >= 70 && $category_id == 0 && $auto_classification['category_id']) {
                        $category_id = $auto_classification['category_id'];
                    }
                    
                    // Si machine suggérée et aucune machine choisie
                    if ($suggested_machine && (!$machine_id || $machine_id == 0)) {
                        $machine_id = $suggested_machine['id'];
                    }
                    
                    // Si date trouvée et aucune date choisie
                    if (!$document_date && !empty($extracted_metadata['most_recent_date'])) {
                        $document_date = $extracted_metadata['most_recent_date'];
                    }
                    
                    // Logger les informations d'analyse
                    $analysis_log = [
                        'classification' => $auto_classification,
                        'metadata' => $extracted_metadata,
                        'suggested_machine' => $suggested_machine,
                        'text_length' => strlen($extracted_text)
                    ];
                    log_document_action($pdo, 'document_analyzed', null, null, 
                        "Analyse auto: " . ($auto_classification['rule_name'] ?? 'aucune') . 
                        " (confiance: " . round($auto_classification['confidence'] ?? 0) . "%)" . 
                        " - " . count($extracted_metadata['dates'] ?? []) . " dates, " . 
                        count($extracted_metadata['immatriculations'] ?? []) . " immat."
                    );
                }
            } catch (Exception $e) {
                // Si erreur d'analyse, continuer sans classification auto
                log_document_action($pdo, 'document_analysis_error', null, null, 
                    "Erreur analyse: " . $e->getMessage()
                );
            }
            // ========================================

            if ($has_machine_support) {
                // Vérifier si la colonne document_date existe
                $has_date_column = false;
                try {
                    $pdo->query("SELECT document_date FROM documents LIMIT 1");
                    $has_date_column = true;
                } catch (PDOException $e) {}
                
                if ($has_date_column) {
                    $stmt = $pdo->prepare("INSERT INTO documents (category_id, machine_id, title, description, filename, original_filename, file_size, file_type, access_level, uploaded_by, search_tags, document_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$category_id, $machine_id, $title, $description, $filename, $original_filename, $file_size, $file_type, $access_level, $_SESSION['user_id'], $search_tags, $document_date]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO documents (category_id, machine_id, title, description, filename, original_filename, file_size, file_type, access_level, uploaded_by, search_tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$category_id, $machine_id, $title, $description, $filename, $original_filename, $file_size, $file_type, $access_level, $_SESSION['user_id'], $search_tags]);
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO documents (category_id, title, description, filename, original_filename, file_size, file_type, access_level, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$category_id, $title, $description, $filename, $original_filename, $file_size, $file_type, $access_level, $_SESSION['user_id']]);
            }
            $document_id = $pdo->lastInsertId();
            
            $machine_info = ($has_machine_support && $machine_id) ? " (Machine ID: $machine_id)" : "";
            log_document_action($pdo, 'document_upload', $document_id, $category_id, "Document uploadé: $original_filename (" . round($file_size/1024, 1) . " KB)$machine_info");

            // Message avec info de classification
            $message = "✅ Document uploadé";
            if ($auto_classification && $auto_classification['confidence'] >= 50) {
                $message .= " - Classifié automatiquement : " . ($auto_classification['rule_name'] ?? 'Inconnu') . 
                           " (" . round($auto_classification['confidence']) . "% confiance)";
            }
            if ($suggested_machine) {
                $message .= " - Machine: " . $suggested_machine['immatriculation'];
            }
        }

        if (isset($_POST['action']) && $_POST['action'] === 'edit_document') {
            $document_id = (int)$_POST['document_id'];
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $category_id = (int)$_POST['category_id'];
            $access_level = $_POST['access_level'];

            $has_machine_support = false;
            try {
                $pdo->query("SELECT machine_id, search_tags FROM documents LIMIT 1");
                $has_machine_support = true;
            } catch (PDOException $e) {}

            if ($has_machine_support) {
                $machine_id = isset($_POST['machine_id']) && $_POST['machine_id'] > 0 ? (int)$_POST['machine_id'] : null;
                $search_tags = isset($_POST['search_tags']) ? trim($_POST['search_tags']) : '';
                $stmt = $pdo->prepare("UPDATE documents SET title = ?, description = ?, category_id = ?, machine_id = ?, search_tags = ?, access_level = ? WHERE id = ?");
                $stmt->execute([$title, $description, $category_id, $machine_id, $search_tags, $access_level, $document_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE documents SET title = ?, description = ?, category_id = ?, access_level = ? WHERE id = ?");
                $stmt->execute([$title, $description, $category_id, $access_level, $document_id]);
            }

            log_document_action($pdo, 'document_update', $document_id, $category_id, "Document modifié: $title");
            $message = "✅ Document modifié";
        }

        if (isset($_POST['action']) && $_POST['action'] === 'delete_document') {
            $document_id = (int)$_POST['document_id'];
            $stmt = $pdo->prepare("SELECT filename FROM documents WHERE id = ?");
            $stmt->execute([$document_id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doc) {
                $file_path = __DIR__ . '/uploads/documents/' . $doc['filename'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                log_document_action($pdo, 'document_delete', $document_id, null, "Document supprimé: " . $doc['filename']);
                $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
                $stmt->execute([$document_id]);
                $message = "✅ Document supprimé";
            }
        }

    } catch (Exception $e) {
        $error = "❌ " . $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT c.*, COUNT(d.id) as doc_count FROM document_categories c LEFT JOIN documents d ON c.id = d.category_id GROUP BY c.id ORDER BY c.display_order, c.name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$has_machine_support = false;
try {
    $pdo->query("SELECT machine_id, search_tags FROM documents LIMIT 1");
    $has_machine_support = true;
} catch (PDOException $e) {}

$machines = [];
if ($has_machine_support) {
    $stmt = $pdo->query("SELECT id, immatriculation, nom, type FROM machines WHERE actif = 1 ORDER BY immatriculation");
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer tous les tags existants
$existing_tags = [];
if ($has_machine_support) {
    $stmt = $pdo->query("SELECT search_tags FROM documents WHERE search_tags IS NOT NULL AND search_tags != ''");
    $all_tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($all_tags as $tags_str) {
        $tags = array_map('trim', explode(',', $tags_str));
        foreach ($tags as $tag) {
            if ($tag && !in_array($tag, $existing_tags)) {
                $existing_tags[] = $tag;
            }
        }
    }
    sort($existing_tags);
}

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_machine = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;
$filter_category = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$where = ["1=1"];
$params = [];

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

if ($filter_machine > 0 && $has_machine_support) {
    $where[] = "d.machine_id = ?";
    $params[] = $filter_machine;
}

if ($filter_category > 0) {
    $where[] = "d.category_id = ?";
    $params[] = $filter_category;
}

if ($has_machine_support) {
    $sql = "SELECT d.*, c.name as category_name, c.icon as category_icon, u.prenom, u.nom, m.immatriculation as machine_immat, m.nom as machine_nom, m.type as machine_type FROM documents d LEFT JOIN document_categories c ON d.category_id = c.id LEFT JOIN users u ON d.uploaded_by = u.id LEFT JOIN machines m ON d.machine_id = m.id WHERE " . implode(" AND ", $where) . " ORDER BY d.created_at DESC";
} else {
    $sql = "SELECT d.*, c.name as category_name, c.icon as category_icon, u.prenom, u.nom FROM documents d LEFT JOIN document_categories c ON d.category_id = c.id LEFT JOIN users u ON d.uploaded_by = u.id WHERE " . implode(" AND ", $where) . " ORDER BY d.created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

require 'header.php';
?>

<!-- PDF.js pour lire les PDFs -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }
</script>

<style>
.admin-container {
    max-width: 1400px;
    margin: 30px auto;
    padding: 0 20px;
}

.admin-header {
    background: linear-gradient(135deg, #004b8d 0%, #00a0c6 100%);
    color: white;
    padding: 40px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.admin-header h1 {
    margin: 0 0 10px 0;
    font-size: 2rem;
}

.admin-header p {
    margin: 0;
    opacity: 0.9;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #004b8d;
}

.stat-card h3 {
    font-size: 2rem;
    margin: 0 0 5px 0;
    color: #004b8d;
}

.stat-card p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.section-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 30px;
    overflow: hidden;
}

.section-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 20px 25px;
    border-bottom: 2px solid #dee2e6;
}

.section-header h2 {
    margin: 0;
    font-size: 1.5rem;
    color: #333;
}

.section-body {
    padding: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #004b8d;
    box-shadow: 0 0 0 3px rgba(0,75,141,0.1);
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #004b8d 0%, #00a0c6 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,75,141,0.3);
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40,167,69,0.3);
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.tag-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    max-height: 200px;
    overflow-y: auto;
}

.tag-suggestion {
    padding: 6px 12px;
    background: white;
    border: 2px solid #004b8d;
    color: #004b8d;
    border-radius: 20px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.tag-suggestion:hover {
    background: #004b8d;
    color: white;
    transform: scale(1.05);
}

.search-filter-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.search-filter-bar input,
.search-filter-bar select {
    flex: 1;
    min-width: 200px;
}

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
}

table td {
    padding: 12px;
    border-bottom: 1px solid #e9ecef;
}

table tr:hover {
    background: #f8f9fa;
}

.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-category {
    background: #dbeafe;
    color: #1e40af;
}

.badge-machine {
    background: #fef3c7;
    color: #92400e;
}

.badge-public {
    background: #d1fae5;
    color: #065f46;
}

.badge-members {
    background: #e0e7ff;
    color: #3730a3;
}

.badge-admin {
    background: #f3e8ff;
    color: #6b21a8;
}

.doc-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.doc-tag {
    padding: 2px 8px;
    background: #e0e0e0;
    border-radius: 10px;
    font-size: 0.75rem;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.category-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 15px;
    transition: all 0.3s ease;
}

.category-card:hover {
    border-color: #004b8d;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.category-card h4 {
    margin: 0 0 10px 0;
    font-size: 1.2rem;
}

.category-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 2px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.modal-body {
    padding: 25px;
}

.drag-drop-zone {
    border: 3px dashed #004b8d;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 20px;
}

.drag-drop-zone:hover {
    border-color: #00a0c6;
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    transform: scale(1.02);
}

.drag-drop-zone.drag-over {
    border-color: #00a0c6;
    background: linear-gradient(135deg, #bbdefb 0%, #90caf9 100%);
    transform: scale(1.05);
}

.drag-drop-zone .icon {
    font-size: 4rem;
    color: #004b8d;
    margin-bottom: 15px;
}

.drag-drop-zone .text {
    font-size: 1.1rem;
    color: #333;
    margin-bottom: 10px;
}

.drag-drop-zone .subtext {
    font-size: 0.9rem;
    color: #666;
}

.file-info {
    background: #e3f2fd;
    border: 2px solid #004b8d;
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
    display: none;
}

.file-info.active {
    display: block;
}

.file-info .file-name {
    font-weight: 600;
    color: #004b8d;
    margin-bottom: 5px;
}

.file-info .file-size {
    color: #666;
    font-size: 0.9rem;
}
</style>

<div class="admin-container">
    <div class="admin-header">
        <h1>📚 Administration des Documents</h1>
        <p>Gérez les catégories, uploadez des documents et organisez votre bibliothèque</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
        <script>
            // Réinitialiser la zone de drop après un upload réussi
            if (document.getElementById('dropZone')) {
                document.getElementById('dropZone').querySelector('.icon').textContent = '📁';
                document.getElementById('dropZone').querySelector('.text').textContent = 'Glissez votre fichier ici';
                document.getElementById('dropZone').querySelector('.subtext').textContent = 'ou cliquez pour sélectionner';
                document.getElementById('fileInfo').classList.remove('active');
            }
        </script>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="stats-cards">
        <div class="stat-card">
            <h3><?= count($documents) ?></h3>
            <p>Documents total</p>
        </div>
        <div class="stat-card">
            <h3><?= count($categories) ?></h3>
            <p>Catégories</p>
        </div>
        <?php if ($has_machine_support): ?>
        <div class="stat-card">
            <h3><?= count($existing_tags) ?></h3>
            <p>Tags uniques</p>
        </div>
        <div class="stat-card">
            <h3><?= count($machines) ?></h3>
            <p>Machines actives</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Gestion des catégories -->
    <div class="section-card">
        <div class="section-header">
            <h2>📁 Catégories</h2>
        </div>
        <div class="section-body">
            <button class="btn btn-success" onclick="openCategoryModal()">
                <i class="bi bi-plus-circle"></i> Nouvelle catégorie
            </button>

            <div class="categories-grid" style="margin-top: 20px;">
                <?php foreach ($categories as $cat): ?>
                    <div class="category-card">
                        <h4><?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?></h4>
                        <p style="color: #666; font-size: 0.9rem; margin: 10px 0;">
                            <?= htmlspecialchars($cat['description']) ?>
                        </p>
                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                            <span class="badge badge-<?= $cat['access_level'] ?>">
                                <?= $cat['access_level'] === 'public' ? '🌍 Public' : ($cat['access_level'] === 'members' ? '👥 Membres' : '🔒 Admin') ?>
                            </span>
                            <span class="badge badge-category"><?= $cat['doc_count'] ?> docs</span>
                        </div>
                        <div class="category-actions">
                            <button class="btn btn-secondary" onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)">
                                <i class="bi bi-pencil"></i> Modifier
                            </button>
                            <?php if ($cat['doc_count'] == 0): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette catégorie ?')">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Upload de documents -->
    <div class="section-card">
        <div class="section-header">
            <h2>📤 Uploader un document</h2>
        </div>
        <div class="section-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_document">

                <div class="form-group">
                    <label>Catégorie *</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>">
                                <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($has_machine_support && count($machines) > 0): ?>
                <div class="form-group">
                    <label>Machine (optionnel)</label>
                    <select name="machine_id" class="form-control">
                        <option value="">-- Aucune machine --</option>
                        <?php foreach ($machines as $machine): ?>
                            <option value="<?= $machine['id'] ?>">
                                <?= htmlspecialchars($machine['immatriculation']) ?> - <?= htmlspecialchars($machine['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                </div>

                <?php if ($has_machine_support): ?>
                <div class="form-group">
                    <label>📅 Date du document (optionnel)</label>
                    <input type="date" id="document_date" name="document_date" class="form-control">
                    <small class="text-muted">Date de la facture ou du document si applicable</small>
                </div>

                <div class="form-group">
                    <label>🏷️ Tags (séparés par des virgules)</label>
                    <input type="text" id="search_tags" name="search_tags" class="form-control" 
                           placeholder="Ex: manuel, maintenance, procédure">
                    
                    <?php if (count($existing_tags) > 0): ?>
                    <div style="margin-top: 10px;">
                        <strong style="font-size: 0.9rem; color: #666;">Tags suggérés (cliquez pour ajouter) :</strong>
                        <div class="tag-cloud">
                            <?php foreach ($existing_tags as $tag): ?>
                                <button type="button" class="tag-suggestion" onclick="addTag('<?= htmlspecialchars($tag, ENT_QUOTES) ?>')">
                                    <?= htmlspecialchars($tag) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Niveau d'accès *</label>
                    <select name="access_level" class="form-control" required>
                        <option value="admin_only">🔒 Admin uniquement</option>
                        <option value="members" selected>👥 Tous les membres</option>
                        <option value="public">🌍 Public</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fichier *</label>
                    <div class="drag-drop-zone" id="dropZone">
                        <div class="icon">📁</div>
                        <div class="text">Glissez votre fichier ici</div>
                        <div class="subtext">ou cliquez pour sélectionner</div>
                    </div>
                    <input type="file" name="file" id="fileInput" style="display: none;" required>
                    <div class="file-info" id="fileInfo">
                        <div class="file-name" id="fileName"></div>
                        <div class="file-size" id="fileSize"></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-upload"></i> Uploader
                </button>
            </form>
        </div>
    </div>

    <!-- Liste des documents -->
    <div class="section-card">
        <div class="section-header">
            <h2>📋 Documents (<?= count($documents) ?>)</h2>
        </div>
        <div class="section-body">
            <form method="GET" class="search-filter-bar">
                <input type="text" name="search" class="form-control" 
                       placeholder="🔍 Rechercher..." 
                       value="<?= htmlspecialchars($search_query) ?>">
                
                <select name="category_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filter_category == $cat['id'] ? 'selected' : '' ?>>
                            <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($has_machine_support && count($machines) > 0): ?>
                <select name="machine_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Toutes les machines</option>
                    <?php foreach ($machines as $machine): ?>
                        <option value="<?= $machine['id'] ?>" <?= $filter_machine == $machine['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($machine['immatriculation']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">Rechercher</button>
                
                <?php if ($search_query || $filter_category || $filter_machine): ?>
                    <a href="documents_admin.php" class="btn btn-secondary">Réinitialiser</a>
                <?php endif; ?>
            </form>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Catégorie</th>
                            <?php if ($has_machine_support): ?>
                            <th>Machine</th>
                            <th>Tags</th>
                            <?php endif; ?>
                            <th>Fichier</th>
                            <th>Accès</th>
                            <th>Uploadé par</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($doc['title']) ?></strong>
                                    <?php if ($doc['description']): ?>
                                        <br><small style="color: #666;"><?= htmlspecialchars(substr($doc['description'], 0, 60)) ?><?= strlen($doc['description']) > 60 ? '...' : '' ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-category">
                                        <?= $doc['category_icon'] ?> <?= htmlspecialchars($doc['category_name']) ?>
                                    </span>
                                </td>
                                <?php if ($has_machine_support): ?>
                                <td>
                                    <?php if ($doc['machine_immat']): ?>
                                        <span class="badge badge-machine">
                                            <?= htmlspecialchars($doc['machine_immat']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($doc['search_tags']): ?>
                                        <div class="doc-tags">
                                            <?php 
                                            $tags = array_map('trim', explode(',', $doc['search_tags']));
                                            foreach (array_slice($tags, 0, 3) as $tag): 
                                            ?>
                                                <span class="doc-tag"><?= htmlspecialchars($tag) ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($tags) > 3): ?>
                                                <span class="doc-tag">+<?= count($tags) - 3 ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <small style="font-family: monospace;">
                                        <?= htmlspecialchars($doc['original_filename']) ?>
                                    </small>
                                    <br>
                                    <small style="color: #999;">
                                        <?= round($doc['file_size'] / 1024, 1) ?> KB
                                    </small>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $doc['access_level'] ?>">
                                        <?= $doc['access_level'] === 'public' ? '🌍' : ($doc['access_level'] === 'members' ? '👥' : '🔒') ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($doc['prenom'] . ' ' . $doc['nom']) ?>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($doc['created_at'])) ?>
                                    <br>
                                    <small style="color: #999;">
                                        <?= date('H:i', strtotime($doc['created_at'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button class="btn btn-secondary" 
                                                style="padding: 5px 10px; font-size: 0.85rem;"
                                                onclick="editDocument(<?= htmlspecialchars(json_encode($doc)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="uploads/documents/<?= $doc['filename'] ?>" 
                                           class="btn btn-primary" 
                                           style="padding: 5px 10px; font-size: 0.85rem;"
                                           download>
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <form method="POST" style="display:inline;" 
                                              onsubmit="return confirm('Supprimer ce document ?')">
                                            <input type="hidden" name="action" value="delete_document">
                                            <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                            <button type="submit" class="btn btn-danger" 
                                                    style="padding: 5px 10px; font-size: 0.85rem;">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Édition Document -->
<div class="modal" id="documentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Modifier le document</h3>
            <button class="modal-close" onclick="closeDocumentModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="documentForm">
                <input type="hidden" name="action" value="edit_document">
                <input type="hidden" name="document_id" id="doc_id" value="0">

                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="title" id="doc_title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="doc_description" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>Catégorie *</label>
                    <select name="category_id" id="doc_category" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>">
                                <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($has_machine_support && count($machines) > 0): ?>
                <div class="form-group">
                    <label>Machine</label>
                    <select name="machine_id" id="doc_machine" class="form-control">
                        <option value="">-- Aucune machine --</option>
                        <?php foreach ($machines as $machine): ?>
                            <option value="<?= $machine['id'] ?>">
                                <?= htmlspecialchars($machine['immatriculation']) ?> - <?= htmlspecialchars($machine['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>🏷️ Tags (séparés par des virgules)</label>
                    <input type="text" name="search_tags" id="doc_tags" class="form-control" 
                           placeholder="Ex: manuel, maintenance, procédure">
                    
                    <?php if (count($existing_tags) > 0): ?>
                    <div style="margin-top: 10px;">
                        <strong style="font-size: 0.9rem; color: #666;">Tags suggérés :</strong>
                        <div class="tag-cloud">
                            <?php foreach ($existing_tags as $tag): ?>
                                <button type="button" class="tag-suggestion" onclick="addTagToEdit('<?= htmlspecialchars($tag, ENT_QUOTES) ?>')">
                                    <?= htmlspecialchars($tag) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Niveau d'accès *</label>
                    <select name="access_level" id="doc_access" class="form-control" required>
                        <option value="admin_only">🔒 Admin uniquement</option>
                        <option value="members">👥 Tous les membres</option>
                        <option value="public">🌍 Public</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <button type="button" class="btn btn-secondary" onclick="closeDocumentModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Catégorie -->
<div class="modal" id="categoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Nouvelle catégorie</h3>
            <button class="modal-close" onclick="closeCategoryModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="categoryForm">
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="category_id" id="category_id" value="0">

                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" name="name" id="category_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="category_description" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>Icône (emoji) *</label>
                    <input type="text" name="icon" id="category_icon" class="form-control" 
                           placeholder="📄" required maxlength="5">
                </div>

                <div class="form-group">
                    <label>Niveau d'accès *</label>
                    <select name="access_level" id="category_access" class="form-control" required>
                        <option value="admin_only">🔒 Admin uniquement</option>
                        <option value="members" selected>👥 Tous les membres</option>
                        <option value="public">🌍 Public</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Ordre d'affichage</label>
                    <input type="number" name="display_order" id="category_order" 
                           class="form-control" value="0">
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Gestion du drag & drop
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileInfo = document.getElementById('fileInfo');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');

if (dropZone && fileInput) {
    // Clic sur la zone pour ouvrir le sélecteur
    dropZone.addEventListener('click', () => {
        fileInput.click();
    });

    // Changement de fichier via sélecteur
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFile(e.target.files[0]);
        }
    });

    // Prévenir le comportement par défaut
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Effet visuel lors du drag
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('drag-over');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('drag-over');
        }, false);
    });

    // Gestion du drop
    dropZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleFile(files[0]);
        }
    }, false);

    function handleFile(file) {
        fileName.textContent = '📄 ' + file.name;
        fileSize.textContent = formatFileSize(file.size);
        fileInfo.classList.add('active');
        
        // Changer l'icône et le texte de la zone
        dropZone.querySelector('.icon').textContent = '✅';
        dropZone.querySelector('.text').textContent = 'Fichier sélectionné';
        dropZone.querySelector('.subtext').textContent = 'Cliquez pour changer';
        
        // Analyser et pré-remplir les champs
        analyzeDocument(file);
        
        // Lire le contenu pour générer une description intelligente
        const loadingMsg = document.createElement('div');
        loadingMsg.id = 'reading-indicator';
        loadingMsg.style.cssText = 'background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 10px; margin-top: 10px; font-size: 0.9rem;';
        loadingMsg.innerHTML = '<strong>📖 Lecture du document...</strong> Analyse du contenu en cours';
        fileInfo.appendChild(loadingMsg);
        
        readDocumentContent(file).then(() => {
            const indicator = document.getElementById('reading-indicator');
            if (indicator) {
                indicator.style.background = '#d4edda';
                indicator.style.borderColor = '#28a745';
                indicator.innerHTML = '<strong>✅ Analyse terminée</strong> Description générée';
                setTimeout(() => {
                    indicator.style.transition = 'opacity 0.5s';
                    indicator.style.opacity = '0';
                    setTimeout(() => indicator.remove(), 500);
                }, 3000);
            }
        }).catch(() => {
            const indicator = document.getElementById('reading-indicator');
            if (indicator) indicator.remove();
        });
    }
    
    function analyzeDocument(file) {
        const filename = file.name.toLowerCase();
        const nameWithoutExt = filename.replace(/\.[^/.]+$/, '');
        
        // Dictionnaire de détection
        const patterns = {
            facture: { keywords: ['facture', 'invoice', 'bill'], tags: ['facture', 'comptabilité'], category: 'Factures' },
            manuel: { keywords: ['manuel', 'manual', 'guide', 'documentation'], tags: ['manuel', 'documentation'], category: 'Manuels' },
            maintenance: { keywords: ['maintenance', 'entretien', 'revision', 'visite'], tags: ['maintenance', 'entretien'], category: 'Maintenance' },
            assurance: { keywords: ['assurance', 'insurance', 'contrat'], tags: ['assurance', 'administratif'], category: 'Assurances' },
            procedure: { keywords: ['procedure', 'procédure', 'process', 'checklist'], tags: ['procédure', 'sécurité'], category: 'Procédures' },
            navigation: { keywords: ['carte', 'navigation', 'nav', 'aeronautique'], tags: ['navigation', 'carte'], category: 'Navigation' },
            starlink: { keywords: ['starlink'], tags: ['starlink', 'internet'], category: null },
            internet: { keywords: ['internet', 'wifi', 'connexion'], tags: ['internet', 'informatique'], category: null },
            electricite: { keywords: ['electricite', 'électricité', 'edf', 'enedis'], tags: ['électricité', 'facture'], category: 'Factures' },
            telephonie: { keywords: ['telephone', 'téléphone', 'mobile', 'sfr', 'orange', 'bouygues'], tags: ['téléphonie', 'facture'], category: 'Factures' },
            certification: { keywords: ['certificat', 'certification', 'lapl', 'licence', 'medical'], tags: ['certification', 'administratif'], category: 'Certifications' },
            photo: { keywords: ['photo', 'img', 'image'], tags: ['photo'], category: 'Photos' }
        };
        
        let detectedTags = [];
        let suggestedCategory = null;
        let detectedType = [];
        
        // Analyse du nom de fichier
        for (const [type, config] of Object.entries(patterns)) {
            for (const keyword of config.keywords) {
                if (filename.includes(keyword)) {
                    detectedTags.push(...config.tags);
                    if (config.category && !suggestedCategory) {
                        suggestedCategory = config.category;
                    }
                    detectedType.push(type);
                    break;
                }
            }
        }
        
        // Détection par extension
        const ext = filename.split('.').pop();
        const extensionTags = {
            'pdf': ['pdf'],
            'jpg': ['photo', 'image'],
            'jpeg': ['photo', 'image'],
            'png': ['photo', 'image'],
            'doc': ['document'],
            'docx': ['document'],
            'xls': ['tableur', 'excel'],
            'xlsx': ['tableur', 'excel'],
            'zip': ['archive'],
            'rar': ['archive']
        };
        
        if (extensionTags[ext]) {
            detectedTags.push(...extensionTags[ext]);
        }
        
        // Nettoyer le nom pour le titre (enlever les underscores, tirets, etc.)
        let cleanTitle = nameWithoutExt
            .replace(/[_-]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        
        // Capitaliser la première lettre
        cleanTitle = cleanTitle.charAt(0).toUpperCase() + cleanTitle.slice(1);
        
        // Pré-remplir le titre si vide
        const titleInput = document.querySelector('input[name="title"]');
        if (titleInput && !titleInput.value) {
            titleInput.value = cleanTitle;
        }
        
        // Générer une description automatique si vide
        const descInput = document.querySelector('textarea[name="description"]');
        if (descInput && !descInput.value && detectedType.length > 0) {
            const descriptions = {
                facture: 'Facture - Document comptable',
                manuel: 'Manuel d\'utilisation ou guide technique',
                maintenance: 'Document relatif à la maintenance',
                assurance: 'Document d\'assurance',
                procedure: 'Procédure ou checklist',
                navigation: 'Carte ou document de navigation',
                certification: 'Certificat ou document administratif'
            };
            
            const desc = descriptions[detectedType[0]];
            if (desc) {
                descInput.value = desc;
                descInput.style.backgroundColor = '#fffacd';
                setTimeout(() => {
                    descInput.style.backgroundColor = '';
                }, 2000);
            }
        }
        
        // Pré-remplir les tags (dédupliquer)
        const tagsInput = document.getElementById('search_tags');
        if (tagsInput && detectedTags.length > 0) {
            const uniqueTags = [...new Set(detectedTags)];
            const currentTags = tagsInput.value.trim();
            if (currentTags) {
                const existing = currentTags.split(',').map(t => t.trim());
                const newTags = uniqueTags.filter(t => !existing.includes(t));
                if (newTags.length > 0) {
                    tagsInput.value = currentTags + ', ' + newTags.join(', ');
                }
            } else {
                tagsInput.value = uniqueTags.join(', ');
            }
        }
        
        // Suggérer la catégorie
        const categorySelect = document.querySelector('select[name="category_id"]');
        if (categorySelect && suggestedCategory) {
            // Chercher l'option qui contient le nom suggéré
            const options = categorySelect.querySelectorAll('option');
            for (const option of options) {
                if (option.textContent.toLowerCase().includes(suggestedCategory.toLowerCase())) {
                    categorySelect.value = option.value;
                    // Mettre en évidence visuellement
                    categorySelect.style.backgroundColor = '#fffacd';
                    setTimeout(() => {
                        categorySelect.style.backgroundColor = '';
                    }, 2000);
                    break;
                }
            }
        }
        
        // Afficher un message d'analyse
        if (detectedType.length > 0) {
            const analysisMsg = document.createElement('div');
            analysisMsg.style.cssText = 'background: #e3f2fd; border: 2px solid #2196f3; border-radius: 8px; padding: 10px; margin-top: 10px; font-size: 0.9rem;';
            analysisMsg.innerHTML = `<strong>🔍 Analyse :</strong> ${detectedType.join(', ')} détecté(s) • ${detectedTags.length} tag(s) suggéré(s)`;
            fileInfo.appendChild(analysisMsg);
            
            setTimeout(() => {
                analysisMsg.style.transition = 'opacity 0.5s';
                analysisMsg.style.opacity = '0';
                setTimeout(() => analysisMsg.remove(), 500);
            }, 4000);
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    async function readDocumentContent(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        const descInput = document.querySelector('textarea[name="description"]');
        
        try {
            // MÉTHODE 1: Parser serveur (PRIORITAIRE - Plus fiable pour PDF)
            if (ext === 'pdf') {
                try {
                    // Lire le fichier en base64 pour contourner ModSecurity
                    const reader = new FileReader();
                    const base64Promise = new Promise((resolve, reject) => {
                        reader.onload = () => resolve(reader.result.split(',')[1]);
                        reader.onerror = reject;
                        reader.readAsDataURL(file);
                    });
                    
                    const base64Data = await base64Promise;
                    
                    const serverResponse = await fetch('parse_pdf_server.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            pdf_base64: base64Data,
                            filename: file.name
                        })
                    });
                    
                    console.log('🔍 Réponse serveur - Status:', serverResponse.status, serverResponse.statusText);
                    
                    if (serverResponse.ok) {
                        // Lire d'abord le texte brut pour debug
                        const rawText = await serverResponse.text();
                        console.log('📄 Réponse brute (premiers 500 car):', rawText.substring(0, 500));
                        
                        try {
                            const data = JSON.parse(rawText);
                            console.log('📄 Analyse serveur - Données complètes:', data);
                        
                            if (data.success) {
                                console.log('✅ Parser serveur réussi - Méthode:', data.method);
                                
                                // Pré-remplir la date (priorité à date_iso)
                                if (data.date_iso) {
                                    const dateInput = document.getElementById('document_date');
                                    if (dateInput && !dateInput.value) {
                                        dateInput.value = data.date_iso;
                                        dateInput.style.backgroundColor = '#d4edda';
                                        setTimeout(() => { dateInput.style.backgroundColor = ''; }, 2000);
                                        console.log('✅ Date remplie:', data.date_iso);
                                    }
                                }
                                
                                // Pré-remplir la description avec les infos extraites
                                const descInput = document.getElementById('description');
                                if (descInput && !descInput.value) {
                                    let descParts = [];
                                    
                                    // Type de document
                                    if (data.supplier) {
                                        descParts.push('Facture');
                                        if (data.invoice_number) {
                                            descParts.push('N°' + data.invoice_number);
                                        }
                                        descParts.push('-', data.supplier);
                                    }
                                    
                                    // Date
                                    if (data.date || data.date_iso) {
                                        const dateStr = data.date || data.date_iso;
                                        descParts.push('du', dateStr);
                                    }
                                    
                                    // Montant (avec indication si TTC)
                                    if (data.amount) {
                                        descParts.push('-', data.amount);
                                        if (data.is_ttc) {
                                            descParts.push('TTC');
                                        }
                                    }
                                    
                                    if (descParts.length > 0) {
                                        descInput.value = descParts.join(' ');
                                        descInput.style.backgroundColor = '#d4edda';
                                        setTimeout(() => { descInput.style.backgroundColor = ''; }, 3000);
                                        console.log('✅ Description remplie:', descInput.value);
                                    }
                                }
                                
                                // Afficher les métadonnées extraites pour debug
                                if (data.metadata) {
                                    console.log('📊 Métadonnées extraites:', {
                                        dates: data.metadata.dates,
                                        amounts: data.metadata.amounts,
                                        total_amount: data.metadata.total_amount,
                                        is_ttc: data.metadata.is_ttc
                                    });
                                }
                            
                            // Tags automatiques
                            const tagsInput = document.getElementById('search_tags');
                            if (tagsInput && data.supplier) {
                                const currentTags = tagsInput.value.toLowerCase();
                                const newTag = data.supplier.toLowerCase();
                                if (!currentTags.includes(newTag)) {
                                    tagsInput.value = tagsInput.value ? tagsInput.value + ', ' + newTag : newTag;
                                }
                            }
                            
                            // Catégorie automatique
                            if (data.supplier) {
                                const categorySelect = document.querySelector('select[name="category_id"]');
                                if (categorySelect) {
                                    const supplierLower = data.supplier.toLowerCase();
                                    let categoryValue = null;
                                    
                                    if (supplierLower.includes('edf') || supplierLower.includes('enedis') || supplierLower.includes('electricité')) {
                                        categoryValue = 'Factures - Électricité';
                                    } else if (supplierLower.includes('keyyo') || supplierLower.includes('sfr') || supplierLower.includes('orange') || supplierLower.includes('bouygues') || supplierLower.includes('free')) {
                                        categoryValue = 'Factures - Téléphonie/Internet';
                                    } else if (supplierLower.includes('starlink')) {
                                        categoryValue = 'Factures - Internet';
                                    }
                                    
                                    if (categoryValue) {
                                        for (let option of categorySelect.options) {
                                            if (option.text === categoryValue) {
                                                categorySelect.value = option.value;
                                                categorySelect.style.backgroundColor = '#d4edda';
                                                setTimeout(() => { categorySelect.style.backgroundColor = ''; }, 2000);
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            return; // Succès - on arrête ici
                        } else {
                            console.warn('⚠️ Parser serveur - success=false:', data);
                        }
                        } catch (parseError) {
                            console.error('❌ Erreur parsing JSON:', parseError);
                            console.error('Réponse complète:', rawText);
                        }
                    } else {
                        const errorText = await serverResponse.text();
                        console.error('❌ Erreur serveur HTTP', serverResponse.status, ':', errorText);
                    }
                } catch (error) {
                    console.error('❌ Parser serveur - Exception:', error);
                }
            }
            
            // MÉTHODE 2: Fallback client-side
            let extractedText = '';
            
            if (ext === 'pdf') {
                // Lire le PDF côté client (fallback)
                extractedText = await readPDF(file);
            } else if (['txt', 'csv', 'log'].includes(ext)) {
                // Lire les fichiers texte
                extractedText = await readTextFile(file);
            } else if (['doc', 'docx'].includes(ext)) {
                // Pour les docs Word, on ne peut pas lire directement en JS
                // On garde l'analyse par nom de fichier
                return;
            }
            
            if (extractedText && extractedText.length > 20) {
                // Générer une description intelligente basée sur le contenu
                const smartDesc = generateSmartDescription(extractedText, file.name);
                if (smartDesc && descInput) {
                    // Ajouter au lieu de remplacer si une description existe déjà
                    const currentDesc = descInput.value.trim();
                    if (currentDesc && !currentDesc.includes(smartDesc)) {
                        descInput.value = currentDesc + ' - ' + smartDesc;
                    } else if (!currentDesc) {
                        descInput.value = smartDesc;
                    }
                    
                    // Effet visuel
                    descInput.style.backgroundColor = '#d4edda';
                    setTimeout(() => {
                        descInput.style.backgroundColor = '';
                    }, 3000);
                }
                
                // Détecter et ajouter automatiquement les tags existants trouvés dans le document
                const foundTags = detectExistingTags(extractedText);
                if (foundTags.length > 0) {
                    const tagsInput = document.getElementById('search_tags');
                    if (tagsInput) {
                        const currentTags = tagsInput.value.split(',').map(t => t.trim().toLowerCase()).filter(t => t);
                        const newTags = foundTags.filter(t => !currentTags.includes(t));
                        
                        if (newTags.length > 0) {
                            if (tagsInput.value.trim()) {
                                tagsInput.value += ', ' + newTags.join(', ');
                            } else {
                                tagsInput.value = newTags.join(', ');
                            }
                            // Effet visuel
                            tagsInput.style.backgroundColor = '#d4edda';
                            setTimeout(() => {
                                tagsInput.style.backgroundColor = '';
                            }, 3000);
                        }
                    }
                }
                
                // Détecter et sélectionner automatiquement la catégorie depuis le contenu
                const detectedCategory = detectCategoryFromContent(extractedText);
                if (detectedCategory) {
                    const categorySelect = document.querySelector('select[name="category_id"]');
                    if (categorySelect) {
                        const options = categorySelect.querySelectorAll('option');
                        for (const option of options) {
                            if (option.textContent.toLowerCase().includes(detectedCategory.toLowerCase())) {
                                categorySelect.value = option.value;
                                categorySelect.style.backgroundColor = '#d4edda';
                                setTimeout(() => {
                                    categorySelect.style.backgroundColor = '';
                                }, 3000);
                                break;
                            }
                        }
                    }
                }
            }
        } catch (error) {
            console.log('Erreur lecture document:', error);
        }
    }
    
    async function readPDF(file) {
        if (typeof pdfjsLib === 'undefined') return '';
        
        try {
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
            
            let fullText = '';
            // Lire jusqu'à 10 pages pour meilleure détection
            const numPages = Math.min(pdf.numPages, 10);
            
            for (let i = 1; i <= numPages; i++) {
                const page = await pdf.getPage(i);
                const textContent = await page.getTextContent();
                // Conserver la structure avec espaces et retours à la ligne
                const pageText = textContent.items.map(item => item.str).join(' ');
                fullText += pageText + '\n';
            }
            
            return fullText;
        } catch (error) {
            console.log('Erreur lecture PDF:', error);
            return '';
        }
    }
    
    function readTextFile(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = reject;
            reader.readAsText(file);
        });
    }
    
    function generateSmartDescription(text, filename) {
        // Nettoyer et normaliser le texte (conserver espaces insécables \u00a0)
        const originalText = text;
        text = text.replace(/\s+/g, ' ').trim();
        const lowerText = text.toLowerCase();
        const lowerFilename = filename.toLowerCase();
        
        // EXTRACTION DU NUMÉRO DE FACTURE (texte + nom fichier)
        let invoiceNumber = null;
        const invoicePatterns = [
            /(?:facture|invoice|bill)\s*(?:n°|numéro|number|#)?\s*:?\s*([A-Z0-9-]{5,20})/gi,
            /n°\s*(?:facture|invoice)?\s*:?\s*([A-Z0-9-]{5,20})/gi,
            /référence\s*:?\s*([A-Z0-9-]{5,20})/gi,
            /\b([0-9]{10,15})\b/g  // Numéros longs (10-15 chiffres)
        ];
        for (const pattern of invoicePatterns) {
            const matches = [...text.matchAll(pattern)];
            if (matches.length > 0) {
                invoiceNumber = matches[0][1].trim();
                break;
            }
        }
        
        // Si pas trouvé dans le texte, chercher dans le nom de fichier
        if (!invoiceNumber) {
            const filenameMatch = filename.match(/([0-9]{8,15})/);
            if (filenameMatch) {
                invoiceNumber = filenameMatch[1];
            }
        }
        
        // EXTRACTION AVANCÉE DES MONTANTS avec normalisation poussée
        let amount = null;
        let amounts = [];
        
        // Normaliser le texte : remplacer espaces insécables, multiples espaces, etc.
        const normalizedText = originalText.replace(/[\u00a0\s]+/g, ' ');
        
        const amountPatterns = [
            // Pattern 1: Solde / Dû / À régler (TRÈS HAUTE PRIORITÉ)
            { regex: /(?:solde|d[uû]|reste)\s*(?:à|a)?\s*(?:payer|r[eé]gler)?\s*:?\s*([0-9]{1,3}(?:[\s.,][0-9]{3})*[.,][0-9]{2})\s*(€|EUR|eur)/gi, priority: 110 },
            // Pattern 2: Net à payer / Total net
            { regex: /(?:net|total\s*net)\s*(?:à|a)?\s*payer\s*:?\s*([0-9]{1,3}(?:[\s.,][0-9]{3})*[.,][0-9]{2})\s*(€|EUR|eur)/gi, priority: 105 },
            // Pattern 3: Total TTC ou T.T.C. (avec ou sans points, avec ou sans €)
            { regex: /total\s*(?:€|eur)?\s*[tT]\.?\s*[tT]\.?\s*[cC]\.?\s*:?\s*([0-9]{1,3}(?:[\s.,][0-9]{3})*[.,][0-9]{2})/gi, priority: 102 },
            // Pattern 4: À payer
            { regex: /(?:à|a)\s*payer\s*:?\s*([0-9]{1,3}(?:[\s.,][0-9]{3})*[.,][0-9]{2})\s*(€|EUR|eur)/gi, priority: 100 },
            // Pattern 5: Total TTC explicite (exclure HT)
            { regex: /(?:total|montant)\s*(?:TTC|ttc)\s*:?\s*([0-9]{1,3}(?:[\s.,][0-9]{3})*[.,][0-9]{2})\s*(€|EUR|eur)/gi, priority: 95 },
            // Pattern 6: Total / Montant sans HT
            { regex: /(?:total|montant)\s*(?!.*H\.?T\.?)\s*:?\s*([0-9]{1,3}(?:[\s.,][0-9]{3})*[.,][0-9]{2})\s*(€|EUR|eur)/gi, priority: 85 },
            // Pattern 7: Montant avec contexte
            { regex: /(?:montant|prix|price|amount|coût|tarif)\s*:?\s*([0-9]{1,3}(?:[\s.,][0-9]{3})*[.,][0-9]{2})\s*(€|EUR|eur)/gi, priority: 70 },
            // Pattern 8: Avec milliers (ex: 1 234,56 €)
            { regex: /\b([0-9]{1,3}(?:[\s][0-9]{3})+[.,][0-9]{2})\s*(€|EUR|eur)\b/gi, priority: 60 },
            // Pattern 9: Format décimal pur (ex: 31,37 € ou 31.37 EUR)
            { regex: /\b([0-9]{1,3}[.,][0-9]{2})\s*(€|EUR|eur)\b/gi, priority: 50 },
            // Pattern 10: Format sans contexte mais propre
            { regex: /([0-9]+[.,][0-9]{2})\s*(€|EUR|eur)/gi, priority: 35 }
        ];
        
        // Chercher dans le texte normalisé ET original
        for (const {regex, priority} of amountPatterns) {
            // Chercher dans le texte normalisé
            const matchesNorm = [...normalizedText.matchAll(regex)];
            for (const match of matchesNorm) {
                if (match[1]) {
                    let cleanAmount = match[1].replace(/\s+/g, '').replace(',', '.');
                    const numValue = parseFloat(cleanAmount);
                    if (numValue > 0 && numValue < 100000) {
                        // Formater pour affichage (garder le format original)
                        const displayAmount = match[1].trim() + ' ' + match[2];
                        amounts.push({ 
                            value: numValue, 
                            display: displayAmount, 
                            priority,
                            context: match[0]
                        });
                    }
                }
            }
            
            // Chercher aussi dans le texte original
            const matchesOrig = [...originalText.matchAll(regex)];
            for (const match of matchesOrig) {
                if (match[1]) {
                    let cleanAmount = match[1].replace(/[\s\u00a0]+/g, '').replace(',', '.');
                    const numValue = parseFloat(cleanAmount);
                    if (numValue > 0 && numValue < 100000) {
                        const displayAmount = match[1].trim() + ' ' + match[2];
                        // Éviter les doublons
                        const exists = amounts.some(a => Math.abs(a.value - numValue) < 0.01);
                        if (!exists) {
                            amounts.push({ 
                                value: numValue, 
                                display: displayAmount, 
                                priority,
                                context: match[0]
                            });
                        }
                    }
                }
            }
        }
        
        if (amounts.length > 0) {
            // Trier UNIQUEMENT par priorité (prendre le premier de la plus haute priorité)
            amounts.sort((a, b) => b.priority - a.priority);
            amount = amounts[0].display;
            
            // Log pour debug - afficher tous les montants trouvés
            console.log('🔍 Montants détectés:', amounts.slice(0, 10).map(a => 
                `${a.display} (priorité: ${a.priority}, contexte: "${a.context.substring(0, 50)}...")`
            ));
            console.log('✅ Montant sélectionné:', amount);
        }
        
        // EXTRACTION AVANCÉE DES DATES
        let date = null;
        let allDates = [];
        const datePatterns = [
            { regex: /(?:date\s*de\s*facture|date\s*facture|date|dated?|du|le|faité?\s*le|émise?\s*le)\s*:?\s*([0-3]?[0-9][\s\u00a0]*[\/\.-][\s\u00a0]*[0-1]?[0-9][\s\u00a0]*[\/\.-][\s\u00a0]*[0-9]{2,4})/gi, priority: 100 },
            { regex: /(?:facture|invoice)\s*(?:du|dated?|de)\s*:?\s*([0-3]?[0-9][\s\u00a0]*[\/\.-][\s\u00a0]*[0-1]?[0-9][\s\u00a0]*[\/\.-][\s\u00a0]*[0-9]{2,4})/gi, priority: 90 },
            { regex: /([0-9]{4}[\s\u00a0]*[\/\.-][\s\u00a0]*[0-1]?[0-9][\s\u00a0]*[\/\.-][\s\u00a0]*[0-3]?[0-9])/gi, priority: 50 },
            { regex: /([0-3]?[0-9][\s\u00a0]*[\/\.-][\s\u00a0]*[0-1]?[0-9][\s\u00a0]*[\/\.-][\s\u00a0]*[0-9]{2,4})(?![\s\u00a0]*[\/\.-])/gi, priority: 30 }
        ];
        
        for (const {regex, priority} of datePatterns) {
            const matches = [...text.matchAll(regex)];
            for (const match of matches) {
                const dateStr = (match[1] || match[0]).replace(/[\s\u00a0]+/g, '').trim();
                allDates.push({ date: dateStr, priority });
            }
        }
        
        // Prendre la date avec la plus haute priorité
        if (allDates.length > 0) {
            allDates.sort((a, b) => b.priority - a.priority);
            date = allDates[0].date;
        }
        
        // Pré-remplir le champ date si trouvé
        if (date) {
            const dateInput = document.getElementById('document_date');
            if (dateInput && !dateInput.value) {
                const isoDate = convertToISODate(date);
                if (isoDate) {
                    dateInput.value = isoDate;
                    dateInput.style.backgroundColor = '#fffacd';
                    setTimeout(() => { dateInput.style.backgroundColor = ''; }, 2000);
                }
            }
        }
        
        // Détecter des infos spécifiques
        let description = '';
        
        // Factures
        if (lowerText.includes('facture') || lowerText.includes('invoice')) {
            const supplier = extractSupplier(text, lowerText);
            description = 'Facture';
            if (invoiceNumber) description += ' N°' + invoiceNumber;
            if (supplier) description += ' - ' + supplier;
            if (date) description += ' du ' + date;
            if (amount) description += ' - ' + amount;
        }
        // Starlink spécifique
        else if (lowerText.includes('starlink') || lowerFilename.includes('starlink')) {
            description = 'Document Starlink (Internet satellite)';
            if (lowerText.includes('facture') || lowerText.includes('invoice')) {
                description = 'Facture Starlink';
                if (date) description += ' - ' + date;
                if (amount) description += ' - ' + amount;
            }
        }
        // Manuels
        else if (lowerText.includes('manuel') || lowerText.includes('manual') || lowerText.includes('guide')) {
            const productMatch = text.match(/manuel.*?([A-Z][\w\s-]{5,30})/i);
            description = 'Manuel';
            if (productMatch && productMatch[1]) {
                description += ' - ' + productMatch[1].trim();
            }
        }
        // Assurance
        else if (lowerText.includes('assurance') || lowerText.includes('insurance')) {
            description = 'Document d\'assurance';
            if (date) description += ' - ' + date;
        }
        // Certificat
        else if (lowerText.includes('certificat') || lowerText.includes('certificate')) {
            description = 'Certificat';
            if (lowerText.includes('medical') || lowerText.includes('médical')) {
                description += ' médical';
            }
            if (date) description += ' - ' + date;
        }
        // Navigation
        else if (lowerText.includes('carte') && (lowerText.includes('aero') || lowerText.includes('nav'))) {
            description = 'Carte aéronautique';
        }
        // Par défaut, extraire les premiers mots significatifs
        else {
            const words = text.split(/\s+/).filter(w => w.length > 3).slice(0, 10);
            if (words.length > 0) {
                description = words.join(' ').substring(0, 100);
                if (text.length > 100) description += '...';
            }
        }
        
        return description;
    }
    
    function extractSupplier(text, lowerText) {
        // Liste de fournisseurs connus avec variantes et patterns avancés
        // Ordre important : les plus spécifiques en premier
        const suppliers = {
            'keyyo': { names: ['Keyyo'], patterns: [/keyyo/gi, /manager\.keyyo\.com/gi], priority: 100 },
            'starlink': { names: ['Starlink'], patterns: [/starlink/gi, /star\s*link/gi], priority: 90 },
            'red': { names: ['RED by SFR'], patterns: [/red\s*by\s*sfr/gi], priority: 85 },
            'sosh': { names: ['Sosh'], patterns: [/\bsosh\b/gi], priority: 85 },
            'ovh': { names: ['OVH'], patterns: [/\bOVH\b/gi, /ovh\s*telecom/gi], priority: 80 },
            'orange': { names: ['Orange'], patterns: [/\borange\b/gi, /orange\s*(?:telecom|business|france)/gi], priority: 70 },
            'sfr': { names: ['SFR'], patterns: [/\bSFR\b/gi], priority: 65 },
            'bouygues': { names: ['Bouygues Telecom'], patterns: [/bouygues/gi, /b\s*&\s*you/gi], priority: 70 },
            'free': { names: ['Free'], patterns: [/\bfree\b/gi, /free\s*(?:telecom|mobile)/gi, /iliad/gi], priority: 70 },
            'edf': { names: ['EDF'], patterns: [/\bEDF\b/gi, /electricite.{0,15}france/gi], priority: 60 },
            'enedis': { names: ['Enedis'], patterns: [/enedis/gi], priority: 60 },
            'total': { names: ['TotalEnergies'], patterns: [/total(?:\s*energies)?/gi] },
            'shell': { names: ['Shell'], patterns: [/\bshell\b/gi] },
            'bp': { names: ['BP'], patterns: [/\bBP\b/gi] },
            'esso': { names: ['Esso'], patterns: [/esso/gi] },
            'carrefour': { names: ['Carrefour'], patterns: [/carrefour/gi] },
            'leclerc': { names: ['E.Leclerc'], patterns: [/(?:e\.)?leclerc/gi] },
            'intermarche': { names: ['Intermarché'], patterns: [/intermarch[eé]/gi] },
            'auchan': { names: ['Auchan'], patterns: [/auchan/gi] },
            'maif': { names: ['MAIF'], patterns: [/\bMAIF\b/gi] },
            'macif': { names: ['MACIF'], patterns: [/\bMACIF\b/gi] },
            'axa': { names: ['AXA'], patterns: [/\bAXA\b/gi] },
            'allianz': { names: ['Allianz'], patterns: [/allianz/gi] },
            'generali': { names: ['Generali'], patterns: [/generali/gi] }
        };
        
        // Chercher avec patterns regex
        for (const [key, config] of Object.entries(suppliers)) {
            for (const pattern of config.patterns) {
                if (pattern.test(text)) {
                    return config.names[0];
                }
            }
        }
        
        // Chercher "Fournisseur:" ou "De:" ou "From:"
        const supplierPatterns = [
            /(?:fournisseur|supplier|vendeur|seller)[\s:]+([A-Z][a-zA-Z\s&\.-]{2,40})/i,
            /(?:de|from)[\s:]+([A-Z][a-zA-Z\s&\.-]{3,40})(?:\s*\n|$)/i,
            /([A-Z][A-Z\s&]{2,30})(?:\s*-)?\s*(?:facture|invoice)/i
        ];
        
        for (const pattern of supplierPatterns) {
            const match = text.match(pattern);
            if (match && match[1]) {
                const supplier = match[1].trim();
                // Filtrer les faux positifs communs
                const blacklist = ['facture', 'invoice', 'total', 'montant', 'date', 'numero', 'number'];
                if (!blacklist.some(word => supplier.toLowerCase().includes(word))) {
                    return supplier;
                }
            }
        }
        
        return null;
    }
    
    function convertToISODate(dateStr) {
        // Nettoyer la chaîne
        dateStr = dateStr.replace(/\s+/g, '').trim();
        
        // Convertir différents formats de date en ISO (YYYY-MM-DD)
        const patterns = [
            { regex: /([0-3]?[0-9])[\/\.-]([0-1]?[0-9])[\/\.-]([0-9]{4})/, format: 'DMY' },  // DD/MM/YYYY
            { regex: /([0-3]?[0-9])[\/\.-]([0-1]?[0-9])[\/\.-]([0-9]{2})/, format: 'DMY2' },   // DD/MM/YY
            { regex: /([0-9]{4})[\/\.-]([0-1]?[0-9])[\/\.-]([0-3]?[0-9])/, format: 'YMD' },   // YYYY/MM/DD
            { regex: /([0-1]?[0-9])[\/\.-]([0-3]?[0-9])[\/\.-]([0-9]{4})/, format: 'MDY' }    // MM/DD/YYYY
        ];
        
        for (const {regex, format} of patterns) {
            const match = dateStr.match(regex);
            if (match) {
                let day, month, year;
                
                if (format === 'YMD') {
                    year = match[1];
                    month = match[2].padStart(2, '0');
                    day = match[3].padStart(2, '0');
                } else if (format === 'MDY') {
                    month = match[1].padStart(2, '0');
                    day = match[2].padStart(2, '0');
                    year = match[3];
                } else if (format === 'DMY' || format === 'DMY2') {
                    day = match[1].padStart(2, '0');
                    month = match[2].padStart(2, '0');
                    year = match[3];
                    
                    // Convertir année sur 2 chiffres
                    if (year.length === 2) {
                        const yearNum = parseInt(year);
                        // Si > 50, c'est 19XX, sinon 20XX
                        year = yearNum > 50 ? '19' + year : '20' + year;
                    }
                }
                
                // Valider la date
                const monthNum = parseInt(month);
                const dayNum = parseInt(day);
                
                if (monthNum >= 1 && monthNum <= 12 && dayNum >= 1 && dayNum <= 31) {
                    return year + '-' + month + '-' + day;
                }
            }
        }
        return null;
    }
    
    function detectExistingTags(text) {
        const lowerText = text.toLowerCase();
        const foundTags = [];
        
        // 1. Chercher les tags existants dans la base
        const existingTagsButtons = document.querySelectorAll('.tag-suggestion');
        const existingTags = Array.from(existingTagsButtons).map(btn => btn.textContent.trim().toLowerCase());
        
        for (const tag of existingTags) {
            if (lowerText.includes(tag)) {
                foundTags.push(tag);
            }
        }
        
        // 2. Ajouter des tags intelligents basés sur des mots-clés
        const smartTags = {
            'starlink': ['starlink'],
            'facture': ['facture', 'invoice'],
            'edf': ['edf'],
            'orange': ['orange'],
            'sfr': ['sfr'],
            'free': ['free'],
            'bouygues': ['bouygues'],
            'internet': ['internet', 'connexion'],
            'électricité': ['électricité', 'electricite', 'enedis'],
            'assurance': ['assurance', 'insurance'],
            'maintenance': ['maintenance', 'entretien'],
            'manuel': ['manuel', 'manual', 'guide'],
            'navigation': ['navigation', 'carte aeronautique']
        };
        
        for (const [tag, keywords] of Object.entries(smartTags)) {
            for (const keyword of keywords) {
                if (lowerText.includes(keyword) && !foundTags.includes(tag)) {
                    foundTags.push(tag);
                    break;
                }
            }
        }
        
        return foundTags;
    }
    
    function detectCategoryFromContent(text) {
        const lowerText = text.toLowerCase();
        
        // Détecter la catégorie basée sur le contenu
        if (lowerText.includes('facture') || lowerText.includes('invoice')) {
            return 'Factures';
        }
        if (lowerText.includes('manuel') || lowerText.includes('manual') || lowerText.includes('guide')) {
            return 'Manuels';
        }
        if (lowerText.includes('assurance') || lowerText.includes('insurance')) {
            return 'Assurances';
        }
        if (lowerText.includes('maintenance') || lowerText.includes('entretien')) {
            return 'Maintenance';
        }
        if (lowerText.includes('certificat') || lowerText.includes('certificate')) {
            return 'Certifications';
        }
        
        return null;
    }
}

function addTag(tag) {
    const input = document.getElementById('search_tags');
    const current = input.value.trim();
    
    if (current) {
        const tags = current.split(',').map(t => t.trim());
        if (!tags.includes(tag)) {
            input.value = current + ', ' + tag;
        }
    } else {
        input.value = tag;
    }
    
    input.focus();
}

function addTagToEdit(tag) {
    const input = document.getElementById('doc_tags');
    const current = input.value.trim();
    
    if (current) {
        const tags = current.split(',').map(t => t.trim());
        if (!tags.includes(tag)) {
            input.value = current + ', ' + tag;
        }
    } else {
        input.value = tag;
    }
    
    input.focus();
}

function editDocument(doc) {
    document.getElementById('doc_id').value = doc.id;
    document.getElementById('doc_title').value = doc.title;
    document.getElementById('doc_description').value = doc.description || '';
    document.getElementById('doc_category').value = doc.category_id;
    document.getElementById('doc_access').value = doc.access_level;
    
    <?php if ($has_machine_support): ?>
    if (document.getElementById('doc_machine')) {
        document.getElementById('doc_machine').value = doc.machine_id || '';
    }
    if (document.getElementById('doc_tags')) {
        document.getElementById('doc_tags').value = doc.search_tags || '';
    }
    <?php endif; ?>
    
    document.getElementById('documentModal').classList.add('active');
}

function closeDocumentModal() {
    document.getElementById('documentModal').classList.remove('active');
}

function openCategoryModal() {
    document.getElementById('modalTitle').textContent = 'Nouvelle catégorie';
    document.getElementById('categoryForm').reset();
    document.getElementById('category_id').value = '0';
    document.getElementById('categoryModal').classList.add('active');
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.remove('active');
}

function editCategory(cat) {
    document.getElementById('modalTitle').textContent = 'Modifier la catégorie';
    document.getElementById('category_id').value = cat.id;
    document.getElementById('category_name').value = cat.name;
    document.getElementById('category_description').value = cat.description || '';
    document.getElementById('category_icon').value = cat.icon;
    document.getElementById('category_access').value = cat.access_level;
    document.getElementById('category_order').value = cat.display_order;
    document.getElementById('categoryModal').classList.add('active');
}

// Fermer le modal en cliquant en dehors
document.getElementById('categoryModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCategoryModal();
    }
});

document.getElementById('documentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDocumentModal();
    }
});
</script>

<?php require 'footer.php'; ?>
