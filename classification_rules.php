<?php
require_once 'config.php';
require_once 'auth.php';

if (!is_admin()) {
    header('Location: acces_refuse.php');
    exit;
}

$message = '';
$error = '';

// Vérifier si la table existe
$table_exists = false;
try {
    $pdo->query("SELECT 1 FROM document_classification_rules LIMIT 1");
    $table_exists = true;
} catch (PDOException $e) {
    $error = "⚠️ La table de classification n'existe pas encore. <a href='setup/migrate_document_classification.php'>Exécuter la migration</a>";
}

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_exists) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'save_rule':
                    $rule_id = isset($_POST['rule_id']) ? (int)$_POST['rule_id'] : 0;
                    $name = trim($_POST['name']);
                    $category_name = trim($_POST['category_name']);
                    $keywords = trim($_POST['keywords']);
                    $required_keywords = trim($_POST['required_keywords']);
                    $priority = (int)$_POST['priority'];
                    $requires_amount = isset($_POST['requires_amount']) ? 1 : 0;
                    $requires_date = isset($_POST['requires_date']) ? 1 : 0;
                    $requires_immatriculation = isset($_POST['requires_immatriculation']) ? 1 : 0;
                    $active = isset($_POST['active']) ? 1 : 0;

                    if ($rule_id > 0) {
                        $stmt = $pdo->prepare("
                            UPDATE document_classification_rules 
                            SET name = ?, category_name = ?, keywords = ?, required_keywords = ?, 
                                priority = ?, requires_amount = ?, requires_date = ?, 
                                requires_immatriculation = ?, active = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $category_name, $keywords, $required_keywords, 
                                       $priority, $requires_amount, $requires_date, 
                                       $requires_immatriculation, $active, $rule_id]);
                        $message = "✅ Règle modifiée";
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO document_classification_rules 
                            (name, category_name, keywords, required_keywords, priority, 
                             requires_amount, requires_date, requires_immatriculation, active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$name, $category_name, $keywords, $required_keywords, 
                                       $priority, $requires_amount, $requires_date, 
                                       $requires_immatriculation, $active]);
                        $message = "✅ Règle créée";
                    }
                    break;

                case 'delete_rule':
                    $rule_id = (int)$_POST['rule_id'];
                    $stmt = $pdo->prepare("DELETE FROM document_classification_rules WHERE id = ?");
                    $stmt->execute([$rule_id]);
                    $message = "✅ Règle supprimée";
                    break;

                case 'toggle_rule':
                    $rule_id = (int)$_POST['rule_id'];
                    $stmt = $pdo->prepare("UPDATE document_classification_rules SET active = NOT active WHERE id = ?");
                    $stmt->execute([$rule_id]);
                    $message = "✅ Règle modifiée";
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "❌ Erreur : " . $e->getMessage();
    }
}

// Récupérer les règles
$rules = [];
if ($table_exists) {
    $stmt = $pdo->query("SELECT * FROM document_classification_rules ORDER BY priority DESC, name ASC");
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les catégories
$stmt = $pdo->query("SELECT id, name FROM document_categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Règle à éditer
$edit_rule = null;
if (isset($_GET['edit']) && $table_exists) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM document_classification_rules WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_rule = $stmt->fetch(PDO::FETCH_ASSOC);
}

require 'header.php';
?>

<style>
.classification-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.page-subtitle {
    color: #6b7280;
    margin-bottom: 2rem;
}

.rules-grid {
    display: grid;
    gap: 1.5rem;
}

.rule-card {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-left: 4px solid #004b8d;
}

.rule-card.inactive {
    opacity: 0.6;
    border-left-color: #9ca3af;
}

.rule-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.rule-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
}

.rule-priority {
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: white;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
}

.rule-category {
    color: #00a0c6;
    font-weight: 600;
    margin-bottom: 1rem;
}

.rule-keywords {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 1rem 0;
}

.keyword-tag {
    background: #f3f4f6;
    padding: 0.25rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.85rem;
    color: #4b5563;
}

.keyword-tag.required {
    background: #dcfce7;
    color: #166534;
    font-weight: 600;
}

.rule-requirements {
    display: flex;
    gap: 1rem;
    margin: 1rem 0;
    font-size: 0.9rem;
    color: #6b7280;
}

.requirement-badge {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.rule-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.3s;
    font-size: 0.9rem;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 75, 141, 0.3);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-sm {
    padding: 0.35rem 0.75rem;
    font-size: 0.85rem;
}

.form-card {
    background: white;
    border-radius: 1rem;
    padding: 2rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    font-family: inherit;
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.form-help {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 0.35rem;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0.5rem 0;
}

.checkbox-group input[type="checkbox"] {
    width: auto;
}

.message {
    padding: 1rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
}

.message.success {
    background: #f0fdf4;
    border-left: 4px solid #10b981;
    color: #166534;
}

.message.error {
    background: #fef2f2;
    border-left: 4px solid #ef4444;
    color: #991b1b;
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
    border-radius: 1rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    text-align: center;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #004b8d;
}

.stat-label {
    color: #6b7280;
    font-size: 0.9rem;
    margin-top: 0.5rem;
}
</style>

<div class="classification-container">
    <h1 class="page-title">🤖 Règles de Classification Automatique</h1>
    <p class="page-subtitle">Configurez comment les documents sont automatiquement classés lors de l'upload</p>

    <?php if ($message): ?>
        <div class="message success"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($table_exists): ?>
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($rules); ?></div>
                <div class="stat-label">Règles totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($rules, fn($r) => $r['active'])); ?></div>
                <div class="stat-label">Règles actives</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($categories); ?></div>
                <div class="stat-label">Catégories</div>
            </div>
        </div>

        <!-- Formulaire création/édition -->
        <?php if ($edit_rule || isset($_GET['new'])): ?>
        <div class="form-card">
            <h2><?php echo $edit_rule ? '✏️ Modifier la règle' : '➕ Nouvelle règle'; ?></h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_rule">
                <?php if ($edit_rule): ?>
                    <input type="hidden" name="rule_id" value="<?php echo $edit_rule['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>📋 Nom de la règle *</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($edit_rule['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>📁 Catégorie cible *</label>
                    <input type="text" name="category_name" value="<?php echo htmlspecialchars($edit_rule['category_name'] ?? ''); ?>" required list="categories-list">
                    <datalist id="categories-list">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-help">Nom de la catégorie de destination (ex: "Factures")</div>
                </div>

                <div class="form-group">
                    <label>🔑 Mots-clés optionnels</label>
                    <textarea name="keywords"><?php echo htmlspecialchars($edit_rule['keywords'] ?? ''); ?></textarea>
                    <div class="form-help">Séparés par des virgules (ex: facture,invoice,montant,total)</div>
                </div>

                <div class="form-group">
                    <label>⚡ Mots-clés obligatoires (regex)</label>
                    <input type="text" name="required_keywords" value="<?php echo htmlspecialchars($edit_rule['required_keywords'] ?? ''); ?>">
                    <div class="form-help">Pattern regex, séparé par | (ex: facture|invoice|bill)</div>
                </div>

                <div class="form-group">
                    <label>🎯 Priorité (0-100)</label>
                    <input type="number" name="priority" value="<?php echo $edit_rule['priority'] ?? 50; ?>" min="0" max="100">
                    <div class="form-help">Plus élevé = prioritaire en cas de match multiple</div>
                </div>

                <div class="form-group">
                    <label>📊 Exigences de données</label>
                    <div class="checkbox-group">
                        <input type="checkbox" name="requires_amount" id="req_amount" <?php echo !empty($edit_rule['requires_amount']) ? 'checked' : ''; ?>>
                        <label for="req_amount">Nécessite un montant</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="requires_date" id="req_date" <?php echo !empty($edit_rule['requires_date']) ? 'checked' : ''; ?>>
                        <label for="req_date">Nécessite une date</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="requires_immatriculation" id="req_immat" <?php echo !empty($edit_rule['requires_immatriculation']) ? 'checked' : ''; ?>>
                        <label for="req_immat">Nécessite une immatriculation</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="active" id="active" <?php echo !isset($edit_rule) || !empty($edit_rule['active']) ? 'checked' : ''; ?>>
                        <label for="active">Règle active</label>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                    <a href="classification_rules.php" class="btn btn-secondary">❌ Annuler</a>
                </div>
            </form>
        </div>
        <?php else: ?>
            <div style="margin-bottom: 2rem;">
                <a href="?new" class="btn btn-primary">➕ Nouvelle règle</a>
                <a href="documents_admin.php" class="btn btn-secondary">← Retour aux documents</a>
            </div>
        <?php endif; ?>

        <!-- Liste des règles -->
        <h2 style="font-size: 1.5rem; margin-bottom: 1rem;">📋 Règles configurées</h2>
        
        <div class="rules-grid">
            <?php foreach ($rules as $rule): ?>
                <div class="rule-card <?php echo $rule['active'] ? '' : 'inactive'; ?>">
                    <div class="rule-header">
                        <div>
                            <div class="rule-name"><?php echo htmlspecialchars($rule['name']); ?></div>
                            <div class="rule-category">→ <?php echo htmlspecialchars($rule['category_name']); ?></div>
                        </div>
                        <div class="rule-priority">Priorité: <?php echo $rule['priority']; ?></div>
                    </div>

                    <?php if ($rule['required_keywords']): ?>
                        <div class="rule-keywords">
                            <strong>Obligatoire:</strong>
                            <?php foreach (explode('|', $rule['required_keywords']) as $kw): ?>
                                <span class="keyword-tag required"><?php echo htmlspecialchars($kw); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($rule['keywords']): ?>
                        <div class="rule-keywords">
                            <strong>Optionnels:</strong>
                            <?php foreach (array_slice(explode(',', $rule['keywords']), 0, 8) as $kw): ?>
                                <span class="keyword-tag"><?php echo htmlspecialchars(trim($kw)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="rule-requirements">
                        <?php if ($rule['requires_amount']): ?>
                            <div class="requirement-badge">💰 Montant requis</div>
                        <?php endif; ?>
                        <?php if ($rule['requires_date']): ?>
                            <div class="requirement-badge">📅 Date requise</div>
                        <?php endif; ?>
                        <?php if ($rule['requires_immatriculation']): ?>
                            <div class="requirement-badge">✈️ Immatriculation requise</div>
                        <?php endif; ?>
                        <?php if (!$rule['active']): ?>
                            <div class="requirement-badge">⏸️ Désactivée</div>
                        <?php endif; ?>
                    </div>

                    <div class="rule-actions">
                        <a href="?edit=<?php echo $rule['id']; ?>" class="btn btn-secondary btn-sm">✏️ Éditer</a>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_rule">
                            <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <?php echo $rule['active'] ? '⏸️ Désactiver' : '▶️ Activer'; ?>
                            </button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette règle ?');">
                            <input type="hidden" name="action" value="delete_rule">
                            <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑️ Supprimer</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
