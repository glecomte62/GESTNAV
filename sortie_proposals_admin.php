<?php
if (!defined('GESTNAV_LOADED')) {
    define('GESTNAV_LOADED', true);
    require_once 'config.php';
    require_once 'auth.php';
}

require_login();
require_admin();

// Charge les dépendances de manière sécurisée
if (file_exists(__DIR__ . '/mail_helper.php') && !function_exists('gestnav_send_mail')) {
    require_once 'mail_helper.php';
}

if (file_exists(__DIR__ . '/utils/proposal_email_notifier.php') && !class_exists('ProposalEmailNotifier')) {
    require_once 'utils/proposal_email_notifier.php';
}

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'delete') {
        $proposal_id = (int)$_POST['id'];
        
        try {
            // Récupérer la photo pour la supprimer
            $stmt = $pdo->prepare("SELECT photo_filename FROM sortie_proposals WHERE id = ?");
            $stmt->execute([$proposal_id]);
            $proposal = $stmt->fetch();
            
            if ($proposal && !empty($proposal['photo_filename'])) {
                $photoPath = __DIR__ . '/uploads/proposals/' . $proposal['photo_filename'];
                if (is_file($photoPath)) {
                    @unlink($photoPath);
                }
            }
            
            // Supprimer la proposition
            $stmt = $pdo->prepare("DELETE FROM sortie_proposals WHERE id = ?");
            $stmt->execute([$proposal_id]);
            
            $success = "Proposition supprimée avec succès.";
            header('Location: sortie_proposals_admin.php?success=deleted');
            exit;
        } catch (Exception $e) {
            error_log("Erreur suppression: " . $e->getMessage());
            $errors[] = "Erreur lors de la suppression: " . $e->getMessage();
        }
    } elseif ($action === 'update_status') {
        $proposal_id = (int)$_POST['id'];
        $new_status = trim($_POST['status'] ?? '');
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        
        if (in_array($new_status, ['en_attente', 'accepte', 'en_preparation', 'validee', 'rejetee'])) {
            $stmt = $pdo->prepare("UPDATE sortie_proposals SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $admin_notes, $proposal_id]);
            
            // Send notification if class is available
            if (class_exists('ProposalEmailNotifier')) {
                try {
                    $notifier = new ProposalEmailNotifier($pdo);
                    $notifier->notifyStatusChange($proposal_id, $new_status, $admin_notes);
                } catch (Exception $e) {
                    error_log("Erreur notification: " . $e->getMessage());
                }
            }
            
            header('Location: sortie_proposals_admin.php');
            exit;
        }
    } elseif ($action === 'create_sortie') {
        $proposal_id = (int)($_POST['id'] ?? 0);
        
        if ($proposal_id > 0) {
            // Récupérer la proposition
            $stmt = $pdo->prepare("SELECT * FROM sortie_proposals WHERE id = ?");
            $stmt->execute([$proposal_id]);
            $proposal = $stmt->fetch();
            
            if ($proposal) {
                try {
                    // Déterminer la date: premier du mois proposé, année prochaine si nécessaire
                    $months_fr = ['janvier' => '01', 'février' => '02', 'mars' => '03', 'avril' => '04', 'mai' => '05', 'juin' => '06', 'juillet' => '07', 'août' => '08', 'septembre' => '09', 'octobre' => '10', 'novembre' => '11', 'décembre' => '12'];
                    $month = strtolower($proposal['month_proposed']);
                    $month_num = $months_fr[$month] ?? '01';
                    
                    $current_year = date('Y');
                    $current_month = date('m');
                    $year = (int)$month_num > (int)$current_month ? $current_year : $current_year + 1;
                    $date_sortie = $year . '-' . $month_num . '-01 09:00:00';
                    
                    // Créer la sortie avec destination_id si disponible
                    $stmt = $pdo->prepare("INSERT INTO sorties (date_sortie, titre, description, statut, created_by, destination_id) VALUES (?, ?, ?, 'en étude', ?, ?)");
                    
                    $stmt->execute([
                        $date_sortie,
                        $proposal['titre'],
                        $proposal['description'],
                        $_SESSION['user_id'] ?? 1,  // admin user
                        $proposal['aerodrome_id'] ?: null  // destination depuis la proposition
                    ]);
                    
                    $sortie_id = $pdo->lastInsertId();
                    
                    // Copier la photo si elle existe
                    if (!empty($proposal['photo_filename'])) {
                        $srcPhoto = __DIR__ . '/uploads/proposals/' . $proposal['photo_filename'];
                        if (is_file($srcPhoto)) {
                            // Copier vers uploads/sorties
                            $uploadDir = __DIR__ . '/uploads/sorties';
                            if (!is_dir($uploadDir)) {
                                @mkdir($uploadDir, 0775, true);
                            }
                            $destPhoto = $uploadDir . '/' . $proposal['photo_filename'];
                            if (@copy($srcPhoto, $destPhoto)) {
                                // Enregistrer dans sortie_photos
                                $stmtPhoto = $pdo->prepare("INSERT INTO sortie_photos (sortie_id, filename) VALUES (?, ?)");
                                $stmtPhoto->execute([$sortie_id, $proposal['photo_filename']]);
                            }
                        }
                    }
                    
                    // Marquer la proposition comme validée
                    $stmt = $pdo->prepare("UPDATE sortie_proposals SET status = 'validee', admin_notes = 'Sortie créée (en_étude)', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$proposal_id]);
                    
                    // Envoyer email de remerciement au proposant
                    try {
                        $stmtUser = $pdo->prepare("SELECT u.email, u.prenom, u.nom FROM users u JOIN sortie_proposals sp ON u.id = sp.user_id WHERE sp.id = ?");
                        $stmtUser->execute([$proposal_id]);
                        $proposer = $stmtUser->fetch();
                        
                        if ($proposer && !empty($proposer['email'])) {
                            $to = $proposer['email'];
                            $prenom = $proposer['prenom'];
                            $nom = $proposer['nom'];
                            
                            $subject = '🎉 Merci pour ta proposition de sortie !';
                            
                            $message = '
                                <html>
                                <body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
                                    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                                        <h2 style="color: #004b8d;">Bonjour ' . htmlspecialchars($prenom) . ',</h2>
                                        
                                        <p style="font-size: 16px;">
                                            Nous te remercions chaleureusement pour ta proposition de sortie <strong>"' . htmlspecialchars($proposal['titre']) . '"</strong> ! 
                                            C\'est grâce à l\'engagement de membres comme toi que notre club reste dynamique et propose des sorties variées.
                                        </p>
                                        
                                        <div style="background: #f0fbff; border-left: 4px solid #00a0c6; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                            <p style="margin: 0; font-size: 15px;">
                                                <strong>📋 Statut actuel :</strong> Ton projet est maintenant <strong>en cours d\'étude</strong> au sein du comité. 
                                                Nous analysons sa faisabilité et réfléchissons à la meilleure façon de le mettre en place.
                                            </p>
                                        </div>
                                        
                                        <p style="font-size: 15px;">
                                            Tu seras tenu(e) informé(e) de l\'avancement et des décisions prises. 
                                            N\'hésite pas à contacter un membre du comité si tu as des questions ou des précisions à apporter.
                                        </p>
                                        
                                        <p style="font-size: 15px; margin-top: 30px;">
                                            Merci encore pour ta contribution ! ✈️
                                        </p>
                                        
                                        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
                                        
                                        <div style="text-align: center; padding-top: 20px;">';
                            
                            // Ajouter le logo s'il existe
                            $logoPath = __DIR__ . '/assets/img/logo.png';
                            if (file_exists($logoPath)) {
                                $logoData = base64_encode(file_get_contents($logoPath));
                                $logoMimeType = mime_content_type($logoPath);
                                $message .= '<img src="data:' . $logoMimeType . ';base64,' . $logoData . '" style="height: 50px; margin-bottom: 10px;" alt="Logo Club ULM Evasion">';
                            }
                            
                            $message .= '
                                            <p style="font-size: 12px; color: #666; margin: 5px 0;">
                                                <strong>Mail envoyé avec l\'application GESTNAV v2.0.0</strong>
                                            </p>
                                            <p style="font-size: 11px; color: #999; margin: 5px 0;">
                                                Gestion des Sorties et Membres - Club ULM Evasion
                                            </p>
                                        </div>
                                    </div>
                                </body>
                                </html>
                            ';
                            
                            $headers = "MIME-Version: 1.0\r\n";
                            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                            $headers .= "From: CLUB ULM EVASION <info@clubulmevasion.fr>\r\n";
                            
                            @mail($to, $subject, $message, $headers);
                            
                            // Logger l'envoi
                            try {
                                $stmtLog = $pdo->prepare("INSERT INTO email_logs (subject, message, recipient_count, sender_id, created_at) VALUES (?, ?, 1, ?, NOW())");
                                $stmtLog->execute([
                                    $subject,
                                    $message,
                                    $_SESSION['user_id'] ?? 1
                                ]);
                            } catch (Exception $e) {
                                error_log("Erreur log email: " . $e->getMessage());
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Erreur envoi email remerciement: " . $e->getMessage());
                    }
                    
                    // Envoyer notification (ancien système)
                    if (class_exists('ProposalEmailNotifier')) {
                        try {
                            $notifier = new ProposalEmailNotifier($pdo);
                            $notifier->notifyStatusChange($proposal_id, 'validee', 'Votre proposition a été convertie en sortie officielle en phase d\'étude!');
                        } catch (Exception $e) {
                            error_log("Erreur notification: " . $e->getMessage());
                        }
                    }
                    
                    header('Location: sorties.php');
                    exit;
                } catch (Exception $e) {
                    error_log("Erreur création sortie: " . $e->getMessage());
                    $errors[] = "Erreur création sortie: " . $e->getMessage();
                }
            }
        }
    }
}

$status_filter = trim($_GET['status_filter'] ?? '');
$query = "SELECT sp.*, u.prenom, u.nom, a.oaci, a.nom as aero_nom FROM sortie_proposals sp JOIN users u ON sp.user_id = u.id LEFT JOIN aerodromes_fr a ON sp.aerodrome_id = a.id WHERE 1=1";
$params = [];

if (!empty($status_filter)) {
    $query .= " AND sp.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY sp.created_at DESC";

$proposals = [];
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $proposals = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur requête proposals: " . $e->getMessage());
    $proposals = [];
}

require 'header.php';
?>

<style>
.admin-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.admin-header {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    color: #ffffff;
    padding: 2rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
}

.admin-header h1 { 
    margin: 0; 
    font-size: 1.75rem; 
}

.admin-controls {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}

.filter-btn {
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.9rem;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
}

.filter-btn:hover, .filter-btn.active {
    background: #7c3aed;
    color: #ffffff;
}

.alert-success {
    background: #d1fae5;
    border: 1px solid #6ee7b7;
    color: #065f46;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
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
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.proposal-card:hover {
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.card-header {
    padding: 1rem;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}

.card-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0 0 0.5rem;
    color: #111827;
}

.card-proposer {
    font-size: 0.85rem;
    color: #6b7280;
}

.card-body {
    padding: 1rem;
    flex-grow: 1;
}

.card-info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
}

.card-info-label {
    font-weight: 600;
    color: #374151;
}

.card-info-value {
    color: #6b7280;
}

.status-badge {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #ffffff;
}

.card-footer {
    padding: 1rem;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-edit {
    background: #0066c0;
    color: #ffffff;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.85rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: background 0.3s;
}

.btn-edit:hover {
    background: #0052a3;
}

.btn-create {
    background: #10b981;
    color: #ffffff;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.85rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
    flex-grow: 1;
    text-align: center;
}

.btn-create:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-delete {
    background: #ef4444;
    color: #ffffff;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-delete:hover {
    background: #dc2626;
}

.btn-status {
    background: #8b5cf6;
    color: #ffffff;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-status:hover {
    background: #7c3aed;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.4);
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #fefefe;
    padding: 2rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    width: 90%;
    max-width: 500px;
}

.modal-header { 
    font-size: 1.25rem; 
    font-weight: 700; 
    margin-bottom: 1rem; 
}

.form-group { 
    margin-bottom: 1rem; 
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.form-group select, .form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-family: inherit;
}

.form-group textarea { 
    min-height: 100px;
    resize: vertical;
}

.modal-buttons {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

.btn-save {
    background: #7c3aed;
    color: #ffffff;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
}

.btn-save:hover {
    background: #6d28d9;
}

.btn-cancel {
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
}

.btn-cancel:hover {
    background: #e5e7eb;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6b7280;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .proposals-grid {
        grid-template-columns: 1fr;
    }
    
    .card-footer {
        flex-direction: column;
    }
    
    .btn-create {
        width: 100%;
    }
}
</style>

<div class="admin-container">
    <div class="admin-header">
        <h1>✏️ Admin - Sorties Proposées</h1>
    </div>

    <?php if (!empty($_GET['success']) && $_GET['success'] === 'deleted'): ?>
        <div class="alert-success">
            ✅ Proposition supprimée avec succès.
        </div>
    <?php endif; ?>

    <div class="admin-controls">
        <span style="font-weight: 600;">Filtrer:</span>
        <a href="sortie_proposals_admin.php" class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">Tous</a>
        <a href="?status_filter=en_attente" class="filter-btn <?= $status_filter === 'en_attente' ? 'active' : '' ?>">En attente</a>
        <a href="?status_filter=accepte" class="filter-btn <?= $status_filter === 'accepte' ? 'active' : '' ?>">Acceptées</a>
        <a href="?status_filter=en_preparation" class="filter-btn <?= $status_filter === 'en_preparation' ? 'active' : '' ?>">En préparation</a>
        <a href="?status_filter=validee" class="filter-btn <?= $status_filter === 'validee' ? 'active' : '' ?>">Validées</a>
        <a href="?status_filter=rejetee" class="filter-btn <?= $status_filter === 'rejetee' ? 'active' : '' ?>">Rejetées</a>
    </div>

    <?php if (empty($proposals)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <p>Aucune proposition de sortie trouvée.</p>
        </div>
    <?php else: ?>
        <div class="proposals-grid">
            <?php 
            $colors = ['en_attente' => '#fbbf24', 'accepte' => '#34d399', 'en_preparation' => '#60a5fa', 'validee' => '#10b981', 'rejetee' => '#f87171'];
            $labels = ['en_attente' => 'En attente', 'accepte' => 'Acceptée', 'en_preparation' => 'En préparation', 'validee' => 'Validée', 'rejetee' => 'Rejetée'];
            
            foreach ($proposals as $p): ?>
                <div class="proposal-card">
                    <div class="card-header">
                        <h3 class="card-title"><?= htmlspecialchars($p['titre']) ?></h3>
                        <div class="card-proposer">Par <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></div>
                    </div>
                    
                    <div class="card-body">
                        <div class="card-info-row">
                            <span class="card-info-label">Mois proposé:</span>
                            <span class="card-info-value"><?= htmlspecialchars($p['month_proposed']) ?></span>
                        </div>
                        <div class="card-info-row">
                            <span class="card-info-label">Aérodrome:</span>
                            <span class="card-info-value"><?= htmlspecialchars($p['oaci'] ?? '—') ?></span>
                        </div>
                        <div class="card-info-row">
                            <span class="card-info-label">Statut:</span>
                            <span>
                                <span class="status-badge" style="background-color: <?= $colors[$p['status']] ?>;">
                                    <?= htmlspecialchars($labels[$p['status']]) ?>
                                </span>
                            </span>
                        </div>
                        <div class="card-info-row">
                            <span class="card-info-label">Créée:</span>
                            <span class="card-info-value"><?= date('d/m/Y', strtotime($p['created_at'])) ?></span>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <a href="sortie_proposal_detail.php?id=<?= (int)$p['id'] ?>" class="btn-edit">👁️ Voir détail</a>
                        <button class="btn-status" onclick="openModal(<?= (int)$p['id'] ?>, '<?= htmlspecialchars($p['status']) ?>')">
                            ⚙️ Statut
                        </button>
                        <form method="POST" style="display: inline; flex-grow: 1;">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="action" value="create_sortie">
                            <button type="submit" class="btn-create" onclick="return confirm('✓ Créer une sortie officielle à partir de cette proposition ?')">
                                ➕ Créer sortie
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn-delete" onclick="return confirm('⚠️ Êtes-vous sûr de vouloir supprimer cette proposition ?\\n\\nCette action est irréversible.')">
                                🗑️ Supprimer
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Modifier le statut</div>
        <form method="post">
            <input type="hidden" name="id" id="modalId">
            <input type="hidden" name="action" value="update_status">

            <div class="form-group">
                <label>Nouveau statut</label>
                <select name="status" required>
                    <option value="en_attente">En attente</option>
                    <option value="accepte">Acceptée</option>
                    <option value="en_preparation">En préparation</option>
                    <option value="validee">Validée</option>
                    <option value="rejetee">Rejetée</option>
                </select>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="admin_notes" placeholder="Notes administrateur..."></textarea>
            </div>

            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
                <button type="submit" class="btn-save">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id, status) {
    document.getElementById('modalId').value = id;
    document.querySelector('select[name="status"]').value = status;
    document.getElementById('editModal').classList.add('active');
}

function closeModal() {
    document.getElementById('editModal').classList.remove('active');
}

window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        modal.classList.remove('active');
    }
}
</script>

<?php require 'footer.php'; ?>
