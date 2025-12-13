<?php
require 'header.php';
require_login();
require_admin();

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

// Récupérer les inscriptions
$stmt_ins = $pdo->prepare("
    SELECT ei.id, ei.user_id, ei.nb_accompagnants, ei.statut, ei.notes, u.email, u.prenom, u.nom
    FROM evenement_inscriptions ei
    JOIN users u ON u.id = ei.user_id
    WHERE ei.evenement_id = ?
    ORDER BY ei.statut DESC, u.nom, u.prenom
");
$stmt_ins->execute([$evenement_id]);
$inscriptions = $stmt_ins->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$confirmees = array_filter($inscriptions, fn($i) => $i['statut'] === 'confirmée');
$annulees = array_filter($inscriptions, fn($i) => $i['statut'] === 'annulée');
$total_personnes = count($confirmees) + array_sum(array_map(fn($i) => $i['nb_accompagnants'], $confirmees));
?>

<div class="container mt-4">
    <h1 class="mb-4">📅 <?= htmlspecialchars($evt['titre']) ?></h1>
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
        <div class="col-md-6">
            <div class="gn-card">
                <div class="gn-card-header">
                    <h3 class="gn-card-title">Informations de l'événement</h3>
                </div>
                <div style="padding: 1.5rem;">
                    <p><strong>Type :</strong> <span class="badge bg-info"><?= $evt['type'] ?></span></p>
                    <p><strong>Date et heure :</strong> <?= date('d/m/Y à H:i', strtotime($evt['date_evenement'])) ?></p>
                    <p><strong>Lieu :</strong> <?= htmlspecialchars($evt['lieu']) ?></p>
                    <p><strong>Statut :</strong> <span class="badge bg-secondary"><?= $evt['statut'] ?></span></p>
                    
                    <?php if ($evt['adresse']): ?>
                        <p><strong>Adresse :</strong><br><?= nl2br(htmlspecialchars($evt['adresse'])) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($evt['description']): ?>
                        <p><strong>Description :</strong><br><?= nl2br(htmlspecialchars($evt['description'])) ?></p>
                    <?php endif; ?>
                    
                    <p><strong>Créé par :</strong> <?= htmlspecialchars($evt['prenom'] . ' ' . $evt['nom']) ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="gn-card">
                <div class="gn-card-header">
                    <h3 class="gn-card-title">Statistiques d'inscription</h3>
                </div>
                <div style="padding: 1.5rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div style="padding: 1rem; background: #e8f5e9; border-radius: 6px; text-align: center;">
                            <h4 style="color: #2e7d32; margin: 0; font-size: 2rem;">
                                <?= count($confirmees) ?>
                            </h4>
                            <p style="margin: 0; color: #558b2f;">Confirmées</p>
                        </div>
                        
                        <div style="padding: 1rem; background: #ffebee; border-radius: 6px; text-align: center;">
                            <h4 style="color: #c62828; margin: 0; font-size: 2rem;">
                                <?= count($annulees) ?>
                            </h4>
                            <p style="margin: 0; color: #d32f2f;">Annulées</p>
                        </div>
                    </div>
                    
                    <div style="padding: 1rem; background: #e3f2fd; border-radius: 6px; text-align: center;">
                        <h4 style="color: #1565c0; margin: 0; font-size: 2rem;">
                            <?= $total_personnes ?>
                        </h4>
                        <p style="margin: 0; color: #1976d2;">Personnes (inclus accompagnants)</p>
                    </div>
                    
                    <?php 
                    $accompagnants_total = array_sum(array_map(fn($i) => $i['nb_accompagnants'], $confirmees));
                    if ($accompagnants_total > 0): 
                    ?>
                        <div style="margin-top: 1rem; padding: 1rem; background: #fff9c4; border-radius: 6px; border-left: 4px solid #fbc02d;">
                            <p style="margin: 0;"><strong><?= $accompagnants_total ?></strong> accompagnant(s)</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Liste des inscriptions -->
    <h3 class="mt-5 mb-3">📋 Inscriptions</h3>
    
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Statut</th>
                    <th>Accompagnants</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inscriptions as $ins): ?>
                    <tr class="<?= $ins['statut'] === 'annulée' ? 'table-danger' : 'table-success' ?>">
                        <td><strong><?= htmlspecialchars($ins['prenom'] . ' ' . $ins['nom']) ?></strong></td>
                        <td><?= htmlspecialchars($ins['email']) ?></td>
                        <td>
                            <span class="badge <?= $ins['statut'] === 'confirmée' ? 'bg-success' : 'bg-danger' ?>">
                                <?= $ins['statut'] ?>
                            </span>
                        </td>
                        <td><?= $ins['nb_accompagnants'] ?></td>
                        <td><?= $ins['notes'] ? htmlspecialchars(substr($ins['notes'], 0, 50)) . '...' : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 2rem;">
        <a href="evenements_admin.php" class="btn btn-secondary">Retour</a>
    </div>
</div>

<?php require 'footer.php'; ?>
