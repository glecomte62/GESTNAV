<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Récupérer les emails envoyés (avec gestion d'erreur si table n'existe pas)
$emails = [];
$totalEmails = 0;
try {
    // Compter le total
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM email_logs");
    $totalEmails = (int)$totalStmt->fetchColumn();
    
    // Récupérer les logs avec infos utilisateur
    $query = "SELECT el.*, u.nom, u.prenom 
              FROM email_logs el
              LEFT JOIN users u ON u.id = el.sender_id
              ORDER BY el.created_at DESC 
              LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $pdo->query($query);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Table email_logs non trouvée. Veuillez exécuter la migration d\'installation.';
}

$totalPages = max(1, ceil($totalEmails / $perPage));

require 'header.php';
?>

<style>
.history-page { max-width: 1000px; margin: 0 auto; padding: 2rem 1rem 3rem; }
.history-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 2rem; padding: 1.5rem 1.75rem; border-radius: 1.25rem; background: linear-gradient(135deg, #004b8d, #00a0c6); color: #fff; box-shadow: 0 12px 30px rgba(0,0,0,0.25); }
.history-header h1 { font-size: 1.6rem; margin: 0; letter-spacing: 0.03em; text-transform: uppercase; }
.history-header-icon { font-size: 2.4rem; opacity: 0.9; }

.card { background: #ffffff; border-radius: 1.25rem; padding: 1.75rem 1.5rem; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); border: 1px solid rgba(0, 0, 0, 0.03); }
.card-title { font-size: 1.05rem; font-weight: 700; color: #1f2937; margin-bottom: 1.5rem; }

.alert { padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; border-left: 4px solid; }
.alert-error { background: rgba(239,68,68,0.1); color: #991b1b; border-left-color: #ef4444; }
.alert-info { background: rgba(59,130,246,0.1); color: #1e40af; border-left-color: #3b82f6; }

.email-row { padding: 1rem; background: #f9fafb; border-radius: 0.75rem; border-left: 3px solid #00a0c6; margin-bottom: 1rem; cursor: pointer; transition: all 0.2s; }
.email-row:hover { background: #f3f4f6; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.email-row-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
.email-row-title { font-weight: 600; color: #1f2937; font-size: 1rem; flex: 1; }
.email-row-date { font-size: 0.85rem; color: #9ca3af; }
.email-row-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; font-size: 0.9rem; }
.email-detail-item { display: flex; flex-direction: column; }
.email-detail-label { font-weight: 600; color: #6b7280; font-size: 0.8rem; text-transform: uppercase; }
.email-detail-value { color: #374151; }

.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: white; border-radius: 1.25rem; padding: 2rem; max-width: 800px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3); }
.modal-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1.5rem; }
.modal-title { font-size: 1.25rem; font-weight: 700; color: #1f2937; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #9ca3af; padding: 0; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; border-radius: 0.5rem; transition: all 0.2s; }
.modal-close:hover { background: #f3f4f6; color: #374151; }
.modal-body { color: #374151; }
.modal-info { display: grid; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e5e7eb; }
.modal-info-row { display: flex; gap: 0.5rem; }
.modal-info-label { font-weight: 600; color: #6b7280; min-width: 120px; }
.modal-message { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem; line-height: 1.6; }

.pagination { display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 2rem; }
.pagination a, .pagination span { padding: 0.5rem 0.75rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; text-decoration: none; color: #374151; font-size: 0.9rem; transition: all 0.2s; }
.pagination a:hover { background: #f3f4f6; }
.pagination .current { background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; border-color: #004b8d; }

.recipient-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 0.35rem; font-size: 0.75rem; font-weight: 600; background: #e0f2fe; color: #0369a1; }
.btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.9rem; }
.btn-primary { background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; }
.btn-primary:hover { filter: brightness(1.08); }

.empty-state { text-align: center; padding: 3rem; color: #9ca3af; }
.empty-state-icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

.filter-bar { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.filter-bar select, .filter-bar input { padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.75rem; font-family: inherit; font-size: 0.9rem; }
.filter-bar select:focus, .filter-bar input:focus { outline: none; border-color: #00a0c6; box-shadow: 0 0 0 3px rgba(0,160,198,0.1); }
</style>

<div class="history-page">
    <div class="history-header">
        <div><h1>Historique des Emails</h1></div>
        <div class="history-header-icon">📧</div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <strong>⚠️ Erreur:</strong> <?= htmlspecialchars($error) ?><br>
            <small>
                <a href="install_email_logs.php" style="color: inherit; text-decoration: underline;">
                    Cliquez ici pour installer les tables nécessaires
                </a>
            </small>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">📋 Derniers emails envoyés (<?= $totalEmails ?>)</div>

        <?php if (empty($emails)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>Aucun email enregistré pour le moment.</p>
            </div>
        <?php else: ?>
            <div id="emailList">
                <?php foreach ($emails as $email): ?>
                    <div class="email-row" onclick="showEmail(<?= $email['id'] ?>)">
                        <div class="email-row-header">
                            <div class="email-row-title">✉️ <?= htmlspecialchars($email['subject']) ?></div>
                            <div class="email-row-date"><?= date('d/m/Y H:i', strtotime($email['created_at'])) ?></div>
                        </div>
                        <div class="email-row-details">
                            <div class="email-detail-item">
                                <div class="email-detail-label">Expéditeur</div>
                                <div class="email-detail-value"><?= htmlspecialchars($email['prenom'] . ' ' . $email['nom']) ?></div>
                            </div>
                            <div class="email-detail-item">
                                <div class="email-detail-label">Destinataires</div>
                                <div class="email-detail-value">
                                    <span class="recipient-badge"><?= (int)$email['recipient_count'] ?> personne<?= $email['recipient_count'] > 1 ? 's' : '' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1">« Début</a>
                        <a href="?page=<?= $page - 1 ?>">‹ Préc</a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>">Suiv ›</a>
                        <a href="?page=<?= $totalPages ?>">Fin »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<!-- MODAL -->
<div id="emailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Détails de l'email</div>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body" id="modalBody">
            <p style="text-align: center; color: #9ca3af;">Chargement...</p>
        </div>
    </div>
</div>

<script>
function showEmail(id) {
    const modal = document.getElementById('emailModal');
    const modalBody = document.getElementById('modalBody');
    
    modal.classList.add('active');
    modalBody.innerHTML = '<p style="text-align: center; color: #9ca3af;">Chargement...</p>';
    
    fetch(`get_email_detail.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                modalBody.innerHTML = `<p style="color: #ef4444;">${data.error}</p>`;
                return;
            }
            
            const date = new Date(data.created_at);
            const dateStr = date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Préparer la liste des destinataires
            let recipientsList = '';
            if (data.recipients && data.recipients.length > 0) {
                recipientsList = '<div style="margin-top: 1rem; max-height: 200px; overflow-y: auto;">';
                recipientsList += '<div style="font-weight: 600; color: #6b7280; margin-bottom: 0.5rem;">Liste des destinataires :</div>';
                recipientsList += '<div style="display: flex; flex-direction: column; gap: 0.25rem;">';
                data.recipients.forEach(r => {
                    recipientsList += `<div style="padding: 0.35rem 0.75rem; background: #f9fafb; border-radius: 0.5rem; font-size: 0.875rem;">
                        <strong>${r.name || 'Sans nom'}</strong> 
                        <span style="color: #9ca3af;">- ${r.email}</span>
                    </div>`;
                });
                recipientsList += '</div></div>';
            } else if (data.recipient_count > 0) {
                recipientsList = '<div style="margin-top: 1rem; padding: 0.75rem; background: #fef3c7; border-radius: 0.5rem; font-size: 0.875rem; color: #92400e;">';
                recipientsList += '<em>⚠️ Liste des destinataires non disponible (email envoyé avant la mise à jour du système)</em>';
                recipientsList += '</div>';
            }
            
            modalBody.innerHTML = `
                <div class="modal-info">
                    <div class="modal-info-row">
                        <span class="modal-info-label">Date :</span>
                        <span>${dateStr}</span>
                    </div>
                    <div class="modal-info-row">
                        <span class="modal-info-label">Expéditeur :</span>
                        <span>${data.sender}</span>
                    </div>
                    <div class="modal-info-row">
                        <span class="modal-info-label">Objet :</span>
                        <span><strong>${data.subject}</strong></span>
                    </div>
                    <div class="modal-info-row">
                        <span class="modal-info-label">Destinataires :</span>
                        <span>${data.recipient_count} personne${data.recipient_count > 1 ? 's' : ''}</span>
                    </div>
                    ${recipientsList}
                </div>
                <div class="modal-message">
                    ${data.message || '<em style="color: #9ca3af;">Message non disponible</em>'}
                </div>
            `;
        })
        .catch(err => {
            modalBody.innerHTML = '<p style="color: #ef4444;">Erreur lors du chargement</p>';
        });
}

function closeModal() {
    document.getElementById('emailModal').classList.remove('active');
}

// Fermer avec Echap
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});

// Fermer en cliquant en dehors
document.getElementById('emailModal').addEventListener('click', e => {
    if (e.target.id === 'emailModal') closeModal();
});
</script>

<?php require 'footer.php'; ?>
