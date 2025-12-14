<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

// Vérifier que c'est un admin
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

$poll_id = intval($_GET['id'] ?? 0);

// Récupérer le sondage
$stmt = $pdo->prepare("SELECT * FROM polls WHERE id = ? AND creator_id = ?");
$stmt->execute([$poll_id, $_SESSION['user_id']]);
$poll = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$poll) {
    header('Location: sondages_admin.php');
    exit;
}

// Récupérer les options avec votes détaillés
// Trier par date chronologique pour les sondages de type date, sinon par votes
$order_by = $poll['type'] === 'date' ? 'po.text ASC' : 'votes DESC';
$stmt = $pdo->prepare("
    SELECT po.*, COUNT(DISTINCT pv.id) as votes
    FROM poll_options po
    LEFT JOIN poll_votes pv ON po.id = pv.option_id
    WHERE po.poll_id = ?
    GROUP BY po.id
    ORDER BY $order_by
");
$stmt->execute([$poll_id]);
$options = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les votes détaillés
$stmt = $pdo->prepare("
    SELECT pv.*, u.email, u.prenom, u.nom, po.text as option_text
    FROM poll_votes pv
    JOIN users u ON pv.user_id = u.id
    JOIN poll_options po ON pv.option_id = po.id
    WHERE pv.poll_id = ?
    ORDER BY pv.created_at DESC
");
$stmt->execute([$poll_id]);
$votes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_votes = count($votes);

require 'header.php';
?>

<style>
.detail-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.back-button {
    display: inline-block;
    margin-bottom: 2rem;
    color: #00a0c6;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.back-button:hover {
    color: #004b8d;
}

.header-card {
    background: white;
    border-radius: 1.25rem;
    padding: 2rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.03);
    margin-bottom: 2rem;
}

.poll-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 2rem;
    margin-bottom: 1.5rem;
}

.poll-info h1 {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.5rem;
}

.poll-info-text {
    color: #6b7280;
    font-size: 0.95rem;
    margin: 0.25rem 0;
    line-height: 1.6;
}

.poll-badges {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
}

.badge-status {
    background: #f0fdf4;
    color: #166534;
}

.badge-status.clos {
    background: #fee2e2;
    color: #991b1b;
}

.badge-type {
    background: rgba(0, 160, 198, 0.1);
    color: #004b8d;
}

.actions-group {
    display: flex;
    gap: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.95rem;
}

.btn-primary {
    background: linear-gradient(135deg, #004b8d 0%, #00a0c6 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 75, 141, 0.3);
}

.two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.card {
    background: white;
    border-radius: 1.25rem;
    padding: 2rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.03);
}

.card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 1.5rem;
}

.option-item {
    margin-bottom: 1.5rem;
}

.option-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.option-text {
    font-weight: 600;
    color: #1f2937;
}

.option-votes {
    font-size: 1.1rem;
    font-weight: 700;
    color: #00a0c6;
}

.option-bar {
    background: #f3f4f6;
    border-radius: 0.25rem;
    height: 8px;
    overflow: hidden;
}

.option-fill {
    background: linear-gradient(90deg, #004b8d, #00a0c6);
    height: 100%;
    transition: width 0.3s;
}

.option-percentage {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.modal {
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
    padding: 1rem;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 1.25rem;
    padding: 2rem;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
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

.form-group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    font-family: inherit;
}

.modal-footer {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.btn-secondary {
    background: #e5e7eb;
    color: #1f2937;
}

.btn-secondary:hover {
    background: #d1d5db;
}

.votes-list {
    max-height: 400px;
    overflow-y: auto;
}

.vote-item {
    padding: 1rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
    border-left: 4px solid #00a0c6;
}

.vote-user {
    font-weight: 600;
    color: #1f2937;
}

.vote-option {
    color: #6b7280;
    margin-top: 0.25rem;
}

.vote-time {
    color: #9ca3af;
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, rgba(0, 160, 198, 0.05), rgba(0, 75, 141, 0.05));
    padding: 1.5rem;
    border-radius: 0.75rem;
    text-align: center;
    border: 1px solid rgba(0, 160, 198, 0.1);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #004b8d;
}

.stat-label {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 0.5rem;
}

@media (max-width: 768px) {
    .two-columns {
        grid-template-columns: 1fr;
    }

    .poll-header {
        flex-direction: column;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="detail-container">
    <a href="sondages_admin.php" class="back-button">← Retour aux sondages</a>

    <!-- En-tête principal -->
    <div class="header-card">
        <div class="poll-header">
            <div class="poll-info">
                <h1><?php echo htmlspecialchars($poll['titre']); ?></h1>
                <?php if (!empty($poll['description'])): ?>
                    <p class="poll-info-text"><?php echo htmlspecialchars($poll['description']); ?></p>
                <?php endif; ?>
                <div class="poll-badges">
                    <span class="badge badge-type">
                        <?php echo $poll['type'] === 'date' ? '📅 Sondage de date' : '📊 Choix multiple'; ?>
                    </span>
                    <span class="badge badge-status <?php echo $poll['status']; ?>">
                        <?php echo $poll['status'] === 'ouvert' ? '🟢 OUVERT' : '🔴 CLÔTURÉ'; ?>
                    </span>
                </div>
            </div>
            <div class="actions-group">
                <button type="button" class="btn btn-primary" onclick="openNotificationModal()">
                    📧 Notifier les membres
                </button>
            </div>
        </div>

        <!-- Statistiques rapides -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_votes; ?></div>
                <div class="stat-label">Vote<?php echo $total_votes !== 1 ? 's' : ''; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($options); ?></div>
                <div class="stat-label">Option<?php echo count($options) !== 1 ? 's' : ''; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo date('d/m/Y', strtotime($poll['created_at'])); ?></div>
                <div class="stat-label">Créé le</div>
            </div>
        </div>
    </div>

    <!-- Résultats et votes -->
    <div class="two-columns">
        <!-- Résultats par option -->
        <div class="card">
            <h2 class="card-title">📊 Résultats par option</h2>
            <div>
                <?php foreach ($options as $option): ?>
                    <div class="option-item">
                        <div class="option-header">
                            <span class="option-text"><?php echo htmlspecialchars($option['text']); ?></span>
                            <span class="option-votes"><?php echo $option['votes']; ?></span>
                        </div>
                        <div class="option-bar">
                            <div class="option-fill" style="width: <?php echo $total_votes > 0 ? ($option['votes'] / $total_votes * 100) : 0; ?>%"></div>
                        </div>
                        <div class="option-percentage">
                            <?php echo $total_votes > 0 ? round($option['votes'] / $total_votes * 100) : 0; ?>% (<?php echo $option['votes']; ?>/<?php echo $total_votes; ?>)
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($total_votes === 0): ?>
                    <p style="color: #9ca3af; text-align: center;">Aucun vote pour le moment</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historique des votes -->
        <div class="card">
            <h2 class="card-title">🗳️ Historique des votes</h2>
            <div class="votes-list">
                <?php if (empty($votes)): ?>
                    <p style="color: #9ca3af; text-align: center;">Aucun vote enregistré</p>
                <?php else: ?>
                    <?php foreach ($votes as $vote): ?>
                        <div class="vote-item">
                            <div class="vote-user">✅ <?php echo htmlspecialchars($vote['prenom'] . ' ' . $vote['nom']); ?></div>
                            <div class="vote-option">pour: <strong><?php echo htmlspecialchars($vote['option_text']); ?></strong></div>
                            <div class="vote-time"><?php echo date('d/m/Y H:i', strtotime($vote['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de notification -->
<div id="notificationModal" class="modal">
    <div class="modal-content">
        <h2 class="modal-header">📧 Notifier les membres</h2>

        <form id="notificationForm" onsubmit="sendNotification(event)">
            <input type="hidden" name="action" value="notify">
            <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>">

            <div class="form-group">
                <label>Type de destinataires</label>
                <select name="recipient_type" required>
                    <option value="all">Tous les membres</option>
                    <option value="club">Membres Club</option>
                    <option value="actif">Membres Actifs</option>
                    <option value="invite">Invités</option>
                </select>
            </div>

            <div class="form-group">
                <p style="color: #6b7280; font-size: 0.9rem;">
                    Un email sera envoyé aux membres sélectionnés pour les inviter à voter.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeNotificationModal()">
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary">
                    📧 Envoyer notification
                </button>
            </div>
        </form>

        <div id="notificationMessage" style="margin-top: 1rem;"></div>
    </div>
</div>

<script>
function openNotificationModal() {
    document.getElementById('notificationModal').classList.add('active');
}

function closeNotificationModal() {
    document.getElementById('notificationModal').classList.remove('active');
    document.getElementById('notificationForm').reset();
    document.getElementById('notificationMessage').innerHTML = '';
}

function sendNotification(event) {
    event.preventDefault();

    const form = document.getElementById('notificationForm');
    const poll_id = form.querySelector('input[name="poll_id"]').value;
    const recipient_type = form.querySelector('select[name="recipient_type"]').value;

    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = '⏳ Envoi en cours...';

    fetch('send_poll_notification.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => response.json())
    .then(data => {
        const messageDiv = document.getElementById('notificationMessage');
        if (data.success) {
            messageDiv.innerHTML = '<div style="background: #f0fdf4; border-left: 4px solid #10b981; padding: 1rem; border-radius: 0.5rem; color: #166534;">' + data.message + '</div>';
            button.textContent = '📧 Envoyer notification';
            button.disabled = false;
            setTimeout(() => {
                closeNotificationModal();
            }, 2000);
        } else {
            messageDiv.innerHTML = '<div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 1rem; border-radius: 0.5rem; color: #991b1b;">' + data.message + '</div>';
            button.textContent = '📧 Envoyer notification';
            button.disabled = false;
        }
    })
    .catch(error => {
        document.getElementById('notificationMessage').innerHTML = '<div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 1rem; border-radius: 0.5rem; color: #991b1b;">❌ Erreur: ' + error.message + '</div>';
        button.textContent = '📧 Envoyer notification';
        button.disabled = false;
    });
}

// Fermer la modal en cliquant en dehors
document.getElementById('notificationModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeNotificationModal();
    }
});
</script>

<?php require 'footer.php'; ?>
