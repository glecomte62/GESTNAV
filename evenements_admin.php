<?php
require 'header.php';
require_login();
require_admin();
require_once 'mail_helper.php';

$message = '';
$error = false;

// Récupérer tous les événements
// Récupérer tous les événements
$stmt = $pdo->query("
    SELECT e.*, u.prenom, u.nom,
           COUNT(ei.id) as total_inscrits,
           SUM(CASE WHEN ei.statut = 'confirmée' THEN 1 ELSE 0 END) as confirmees,
           SUM(CASE WHEN ei.statut = 'annulée' THEN 1 ELSE 0 END) as annulees,
           SUM(CASE WHEN ei.statut = 'confirmée' THEN ei.nb_accompagnants ELSE 0 END) as total_accompagnants
    FROM evenements e
    LEFT JOIN users u ON u.id = e.created_by
    LEFT JOIN evenement_inscriptions ei ON ei.evenement_id = e.id
    GROUP BY e.id
    ORDER BY e.date_evenement DESC
");
$evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Traiter la création d'un nouvel événement
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
        $titre = $_POST['titre'] ?? '';
        $description = $_POST['description'] ?? '';
        $type = $_POST['type'] ?? 'reunion';
        $date_evenement = $_POST['date_evenement'] ?? '';
        $date_fin = $_POST['date_fin'] ?? '';
        $lieu = $_POST['lieu'] ?? '';
        $adresse = $_POST['adresse'] ?? '';
        
        // Vérifier si c'est un événement multi-jours
        $is_multi_day = 0;
        $date_fin_param = null;
        if (!empty($date_fin) && $date_fin > $date_evenement) {
            $is_multi_day = 1;
            $date_fin_param = $date_fin;
        }
        
        if (!$titre || !$date_evenement || !$lieu) {
            $error = true;
            $message = "Veuillez remplir tous les champs obligatoires.";
        } else {
            try {
                $cover_filename = '';
                $hasCover = false;
                try {
                    $colCheck = $pdo->query("SHOW COLUMNS FROM evenements LIKE 'cover_filename'");
                    if ($colCheck && $colCheck->fetch()) { $hasCover = true; }
                } catch (Throwable $e) {}
                if (!empty($_FILES['cover']['name']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['cover']['tmp_name'];
                    $name = basename($_FILES['cover']['name']);
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                        $dir = __DIR__ . '/uploads/events';
                        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                        $safe = 'event_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        if (@move_uploaded_file($tmp, $dir . '/' . $safe)) {
                            $cover_filename = $safe;
                        }
                    }
                }
                if ($hasCover && $cover_filename) {
                    $ins = $pdo->prepare("INSERT INTO evenements (titre, description, type, date_evenement, date_fin, is_multi_day, lieu, adresse, cover_filename, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$titre, $description, $type, $date_evenement, $date_fin_param, $is_multi_day, $lieu, $adresse, $cover_filename, $_SESSION['user_id']]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO evenements (titre, description, type, date_evenement, date_fin, is_multi_day, lieu, adresse, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$titre, $description, $type, $date_evenement, $date_fin_param, $is_multi_day, $lieu, $adresse, $_SESSION['user_id']]);
                }
                $message = "Événement créé";
            } catch (Exception $e) {
                $error = true;
                $message = "Erreur : " . $e->getMessage();
            }
        }
    }

    // Traiter l'envoi d'invitations
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_invites') {
        $evenement_id = (int)($_POST['evenement_id'] ?? 0);
        if ($evenement_id <= 0) {
            $error = true;
            $message = "Événement non spécifié.";
        } else {
            try {
                $stmt_evt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
                $stmt_evt->execute([$evenement_id]);
                $evt = $stmt_evt->fetch(PDO::FETCH_ASSOC);
                if (!$evt) { throw new Exception("Événement introuvable"); }
                $stmt_users = $pdo->query("SELECT id, email, prenom, nom FROM users WHERE actif = 1 ORDER BY nom, prenom");
                $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
                $sent = 0; $failed = 0;
                foreach ($users as $user) {
                    $check = $pdo->prepare("SELECT id FROM evenement_inscriptions WHERE evenement_id = ? AND user_id = ?");
                    $check->execute([$evenement_id, $user['id']]);
                    if ($check->fetch()) { continue; }
                    $token = bin2hex(random_bytes(32));
                    $ins = $pdo->prepare("INSERT INTO evenement_inscriptions (evenement_id, user_id, action_token, statut) VALUES (?, ?, ?, 'en_attente')");
                    $ins->execute([$evenement_id, $user['id'], $token]);
                    $base_url_evt = app_url('action_evenement.php');
                    $url_inscrire = $base_url_evt . "?action=inscrire&token=" . $token;
                    $url_annuler = $base_url_evt . "?action=annuler&token=" . $token;
                    $html_mail = "<html><head><meta charset='UTF-8'><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333}.container{max-width:600px;margin:0 auto}.header{background:linear-gradient(135deg,#004b8d,#00a0c6);color:white;padding:20px;border-radius:8px 8px 0 0}.content{padding:20px;background:#f9f9f9}.actions{background:white;padding:20px;border-radius:0 0 8px 8px}.action-btn{display:inline-block;margin:10px 5px 10px 0;padding:12px 24px;border-radius:6px;font-weight:bold;text-decoration:none;color:white}.btn-inscrire{background:#5cb85c}.btn-annuler{background:#d9534f}hr{border:none;border-top:1px solid #ddd;margin:20px 0}</style></head><body><div class='container'><div class='header'><h2>📅 Invitation à un événement</h2></div><div class='content'><p>Bonjour <strong>" . htmlspecialchars($user['prenom'] . ' ' . $user['nom']) . "</strong>,</p><p>Vous êtes invité à participer à :</p><div class='info'><strong>📌 " . htmlspecialchars($evt['titre']) . "</strong><br><strong>📅 Date :</strong> " . date('d/m/Y à H:i', strtotime($evt['date_evenement'])) . "<br><strong>📍 Lieu :</strong> " . htmlspecialchars($evt['lieu']) . "<br></div><p>" . nl2br(htmlspecialchars($evt['description'])) . "</p>" . (!empty($evt['adresse']) ? "<p><strong>Adresse complète :</strong> " . nl2br(htmlspecialchars($evt['adresse'])) . "</p>" : "") . "</div><div class='actions'><p><strong>Votre réponse :</strong></p><p><a href='" . $url_inscrire . "' class='action-btn btn-inscrire'>✓ S'inscrire</a></p><p><a href='" . $url_annuler . "' class='action-btn btn-annuler'>✗ Annuler</a></p><hr><p style='font-size:12px;color:#666'>Ce mail a été envoyé automatiquement. Pour toute question, contactez: <strong>info@clubulmevasion.fr</strong></p></div></div></body></html>";
                    $result = gestnav_send_mail($pdo, $user['email'], "Invitation : " . $evt['titre'], $html_mail);
                    if ($result['success']) { $sent++; } else { $failed++; }
                }
                $message = "Invitations envoyées : $sent, échouées : $failed";
            } catch (Exception $e) {
                $error = true; $message = "Erreur : " . $e->getMessage();
            }
        }
    }

    ?>

        <div class="container mt-4">
            <h1 class="mb-4">📅 Gestion des événements</h1>
            <?php if ($message): ?>
                <div class="alert alert-<?= $error ? 'danger' : 'success' ?>" role="alert">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="gn-card mb-4">
                <div class="gn-card-header">
                    <h3 class="gn-card-title">➕ Créer un nouvel événement</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" style="padding: 1.5rem;">
                    <input type="hidden" name="action" value="create">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Titre *</label>
                            <input type="text" name="titre" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type *</label>
                            <select name="type" class="form-select" required>
                                <option value="reunion">Réunion</option>
                                <option value="assemblee">Assemblée générale</option>
                                <option value="formation">Formation</option>
                                <option value="social">Événement social</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date et heure de début *</label>
                            <input type="datetime-local" name="date_evenement" class="form-control" id="date_evenement" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date et heure de fin</label>
                            <input type="datetime-local" name="date_fin" class="form-control" id="date_fin">
                            <div class="form-text">Uniquement pour les événements sur plusieurs jours</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Lieu *</label>
                            <input type="text" name="lieu" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adresse complète</label>
                        <textarea name="adresse" class="form-control" rows="2" placeholder="Adresse, numéro de salle, etc."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Image de couverture (jpg, png, webp)</label>
                        <input type="file" name="cover" class="form-control" accept="image/*">
                        <div class="form-text">Optionnel. Utilisée comme vignette si la colonne "cover_filename" existe.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Créer l'événement</button>
                </form>
            </div>

            <h3 class="mt-5 mb-3">📋 Événements</h3>
            <?php if (empty($evenements)): ?>
                <div class="alert alert-info">Aucun événement pour le moment.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Titre</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Lieu</th>
                                <th>Inscriptions</th>
                                <th>Accompagnants</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evenements as $evt): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:.5rem;">
                                             <?php if (!empty($evt['cover_filename'])): ?>
                                                 <img src="uploads/events/<?= htmlspecialchars($evt['cover_filename']) ?>" alt="" style="width:46px;height:46px;object-fit:cover;border-radius:.5rem;border:1px solid #e5e7eb;">
                                                 <span class="text-muted" style="font-size:12px;"><?= htmlspecialchars($evt['cover_filename']) ?></span>
                                             <?php else: ?>
                                                 <div style="width:46px;height:46px;border-radius:.5rem;border:1px solid #e5e7eb;background:#f1f3f5;display:flex;align-items:center;justify-content:center;color:#94a3b8;">
                                                     <i class="bi bi-image"></i>
                                                 </div>
                                             <?php endif; ?>
                                            <strong><?= htmlspecialchars($evt['titre']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($evt['is_multi_day']) && !empty($evt['date_fin'])): ?>
                                            Du <?= date('d/m/Y', strtotime($evt['date_evenement'])) ?><br>
                                            au <?= date('d/m/Y', strtotime($evt['date_fin'])) ?>
                                        <?php else: ?>
                                            <?= date('d/m/Y H:i', strtotime($evt['date_evenement'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-info"><?= $evt['type'] ?></span></td>
                                    <td><?= htmlspecialchars($evt['lieu']) ?></td>
                                    <td>
                                        <span class="badge bg-success"><?= $evt['confirmees'] ?? 0 ?> confirmées</span>
                                        <span class="badge bg-danger"><?= $evt['annulees'] ?? 0 ?> annulées</span>
                                    </td>
                                    <td><?= $evt['total_accompagnants'] ?? 0 ?> personne(s)</td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="send_invites">
                                            <input type="hidden" name="evenement_id" value="<?= $evt['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-primary" title="Envoyer les invitations par email">
                                                <i class="bi bi-envelope"></i> Inviter
                                            </button>
                                        </form>
                                        <a href="evenement_edit.php?id=<?= $evt['id'] ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i> Éditer
                                        </a>
                                        <a href="evenement_detail.php?id=<?= $evt['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i> Détails
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php require 'footer.php'; ?>
