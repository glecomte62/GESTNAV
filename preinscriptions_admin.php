<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

// ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $preinscription_id = (int)($_POST['preinscription_id'] ?? 0);
    
    if ($action === 'valider' && $preinscription_id > 0) {
        try {
            $pdo->beginTransaction();
            
            // Récupérer la pré-inscription
            $stmt = $pdo->prepare("SELECT * FROM preinscriptions WHERE id = ? AND statut = 'en_attente'");
            $stmt->execute([$preinscription_id]);
            $preinsc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$preinsc) {
                throw new Exception("Pré-inscription introuvable ou déjà traitée");
            }
            
            // Vérifier que l'email n'existe pas déjà
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$preinsc['email']]);
            if ($stmt->fetch()) {
                throw new Exception("Un compte existe déjà avec cet email");
            }
            
            // Créer le compte utilisateur
            $password_temp = bin2hex(random_bytes(8));
            $password_hash = password_hash($password_temp, PASSWORD_DEFAULT);
            
            // Utiliser le GSM comme téléphone principal (priorité), sinon le téléphone fixe
            $telephone_principal = !empty($preinsc['gsm']) ? $preinsc['gsm'] : $preinsc['telephone'];
            
            // Vérifier quelles colonnes existent dans la table users
            $colsStmt = $pdo->query('SHOW COLUMNS FROM users');
            $userCols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
            
            // Construire dynamiquement la requête en fonction des colonnes disponibles
            $columns = ['nom', 'prenom', 'email', 'password_hash', 'role', 'actif'];
            $values = [$preinsc['nom'], $preinsc['prenom'], $preinsc['email'], $password_hash, 'membre', 1];
            
            // Colonnes optionnelles
            if (in_array('telephone', $userCols)) {
                $columns[] = 'telephone';
                $values[] = $telephone_principal;
            }
            
            // Gérer la photo : copier depuis preinscriptions/ vers uploads/
            $finalPhotoPath = null;
            if (!empty($preinsc['photo_filename'])) {
                $sourcePath = __DIR__ . '/uploads/preinscriptions/' . $preinsc['photo_filename'];
                if (file_exists($sourcePath)) {
                    $ext = pathinfo($preinsc['photo_filename'], PATHINFO_EXTENSION);
                    $newFilename = 'member_' . time() . '_' . uniqid() . '.' . $ext;
                    $destPath = __DIR__ . '/uploads/' . $newFilename;
                    
                    @mkdir(__DIR__ . '/uploads', 0755, true);
                    
                    if (copy($sourcePath, $destPath)) {
                        $finalPhotoPath = 'uploads/' . $newFilename;
                    }
                }
            }
            
            if (in_array('photo_path', $userCols)) {
                $columns[] = 'photo_path';
                $values[] = $finalPhotoPath;
            } elseif (in_array('photo', $userCols)) {
                $columns[] = 'photo';
                // Pour la colonne 'photo', extraire juste le nom du fichier
                $values[] = $finalPhotoPath ? basename($finalPhotoPath) : null;
            }
            
            if (in_array('type_membre', $userCols)) {
                $columns[] = 'type_membre';
                $values[] = 'invite';
            }
            
            if (in_array('created_at', $userCols)) {
                $columns[] = 'created_at';
                $values[] = date('Y-m-d H:i:s');
            }
            
            // Construire la requête SQL
            $columnsStr = implode(', ', $columns);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            
            $stmtUser = $pdo->prepare("
                INSERT INTO users ($columnsStr) VALUES ($placeholders)
            ");
            
            $stmtUser->execute($values);
            
            $user_id = $pdo->lastInsertId();
            
            // Mettre à jour la pré-inscription
            $stmtUpdate = $pdo->prepare("
                UPDATE preinscriptions 
                SET statut = 'validee', 
                    validated_at = NOW(), 
                    validated_by = ?, 
                    user_id = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([$_SESSION['user_id'], $user_id, $preinscription_id]);
            
            $pdo->commit();
            
            // Envoyer un email au nouveau membre
            $subject = "Bienvenue au Club ULM Evasion - Votre compte est activé !";
            $message = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto;'>
                        <div style='background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; padding: 2rem; text-align: center; border-radius: 10px 10px 0 0;'>
                            <h1 style='margin: 0;'>🎉 Bienvenue !</h1>
                        </div>
                        <div style='padding: 2rem; background: #f9f9f9;'>
                            <p>Bonjour <strong>{$preinsc['prenom']} {$preinsc['nom']}</strong>,</p>
                            
                            <p>Nous avons le plaisir de vous informer que votre demande d'inscription au <strong>Club ULM Evasion</strong> a été acceptée ! 🎊</p>
                            
                            <div style='background: white; padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0;'>
                                <h3 style='margin-top: 0; color: #004b8d;'>Vos identifiants de connexion</h3>
                                <p><strong>URL :</strong> <a href='https://gestnav.clubulmevasion.fr'>https://gestnav.clubulmevasion.fr</a></p>
                                <p><strong>Email :</strong> {$preinsc['email']}</p>
                                <p><strong>Mot de passe temporaire :</strong> <code style='background: #f0f0f0; padding: 4px 8px; border-radius: 4px;'>{$password_temp}</code></p>
                                <p style='color: #d97706; font-size: 0.9rem;'>⚠️ Pensez à changer votre mot de passe lors de votre première connexion</p>
                            </div>
                            
                            <p>Vous pouvez maintenant :</p>
                            <ul>
                                <li>Consulter les sorties prévues</li>
                                <li>Vous inscrire aux vols</li>
                                <li>Proposer des destinations</li>
                                <li>Accéder à l'annuaire des membres</li>
                            </ul>
                            
                            <p>Nous sommes ravis de vous compter parmi nous !</p>
                            
                            <p style='margin-top: 2rem;'>
                                À très bientôt,<br>
                                <strong>L'équipe du Club ULM Evasion</strong>
                            </p>
                        </div>
                        <div style='background: #004b8d; color: white; padding: 1rem; text-align: center; font-size: 0.85rem; border-radius: 0 0 10px 10px;'>
                            GESTNAV v2.0.0 - Gestion Club ULM
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Club ULM Evasion <info@clubulmevasion.fr>\r\n";
            
            @mail($preinsc['email'], $subject, $message, $headers);
            
            // Logger l'envoi
            try {
                $stmtLog = $pdo->prepare("INSERT INTO email_logs (sender_id, recipient_email, subject, message_html, status, created_at) VALUES (?, ?, ?, ?, 'sent', NOW())");
                $stmtLog->execute([
                    $_SESSION['user_id'] ?? 1,
                    $preinsc['email'],
                    $subject,
                    $message
                ]);
            } catch (Exception $e) {
                error_log("Erreur log email validation: " . $e->getMessage());
            }
            
            $_SESSION['success'] = "Pré-inscription validée et compte créé avec succès ! Email envoyé au membre.";
            header('Location: preinscriptions_admin.php');
            exit;
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error'] = "Erreur : " . $e->getMessage();
            header('Location: preinscriptions_admin.php');
            exit;
        }
    }
    
    if ($action === 'refuser' && $preinscription_id > 0) {
        $motif = trim($_POST['motif_refus'] ?? 'Votre demande ne correspond pas aux critères du club.');
        
        try {
            // Récupérer la pré-inscription
            $stmt = $pdo->prepare("SELECT * FROM preinscriptions WHERE id = ? AND statut = 'en_attente'");
            $stmt->execute([$preinscription_id]);
            $preinsc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$preinsc) {
                throw new Exception("Pré-inscription introuvable ou déjà traitée");
            }
            
            // Mettre à jour le statut
            $stmtUpdate = $pdo->prepare("
                UPDATE preinscriptions 
                SET statut = 'refusee', 
                    validated_at = NOW(), 
                    validated_by = ?,
                    notes_admin = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([$_SESSION['user_id'], $motif, $preinscription_id]);
            
            // Envoyer un email de refus
            $subject = "Demande d'inscription au Club ULM Evasion";
            $message = "
                <p>Bonjour {$preinsc['prenom']} {$preinsc['nom']},</p>
                <p>Nous avons bien étudié votre demande d'inscription au Club ULM Evasion.</p>
                <p>Malheureusement, nous ne sommes pas en mesure de donner une suite favorable à votre candidature pour le moment.</p>
                <p><em>Motif : " . htmlspecialchars($motif) . "</em></p>
                <p>Nous vous remercions pour l'intérêt que vous portez à notre club.</p>
                <p style='margin-top: 2rem;'>Cordialement,<br>L'équipe du Club ULM Evasion</p>
            ";
            
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Club ULM Evasion <info@clubulmevasion.fr>\r\n";
            
            @mail($preinsc['email'], $subject, $message, $headers);
            
            // Logger l'envoi
            try {
                $stmtLog = $pdo->prepare("INSERT INTO email_logs (sender_id, recipient_email, subject, message_html, status, created_at) VALUES (?, ?, ?, ?, 'sent', NOW())");
                $stmtLog->execute([
                    $_SESSION['user_id'] ?? 1,
                    $preinsc['email'],
                    $subject,
                    $message
                ]);
            } catch (Exception $e) {
                error_log("Erreur log email refus: " . $e->getMessage());
            }
            
            $_SESSION['success'] = "Pré-inscription refusée. Email envoyé au candidat.";
            header('Location: preinscriptions_admin.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur : " . $e->getMessage();
            header('Location: preinscriptions_admin.php');
            exit;
        }
    }
}

// Récupérer les pré-inscriptions
$filter = $_GET['filter'] ?? 'en_attente';
$whereClause = "WHERE statut = ?";
$params = [$filter];

if ($filter === 'toutes') {
    $whereClause = "";
    $params = [];
}

$sql = "
    SELECT p.*, u.nom as validateur_nom, u.prenom as validateur_prenom
    FROM preinscriptions p
    LEFT JOIN users u ON u.id = p.validated_by
    $whereClause
    ORDER BY p.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$preinscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter par statut
$stats = [
    'en_attente' => (int)$pdo->query("SELECT COUNT(*) FROM preinscriptions WHERE statut = 'en_attente'")->fetchColumn(),
    'validee' => (int)$pdo->query("SELECT COUNT(*) FROM preinscriptions WHERE statut = 'validee'")->fetchColumn(),
    'refusee' => (int)$pdo->query("SELECT COUNT(*) FROM preinscriptions WHERE statut = 'refusee'")->fetchColumn(),
];

require 'header.php';
?>

<style>
.preinsc-admin-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.preinsc-header {
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

.preinsc-header h1 {
    font-size: 1.6rem;
    margin: 0;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.card {
    background: #ffffff;
    border-radius: 1.25rem;
    padding: 1.75rem 1.5rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.03);
    margin-bottom: 1.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    text-align: center;
    border: 2px solid #e5e7eb;
    cursor: pointer;
    transition: all 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-card.active {
    border-color: #00a0c6;
    background: #f0fbff;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: #004b8d;
}

.stat-label {
    font-size: 0.9rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.preinsc-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.preinsc-item {
    background: white;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
    padding: 1.25rem;
    display: grid;
    grid-template-columns: 80px 1fr auto;
    gap: 1.25rem;
    align-items: center;
}

.preinsc-photo {
    width: 80px;
    height: 80px;
    border-radius: 0.75rem;
    overflow: hidden;
    background: #f3f4f6;
}

.preinsc-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.preinsc-info {
    flex: 1;
}

.preinsc-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.25rem;
}

.preinsc-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 0.5rem;
}

.preinsc-actions {
    display: flex;
    gap: 0.5rem;
}

.btn {
    padding: 0.625rem 1.25rem;
    border: none;
    border-radius: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.875rem;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-info {
    background: #3b82f6;
    color: white;
}

.btn-info:hover {
    background: #2563eb;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
}

.badge-success {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}

.badge-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 2rem;
    border-radius: 1.25rem;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-close {
    float: right;
    font-size: 1.5rem;
    cursor: pointer;
    color: #9ca3af;
}

.modal-close:hover {
    color: #1f2937;
}
</style>

<div class="preinsc-admin-page">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="preinsc-header">
        <div>
            <h1>Pré-inscriptions</h1>
            <p style="margin: 0.5rem 0 0; opacity: 0.95;">Validation des demandes d'adhésion</p>
        </div>
        <div style="font-size: 2.4rem; opacity: 0.9;">📝</div>
    </div>

    <div class="stats-grid">
        <a href="?filter=en_attente" style="text-decoration: none;">
            <div class="stat-card <?= $filter === 'en_attente' ? 'active' : '' ?>">
                <div class="stat-number"><?= $stats['en_attente'] ?></div>
                <div class="stat-label">En attente</div>
            </div>
        </a>
        <a href="?filter=validee" style="text-decoration: none;">
            <div class="stat-card <?= $filter === 'validee' ? 'active' : '' ?>">
                <div class="stat-number"><?= $stats['validee'] ?></div>
                <div class="stat-label">Validées</div>
            </div>
        </a>
        <a href="?filter=refusee" style="text-decoration: none;">
            <div class="stat-card <?= $filter === 'refusee' ? 'active' : '' ?>">
                <div class="stat-number"><?= $stats['refusee'] ?></div>
                <div class="stat-label">Refusées</div>
            </div>
        </a>
        <a href="?filter=toutes" style="text-decoration: none;">
            <div class="stat-card <?= $filter === 'toutes' ? 'active' : '' ?>">
                <div class="stat-number"><?= array_sum($stats) ?></div>
                <div class="stat-label">Total</div>
            </div>
        </a>
    </div>

    <div class="card">
        <?php if (empty($preinscriptions)): ?>
            <p style="text-align: center; color: #9ca3af; padding: 2rem;">Aucune pré-inscription</p>
        <?php else: ?>
            <div class="preinsc-list">
                <?php foreach ($preinscriptions as $p): ?>
                    <div class="preinsc-item">
                        <div class="preinsc-photo">
                            <?php if ($p['photo_filename']): ?>
                                <img src="uploads/preinscriptions/<?= htmlspecialchars($p['photo_filename']) ?>" alt="Photo">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">👤</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="preinsc-info">
                            <div class="preinsc-name">
                                <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?>
                                <?php if ($p['statut'] === 'en_attente'): ?>
                                    <span class="badge badge-warning">En attente</span>
                                <?php elseif ($p['statut'] === 'validee'): ?>
                                    <span class="badge badge-success">Validée</span>
                                <?php elseif ($p['statut'] === 'refusee'): ?>
                                    <span class="badge badge-danger">Refusée</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="preinsc-meta">
                                <span>📧 <?= htmlspecialchars($p['email']) ?></span>
                                <span>📱 <?= htmlspecialchars($p['gsm']) ?></span>
                                <span>📍 <?= htmlspecialchars($p['ville']) ?></span>
                                <span>🎂 <?= date('d/m/Y', strtotime($p['date_naissance'])) ?></span>
                                <?php if ($p['est_pilote']): ?>
                                    <span>✈️ Pilote (<?= htmlspecialchars($p['numero_licence']) ?>)</span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">
                                Demande du <?= date('d/m/Y à H:i', strtotime($p['created_at'])) ?>
                            </div>
                        </div>
                        
                        <div class="preinsc-actions">
                            <button class="btn btn-info" onclick="showDetails(<?= $p['id'] ?>)">👁️ Détails</button>
                            <?php if ($p['statut'] === 'en_attente'): ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Valider cette pré-inscription et créer le compte ?');">
                                    <input type="hidden" name="action" value="valider">
                                    <input type="hidden" name="preinscription_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-success">✅ Valider</button>
                                </form>
                                <button class="btn btn-danger" onclick="showRefuseModal(<?= $p['id'] ?>)">❌ Refuser</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal détails -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('detailsModal')">&times;</span>
        <div id="detailsContent"></div>
    </div>
</div>

<!-- Modal refus -->
<div id="refuseModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('refuseModal')">&times;</span>
        <h3 style="margin-top: 0;">Refuser la pré-inscription</h3>
        <form method="post">
            <input type="hidden" name="action" value="refuser">
            <input type="hidden" name="preinscription_id" id="refusePreinscId">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Motif du refus</label>
                <textarea name="motif_refus" rows="4" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem;" required>Votre demande ne correspond pas aux critères du club pour le moment.</textarea>
            </div>
            <button type="submit" class="btn btn-danger" style="width: 100%;">Confirmer le refus</button>
        </form>
    </div>
</div>

<script>
const preinscriptions = <?= json_encode($preinscriptions) ?>;

function showDetails(id) {
    const p = preinscriptions.find(item => item.id == id);
    if (!p) return;
    
    const content = `
        <h2 style="margin-top: 0;">${p.prenom} ${p.nom}</h2>
        
        <div style="margin: 1.5rem 0;">
            ${p.photo_filename ? `<img src="uploads/preinscriptions/${p.photo_filename}" style="max-width: 200px; border-radius: 8px;">` : ''}
        </div>
        
        <h3>Informations personnelles</h3>
        <table style="width: 100%; font-size: 0.9rem;">
            <tr><td><strong>Nom complet :</strong></td><td>${p.prenom} ${p.nom}</td></tr>
            <tr><td><strong>Email :</strong></td><td>${p.email}</td></tr>
            <tr><td><strong>Téléphone :</strong></td><td>${p.telephone}</td></tr>
            <tr><td><strong>GSM :</strong></td><td>${p.gsm}</td></tr>
            <tr><td><strong>Date de naissance :</strong></td><td>${new Date(p.date_naissance).toLocaleDateString('fr-FR')}</td></tr>
            <tr><td><strong>Profession :</strong></td><td>${p.profession}</td></tr>
        </table>
        
        <h3>Adresse</h3>
        <p>${p.adresse_ligne1}<br>
        ${p.adresse_ligne2 ? p.adresse_ligne2 + '<br>' : ''}
        ${p.code_postal} ${p.ville}<br>
        ${p.pays}</p>
        
        <h3>Contact d'urgence</h3>
        <p><strong>${p.contact_urgence_nom}</strong><br>
        📞 ${p.contact_urgence_tel}<br>
        📧 ${p.contact_urgence_email}</p>
        
        <h3>Présentation</h3>
        <p style="white-space: pre-wrap; background: #f9fafb; padding: 1rem; border-radius: 0.5rem;">${p.presentation}</p>
        
        <h3>Expérience de pilotage</h3>
        <p>${p.est_pilote ? `✈️ Pilote - Licence n° ${p.numero_licence}` : '❌ Non pilote'}</p>
        
        ${p.statut !== 'en_attente' ? `
            <h3>Traitement</h3>
            <p><strong>Statut :</strong> ${p.statut === 'validee' ? '✅ Validée' : '❌ Refusée'}<br>
            <strong>Traité le :</strong> ${new Date(p.validated_at).toLocaleString('fr-FR')}<br>
            <strong>Par :</strong> ${p.validateur_prenom} ${p.validateur_nom}
            ${p.notes_admin ? `<br><strong>Motif :</strong> ${p.notes_admin}` : ''}
            </p>
        ` : ''}
    `;
    
    document.getElementById('detailsContent').innerHTML = content;
    document.getElementById('detailsModal').style.display = 'block';
}

function showRefuseModal(id) {
    document.getElementById('refusePreinscId').value = id;
    document.getElementById('refuseModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php require 'footer.php'; ?>
