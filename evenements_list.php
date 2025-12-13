<?php
require 'header.php';
require_login();

// Récupérer tous les événements futurs/en cours (avec date >= aujourd'hui)
$stmt = $pdo->prepare("
    SELECT e.*, COUNT(DISTINCT ei.id) as nb_inscrits, 
           SUM(CASE WHEN ei.statut = 'confirmée' THEN 1 ELSE 0 END) as confirmees,
           SUM(CASE WHEN ei.statut = 'confirmée' THEN ei.nb_accompagnants ELSE 0 END) as total_accompagnants
    FROM evenements e
    LEFT JOIN evenement_inscriptions ei ON ei.evenement_id = e.id
    WHERE e.statut IN ('prévu', 'en_cours')
      AND e.date_evenement >= CURDATE()
    GROUP BY e.id
    ORDER BY e.date_evenement ASC
");
$stmt->execute();
$evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les événements passés (date < aujourd'hui OU statut terminé/annulé)
$stmt_past = $pdo->prepare("
    SELECT e.*, COUNT(DISTINCT ei.id) as nb_inscrits,
           SUM(CASE WHEN ei.statut = 'confirmée' THEN 1 ELSE 0 END) as confirmees
    FROM evenements e
    LEFT JOIN evenement_inscriptions ei ON ei.evenement_id = e.id
    WHERE e.statut NOT IN ('prévu', 'en_cours')
       OR e.date_evenement < CURDATE()
    GROUP BY e.id
    ORDER BY e.date_evenement DESC
");
$stmt_past->execute();
$evenements_past = $stmt_past->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="m-0">📅 Événements du Club</h1>
        <?php if (is_admin()): ?>
            <a href="evenements_admin.php" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Nouvel événement
            </a>
        <?php endif; ?>
    </div>
    
    <?php if (empty($evenements)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Aucun événement prévu pour le moment.
        </div>
    <?php else: ?>
        <h3 class="mb-3">Événements à venir</h3>
        <div class="row mb-5">
            <?php foreach ($evenements as $evt): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="gn-card" style="height: 100%; display: flex; flex-direction: column;">
                        <div class="gn-card-header">
                            <h5 class="gn-card-title">
                                <?= htmlspecialchars($evt['titre']) ?>
                                <span class="badge bg-primary" style="font-size: 0.7rem;">
                                    <?= $evt['type'] ?>
                                </span>
                            </h5>
                        </div>
                        <?php if (!empty($evt['cover_filename'])): ?>
                            <div style="width:100%;height:160px;overflow:hidden;border-bottom:1px solid #eee;">
                                <img src="uploads/events/<?= htmlspecialchars($evt['cover_filename']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
                            </div>
                        <?php endif; ?>
                        <div style="padding: 1.5rem; flex-grow: 1;">
                            <p><i class="bi bi-calendar3"></i> <strong><?= date('d/m/Y', strtotime($evt['date_evenement'])) ?> à <?= date('H:i', strtotime($evt['date_evenement'])) ?></strong></p>
                            <p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($evt['lieu']) ?></p>
                            
                            <?php if ($evt['description']): ?>
                                <p style="font-size: 0.9rem; color: #666; margin-top: 1rem;">
                                    <?= htmlspecialchars(substr($evt['description'], 0, 100)) ?>
                                    <?php if (strlen($evt['description']) > 100): ?>
                                        ...
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            
                            <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid #ddd;">
                                <small style="color: #888;">
                                    📍 <?= ($evt['confirmees'] ?? 0) ?> inscription(s)
                                    <?php if (($evt['total_accompagnants'] ?? 0) > 0): ?>
                                        + <?= $evt['total_accompagnants'] ?> accompagnant(s)
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <div style="padding: 1rem; border-top: 1px solid #eee;">
                            <a href="evenement_inscription_detail.php?id=<?= $evt['id'] ?>" class="btn btn-sm btn-primary w-100">
                                Voir les détails
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Événements passés -->
    <?php if (!empty($evenements_past)): ?>
        <hr class="my-5">
        <h3 class="mb-3">Événements passés</h3>
        <div class="row">
            <?php foreach ($evenements_past as $evt): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="gn-card" style="opacity: 0.7; height: 100%; display: flex; flex-direction: column;">
                        <div class="gn-card-header">
                            <h5 class="gn-card-title">
                                <?= htmlspecialchars($evt['titre']) ?>
                                <span class="badge bg-secondary" style="font-size: 0.7rem;">
                                    <?= $evt['type'] ?>
                                </span>
                            </h5>
                        </div>
                        <?php if (!empty($evt['cover_filename'])): ?>
                            <div style="width:100%;height:140px;overflow:hidden;border-bottom:1px solid #eee;">
                                <img src="uploads/events/<?= htmlspecialchars($evt['cover_filename']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
                            </div>
                        <?php endif; ?>
                        <div style="padding: 1.5rem; flex-grow: 1;">
                            <p><i class="bi bi-calendar3"></i> <strong><?= date('d/m/Y', strtotime($evt['date_evenement'])) ?></strong></p>
                            <p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($evt['lieu']) ?></p>
                            
                            <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid #ddd;">
                                <small style="color: #888;">
                                    <span class="badge bg-light text-dark"><?= $evt['statut'] ?></span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
