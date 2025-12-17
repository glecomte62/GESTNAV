<?php
require 'config.php';
session_start();

// Vérifier la connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$evenement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($evenement_id <= 0) {
    die("Événement non spécifié.");
}

// Gestion de l'ajout de commentaire
$comment_message = '';
$comments_enabled = true;

// Vérifier si la table event_comments existe
try {
    $check = $pdo->query("SHOW TABLES LIKE 'event_comments'");
    if ($check->rowCount() == 0) {
        $comments_enabled = false;
    }
} catch (Exception $e) {
    $comments_enabled = false;
}

// Gestion des actions sur les commentaires (AVANT tout output HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $comments_enabled) {
    $action = $_POST['action'] ?? '';
    
    // Ajout de commentaire ou réponse
    if ($action === 'add_comment') {
        $comment_text = trim($_POST['comment_text'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        
        if (!empty($comment_text)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO event_comments (event_id, user_id, parent_id, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$evenement_id, $_SESSION['user_id'], $parent_id, $comment_text]);
                header("Location: evenement_participants.php?id=$evenement_id&msg=comment_added#comment-section");
                exit;
            } catch (Exception $e) {
                // Continue et affiche l'erreur
            }
        }
    }
    
    // Édition de commentaire
    elseif ($action === 'edit_comment') {
        $comment_id = (int)($_POST['comment_id'] ?? 0);
        $comment_text = trim($_POST['comment_text'] ?? '');
        
        if ($comment_id > 0 && !empty($comment_text)) {
            try {
                // Vérifier que c'est bien le commentaire de l'utilisateur
                $stmt = $pdo->prepare("UPDATE event_comments SET comment = ?, updated_at = NOW() WHERE id = ? AND user_id = ? AND event_id = ?");
                $result = $stmt->execute([$comment_text, $comment_id, $_SESSION['user_id'], $evenement_id]);
                if ($stmt->rowCount() > 0) {
                    header("Location: evenement_participants.php?id=$evenement_id&msg=comment_edited");
                    exit;
                }
            } catch (Exception $e) {
                // Continue et affiche l'erreur
            }
        }
    }
    
    // Suppression de commentaire
    elseif ($action === 'delete_comment') {
        $comment_id = (int)($_POST['comment_id'] ?? 0);
        
        if ($comment_id > 0) {
            try {
                // Vérifier que c'est bien le commentaire de l'utilisateur (ou admin)
                $is_admin = ($_SESSION['role'] ?? '') === 'admin';
                $where_clause = $is_admin ? "id = ? AND event_id = ?" : "id = ? AND user_id = ? AND event_id = ?";
                $params = $is_admin ? [$comment_id, $evenement_id] : [$comment_id, $_SESSION['user_id'], $evenement_id];
                
                $stmt = $pdo->prepare("DELETE FROM event_comments WHERE $where_clause");
                $result = $stmt->execute($params);
                if ($stmt->rowCount() > 0) {
                    header("Location: evenement_participants.php?id=$evenement_id&msg=comment_deleted");
                    exit;
                }
            } catch (Exception $e) {
                // Continue et affiche l'erreur
            }
        }
    }
}

// Messages après redirection
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'comment_added':
            $comment_message = '<div class="alert alert-success">✅ Commentaire ajouté avec succès</div>';
            break;
        case 'comment_edited':
            $comment_message = '<div class="alert alert-success">✅ Commentaire modifié avec succès</div>';
            break;
        case 'comment_deleted':
            $comment_message = '<div class="alert alert-success">✅ Commentaire supprimé avec succès</div>';
            break;
    }
}

// Maintenant on peut inclure le header (après les redirections)
require 'header.php';
require_login();

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

// Lecture seule: aucune édition depuis cette page

// Récupérer uniquement les inscriptions confirmées (lecture seule)
$onlyConfirmed = true;
$whereStatut = $onlyConfirmed ? "AND ei.statut = 'confirmée'" : '';
$stmt_ins = $pdo->prepare("
    SELECT ei.id, ei.user_id, ei.nb_accompagnants, ei.statut, ei.notes, u.email, u.prenom, u.nom
    FROM evenement_inscriptions ei
    JOIN users u ON u.id = ei.user_id
    WHERE ei.evenement_id = ? $whereStatut
    ORDER BY u.nom, u.prenom
");
$stmt_ins->execute([$evenement_id]);
$inscriptions = $stmt_ins->fetchAll(PDO::FETCH_ASSOC);

// Statistiques (basées sur confirmées)
$stmt_stats = $pdo->prepare("SELECT COUNT(*) as c, COALESCE(SUM(nb_accompagnants),0) as acc FROM evenement_inscriptions WHERE evenement_id = ? AND statut = 'confirmée'");
$stmt_stats->execute([$evenement_id]);
$st = $stmt_stats->fetch(PDO::FETCH_ASSOC) ?: ['c'=>0,'acc'=>0];
$total_inscrits = (int)$st['c'];
$total_accompagnants = (int)$st['acc'];
$total_personnes = $total_inscrits + $total_accompagnants;

// Déterminer si l'utilisateur courant est inscrit
$my_inscription = null;
try {
    $stmt_my = $pdo->prepare("SELECT * FROM evenement_inscriptions WHERE evenement_id = ? AND user_id = ? LIMIT 1");
    $stmt_my->execute([$evenement_id, (int)$_SESSION['user_id']]);
    $my_inscription = $stmt_my->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $my_inscription = null;
}
?>

<div class="container mt-4">
    <a href="evenements_list.php" class="btn btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Retour aux événements
    </a>
    
    <h1 class="mb-4">📋 <?= htmlspecialchars($evt['titre']) ?></h1>
    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
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
    
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="gn-card mb-4">
                <div class="gn-card-header">
                    <h3 class="gn-card-title">Informations</h3>
                </div>
                <div style="padding: 1.5rem;">
                    <p><strong>Type :</strong> <span class="badge bg-info"><?= ucfirst($evt['type']) ?></span></p>
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
                    <p><strong>Statut :</strong> <span class="badge bg-secondary"><?= ucfirst($evt['statut']) ?></span></p>
                    
                    <?php if ($evt['date_limite_inscription']): ?>
                        <p><strong>Date limite d'inscription :</strong> <?= date('d/m/Y à H:i', strtotime($evt['date_limite_inscription'])) ?></p>
                    <?php endif; ?>
                    
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
        
        <div class="col-md-4">
            <div class="gn-card">
                <div class="gn-card-header">
                    <h3 class="gn-card-title">Statistiques</h3>
                </div>
                <div style="padding: 1.5rem;">
                    <div style="display: grid; gap: 1rem;">
                        <div style="padding: 1rem; background: #e8f5e9; border-radius: 6px; text-align: center;">
                            <h4 style="color: #2e7d32; margin: 0; font-size: 2rem;">
                                <?= $total_inscrits ?>
                            </h4>
                            <p style="margin: 0; color: #558b2f;">Personnes inscrites</p>
                        </div>
                        
                        <div style="padding: 1rem; background: #fff3e0; border-radius: 6px; text-align: center;">
                            <h4 style="color: #e65100; margin: 0; font-size: 2rem;">
                                <?= $total_accompagnants ?>
                            </h4>
                            <p style="margin: 0; color: #f57c00;">Accompagnants</p>
                        </div>
                        
                        <div style="padding: 1rem; background: #e3f2fd; border-radius: 6px; text-align: center;">
                            <h4 style="color: #1565c0; margin: 0; font-size: 2rem;">
                                <?= $total_personnes ?>
                            </h4>
                            <p style="margin: 0; color: #1976d2;">Total de personnes</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Inscriptions / Participants -->
    <div class="gn-card">
        <div class="gn-card-header">
            <h3 class="gn-card-title">
                <?= is_admin() ? '📝 Inscriptions' : '👥 Participants confirmés' ?>
                (<?= is_admin() ? count($inscriptions) : $total_inscrits ?>)
            </h3>
            <?php if (!is_admin()): ?>
                <div style="margin-top:0.5rem; display:flex; flex-wrap:wrap; gap:0.5rem;">
                    <?php if (!$my_inscription): ?>
                        <a href="evenement_inscription_detail.php?id=<?= $evenement_id ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-check-circle"></i> Je m'inscris
                        </a>
                    <?php else: ?>
                        <a href="evenement_inscription_detail.php?id=<?= $evenement_id ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> Modifier mon inscription
                        </a>
                        <a href="evenement_inscription_detail.php?id=<?= $evenement_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Annuler votre inscription à cet événement ?');">
                            <i class="bi bi-x-circle"></i> Annuler mon inscription
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if (empty($inscriptions)): ?>
            <div style="padding: 1.5rem; color: #999;">
                <p><?= is_admin() ? 'Aucune inscription pour le moment.' : 'Aucun participant confirmé pour le moment.' ?></p>
            </div>
        <?php else: ?>
            <div style="padding: 0;">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <?php if (is_admin()): ?><th style="width:150px;">Statut</th><?php endif; ?>
                            <th style="text-align: center;">Accompagnants</th>
                            <th style="text-align: center;">Total</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inscriptions as $ins): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ins['prenom'] . ' ' . $ins['nom']) ?></strong></td>
                                <?php if (is_admin()): ?>
                                  <td>
                                    <?php $label = ['confirmée'=>'Confirmée','en_attente'=>'En attente','annulée'=>'Annulée'][$ins['statut']] ?? ucfirst($ins['statut']); ?>
                                    <span class="badge <?= $ins['statut']==='confirmée'?'bg-success':($ins['statut']==='en_attente'?'bg-secondary':'bg-danger') ?>"><?= htmlspecialchars($label) ?></span>
                                  </td>
                                <?php endif; ?>
                                <td style="text-align: center;">
                                    <?php if ($ins['nb_accompagnants'] > 0): ?>
                                        <span class="badge bg-warning"><?= (int)$ins['nb_accompagnants'] ?></span>
                                    <?php else: ?>
                                        <span style="color: #ccc;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <strong><?= 1 + (int)$ins['nb_accompagnants'] ?></strong>
                                </td>
                                <td>
                                    <?php if ($ins['notes']): ?>
                                        <small style="color: #666;">
                                            <?= htmlspecialchars(substr($ins['notes'], 0, 50)) ?><?= strlen($ins['notes']) > 50 ? '...' : '' ?>
                                        </small>
                                    <?php else: ?>
                                        <small style="color: #ccc;">—</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section Commentaires -->
    <?php
    // Récupération des commentaires
    $comments = [];
    if ($comments_enabled) {
        try {
            $stmt = $pdo->prepare("
                SELECT c.*, u.prenom, u.nom 
                FROM event_comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.event_id = ?
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$evenement_id]);
            $all_comments = $stmt->fetchAll();
            
            // Organiser en hiérarchie parent/enfants
            $comments = []; // Commentaires principaux
            $replies = [];  // Réponses groupées par parent_id
            
            foreach ($all_comments as $comment) {
                if ($comment['parent_id'] === null) {
                    $comments[] = $comment;
                } else {
                    if (!isset($replies[$comment['parent_id']])) {
                        $replies[$comment['parent_id']] = [];
                    }
                    $replies[$comment['parent_id']][] = $comment;
                }
            }
        } catch (Exception $e) {
            $comments = [];
            $replies = [];
            $comments_enabled = false;
        }
    }
    ?>

    <?php if ($comments_enabled): ?>
    <div class="gn-card" id="comment-section">
        <div class="gn-card-header">
            <h3 class="gn-card-title">💬 Discussion (<?= count($all_comments ?? []) ?>)</h3>
        </div>
        <div style="padding: 1.5rem;">
            <?php if (!empty($comment_message)): ?>
                <?= $comment_message ?>
            <?php endif; ?>

            <!-- Formulaire d'ajout de commentaire principal -->
            <form method="POST" style="margin-bottom: 2rem;">
                <input type="hidden" name="action" value="add_comment">
                <div class="mb-3">
                    <label for="comment_text" class="form-label">Ajouter un commentaire</label>
                    <textarea 
                        class="form-control" 
                        id="comment_text" 
                        name="comment_text" 
                        rows="3" 
                        placeholder="Partagez vos questions, suggestions ou informations sur cet événement..."
                        required
                    ></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-send"></i> Publier
                </button>
            </form>

            <!-- Liste des commentaires -->
            <?php if (empty($comments)): ?>
                <div style="padding: 2rem; text-align: center; color: #999;">
                    <i class="bi bi-chat-dots" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p style="margin-top: 1rem;">Aucun commentaire pour le moment. Soyez le premier à lancer la discussion !</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <?php foreach ($comments as $comment): ?>
                        <?php 
                        $is_author = ($comment['user_id'] == $_SESSION['user_id']);
                        $can_delete = $is_author || is_admin();
                        $edit_mode = isset($_GET['edit']) && $_GET['edit'] == $comment['id'];
                        ?>
                        <div id="comment-<?= $comment['id'] ?>" style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 1rem; background: #fafafa;">
                            <div style="display: flex; align-items: center; margin-bottom: 0.75rem; gap: 0.5rem;">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: <?= CLUB_COLOR_PRIMARY ?? '#1976d2' ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.1rem;">
                                    <?= strtoupper(substr($comment['prenom'], 0, 1)) ?>
                                </div>
                                <div style="flex: 1;">
                                    <strong style="color: #333;"><?= htmlspecialchars($comment['prenom'] . ' ' . $comment['nom']) ?></strong>
                                    <small style="color: #999; display: block;">
                                        <?php
                                        $date = new DateTime($comment['created_at']);
                                        $now = new DateTime();
                                        $diff = $now->diff($date);
                                        
                                        if ($diff->days == 0) {
                                            if ($diff->h == 0) {
                                                echo $diff->i == 0 ? "À l'instant" : "Il y a " . $diff->i . " min";
                                            } else {
                                                echo "Il y a " . $diff->h . "h";
                                            }
                                        } elseif ($diff->days == 1) {
                                            echo "Hier à " . $date->format('H:i');
                                        } elseif ($diff->days < 7) {
                                            echo "Il y a " . $diff->days . " jours";
                                        } else {
                                            echo $date->format('d/m/Y à H:i');
                                        }
                                        ?>
                                        <?php if ($comment['updated_at']): ?>
                                            <span style="color: #999; font-style: italic;"> (modifié)</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php if ($is_author || $can_delete): ?>
                                <div style="display: flex; gap: 0.5rem;">
                                    <?php if (!$edit_mode): ?>
                                        <a href="?id=<?= $evenement_id ?>&reply=<?= $comment['id'] ?>#reply-<?= $comment['id'] ?>" 
                                           class="btn btn-sm btn-outline-secondary" 
                                           title="Répondre">
                                            <i class="bi bi-reply"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($is_author && !$edit_mode): ?>
                                        <a href="?id=<?= $evenement_id ?>&edit=<?= $comment['id'] ?>#comment-<?= $comment['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($can_delete): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce commentaire <?= isset($replies[$comment['id']]) ? 'et toutes ses réponses' : '' ?> ?');">
                                            <input type="hidden" name="action" value="delete_comment">
                                            <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div>
                                    <a href="?id=<?= $evenement_id ?>&reply=<?= $comment['id'] ?>#reply-<?= $comment['id'] ?>" 
                                       class="btn btn-sm btn-outline-secondary" 
                                       title="Répondre">
                                        <i class="bi bi-reply"></i>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($edit_mode): ?>
                                <!-- Mode édition -->
                                <form method="POST" style="margin-top: 1rem;">
                                    <input type="hidden" name="action" value="edit_comment">
                                    <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                    <textarea class="form-control mb-2" name="comment_text" rows="3" required><?= htmlspecialchars($comment['comment']) ?></textarea>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-check"></i> Enregistrer
                                        </button>
                                        <a href="?id=<?= $evenement_id ?>#comment-section" class="btn btn-sm btn-secondary">
                                            <i class="bi bi-x"></i> Annuler
                                        </a>
                                    </div>
                                </form>
                            <?php else: ?>
                                <!-- Mode affichage -->
                                <div style="color: #333; line-height: 1.6; white-space: pre-wrap;">
                                    <?= htmlspecialchars($comment['comment']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Formulaire de réponse -->
                            <?php if (isset($_GET['reply']) && $_GET['reply'] == $comment['id']): ?>
                            <div id="reply-<?= $comment['id'] ?>" style="margin-top: 1rem; padding: 1rem; background: #f0f0f0; border-radius: 6px;">
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_comment">
                                    <input type="hidden" name="parent_id" value="<?= $comment['id'] ?>">
                                    <div class="mb-2">
                                        <label class="form-label" style="font-size: 0.9rem; font-weight: 600;">
                                            <i class="bi bi-reply"></i> Répondre à <?= htmlspecialchars($comment['prenom']) ?>
                                        </label>
                                        <textarea 
                                            class="form-control" 
                                            name="comment_text" 
                                            rows="2" 
                                            placeholder="Votre réponse..."
                                            required
                                            autofocus
                                        ></textarea>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-send"></i> Répondre
                                        </button>
                                        <a href="?id=<?= $evenement_id ?>#comment-section" class="btn btn-sm btn-secondary">
                                            <i class="bi bi-x"></i> Annuler
                                        </a>
                                    </div>
                                </form>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Affichage des réponses -->
                            <?php if (isset($replies[$comment['id']])): ?>
                            <div style="margin-top: 1rem; margin-left: 2rem; border-left: 3px solid #e0e0e0; padding-left: 1rem;">
                                <?php foreach ($replies[$comment['id']] as $reply): ?>
                                    <?php 
                                    $is_reply_author = ($reply['user_id'] == $_SESSION['user_id']);
                                    $can_delete_reply = $is_reply_author || is_admin();
                                    $edit_reply_mode = isset($_GET['edit']) && $_GET['edit'] == $reply['id'];
                                    ?>
                                    <div id="comment-<?= $reply['id'] ?>" style="margin-bottom: 1rem; padding: 0.75rem; background: #fff; border-radius: 6px; border: 1px solid #e0e0e0;">
                                        <div style="display: flex; align-items: center; margin-bottom: 0.5rem; gap: 0.5rem;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background: <?= CLUB_COLOR_PRIMARY ?? '#1976d2' ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9rem;">
                                                <?= strtoupper(substr($reply['prenom'], 0, 1)) ?>
                                            </div>
                                            <div style="flex: 1;">
                                                <strong style="color: #333; font-size: 0.9rem;"><?= htmlspecialchars($reply['prenom'] . ' ' . $reply['nom']) ?></strong>
                                                <small style="color: #999; display: block; font-size: 0.8rem;">
                                                    <?php
                                                    $date = new DateTime($reply['created_at']);
                                                    $now = new DateTime();
                                                    $diff = $now->diff($date);
                                                    
                                                    if ($diff->days == 0) {
                                                        if ($diff->h == 0) {
                                                            echo $diff->i == 0 ? "À l'instant" : "Il y a " . $diff->i . " min";
                                                        } else {
                                                            echo "Il y a " . $diff->h . "h";
                                                        }
                                                    } elseif ($diff->days == 1) {
                                                        echo "Hier à " . $date->format('H:i');
                                                    } elseif ($diff->days < 7) {
                                                        echo "Il y a " . $diff->days . " jours";
                                                    } else {
                                                        echo $date->format('d/m/Y à H:i');
                                                    }
                                                    ?>
                                                    <?php if ($reply['updated_at']): ?>
                                                        <span style="font-style: italic;"> (modifié)</span>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <?php if ($is_reply_author || $can_delete_reply): ?>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <?php if ($is_reply_author && !$edit_reply_mode): ?>
                                                    <a href="?id=<?= $evenement_id ?>&edit=<?= $reply['id'] ?>#comment-<?= $reply['id'] ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       style="padding: 0.2rem 0.5rem; font-size: 0.8rem;"
                                                       title="Modifier">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($can_delete_reply): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette réponse ?');">
                                                        <input type="hidden" name="action" value="delete_comment">
                                                        <input type="hidden" name="comment_id" value="<?= $reply['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" style="padding: 0.2rem 0.5rem; font-size: 0.8rem;" title="Supprimer">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($edit_reply_mode): ?>
                                            <!-- Mode édition réponse -->
                                            <form method="POST">
                                                <input type="hidden" name="action" value="edit_comment">
                                                <input type="hidden" name="comment_id" value="<?= $reply['id'] ?>">
                                                <textarea class="form-control mb-2" name="comment_text" rows="2" required><?= htmlspecialchars($reply['comment']) ?></textarea>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-check"></i> Enregistrer
                                                    </button>
                                                    <a href="?id=<?= $evenement_id ?>#comment-section" class="btn btn-sm btn-secondary">
                                                        <i class="bi bi-x"></i> Annuler
                                                    </a>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <!-- Mode affichage réponse -->
                                            <div style="color: #333; line-height: 1.5; white-space: pre-wrap; font-size: 0.9rem;">
                                                <?= htmlspecialchars($reply['comment']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <!-- Commentaires désactivés car la table n'existe pas -->
    <div class="gn-card">
        <div class="gn-card-header">
            <h3 class="gn-card-title">💬 Discussion</h3>
        </div>
        <div style="padding: 1.5rem;">
            <div class="alert alert-info">
                <h5>💡 Fonctionnalité de commentaires disponible prochainement</h5>
                <p style="margin: 0.5rem 0 0 0;">Pour activer les commentaires, l'administrateur doit exécuter le script d'installation : <code>install_event_comments.php</code></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
