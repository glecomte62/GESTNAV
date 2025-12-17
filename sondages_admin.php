<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'date_helper.php';
require_login();

// Vérifier que l'utilisateur est administrateur
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

// Message de succès après redirection
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'edited') {
        $message = "✅ Sondage et options modifiés avec succès";
    }
}

// Création d'un nouveau sondage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type = $_POST['type'] ?? 'choix_multiple';
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

        if (empty($titre)) {
            throw new Exception('Le titre est requis');
        }

        if (!in_array($type, ['date', 'choix_multiple'])) {
            throw new Exception('Type de sondage invalide');
        }

        $stmt = $pdo->prepare("INSERT INTO polls (titre, description, type, creator_id, deadline, status) VALUES (?, ?, ?, ?, ?, 'ouvert')");
        $stmt->execute([$titre, $description, $type, $_SESSION['user_id'], $deadline]);
        
        $poll_id = $pdo->lastInsertId();

        // Ajouter les options
        if ($type === 'date') {
            // Pour les sondages de date, les options seront des dates
            $dates = array_filter(array_map('trim', explode("\n", $_POST['date_options'] ?? '')));
            foreach ($dates as $date) {
                if (!empty($date)) {
                    $stmt = $pdo->prepare("INSERT INTO poll_options (poll_id, text) VALUES (?, ?)");
                    $stmt->execute([$poll_id, $date]);
                }
            }
        } else {
            // Pour les choix multiples
            $options = array_filter(array_map('trim', explode("\n", $_POST['options'] ?? '')));
            foreach ($options as $option) {
                if (!empty($option)) {
                    $stmt = $pdo->prepare("INSERT INTO poll_options (poll_id, text) VALUES (?, ?)");
                    $stmt->execute([$poll_id, $option]);
                }
            }
        }

        $message = "✅ Sondage créé avec succès ! ID: $poll_id";
    } catch (Exception $e) {
        $error = "❌ Erreur : " . $e->getMessage();
    }
}

// Éditer un sondage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $poll_id = intval($_POST['poll_id']);
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $allow_multiple_choices = isset($_POST['allow_multiple_choices']) ? 1 : 0;
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

        if (empty($titre)) {
            throw new Exception('Le titre est requis');
        }

        $stmt = $pdo->prepare("UPDATE polls SET titre = ?, description = ?, allow_multiple_choices = ?, deadline = ? WHERE id = ? AND creator_id = ?");
        $stmt->execute([$titre, $description, $allow_multiple_choices, $deadline, $poll_id, $_SESSION['user_id']]);
        
        // Gérer les options modifiées
        if (isset($_POST['option_id']) && is_array($_POST['option_id'])) {
            foreach ($_POST['option_id'] as $index => $option_id) {
                $option_text = trim($_POST['option_text'][$index] ?? '');
                if (!empty($option_text) && !empty($option_id)) {
                    // Mettre à jour l'option existante
                    $stmt = $pdo->prepare("UPDATE poll_options SET text = ? WHERE id = ? AND poll_id = ?");
                    $stmt->execute([$option_text, $option_id, $poll_id]);
                }
            }
        }
        
        // Gérer les nouvelles options
        if (isset($_POST['new_options']) && is_array($_POST['new_options'])) {
            foreach ($_POST['new_options'] as $new_option) {
                $new_option = trim($new_option);
                if (!empty($new_option)) {
                    $stmt = $pdo->prepare("INSERT INTO poll_options (poll_id, text) VALUES (?, ?)");
                    $stmt->execute([$poll_id, $new_option]);
                }
            }
        }
        
        // Gérer les options à supprimer
        if (isset($_POST['delete_options']) && is_array($_POST['delete_options'])) {
            foreach ($_POST['delete_options'] as $option_id_to_delete) {
                // Vérifier qu'il n'y a pas de votes sur cette option avant de la supprimer
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM poll_votes WHERE option_id = ?");
                $stmt->execute([$option_id_to_delete]);
                $votes_count = $stmt->fetchColumn();
                
                if ($votes_count == 0) {
                    $stmt = $pdo->prepare("DELETE FROM poll_options WHERE id = ? AND poll_id = ?");
                    $stmt->execute([$option_id_to_delete, $poll_id]);
                }
            }
        }
        
        $message = "✅ Sondage et options modifiés avec succès";
        // Rediriger pour éviter la resoumission
        header("Location: sondages_admin.php?msg=edited");
        exit;
    } catch (Exception $e) {
        $error = "❌ Erreur : " . $e->getMessage();
    }
}

// Clôturer un sondage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close') {
    try {
        $poll_id = intval($_POST['poll_id']);
        $stmt = $pdo->prepare("UPDATE polls SET status = 'clos' WHERE id = ? AND creator_id = ?");
        $stmt->execute([$poll_id, $_SESSION['user_id']]);
        $message = "✅ Sondage clôturé";
    } catch (Exception $e) {
        $error = "❌ Erreur : " . $e->getMessage();
    }
}

// Récupérer un sondage spécifique pour édition
$poll_to_edit = null;
$poll_options = [];
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM polls WHERE id = ? AND creator_id = ?");
    $stmt->execute([$edit_id, $_SESSION['user_id']]);
    $poll_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Charger les options du sondage
    if ($poll_to_edit) {
        $stmt = $pdo->prepare("SELECT * FROM poll_options WHERE poll_id = ? ORDER BY id ASC");
        $stmt->execute([$poll_to_edit['id']]);
        $poll_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Récupérer les sondages
$stmt = $pdo->prepare("
    SELECT p.*, u.email, COUNT(DISTINCT pv.id) as total_votes 
    FROM polls p 
    LEFT JOIN users u ON p.creator_id = u.id 
    LEFT JOIN poll_votes pv ON p.id = pv.poll_id 
    WHERE p.creator_id = ? 
    GROUP BY p.id 
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

require 'header.php';
?>

<style>
.sondages-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.section-title {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 3px solid #004b8d;
}

.two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 3rem;
}

.form-card {
    background: white;
    border-radius: 1.25rem;
    padding: 2rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.03);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    font-family: inherit;
    font-size: 0.95rem;
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #00a0c6;
    box-shadow: 0 0 0 3px rgba(0, 160, 198, 0.1);
}

.btn-primary {
    background: linear-gradient(135deg, #004b8d 0%, #00a0c6 100%);
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.95rem;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 75, 141, 0.3);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

.btn-danger {
    background: #ef4444;
    color: white;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.85rem;
}

.btn-danger:hover {
    background: #dc2626;
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

.polls-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.poll-card {
    background: white;
    border-radius: 1.25rem;
    padding: 1.75rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
}

.poll-card.clos {
    opacity: 0.7;
    background: #f9fafb;
}

.poll-badge {
    display: inline-block;
    width: fit-content;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}

.poll-badge.ouvert {
    background: #dcfce7;
    color: #166534;
}

.poll-badge.clos {
    background: #fee2e2;
    color: #991b1b;
}

.poll-type {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(0, 160, 198, 0.1);
    color: #004b8d;
    margin-bottom: 0.75rem;
}

.poll-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.5rem;
}

.poll-description {
    color: #6b7280;
    font-size: 0.9rem;
    margin: 0 0 1rem;
    line-height: 1.6;
}

.poll-meta {
    font-size: 0.85rem;
    color: #9ca3af;
    margin: 1rem 0;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.poll-options {
    margin: 1rem 0;
    flex-grow: 1;
}

.poll-option {
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
}

.poll-option-bar {
    background: #f3f4f6;
    border-radius: 0.25rem;
    height: 6px;
    margin-top: 0.25rem;
    overflow: hidden;
}

.poll-option-fill {
    background: linear-gradient(90deg, #004b8d, #00a0c6);
    height: 100%;
    transition: width 0.3s;
}

.poll-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.9rem;
    color: #6b7280;
    margin: 1rem 0;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.poll-stat {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.poll-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
}

.poll-actions form {
    display: contents;
}

.form-inline {
    display: inline;
}

.type-toggle {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.type-btn {
    flex: 1;
    padding: 0.75rem;
    border: 2px solid #e5e7eb;
    background: white;
    border-radius: 0.5rem;
    cursor: pointer;
    font-weight: 600;
    color: #6b7280;
    transition: all 0.3s;
}

.type-btn.active {
    border-color: #00a0c6;
    background: rgba(0, 160, 198, 0.1);
    color: #004b8d;
}

.type-btn:hover {
    border-color: #00a0c6;
}

.options-input {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.options-note {
    font-size: 0.85rem;
    color: #6b7280;
    font-style: italic;
}

@media (max-width: 1024px) {
    .two-columns {
        grid-template-columns: 1fr;
    }
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: white;
    border-radius: 1.25rem;
    color: #9ca3af;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
}

.modal-content {
    background: white;
    border-radius: 1.25rem;
    padding: 2rem;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e5e7eb;
}

.modal-header h2 {
    margin: 0;
    color: #1f2937;
    font-size: 1.5rem;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #9ca3af;
    padding: 0.25rem;
    line-height: 1;
}

.btn-close:hover {
    color: #1f2937;
}

.btn-secondary {
    background: #6b7280;
    color: white;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.85rem;
    text-decoration: none;
    display: inline-block;
}

.btn-secondary:hover {
    background: #4b5563;
}
</style>

<!-- Modal d'édition -->
<?php if ($poll_to_edit): ?>
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>✏️ Éditer le sondage</h2>
            <button type="button" class="btn-close" onclick="closeEditModal()">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="poll_id" value="<?php echo $poll_to_edit['id']; ?>">

            <div class="form-group">
                <label>📋 Titre du sondage *</label>
                <input type="text" name="titre" value="<?php echo htmlspecialchars($poll_to_edit['titre']); ?>" required>
            </div>

            <div class="form-group">
                <label>📝 Description</label>
                <textarea name="description"><?php echo htmlspecialchars($poll_to_edit['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="allow_multiple_choices" value="1" <?php echo !empty($poll_to_edit['allow_multiple_choices']) ? 'checked' : ''; ?> style="width: auto;">
                    <span>✅ Autoriser le choix multiple (les membres peuvent voter pour plusieurs options)</span>
                </label>
                <div class="options-note" style="margin-top: 0.5rem;">
                    Particulièrement utile pour les sondages de dates où un membre peut être disponible plusieurs jours
                </div>
            </div>

            <div class="form-group">
                <label>⏰ Date de fermeture (optionnel)</label>
                <input type="datetime-local" name="deadline" value="<?php echo $poll_to_edit['deadline'] ? date('Y-m-d\TH:i', strtotime($poll_to_edit['deadline'])) : ''; ?>">
                <div class="options-note">Laissez vide pour fermer manuellement</div>
            </div>

            <div class="form-group">
                <label>📝 Options du sondage</label>
                <div id="edit-options-container">
                    <?php foreach ($poll_options as $index => $option): ?>
                    <div class="option-item" data-option-id="<?= $option['id'] ?>">
                        <input type="hidden" name="option_id[]" value="<?= $option['id'] ?>">
                        <input type="text" name="option_text[]" value="<?= htmlspecialchars($option['text']) ?>" placeholder="Option <?= $index + 1 ?>" style="flex: 1;">
                        <button type="button" class="btn-remove-option" onclick="removeExistingOption(this, <?= $option['id'] ?>)" title="Supprimer cette option">🗑️</button>
                    </div>
                    <?php endforeach; ?>
                    <div id="new-options-edit-container"></div>
                </div>
                <button type="button" class="btn-add-option" onclick="addEditOption()">➕ Ajouter une option</button>
                <div class="options-note">⚠️ Les options avec des votes ne peuvent pas être supprimées</div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="submit" class="btn-primary" style="flex: 1;">💾 Enregistrer</button>
                <button type="button" class="btn-secondary" onclick="closeEditModal()" style="flex: 1;">❌ Annuler</button>
            </div>
        </form>
    </div>
</div>

<style>
.option-item {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    align-items: center;
}

.option-item input[type="text"] {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
}

.btn-remove-option {
    background: #ef4444;
    color: white;
    border: none;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-remove-option:hover {
    background: #dc2626;
}

.btn-add-option {
    background: #10b981;
    color: white;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    margin-top: 0.5rem;
}

.btn-add-option:hover {
    background: #059669;
}
</style>

<script>
let editOptionCounter = 0;
let deletedOptions = [];

function addEditOption() {
    editOptionCounter++;
    const container = document.getElementById('new-options-edit-container');
    const div = document.createElement('div');
    div.className = 'option-item';
    div.innerHTML = `
        <input type="text" name="new_options[]" placeholder="Nouvelle option" style="flex: 1;">
        <button type="button" class="btn-remove-option" onclick="this.parentElement.remove()" title="Supprimer">🗑️</button>
    `;
    container.appendChild(div);
}

function removeExistingOption(button, optionId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette option ? (Les options avec des votes ne seront pas supprimées)')) {
        const optionItem = button.closest('.option-item');
        
        // Ajouter un champ caché pour marquer l'option comme supprimée
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_options[]';
        input.value = optionId;
        optionItem.appendChild(input);
        
        // Masquer visuellement
        optionItem.style.display = 'none';
    }
}

function closeEditModal() {
    window.location.href = 'sondages_admin.php';
}

// Auto-ouvrir le modal si on est en mode édition
<?php if ($poll_to_edit): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('editModal').style.display = 'flex';
});
<?php endif; ?>
</script>
<?php endif; ?>

<div class="sondages-container">
    <h1 class="section-title">🗳️ Gestion des sondages</h1>

    <?php if ($message): ?>
        <div class="message success"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="two-columns">
        <!-- Formulaire de création -->
        <div class="form-card">
            <h2 style="font-size: 1.35rem; color: #1f2937; margin-top: 0;">➕ Créer un sondage</h2>

            <form method="POST" onchange="updateFormFields()">
                <input type="hidden" name="action" value="create">

                <div class="form-group">
                    <label>📋 Titre du sondage *</label>
                    <input type="text" name="titre" placeholder="Ex: Date de la prochaine sortie" required>
                </div>

                <div class="form-group">
                    <label>📝 Description</label>
                    <textarea name="description" placeholder="Contexte ou détails du sondage..."></textarea>
                </div>

                <div class="form-group">
                    <label>🎯 Type de sondage</label>
                    <div class="type-toggle">
                        <button type="button" class="type-btn active" onclick="setType('choix_multiple')">
                            📊 Choix multiple
                        </button>
                        <button type="button" class="type-btn" onclick="setType('date')">
                            📅 Sondage de date
                        </button>
                    </div>
                    <input type="hidden" name="type" id="type" value="choix_multiple">
                </div>

                <div class="form-group" id="options-group">
                    <label>⭐ Options (une par ligne) *</label>
                    <div class="options-input">
                        <textarea name="options" id="options" placeholder="Option 1&#10;Option 2&#10;Option 3" required></textarea>
                        <div class="options-note">Entrez chaque option sur une nouvelle ligne</div>
                    </div>
                </div>

                <div class="form-group" id="date-group" style="display: none;">
                    <label>📅 Dates proposées (une par ligne)</label>
                    <div class="options-input">
                        <textarea name="date_options" id="date_options" placeholder="Samedi 15 mars&#10;Dimanche 16 mars"></textarea>
                        <div class="options-note">Entrez chaque date sur une nouvelle ligne</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>⏰ Date de fermeture (optionnel)</label>
                    <input type="datetime-local" name="deadline">
                    <div class="options-note">Laissez vide pour fermer manuellement</div>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%;">✅ Créer le sondage</button>
            </form>
        </div>

        <!-- Infos et aide -->
        <div class="form-card" style="background: linear-gradient(135deg, rgba(0, 160, 198, 0.05) 0%, rgba(0, 75, 141, 0.05) 100%);">
            <h2 style="font-size: 1.35rem; color: #004b8d; margin-top: 0;">💡 Guide</h2>
            
            <h4 style="color: #1f2937; margin-bottom: 0.75rem;">📊 Choix multiple</h4>
            <p style="color: #6b7280; font-size: 0.9rem; margin: 0 0 1.5rem;">
                Pour des questions avec plusieurs réponses possibles. Chaque membre vote pour son choix préféré.
            </p>

            <h4 style="color: #1f2937; margin-bottom: 0.75rem;">📅 Sondage de date</h4>
            <p style="color: #6b7280; font-size: 0.9rem; margin: 0 0 1.5rem;">
                Idéal pour caler une date. Les membres votent pour leur(s) date(s) préférée(s).
            </p>

            <h4 style="color: #1f2937; margin-bottom: 0.75rem;">🔔 Après création</h4>
            <ul style="color: #6b7280; font-size: 0.9rem; margin: 0;">
                <li>Envoyez une notification mail aux membres</li>
                <li>Clôturez manuellement ou à une date définie</li>
                <li>Consultez les résultats en temps réel</li>
            </ul>

            <div style="background: white; padding: 1rem; border-radius: 0.5rem; margin-top: 1.5rem; border-left: 4px solid #10b981;">
                <p style="margin: 0; color: #166534; font-size: 0.9rem;">
                    <strong>✅ Conseil:</strong> Fixez une deadline pour clôturer le sondage automatiquement et avoir des résultats définitifs.
                </p>
            </div>
        </div>
    </div>

    <!-- Liste des sondages -->
    <h2 class="section-title" style="margin-top: 3rem;">📊 Mes sondages</h2>

    <?php if (empty($polls)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🗳️</div>
            <p>Aucun sondage créé pour le moment.</p>
            <p style="font-size: 0.9rem;">Créez votre premier sondage avec le formulaire ci-dessus !</p>
        </div>
    <?php else: ?>
        <div class="polls-grid">
            <?php foreach ($polls as $poll): ?>
                <div class="poll-card <?php echo $poll['status']; ?>">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1rem;">
                        <div>
                            <span class="poll-badge <?php echo $poll['status']; ?>">
                                <?php echo $poll['status'] === 'ouvert' ? '🟢 OUVERT' : '🔴 CLÔTURÉ'; ?>
                            </span>
                            <span class="poll-type">
                                <?php echo $poll['type'] === 'date' ? '📅 Date' : '📊 Choix multiple'; ?>
                            </span>
                        </div>
                    </div>

                    <h3 class="poll-title"><?php echo htmlspecialchars($poll['titre']); ?></h3>

                    <?php if (!empty($poll['description'])): ?>
                        <p class="poll-description"><?php echo htmlspecialchars($poll['description']); ?></p>
                    <?php endif; ?>

                    <!-- Options et résultats -->
                    <div class="poll-options">
                        <?php
                        // Trier par date chronologique pour les sondages de type date, sinon par votes
                        if ($poll['type'] === 'date') {
                            $stmt = $pdo->prepare("SELECT po.*, COUNT(pv.id) as votes FROM poll_options po LEFT JOIN poll_votes pv ON po.id = pv.option_id WHERE po.poll_id = ? GROUP BY po.id ORDER BY po.id ASC");
                        } else {
                            $stmt = $pdo->prepare("SELECT po.*, COUNT(pv.id) as votes FROM poll_options po LEFT JOIN poll_votes pv ON po.id = pv.option_id WHERE po.poll_id = ? GROUP BY po.id ORDER BY votes DESC");
                        }
                        $stmt->execute([$poll['id']]);
                        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Pour les sondages de type date, trier les options en PHP par date réelle
                        if ($poll['type'] === 'date') {
                            usort($options, function($a, $b) {
                                $dateA = parse_french_date($a['text']);
                                $dateB = parse_french_date($b['text']);
                                return $dateA <=> $dateB;
                            });
                        }
                        
                        $total_votes = array_sum(array_column($options, 'votes'));
                        ?>

                        <?php foreach ($options as $option): ?>
                            <div class="poll-option">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span><?php echo htmlspecialchars($option['text']); ?></span>
                                    <strong><?php echo $option['votes']; ?> vote<?php echo $option['votes'] !== 1 ? 's' : ''; ?></strong>
                                </div>
                                <div class="poll-option-bar">
                                    <div class="poll-option-fill" style="width: <?php echo $total_votes > 0 ? ($option['votes'] / $total_votes * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Statistiques -->
                    <div class="poll-stats">
                        <div class="poll-stat">
                            🗳️ <strong><?php echo $poll['total_votes']; ?></strong> vote<?php echo $poll['total_votes'] !== 1 ? 's' : ''; ?>
                        </div>
                        <?php if ($poll['deadline']): ?>
                            <div class="poll-stat">
                                ⏰ <?php echo date('d/m/Y H:i', strtotime($poll['deadline'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="poll-actions">
                        <a href="sondages_detail.php?id=<?php echo $poll['id']; ?>" class="btn-primary btn-sm" style="text-decoration: none; text-align: center; flex: 1;">
                            👁️ Détails
                        </a>
                        <?php if ($poll['status'] === 'ouvert'): ?>
                            <a href="?edit=<?php echo $poll['id']; ?>" class="btn-secondary btn-sm" style="text-decoration: none; text-align: center; flex: 1;">
                                ✏️ Éditer
                            </a>
                            <form method="POST" class="form-inline" style="flex: 1;">
                                <input type="hidden" name="action" value="close">
                                <input type="hidden" name="poll_id" value="<?php echo $poll['id']; ?>">
                                <button type="submit" class="btn-danger" style="width: 100%;" onclick="return confirm('Clôturer ce sondage ?');">🔒 Clôturer</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function setType(type) {
    document.getElementById('type').value = type;
    
    const btns = document.querySelectorAll('.type-btn');
    btns.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    const optionsGroup = document.getElementById('options-group');
    const dateGroup = document.getElementById('date-group');

    if (type === 'date') {
        optionsGroup.style.display = 'none';
        dateGroup.style.display = 'block';
        document.getElementById('options').removeAttribute('required');
        document.getElementById('date_options').setAttribute('required', 'required');
    } else {
        optionsGroup.style.display = 'block';
        dateGroup.style.display = 'none';
        document.getElementById('options').setAttribute('required', 'required');
        document.getElementById('date_options').removeAttribute('required');
    }
}

function updateFormFields() {
    // Sync when form changes
}

function closeEditModal() {
    window.location.href = 'sondages_admin.php';
}
</script>

<?php require 'footer.php'; ?>
