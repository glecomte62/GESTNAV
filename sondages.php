<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

$message = '';
$error = '';

// Traiter un vote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'vote') {
    try {
        $poll_id = intval($_POST['poll_id']);
        $user_id = $_SESSION['user_id'];

        // Vérifier que le sondage existe et est ouvert
        $stmt = $pdo->prepare("SELECT * FROM polls WHERE id = ? AND status = 'ouvert'");
        $stmt->execute([$poll_id]);
        $poll = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$poll) {
            throw new Exception('Sondage non trouvé ou fermé');
        }

        // Récupérer les options sélectionnées
        $option_ids = isset($_POST['option_ids']) ? $_POST['option_ids'] : (isset($_POST['option_id']) ? [$_POST['option_id']] : []);
        
        if (empty($option_ids)) {
            throw new Exception('Veuillez sélectionner au moins une option');
        }

        // Si choix multiple non autorisé, limiter à une seule option
        if (!$poll['allow_multiple_choices'] && count($option_ids) > 1) {
            throw new Exception('Ce sondage n\'autorise qu\'un seul choix');
        }

        // Vérifier que toutes les options existent
        foreach ($option_ids as $option_id) {
            $option_id = intval($option_id);
            $stmt = $pdo->prepare("SELECT * FROM poll_options WHERE id = ? AND poll_id = ?");
            $stmt->execute([$option_id, $poll_id]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception('Option invalide');
            }
        }

        // Supprimer les votes existants de l'utilisateur pour ce sondage
        $stmt = $pdo->prepare("DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?");
        $stmt->execute([$poll_id, $user_id]);

        // Ajouter les nouveaux votes
        $stmt = $pdo->prepare("INSERT INTO poll_votes (poll_id, user_id, option_id) VALUES (?, ?, ?)");
        foreach ($option_ids as $option_id) {
            $stmt->execute([$poll_id, $user_id, intval($option_id)]);
        }

        $message = "✅ Votre vote a été enregistré !";
    } catch (Exception $e) {
        $error = "❌ Erreur : " . $e->getMessage();
    }
}

// Récupérer les sondages ouverts
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.email, COUNT(DISTINCT pv.id) as total_votes
        FROM polls p
        LEFT JOIN users u ON p.creator_id = u.id
        LEFT JOIN poll_votes pv ON p.id = pv.poll_id
        WHERE p.status = 'ouvert' 
        AND (p.deadline IS NULL OR p.deadline > NOW())
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $polls = [];
    $error = "Erreur lors du chargement des sondages: " . $e->getMessage();
}

// Récupérer les sondages clôturés
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.email, COUNT(DISTINCT pv.id) as total_votes
        FROM polls p
        LEFT JOIN users u ON p.creator_id = u.id
        LEFT JOIN poll_votes pv ON p.id = pv.poll_id
        WHERE p.status = 'cloture' 
        OR (p.deadline IS NOT NULL AND p.deadline <= NOW())
        GROUP BY p.id
        ORDER BY COALESCE(p.deadline, p.created_at) DESC
    ");
    $stmt->execute();
    $closed_polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $closed_polls = [];
}

require 'header.php';
?>

<style>
.sondages-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.page-header {
    text-align: center;
    margin-bottom: 3rem;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.5rem;
}

.page-subtitle {
    font-size: 1.1rem;
    color: #6b7280;
    margin: 0;
}

.message {
    padding: 1rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
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

.polls-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
    gap: 2rem;
}

.poll-card {
    background: white;
    border-radius: 1.25rem;
    padding: 2rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
}

.poll-header {
    margin-bottom: 1.5rem;
}

.poll-type-badge {
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
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.5rem;
}

.poll-description {
    color: #6b7280;
    font-size: 0.95rem;
    line-height: 1.6;
    margin: 0;
}

.poll-content {
    flex-grow: 1;
    margin: 1.5rem 0;
}

.poll-option {
    margin-bottom: 1rem;
}

.poll-option label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    border: 2px solid transparent;
    transition: all 0.2s;
}

.poll-option label:hover {
    background: #f3f4f6;
    border-color: #e5e7eb;
}

.poll-option input[type="radio"] {
    cursor: pointer;
    accent-color: #00a0c6;
    width: 1.25rem;
    height: 1.25rem;
    flex-shrink: 0;
}

.poll-option-text {
    flex-grow: 1;
    font-weight: 500;
    color: #1f2937;
}

.poll-option-votes {
    font-size: 0.85rem;
    color: #9ca3af;
    text-align: right;
    flex-shrink: 0;
}

.poll-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
    margin-top: 1.5rem;
}

.poll-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: #6b7280;
}

.poll-meta-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.poll-action {
    display: flex;
    gap: 0.75rem;
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

.btn-vote {
    background: linear-gradient(135deg, #004b8d 0%, #00a0c6 100%);
    color: white;
    flex: 1;
}

.btn-vote:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 75, 141, 0.3);
}

.btn-vote:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.user-vote-info {
    font-size: 0.85rem;
    color: #10b981;
    padding: 0.5rem 0;
}

.multiple-choice-notice {
    background: #dcfce7;
    border-left: 4px solid #10b981;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    border-radius: 0.5rem;
    color: #166534;
    font-size: 0.9rem;
    font-weight: 500;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: white;
    border-radius: 1.25rem;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.empty-state-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.empty-state-text {
    color: #6b7280;
}

.deadline-warning {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 0.75rem;
    border-radius: 0.35rem;
    font-size: 0.85rem;
    color: #92400e;
    margin-bottom: 1rem;
}

.closed-section {
    margin-top: 4rem;
}

.section-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e5e7eb;
}

.section-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #6b7280;
    margin: 0 0 0.5rem;
}

.section-subtitle {
    color: #9ca3af;
    font-size: 0.95rem;
}

.poll-card.closed {
    opacity: 0.85;
    border: 1px solid #e5e7eb;
}

.closed-badge {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    background: #fef2f2;
    color: #991b1b;
    margin-bottom: 0.75rem;
}

.results-bar {
    margin-top: 0.5rem;
    height: 8px;
    background: #e5e7eb;
    border-radius: 999px;
    overflow: hidden;
}

.results-bar-fill {
    height: 100%;
    background: linear-gradient(135deg, #004b8d 0%, #00a0c6 100%);
    transition: width 0.5s ease;
}

.winner-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    background: #ecfdf5;
    color: #047857;
    margin-left: 0.5rem;
}

@media (max-width: 768px) {
    .polls-container {
        grid-template-columns: 1fr;
    }

    .page-title {
        font-size: 2rem;
    }
}
</style>

<div class="sondages-page">
    <!-- En-tête -->
    <div class="page-header">
        <h1 class="page-title">🗳️ Sondages</h1>
        <p class="page-subtitle">Votez pour les prochaines dates et décisions du club</p>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="message success"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Sondages -->
    <?php if (empty($polls)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🗳️</div>
            <div class="empty-state-title">Aucun sondage actif</div>
            <p class="empty-state-text">Il n'y a actuellement aucun sondage ouvert. Revenez bientôt !</p>
        </div>
    <?php else: ?>
        <div class="polls-container">
            <?php foreach ($polls as $poll): ?>
                <div class="poll-card">
                    <!-- En-tête du sondage -->
                    <div class="poll-header">
                        <span class="poll-type-badge">
                            <?php echo $poll['type'] === 'date' ? '📅 Sondage de date' : '📊 Choix multiple'; ?>
                        </span>
                        <h2 class="poll-title"><?php echo htmlspecialchars($poll['titre']); ?></h2>
                        <?php if (!empty($poll['description'])): ?>
                            <p class="poll-description"><?php echo htmlspecialchars($poll['description']); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Deadline warning -->
                    <?php if ($poll['deadline']): ?>
                        <?php 
                        $deadline_time = strtotime($poll['deadline']);
                        $now = time();
                        $hours_left = ($deadline_time - $now) / 3600;
                        ?>
                        <?php if ($hours_left < 24 && $hours_left > 0): ?>
                            <div class="deadline-warning">
                                ⏰ Clôture dans <?php echo round($hours_left); ?> heure<?php echo round($hours_left) > 1 ? 's' : ''; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Contenu du vote -->
                    <form method="POST" class="poll-content">
                        <input type="hidden" name="action" value="vote">
                        <input type="hidden" name="poll_id" value="<?php echo $poll['id']; ?>">

                        <?php
                        // Récupérer les options et votes
                        $stmt = $pdo->prepare("
                            SELECT po.*, COUNT(pv.id) as votes
                            FROM poll_options po
                            LEFT JOIN poll_votes pv ON po.id = pv.option_id
                            WHERE po.poll_id = ?
                            GROUP BY po.id
                            ORDER BY votes DESC
                        ");
                        $stmt->execute([$poll['id']]);
                        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Récupérer le vote de l'utilisateur
                        $stmt = $pdo->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
                        $stmt->execute([$poll['id'], $_SESSION['user_id']]);
                        $user_votes = $stmt->fetchAll(PDO::FETCH_COLUMN);

                        $total_votes = array_sum(array_column($options, 'votes'));
                        
                        $input_type = !empty($poll['allow_multiple_choices']) ? 'checkbox' : 'radio';
                        $input_name = !empty($poll['allow_multiple_choices']) ? 'option_ids[]' : 'option_id';
                        ?>

                        <?php if (!empty($poll['allow_multiple_choices'])): ?>
                            <div class="multiple-choice-notice">
                                ✅ Vous pouvez sélectionner plusieurs options
                            </div>
                        <?php endif; ?>

                        <?php foreach ($options as $option): ?>
                            <div class="poll-option">
                                <label>
                                    <input type="<?php echo $input_type; ?>" name="<?php echo $input_name; ?>" value="<?php echo $option['id']; ?>" 
                                        <?php echo in_array($option['id'], $user_votes) ? 'checked' : ''; ?>>
                                    <span class="poll-option-text"><?php echo htmlspecialchars($option['text']); ?></span>
                                    <span class="poll-option-votes">
                                        <?php echo $option['votes']; ?> vote<?php echo $option['votes'] !== 1 ? 's' : ''; ?>
                                        <?php if ($total_votes > 0): ?>
                                            (<?php echo round($option['votes'] / $total_votes * 100); ?>%)
                                        <?php endif; ?>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>

                        <!-- Info de vote utilisateur -->
                        <?php if (!empty($user_votes)): ?>
                            <div class="user-vote-info">
                                ✅ Vous avez voté pour <?php echo count($user_votes); ?> option<?php echo count($user_votes) > 1 ? 's' : ''; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Bouton de vote -->
                        <div style="margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-vote" style="width: 100%;">
                                ✅ Enregistrer mon vote
                            </button>
                        </div>
                    </form>

                    <!-- Pied de page -->
                    <div class="poll-footer">
                        <div class="poll-meta">
                            <div class="poll-meta-item">
                                🗳️ <strong><?php echo $poll['total_votes']; ?></strong> vote<?php echo $poll['total_votes'] !== 1 ? 's' : ''; ?>
                            </div>
                            <?php if ($poll['deadline']): ?>
                                <div class="poll-meta-item">
                                    ⏰ <?php echo date('d/m/Y H:i', strtotime($poll['deadline'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Sondages clôturés -->
    <?php if (!empty($closed_polls)): ?>
        <div class="closed-section">
            <div class="section-header">
                <h2 class="section-title">🔒 Sondages clôturés</h2>
                <p class="section-subtitle">Résultats des votes précédents</p>
            </div>

            <div class="polls-container">
                <?php foreach ($closed_polls as $poll): ?>
                    <div class="poll-card closed">
                        <!-- En-tête du sondage -->
                        <div class="poll-header">
                            <span class="closed-badge">🔒 Clôturé</span>
                            <h2 class="poll-title"><?php echo htmlspecialchars($poll['titre']); ?></h2>
                            <?php if (!empty($poll['description'])): ?>
                                <p class="poll-description"><?php echo htmlspecialchars($poll['description']); ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Résultats -->
                        <div class="poll-content">
                            <?php
                            // Récupérer les options et votes
                            $stmt = $pdo->prepare("
                                SELECT po.*, COUNT(pv.id) as votes
                                FROM poll_options po
                                LEFT JOIN poll_votes pv ON po.id = pv.option_id
                                WHERE po.poll_id = ?
                                GROUP BY po.id
                                ORDER BY votes DESC
                            ");
                            $stmt->execute([$poll['id']]);
                            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            // Récupérer le(s) vote(s) de l'utilisateur
                            $stmt = $pdo->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
                            $stmt->execute([$poll['id'], $_SESSION['user_id']]);
                            $user_votes = $stmt->fetchAll(PDO::FETCH_COLUMN);

                            $total_votes = array_sum(array_column($options, 'votes'));
                            $max_votes = $total_votes > 0 ? max(array_column($options, 'votes')) : 0;
                            ?>

                            <?php foreach ($options as $idx => $option): ?>
                                <div style="margin-bottom: 1.25rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <span style="font-weight: 500; color: #1f2937;">
                                            <?php echo htmlspecialchars($option['text']); ?>
                                            <?php if (in_array($option['id'], $user_votes)): ?>
                                                <span style="color: #10b981; font-size: 0.85rem;">✓ Votre vote</span>
                                            <?php endif; ?>
                                            <?php if ($idx === 0 && $option['votes'] > 0): ?>
                                                <span class="winner-badge">🏆 Gagnant</span>
                                            <?php endif; ?>
                                        </span>
                                        <span style="font-weight: 600; color: #4b5563;">
                                            <?php echo $option['votes']; ?> vote<?php echo $option['votes'] !== 1 ? 's' : ''; ?>
                                            <?php if ($total_votes > 0): ?>
                                                <span style="color: #9ca3af; margin-left: 0.25rem;">
                                                    (<?php echo round($option['votes'] / $total_votes * 100); ?>%)
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="results-bar">
                                        <div class="results-bar-fill" style="width: <?php echo $total_votes > 0 ? ($option['votes'] / $total_votes * 100) : 0; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pied de page -->
                        <div class="poll-footer">
                            <div class="poll-meta">
                                <div class="poll-meta-item">
                                    🗳️ <strong><?php echo $poll['total_votes']; ?></strong> vote<?php echo $poll['total_votes'] !== 1 ? 's' : ''; ?>
                                </div>
                                <?php if ($poll['deadline']): ?>
                                    <div class="poll-meta-item">
                                        🏁 Clôturé le <?php echo date('d/m/Y', strtotime($poll['deadline'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
