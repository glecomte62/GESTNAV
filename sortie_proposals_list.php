<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$month_filter = trim($_GET['month'] ?? '');

$query = "SELECT sp.*, u.prenom, u.nom, a.oaci, a.nom as aero_nom FROM sortie_proposals sp JOIN users u ON sp.user_id = u.id LEFT JOIN aerodromes_fr a ON sp.aerodrome_id = a.id WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (sp.titre LIKE ? OR sp.description LIKE ? OR u.prenom LIKE ? OR u.nom LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($status_filter)) {
    $query .= " AND sp.status = ?";
    $params[] = $status_filter;
}

if (!empty($month_filter)) {
    $query .= " AND sp.month_proposed = ?";
    $params[] = $month_filter;
}

$query .= " ORDER BY sp.created_at DESC LIMIT 100";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $proposals = $stmt->fetchAll();
} catch (Exception $e) {
    $proposals = [];
}

require 'header.php';
?>

<style>
.proposals-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.proposals-header {
    background: linear-gradient(135deg, #004b8d 0%, #0066c0 100%);
    color: #ffffff;
    padding: 3rem 2rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.proposals-header h1 {
    margin: 0;
    font-size: 2rem;
}

.btn-propose {
    background: #10b981;
    color: #ffffff;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-block;
}

.btn-propose:hover {
    background: #059669;
    transform: translateY(-2px);
}

.filters-bar {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #1a1a1a;
    font-size: 0.9rem;
}

.filter-group input,
.filter-group select {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-family: inherit;
}

.btn-filter {
    background: #0066c0;
    color: #ffffff;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-filter:hover {
    background: #004b8d;
}

.proposals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.proposal-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    cursor: pointer;
}

.proposal-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.12);
}

.proposal-photo {
    width: 100%;
    height: 200px;
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    object-fit: cover;
}

.proposal-body {
    padding: 1.5rem;
}

.proposal-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0 0 0.5rem;
    line-height: 1.3;
}

.proposal-meta {
    display: flex;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #6b7280;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.proposal-meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.proposal-proposer {
    font-weight: 600;
    color: #0066c0;
    margin-bottom: 0.75rem;
}

.proposal-description {
    font-size: 0.9rem;
    color: #4b5563;
    line-height: 1.5;
    margin-bottom: 1rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.proposal-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.proposal-status {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #ffffff;
}

.btn-details {
    background: #0066c0;
    color: #ffffff;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-details:hover {
    background: #004b8d;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #6b7280;
}

.empty-state h2 {
    color: #1a1a1a;
    margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
    .proposals-header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .proposals-header h1 {
        font-size: 1.5rem;
    }
    
    .proposals-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="proposals-container">
    <div class="proposals-header">
        <h1>Sorties Proposées</h1>
        <a href="propose_sortie.php" class="btn-propose">+ Proposer une sortie</a>
    </div>

    <div class="filters-bar">
        <form method="get" style="display: contents;">
            <div class="filter-group">
                <label>Rechercher</label>
                <input type="text" name="search" placeholder="Titre, aerodromes..." value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <div class="filter-group">
                <label>Statut</label>
                <select name="status">
                    <option value="">-- Tous les statuts --</option>
                    <option value="en_attente" <?= $status_filter === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                    <option value="accepte" <?= $status_filter === 'accepte' ? 'selected' : '' ?>>Acceptee</option>
                    <option value="en_preparation" <?= $status_filter === 'en_preparation' ? 'selected' : '' ?>>En preparation</option>
                    <option value="validee" <?= $status_filter === 'validee' ? 'selected' : '' ?>>Validee</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Mois</label>
                <select name="month">
                    <option value="">-- Tous les mois --</option>
                    <option value="janvier" <?= $month_filter === 'janvier' ? 'selected' : '' ?>>Janvier</option>
                    <option value="fevrier" <?= $month_filter === 'fevrier' ? 'selected' : '' ?>>Fevrier</option>
                    <option value="mars" <?= $month_filter === 'mars' ? 'selected' : '' ?>>Mars</option>
                    <option value="avril" <?= $month_filter === 'avril' ? 'selected' : '' ?>>Avril</option>
                    <option value="mai" <?= $month_filter === 'mai' ? 'selected' : '' ?>>Mai</option>
                    <option value="juin" <?= $month_filter === 'juin' ? 'selected' : '' ?>>Juin</option>
                    <option value="juillet" <?= $month_filter === 'juillet' ? 'selected' : '' ?>>Juillet</option>
                    <option value="aout" <?= $month_filter === 'aout' ? 'selected' : '' ?>>Aout</option>
                    <option value="septembre" <?= $month_filter === 'septembre' ? 'selected' : '' ?>>Septembre</option>
                    <option value="octobre" <?= $month_filter === 'octobre' ? 'selected' : '' ?>>Octobre</option>
                    <option value="novembre" <?= $month_filter === 'novembre' ? 'selected' : '' ?>>Novembre</option>
                    <option value="decembre" <?= $month_filter === 'decembre' ? 'selected' : '' ?>>Decembre</option>
                </select>
            </div>

            <button type="submit" class="btn-filter">Filtrer</button>
        </form>
    </div>

    <?php if (empty($proposals)): ?>
        <div class="empty-state">
            <h2>Aucune sortie proposee</h2>
            <p>Soyez le premier a proposer une sortie! <a href="propose_sortie.php">Proposer une sortie</a></p>
        </div>
    <?php else: ?>
        <div class="proposals-grid">
            <?php 
            $colors = ['en_attente' => '#fbbf24', 'accepte' => '#34d399', 'en_preparation' => '#60a5fa', 'validee' => '#10b981', 'rejetee' => '#f87171'];
            $labels = ['en_attente' => 'En attente', 'accepte' => 'Acceptee', 'en_preparation' => 'En preparation', 'validee' => 'Validee', 'rejetee' => 'Rejetee'];
            
            foreach ($proposals as $proposal): ?>
                <div class="proposal-card">
                    <?php if ($proposal['photo_filename']): ?>
                        <img src="uploads/proposals/<?= htmlspecialchars($proposal['photo_filename']) ?>" 
                             alt="<?= htmlspecialchars($proposal['titre']) ?>" 
                             class="proposal-photo"
                             loading="lazy"
                             decoding="async">
                    <?php else: ?>
                        <div class="proposal-photo" style="display: flex; align-items: center; justify-content: center; font-size: 3rem;">
                            Avion
                        </div>
                    <?php endif; ?>
                    
                    <div class="proposal-body">
                        <h3 class="proposal-title"><?= htmlspecialchars($proposal['titre']) ?></h3>
                        
                        <div class="proposal-meta">
                            <span><?= htmlspecialchars($proposal['month_proposed']) ?></span>
                            <?php if ($proposal['oaci']): ?>
                                <span><?= htmlspecialchars($proposal['oaci']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="proposal-proposer">
                            Propose par: <?= htmlspecialchars($proposal['prenom'] . ' ' . $proposal['nom']) ?>
                        </div>

                        <p class="proposal-description"><?= htmlspecialchars($proposal['description']) ?></p>

                        <div class="proposal-footer">
                            <span class="proposal-status" style="background-color: <?= $colors[$proposal['status']] ?>;">
                                <?= htmlspecialchars($labels[$proposal['status']]) ?>
                            </span>
                            <a href="sortie_proposal_detail.php?id=<?= (int)$proposal['id'] ?>" class="btn-details">Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
