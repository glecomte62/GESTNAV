<?php
require 'header.php';
require_login();

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
                    <p><strong>Date et heure :</strong> <?= date('d/m/Y à H:i', strtotime($evt['date_evenement'])) ?></p>
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
</div>

<?php require 'footer.php'; ?>
