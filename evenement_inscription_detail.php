<?php
require 'header.php';
require_login();
require_once 'mail_helper.php';
require_once 'utils/activity_log.php';

$user_id = $_SESSION['user_id'];
$evenement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($evenement_id <= 0) {
    die("Événement non spécifié.");
}

// Récupérer l'événement
$stmt = $pdo->prepare("
    SELECT e.*, u.prenom, u.nom
    FROM evenements e
    LEFT JOIN users u ON u.id = e.created_by
    WHERE e.id = ?
");
$stmt->execute([$evenement_id]);
$evt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evt) {
    die("Événement introuvable.");
}

// Vérifier si l'utilisateur est inscrit
$stmt_inscr = $pdo->prepare("
    SELECT id, statut, nb_accompagnants, notes, action_token
    FROM evenement_inscriptions
    WHERE evenement_id = ? AND user_id = ?
");
$stmt_inscr->execute([$evenement_id, $user_id]);
$mon_inscription = $stmt_inscr->fetch(PDO::FETCH_ASSOC);

// Traiter la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['s_inscrire'])) {
    $nb_accompagnants = isset($_POST['nb_accompagnants']) ? (int)$_POST['nb_accompagnants'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    if ($nb_accompagnants < 0 || $nb_accompagnants > 10) {
        $error = "Le nombre d'accompagnants doit être entre 0 et 10.";
    } else {
        try {
            if ($mon_inscription) {
                // Mettre à jour l'inscription existante
                $stmt_update = $pdo->prepare("
                    UPDATE evenement_inscriptions
                    SET statut = 'confirmée', nb_accompagnants = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt_update->execute([$nb_accompagnants, $notes, $mon_inscription['id']]);
                $success = "Votre participation a été mise à jour.";
                // Log opération (mise à jour inscription événement)
                gn_log_current_user_operation($pdo, 'event_update', 'Inscription modifiée');
            } else {
                // Créer une nouvelle inscription
                $action_token = bin2hex(random_bytes(32));
                $stmt_insert = $pdo->prepare("
                    INSERT INTO evenement_inscriptions 
                    (evenement_id, user_id, nb_accompagnants, notes, statut, action_token)
                    VALUES (?, ?, ?, ?, 'confirmée', ?)
                ");
                $stmt_insert->execute([$evenement_id, $user_id, $nb_accompagnants, $notes, $action_token]);
                $success = "Vous avez été inscrit à cet événement.";
                // Log opération (nouvelle inscription événement)
                gn_log_current_user_operation($pdo, 'event_register', 'Inscription effectuée');
                
                // Notifier l'administrateur
                $admin_email = "info@clubulmevasion.fr";
                $subject = "Nouvelle inscription à l'événement : " . $evt['titre'];
                $message = "
                    <h3>Nouvelle inscription à un événement</h3>
                    <p><strong>Événement :</strong> " . htmlspecialchars($evt['titre']) . "</p>
                    <p><strong>Date :</strong> " . date('d/m/Y à H:i', strtotime($evt['date_evenement'])) . "</p>
                    <p><strong>Utilisateur :</strong> " . htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']) . "</p>
                    <p><strong>Email :</strong> " . htmlspecialchars($_SESSION['email']) . "</p>
                    <p><strong>Accompagnants :</strong> " . $nb_accompagnants . "</p>
                    " . ($notes ? "<p><strong>Notes :</strong> " . nl2br(htmlspecialchars($notes)) . "</p>" : "") . "
                ";
                gestnav_send_mail($pdo, $admin_email, $subject, $message);
            }
            
            // Recharger les données
            $stmt_inscr->execute([$evenement_id, $user_id]);
            $mon_inscription = $stmt_inscr->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = "Erreur lors de l'inscription : " . $e->getMessage();
        }
    }
}

// Traiter l'annulation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler'])) {
    try {
        if (!$mon_inscription || empty($mon_inscription['id'])) {
            throw new Exception("Inscription introuvable.");
        }
        $stmt_cancel = $pdo->prepare("UPDATE evenement_inscriptions SET statut = 'annulée' WHERE id = ?");
        $stmt_cancel->execute([$mon_inscription['id']]);

        // Notifier l'administrateur
        $admin_email = "info@clubulmevasion.fr";
        $subject = "Annulation d'inscription à l'événement : " . $evt['titre'];
        $message = "<h3>Annulation d'inscription à un événement</h3>"
                 . "<p><strong>Événement :</strong> " . htmlspecialchars($evt['titre']) . "</p>"
                 . "<p><strong>Date :</strong> " . date('d/m/Y à H:i', strtotime($evt['date_evenement'])) . "</p>"
                 . "<p><strong>Utilisateur :</strong> " . htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']) . "</p>";
        gestnav_send_mail($pdo, $admin_email, $subject, $message);

        // Recharger les données et afficher un message de succès sans redirection
        $stmt_inscr->execute([$evenement_id, $user_id]);
        $mon_inscription = $stmt_inscr->fetch(PDO::FETCH_ASSOC);
        $success = "Votre inscription a été annulée.";
        // Log opération (annulation inscription événement)
        gn_log_current_user_operation($pdo, 'event_cancel', 'Inscription annulée');
    } catch (Exception $e) {
        $error = "Erreur lors de l'annulation : " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <a href="evenements_list.php" class="btn btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Retour aux événements
    </a>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">📅 <?= htmlspecialchars($evt['titre']) ?></h1>
        <?php if (is_admin()): ?>
            <div class="d-flex gap-2">
                <a href="evenement_edit.php?id=<?= $evenement_id ?>" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Éditer l'événement
                </a>
                <form method="POST" action="evenements_admin.php">
                    <input type="hidden" name="action" value="send_invites">
                    <input type="hidden" name="evenement_id" value="<?= $evenement_id ?>">
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-envelope"></i> Inviter les membres
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($evt['cover_filename'])): ?>
        <div class="mb-4">
            <img src="uploads/events/<?= htmlspecialchars($evt['cover_filename']) ?>" alt="" style="width:100%;max-width:720px;height:auto;object-fit:cover;border-radius:.75rem;border:1px solid #e5e7eb;">
        </div>
    <?php else: ?>
        <div class="mb-4" style="width:100%;max-width:720px;height:180px;border-radius:.75rem;border:1px solid #e5e7eb;background:#f1f3f5;display:flex;align-items:center;justify-content:center;color:#94a3b8;">
            <div style="display:flex;align-items:center;gap:.5rem;">
                <i class="bi bi-image" style="font-size:24px;"></i>
                <span>Pas d’illustration</span>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success']) || isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($_GET['success'] ?? $success ?? '') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
        <?php if (isset($_GET['error']) || isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($_GET['error'] ?? $error ?? '') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col-md-7">
            <div class="gn-card mb-4">
                <div class="gn-card-header">
                    <h3 class="gn-card-title">Informations de l'événement</h3>
                </div>
                <div style="padding: 1.5rem;">
                    <p><strong>Type :</strong> <span class="badge bg-info"><?= $evt['type'] ?></span></p>
                    <p>
                        <strong>Date<?= !empty($evt['is_multi_day']) && !empty($evt['date_fin']) ? 's' : ' et heure' ?> :</strong> 
                        <?php if (!empty($evt['is_multi_day']) && !empty($evt['date_fin'])): ?>
                            Du <?= date('d/m/Y à H:i', strtotime($evt['date_evenement'])) ?><br>
                            au <?= date('d/m/Y à H:i', strtotime($evt['date_fin'])) ?>
                        <?php else: ?>
                            <?= date('d/m/Y à H:i', strtotime($evt['date_evenement'])) ?>
                        <?php endif; ?>
                    </p>
                    <p><strong>Lieu :</strong> <?= htmlspecialchars($evt['lieu']) ?></p>
                    <p><strong>Statut :</strong> <span class="badge bg-secondary"><?= $evt['statut'] ?></span></p>
                    
                    <?php if ($evt['adresse']): ?>
                        <p><strong>Adresse :</strong><br>
                            <code style="background: #f5f5f5; padding: 0.5rem; display: block; margin-top: 0.5rem; border-radius: 4px;">
                                <?= nl2br(htmlspecialchars($evt['adresse'])) ?>
                            </code>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($evt['description']): ?>
                        <p><strong>Description :</strong><br><?= nl2br(htmlspecialchars($evt['description'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-5">
            <!-- Formulaire d'inscription -->
            <div class="gn-card">
                <div class="gn-card-header">
                    <h3 class="gn-card-title">
                        <?php if ($mon_inscription): ?>
                            Ma participation
                        <?php else: ?>
                            S'inscrire à cet événement
                        <?php endif; ?>
                    </h3>
                </div>
                <div style="padding: 1.5rem;">
                    <?php if ($mon_inscription && $mon_inscription['statut'] === 'confirmée'): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> Vous êtes inscrit à cet événement
                        </div>
                    <?php elseif ($mon_inscription && $mon_inscription['statut'] === 'annulée'): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle"></i> Votre inscription a été annulée
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="nb_accompagnants" class="form-label">Nombre d'accompagnants</label>
                            <input type="number" class="form-control" id="nb_accompagnants" name="nb_accompagnants" 
                                   min="0" max="10" 
                                   value="<?= $mon_inscription['nb_accompagnants'] ?? 0 ?>"
                                   <?= ($mon_inscription && $mon_inscription['statut'] === 'annulée') ? 'disabled' : '' ?>>
                            <small class="form-text text-muted">Pour nous aider avec la catering et l'organisation</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes ou remarques</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      <?= ($mon_inscription && $mon_inscription['statut'] === 'annulée') ? 'disabled' : '' ?>
                            ><?= $mon_inscription['notes'] ?? '' ?></textarea>
                            <small class="form-text text-muted">Par exemple : régime alimentaire, assistance nécessaire, etc.</small>
                        </div>
                        
                        <?php if (!$mon_inscription || $mon_inscription['statut'] === 'annulée'): ?>
                            <button type="submit" name="s_inscrire" class="btn btn-primary w-100">
                                <i class="bi bi-check2-circle"></i> S'inscrire
                            </button>
                        <?php else: ?>
                            <div class="row g-2">
                                <div class="col-6">
                                    <button type="submit" name="s_inscrire" class="btn btn-primary w-100">
                                        <i class="bi bi-check2-circle"></i> Valider
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="submit" name="annuler" class="btn btn-danger w-100" 
                                            onclick="return confirm('Êtes-vous sûr de vouloir annuler votre participation ?')">
                                        <i class="bi bi-x-circle"></i> Annuler
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistiques -->
    <?php
    $stmt_stats = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN statut = 'confirmée' THEN 1 END) as confirmees,
            COUNT(CASE WHEN statut = 'annulée' THEN 1 END) as annulees,
            SUM(CASE WHEN statut = 'confirmée' THEN nb_accompagnants ELSE 0 END) as total_accompagnants
        FROM evenement_inscriptions
        WHERE evenement_id = ?
    ");
    $stmt_stats->execute([$evenement_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    $total_personnes = ($stats['confirmees'] ?? 0) + ($stats['total_accompagnants'] ?? 0);
    ?>
    
    <div class="row mt-5">
        <div class="col-md-4">
            <div class="gn-card text-center">
                <div style="padding: 1.5rem;">
                    <h2 style="color: #28a745; margin: 0;"><?= $stats['confirmees'] ?? 0 ?></h2>
                    <p style="margin: 0; color: #666;">Inscriptions confirmées</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="gn-card text-center">
                <div style="padding: 1.5rem;">
                    <h2 style="color: #dc3545; margin: 0;"><?= $stats['annulees'] ?? 0 ?></h2>
                    <p style="margin: 0; color: #666;">Annulations</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="gn-card text-center">
                <div style="padding: 1.5rem;">
                    <h2 style="color: #0066cc; margin: 0;"><?= $total_personnes ?></h2>
                    <p style="margin: 0; color: #666;">Total de personnes</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
