<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    header('Location: membres.php');
    exit;
}

// Récupérer les infos du membre
$stmt = $pdo->prepare("SELECT id, prenom, nom, email, telephone, qualification, photo_path FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$membre = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$membre) {
    header('Location: membres.php');
    exit;
}

// Photo du membre
$photoPath = '/assets/img/avatar-placeholder.svg';
if (!empty($membre['photo_path']) && file_exists(__DIR__ . '/' . $membre['photo_path'])) {
    $photoPath = $membre['photo_path'];
} else {
    $uploadsDir = __DIR__ . '/uploads/members';
    foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
        $fs = $uploadsDir . '/member_' . $user_id . '.' . $ext;
        if (file_exists($fs)) {
            $photoPath = '/uploads/members/member_' . $user_id . '.' . $ext;
            break;
        }
    }
}

// Récupérer toutes les inscriptions du membre
$stmtInscr = $pdo->prepare("
    SELECT 
        s.id AS sortie_id,
        s.titre,
        s.date_sortie,
        s.destination_oaci,
        si.created_at AS date_inscription
    FROM sortie_inscriptions si
    JOIN sorties s ON s.id = si.sortie_id
    WHERE si.user_id = ?
    ORDER BY s.date_sortie DESC
");
$stmtInscr->execute([$user_id]);
$inscriptions = $stmtInscr->fetchAll(PDO::FETCH_ASSOC);

// Pour chaque inscription, vérifier si le membre est affecté
$stats = [
    'total_inscriptions' => count($inscriptions),
    'total_affectations' => 0,
    'sorties' => []
];

foreach ($inscriptions as $insc) {
    $sortie_id = (int)$insc['sortie_id'];
    
    // Vérifier si le membre est affecté à une machine pour cette sortie
    $stmtAffect = $pdo->prepare("
        SELECT sa.id, sa.role_onboard, m.nom AS machine_nom, m.immatriculation
        FROM sortie_assignations sa
        JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id
        JOIN machines m ON m.id = sm.machine_id
        WHERE sm.sortie_id = ? AND sa.user_id = ?
    ");
    $stmtAffect->execute([$sortie_id, $user_id]);
    $affectation = $stmtAffect->fetch(PDO::FETCH_ASSOC);
    
    $is_affecte = !empty($affectation);
    if ($is_affecte) {
        $stats['total_affectations']++;
    }
    
    $stats['sorties'][] = [
        'sortie_id' => $sortie_id,
        'titre' => $insc['titre'],
        'date_sortie' => $insc['date_sortie'],
        'destination_oaci' => $insc['destination_oaci'],
        'date_inscription' => $insc['date_inscription'],
        'is_affecte' => $is_affecte,
        'affectation' => $affectation
    ];
}

// Calculer le taux d'affectation
$taux_affectation = $stats['total_inscriptions'] > 0 
    ? round(($stats['total_affectations'] / $stats['total_inscriptions']) * 100) 
    : 0;

$fullName = trim(($membre['prenom'] ?? '') . ' ' . ($membre['nom'] ?? ''));
require 'header.php';
?>

<style>
.stat-card {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    text-align: center;
}
.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: #1a56db;
}
.stat-label {
    font-size: 0.9rem;
    color: #6b7280;
    margin-top: 0.5rem;
}
.taux-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 999px;
    font-weight: 600;
    font-size: 1.2rem;
}
.taux-high {
    background: #fef3c7;
    color: #92400e;
}
.taux-medium {
    background: #dbeafe;
    color: #1e40af;
}
.taux-low {
    background: #fee2e2;
    color: #991b1b;
}
.sortie-row {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    gap: 1rem;
}
.sortie-row:hover {
    background: #f9fafb;
}
.status-icon {
    font-size: 1.5rem;
    min-width: 40px;
    text-align: center;
}
.sortie-info {
    flex: 1;
}
.sortie-title {
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 0.25rem;
}
.sortie-details {
    font-size: 0.85rem;
    color: #6b7280;
}
.affectation-badge {
    background: #10b981;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 500;
}
.waitlist-badge {
    background: #f59e0b;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 500;
}
</style>

<div class="gn-wrapper">
    <!-- En-tête avec photo et infos membre -->
    <div class="gn-card" style="margin-bottom: 2rem;">
        <div class="gn-card-header">
            <h2 class="gn-card-title">📊 Statistiques de participation</h2>
        </div>
        <div class="gn-card-body">
            <div style="display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
                <img src="<?= $photoPath ?>" alt="<?= htmlspecialchars($fullName) ?>" 
                     style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #e5e7eb;">
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 0.5rem 0; font-size: 1.5rem;"><?= htmlspecialchars($fullName) ?></h3>
                    <?php if (!empty($membre['qualification'])): ?>
                        <div style="color: #6b7280; margin-bottom: 0.25rem;">
                            <strong>Qualification:</strong> <?= htmlspecialchars($membre['qualification']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($membre['email'])): ?>
                        <div style="color: #6b7280; margin-bottom: 0.25rem;">
                            <strong>Email:</strong> <?= htmlspecialchars($membre['email']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($membre['telephone'])): ?>
                        <div style="color: #6b7280;">
                            <strong>Téléphone:</strong> <?= htmlspecialchars($membre['telephone']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-value"><?= $stats['total_inscriptions'] ?></div>
            <div class="stat-label">Inscriptions totales</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #10b981;"><?= $stats['total_affectations'] ?></div>
            <div class="stat-label">Sorties affectées</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #f59e0b;"><?= $stats['total_inscriptions'] - $stats['total_affectations'] ?></div>
            <div class="stat-label">En attente</div>
        </div>
        <div class="stat-card">
            <div class="taux-badge <?= $taux_affectation >= 70 ? 'taux-high' : ($taux_affectation >= 40 ? 'taux-medium' : 'taux-low') ?>">
                <?= $taux_affectation ?>%
            </div>
            <div class="stat-label">Taux d'affectation</div>
        </div>
    </div>

    <!-- Liste des sorties -->
    <div class="gn-card">
        <div class="gn-card-header">
            <h3 class="gn-card-title">📅 Historique des inscriptions</h3>
        </div>
        <div>
            <?php if (empty($stats['sorties'])): ?>
                <div style="padding: 2rem; text-align: center; color: #6b7280;">
                    Aucune inscription trouvée pour ce membre.
                </div>
            <?php else: ?>
                <?php foreach ($stats['sorties'] as $sortie): ?>
                    <div class="sortie-row">
                        <div class="status-icon">
                            <?= $sortie['is_affecte'] ? '✅' : '⏳' ?>
                        </div>
                        <div class="sortie-info">
                            <div class="sortie-title">
                                <a href="sortie_info.php?id=<?= $sortie['sortie_id'] ?>" style="text-decoration: none; color: inherit;">
                                    <?= htmlspecialchars($sortie['titre']) ?>
                                </a>
                            </div>
                            <div class="sortie-details">
                                📅 <?= date('d/m/Y', strtotime($sortie['date_sortie'])) ?>
                                <?php if ($sortie['destination_oaci']): ?>
                                    • 🎯 <?= htmlspecialchars($sortie['destination_oaci']) ?>
                                <?php endif; ?>
                                <?php if ($sortie['is_affecte'] && !empty($sortie['affectation'])): ?>
                                    • ✈️ <?= htmlspecialchars($sortie['affectation']['machine_nom']) ?>
                                    (<?= htmlspecialchars($sortie['affectation']['immatriculation']) ?>)
                                    • 👤 <?= htmlspecialchars($sortie['affectation']['role_onboard']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <?php if ($sortie['is_affecte']): ?>
                                <span class="affectation-badge">Affecté</span>
                            <?php else: ?>
                                <span class="waitlist-badge">Liste d'attente</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div style="margin-top: 2rem; text-align: center;">
        <a href="membres.php" class="btn btn-secondary">← Retour aux membres</a>
    </div>
</div>

<?php require 'footer.php'; ?>
