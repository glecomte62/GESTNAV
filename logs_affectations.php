<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

// Créer la table de logs si elle n'existe pas
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS affectations_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sortie_id INT NOT NULL,
        sortie_machine_id INT NOT NULL,
        user_id INT NOT NULL,
        assigned_user_id INT NULL,
        assigned_guest_name VARCHAR(255) NULL,
        slot_number TINYINT NOT NULL,
        action ENUM('add', 'remove', 'update') NOT NULL,
        modified_by_user_id INT NOT NULL,
        modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        notes TEXT NULL,
        INDEX idx_sortie (sortie_id),
        INDEX idx_modified_at (modified_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    // Table existe déjà
}

// Filtres
$filter_sortie = isset($_GET['sortie_id']) ? (int)$_GET['sortie_id'] : 0;

// Récupérer toutes les sorties pour le dropdown
$stmt_sorties = $pdo->query("SELECT id, titre, date_sortie, destination_oaci 
    FROM sorties 
    ORDER BY date_sortie DESC 
    LIMIT 200");
$sorties = $stmt_sorties->fetchAll(PDO::FETCH_ASSOC);

// Construire la requête de logs
$where = [];
$params = [];

if ($filter_sortie > 0) {
    $where[] = "al.sortie_id = ?";
    $params[] = $filter_sortie;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Récupérer les logs individuellement (une ligne par changement)
$sql = "SELECT 
    al.id,
    al.sortie_id,
    al.modified_at,
    al.slot_number,
    al.action,
    s.titre AS sortie_titre,
    s.date_sortie,
    m.nom AS machine_nom,
    m.immatriculation AS machine_immat,
    COALESCE(CONCAT(au.prenom, ' ', au.nom), al.assigned_guest_name, 'Aucune') AS assigned_name,
    CONCAT(u.prenom, ' ', u.nom) AS modified_by_name
FROM affectations_logs al
JOIN sorties s ON s.id = al.sortie_id
JOIN sortie_machines sm ON sm.id = al.sortie_machine_id
JOIN machines m ON m.id = sm.machine_id
LEFT JOIN users au ON au.id = al.assigned_user_id
JOIN users u ON u.id = al.modified_by_user_id
$whereClause
ORDER BY al.modified_at DESC, al.id DESC
LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

require 'header.php';
?>

<style>
.logs-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.logs-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 2rem;
    padding: 1.5rem 1.75rem;
    border-radius: 1.25rem;
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: #fff;
    box-shadow: 0 12px 30px rgba(0,0,0,0.25);
}

.logs-header h1 {
    font-size: 1.6rem;
    margin: 0;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.logs-header-icon {
    font-size: 2.4rem;
    opacity: 0.9;
}

.card {
    background: #ffffff;
    border-radius: 1.25rem;
    padding: 1.75rem 1.5rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.03);
    margin-bottom: 1.5rem;
}

.card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
    letter-spacing: 0.02em;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
}

.form-select {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.75rem;
    font-size: 0.9rem;
    background: white;
    cursor: pointer;
}

.form-select:focus {
    outline: none;
    border-color: #00a0c6;
    box-shadow: 0 0 0 3px rgba(0,160,198,0.1);
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: white;
}

.btn-primary:hover {
    filter: brightness(1.08);
    color: white;
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.logs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.logs-table thead {
    background: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
}

.logs-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: #374151;
    white-space: nowrap;
}

.logs-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: top;
}

.logs-table tbody tr:hover {
    background: #f9fafb;
}

.sortie-name {
    font-weight: 600;
    color: #1f2937;
}

.sortie-date {
    font-size: 0.85rem;
    color: #6b7280;
}

.affectations-detail {
    font-size: 0.85rem;
    color: #374151;
    line-height: 1.6;
}

.affectations-detail .machine {
    display: block;
    margin-bottom: 0.25rem;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-changes {
    background: rgba(0,160,198,0.1);
    color: #00a0c6;
}

.modified-by {
    font-size: 0.85rem;
    color: #6b7280;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #9ca3af;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
</style>

<div class="logs-page">
    <div class="logs-header">
        <div>
            <h1>Logs des affectations</h1>
            <p style="margin: 0.5rem 0 0; opacity: 0.9; font-size: 0.95rem;">Historique complet des affectations aux machines</p>
        </div>
        <div class="logs-header-icon">📋</div>
    </div>

    <!-- Filtres -->
    <div class="card">
        <div class="card-title">🔍 Filtres</div>
        <form method="get" id="filtersForm">
            <div class="filters-grid">
                <div class="form-group">
                    <label class="form-label">Sortie</label>
                    <select name="sortie_id" class="form-select" onchange="document.getElementById('filtersForm').submit()">
                        <option value="0">Toutes les sorties</option>
                        <?php foreach ($sorties as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= $filter_sortie === (int)$s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(date('d/m/Y', strtotime($s['date_sortie']))) ?> - 
                                <?= htmlspecialchars($s['titre']) ?>
                                <?= !empty($s['destination_oaci']) ? ' (' . htmlspecialchars($s['destination_oaci']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="margin-top: 1rem; display: flex; gap: 0.75rem;">
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <a href="logs_affectations.php" class="btn btn-secondary">Réinitialiser</a>
            </div>
        </form>
    </div>

    <!-- Logs -->
    <div class="card">
        <div class="card-title">📊 Historique (<?= count($logs) ?> entrée<?= count($logs) > 1 ? 's' : '' ?>)</div>
        
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p><strong>Aucun log d'affectation</strong></p>
                <p>Les affectations apparaîtront ici une fois que des modifications seront enregistrées.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Date/Heure</th>
                            <th>Sortie</th>
                            <th>Machine</th>
                            <th>Personne affectée</th>
                            <th>Slot</th>
                            <th>Modifié par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="white-space: nowrap;">
                                    <strong><?= htmlspecialchars(date('d/m/Y', strtotime($log['modified_at']))) ?></strong><br>
                                    <span style="font-size: 0.8rem; color: #9ca3af;">
                                        <?= htmlspecialchars(date('H:i:s', strtotime($log['modified_at']))) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="sortie-name"><?= htmlspecialchars($log['sortie_titre']) ?></div>
                                    <div class="sortie-date"><?= htmlspecialchars(date('d/m/Y', strtotime($log['date_sortie']))) ?></div>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($log['machine_nom']) ?></strong><br>
                                    <span style="font-size: 0.85rem; color: #6b7280;">
                                        <?= htmlspecialchars($log['machine_immat']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($log['assigned_name']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-changes"><?= (int)$log['slot_number'] ?></span>
                                </td>
                                <td>
                                    <div class="modified-by">
                                        <strong><?= htmlspecialchars($log['modified_by_name']) ?></strong>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="text-align: center; margin-top: 1rem;">
        <a href="sorties.php" class="btn btn-secondary">← Retour aux sorties</a>
    </div>
</div>

<?php require 'footer.php'; ?>
