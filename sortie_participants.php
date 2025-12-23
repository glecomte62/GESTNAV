<?php
require 'header.php';
require_once __DIR__ . '/utils/waitlist.php';

$sortie_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sortie_id <= 0) {
    die("Sortie non spécifiée.");
}

// Récupérer la sortie
$stmt = $pdo->prepare("SELECT s.*, (SELECT sp.filename FROM sortie_photos sp WHERE sp.sortie_id = s.id ORDER BY sp.created_at DESC LIMIT 1) AS photo_filename FROM sorties s WHERE s.id = ?");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sortie) {
    die("Sortie introuvable.");
}

// Récupérer les inscriptions
$stmt_ins = $pdo->prepare("
    SELECT 
        si.id,
        si.user_id,
        u.prenom,
        u.nom
    FROM sortie_inscriptions si
    JOIN users u ON u.id = si.user_id
    WHERE si.sortie_id = ?
    ORDER BY u.nom, u.prenom
");
$stmt_ins->execute([$sortie_id]);
$inscriptions = $stmt_ins->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$total_inscrits = count($inscriptions);

// Récupérer les machines et leurs équipages (assignations)
$stmt_m = $pdo->prepare("\n    SELECT\n        sm.id AS sm_id,\n        m.id AS machine_id,\n        m.nom AS machine_nom,\n        m.immatriculation AS machine_immat,\n        sa.id AS assign_id,\n        sa.role_onboard,\n        u.prenom AS membre_prenom,\n        u.nom AS membre_nom,\n        u.id AS user_id\n    FROM sortie_machines sm\n    JOIN machines m ON m.id = sm.machine_id\n    LEFT JOIN sortie_assignations sa ON sa.sortie_machine_id = sm.id\n    LEFT JOIN users u ON u.id = sa.user_id\n    WHERE sm.sortie_id = ?\n    ORDER BY m.nom, m.immatriculation, sa.role_onboard, u.nom, u.prenom\n");
$stmt_m->execute([$sortie_id]);
$rows_m = $stmt_m->fetchAll(PDO::FETCH_ASSOC);

$machines_equipes = [];
$total_places_affectees = 0;
$affectes_user_ids = [];  // Pister les users affectés

foreach ($rows_m as $r) {
    $sm_id = (int)$r['sm_id'];
    if (!isset($machines_equipes[$sm_id])) {
        $machines_equipes[$sm_id] = [
            'machine_id' => (int)($r['machine_id'] ?? 0),
            'nom' => $r['machine_nom'],
            'immat' => $r['machine_immat'],
            'equipage' => [],
            'places_max' => 2  // Standard pour ULM: 2 places max
        ];
    }
    if (!empty($r['assign_id'])) {
        $user_id = (int)($r['user_id'] ?? 0);
        $machines_equipes[$sm_id]['equipage'][] = [
            'nom' => trim(($r['membre_prenom'] ?? '') . ' ' . ($r['membre_nom'] ?? '')),
            'role' => $r['role_onboard'] ?? '',
            'is_guest' => false
        ];
        if ($user_id > 0) {
            $affectes_user_ids[$user_id] = true;
        }
        $total_places_affectees++;
    }
}

// Ajouter les invités aux équipages
try {
    $stmt_guests = $pdo->prepare("
        SELECT 
            sm.id AS sm_id,
            g.guest_name
        FROM sortie_machines sm
        LEFT JOIN sortie_assignations_guests g ON g.sortie_machine_id = sm.id
        WHERE sm.sortie_id = ? AND g.guest_name IS NOT NULL
    ");
    $stmt_guests->execute([$sortie_id]);
    $guests = $stmt_guests->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($guests as $guest) {
        $sm_id = (int)$guest['sm_id'];
        if (isset($machines_equipes[$sm_id])) {
            $machines_equipes[$sm_id]['equipage'][] = [
                'nom' => trim($guest['guest_name']),
                'role' => 'invité',
                'is_guest' => true
            ];
            $total_places_affectees++;
        }
    }
} catch (Exception $e) {
    // Table n'existe peut-être pas encore
    error_log("Erreur récupération invités: " . $e->getMessage());
}

// Calcul du nombre de places disponibles
$total_places_disponibles = (count($machines_equipes) * 2) - $total_places_affectees;

// Participants affectés = inscrits qui ont une affectation
$participants_affectes = [];
foreach ($inscriptions as $ins) {
    $uid = (int)($ins['user_id'] ?? 0);
    if (isset($affectes_user_ids[$uid])) {
        $participants_affectes[] = $ins;
    }
}

// Liste d'attente = inscrits non affectés, ordre d'arrivée
$waitlist = gestnav_get_waitlist($pdo, $sortie_id);
?>

<style>
/* Cartes machines (aligné sur machines.php) */
.machine-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem; }
.machine-card { border:1px solid #e6ebf2; border-radius:1rem; overflow:hidden; background:#fff; box-shadow:0 6px 16px rgba(0,0,0,0.06); display:flex; flex-direction:column; }
.machine-card .machine-img { width:100%; aspect-ratio:16/9; object-fit:cover; background:#f2f6fc; }
.machine-card .machine-body { padding:0.9rem; display:flex; flex-direction:column; gap:0.4rem; }
.machine-name { font-weight:700; font-size:1rem; margin:0; }
.machine-meta { color:#555; font-size:0.9rem; }

 /* Header style aligné avec sortie_detail */
 .sortie-detail-header {
     display: flex;
     align-items: center;
     justify-content: space-between;
     gap: 1rem;
     margin: 0 0 1.25rem;
     padding: 1rem 1.25rem;
     border-radius: 1.25rem;
     background: linear-gradient(135deg, #004b8d, #00a0c6);
     color: #fff;
     box-shadow: 0 12px 30px rgba(0,0,0,0.25);
 }
 .sortie-detail-header h1 {
     font-size: 1.4rem;
     margin: 0;
     letter-spacing: 0.03em;
     text-transform: uppercase;
 }
 .sortie-detail-header p { margin: 0.25rem 0 0; opacity: 0.9; font-size: 0.95rem; }
 .btn-secondary-link {
     border: none; padding: 0.4rem 0.8rem; border-radius: 999px; background: transparent;
     font-size: 0.8rem; color: #fff; cursor: pointer; text-decoration: underline;
 }
</style>

<div class="container mt-4">
    <div class="sortie-detail-header">
        <div>
            <h1>Détail de la sortie</h1>
            <p><?= htmlspecialchars($sortie['titre']) ?></p>
        </div>
        <div>
            <button class="btn-secondary-link" onclick="window.location.href='sorties.php'">
                ← Retour aux sorties
            </button>
        </div>
    </div>
    <?php
    // Helper liens cliquables
    if (!function_exists('gn_linkify')) {
        function gn_linkify(string $text): string {
            $pattern = '~(https?://[^\s<]+)|(www\.[^\s<]+)~i';
            $result = '';
            $offset = 0;
            $len = strlen($text);
            while ($offset < $len && preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset)) {
                $url = $m[0][0];
                $pos = (int)$m[0][1];
                $before = substr($text, $offset, $pos - $offset);
                $result .= nl2br(htmlspecialchars($before, ENT_QUOTES, 'UTF-8'));
                $href = stripos($url, 'www.') === 0 ? ('http://' . $url) : $url;
                $result .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
                        . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
                        . '</a>';
                $offset = $pos + strlen($url);
            }
            if ($offset < $len) {
                $rest = substr($text, $offset);
                $result .= nl2br(htmlspecialchars($rest, ENT_QUOTES, 'UTF-8'));
            }
            return $result;
        }
    }
    ?>
    
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="gn-card mb-4">
                <div class="gn-card-header">
                    <h3 class="gn-card-title">Informations de la sortie</h3>
                </div>
                <div style="padding: 1.5rem;">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div style="width:100%; aspect-ratio:16/9; background:#f2f6fc; overflow:hidden; border-radius:0.75rem;">
                                <?php if (!empty($sortie['photo_filename'])): ?>
                                    <img src="uploads/sorties/<?= htmlspecialchars($sortie['photo_filename']) ?>" alt="Photo sortie" style="width:100%; height:100%; object-fit:cover; display:block;">
                                <?php else: ?>
                                    <img src="assets/img/Ulm.jpg" alt="Illustration ULM" style="width:100%; height:100%; object-fit:cover; display:block;">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <p><strong>Date :</strong>
                                <?php if (!empty($sortie['is_multi_day']) && !empty($sortie['date_fin'])): ?>
                                    <?= htmlspecialchars('Du ' . date('d/m/Y', strtotime($sortie['date_sortie'])) . ' au ' . date('d/m/Y', strtotime($sortie['date_fin']))) ?>
                                <?php else: ?>
                                    <?= date('d/m/Y à H:i', strtotime($sortie['date_sortie'])) ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($sortie['destination_oaci'])): ?>
                                <p><strong>Destination :</strong> <?= htmlspecialchars($sortie['destination_oaci']) ?></p>
                            <?php endif; ?>
                            <p><strong>Statut :</strong> <span class="badge <?= $sortie['statut'] === 'terminée' ? 'bg-success' : 'bg-info' ?>">
                                <?= htmlspecialchars(ucfirst($sortie['statut'])) ?>
                            </span></p>
                            <?php if (!empty($sortie['description'])): ?>
                                <p><strong>Description :</strong><br><?= nl2br(htmlspecialchars($sortie['description'])) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($sortie['repas_prevu'])): ?>
                                <p><span class="badge bg-success">🍽️ Repas prévu</span></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($sortie['details'])): ?>
                        <div style="margin-top:1rem;">
                            <strong>Briefing / détails :</strong><br>
                            <div style="margin-top:0.25rem; white-space:pre-wrap;">
                                <?= gn_linkify($sortie['details']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($sortie['repas_details'])): ?>
                        <div style="margin-top:1rem;">
                            <strong>Infos repas :</strong><br>
                            <div style="margin-top:0.25rem; white-space:pre-wrap;">
                                <?= gn_linkify($sortie['repas_details']) ?>
                            </div>
                        </div>
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
                    <div style="padding: 1rem; background: #e8f5e9; border-radius: 6px; text-align: center; margin-bottom: 1rem;">
                        <h4 style="color: #2e7d32; margin: 0; font-size: 2rem;">
                            <?= count($participants_affectes) ?>
                        </h4>
                        <p style="margin: 0; color: #558b2f;">Participants affectés</p>
                    </div>
                    <div style="padding: 1rem; background: #e3f2fd; border-radius: 6px; text-align: center; margin-bottom: 1rem;">
                        <h4 style="color: #1565c0; margin: 0; font-size: 1.5rem;">
                            <?= $total_places_affectees ?>
                        </h4>
                        <p style="margin: 0; color: #0d47a1; font-size: 0.9rem;">Places affectées</p>
                    </div>
                    <div style="padding: 1rem; background: <?= $total_places_disponibles > 0 ? '#fff3cd' : '#f8d7da' ?>; border-radius: 6px; text-align: center;">
                        <h4 style="color: <?= $total_places_disponibles > 0 ? '#856404' : '#721c24' ?>; margin: 0; font-size: 1.5rem;">
                            <?= $total_places_disponibles ?>
                        </h4>
                        <p style="margin: 0; color: <?= $total_places_disponibles > 0 ? '#856404' : '#721c24' ?>; font-size: 0.9rem;">
                            <?= $total_places_disponibles > 0 ? 'Places disponibles' : 'Aucune place disponible' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Liste des participants AFFECTÉS -->
    <div class="gn-card">
        <div class="gn-card-header">
            <h3 class="gn-card-title">👥 Participants affectés (<?= count($participants_affectes) ?>)</h3>
        </div>
        
        <?php if (empty($participants_affectes)): ?>
            <div style="padding: 1.5rem; color: #999;">
                <p>Aucun participant affecté pour le moment.</p>
            </div>
        <?php else: ?>
            <div style="padding: 0;">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants_affectes as $ins): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ins['prenom'] . ' ' . $ins['nom']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Machines et équipages -->
    <div class="gn-card mt-4">
        <div class="gn-card-header">
            <h3 class="gn-card-title">🛩️ Machines et équipages</h3>
        </div>
        <?php if (empty($machines_equipes)): ?>
            <div style="padding: 1.5rem; color: #999;">
                <p>Aucune machine renseignée pour cette sortie.</p>
            </div>
        <?php else: ?>
            <div style="padding: 1rem 1.5rem;">
                <div class="machine-grid">
                <?php if (!function_exists('gestnav_machine_photo_url')) {
                    function gestnav_machine_photo_url(int $id): string {
                        $relBase = 'uploads/machines';
                        $absBase = __DIR__ . '/uploads/machines';
                        foreach (['jpg','jpeg','png','webp'] as $ext) {
                            $abs = $absBase . '/machine_' . $id . '.' . $ext;
                            if (file_exists($abs)) {
                                return $relBase . '/machine_' . $id . '.' . $ext;
                            }
                        }
                        return 'assets/img/machine-placeholder.svg';
                    }
                }
                foreach ($machines_equipes as $sm_id => $info):
                    $photo = $info['machine_id'] ? gestnav_machine_photo_url((int)$info['machine_id']) : 'assets/img/machine-placeholder.svg';
                ?>
                    <div class="machine-card">
                        <img class="machine-img" src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($info['nom']) ?>" loading="lazy" width="640" height="360">
                        <div class="machine-body">
                            <div class="machine-name"><?= htmlspecialchars($info['nom']) ?></div>
                            <?php if (!empty($info['immat'])): ?>
                                <div class="machine-meta"><?= htmlspecialchars($info['immat']) ?></div>
                            <?php endif; ?>
                        
                            <?php 
                                $places_occupees = count($info['equipage']);
                                $places_disponibles = $info['places_max'] - $places_occupees;
                                $places_color = $places_disponibles > 0 ? '#2e7d32' : '#c62828';
                            ?>
                            <div style="padding: 0.5rem; background: #f5f5f5; border-radius: 4px; margin-bottom: 0.5rem; font-size: 0.85rem;">
                                <strong style="color: #333;">Places :</strong> 
                                <span style="color: <?= $places_color ?>;"><?= $places_occupees ?>/<?= $info['places_max'] ?></span>
                                <?php if ($places_disponibles > 0): ?>
                                    <span style="color: #2e7d32; font-weight: 600;">(<?= $places_disponibles ?> libre<?= $places_disponibles > 1 ? 's' : '' ?>)</span>
                                <?php else: ?>
                                    <span style="color: #c62828; font-weight: 600;">⚠️ Complet</span>
                                <?php endif; ?>
                            </div>
                        
                        <?php if (empty($info['equipage'])): ?>
                            <div style="color:#999; font-size: 0.9rem;">Aucun équipage assigné.</div>
                        <?php else: ?>
                            <ul style="list-style: none; padding-left: 0; margin: 0; display: flex; flex-direction: column; gap: 0.35rem;">
                                <?php foreach ($info['equipage'] as $m): ?>
                                    <li>
                                        <span style="font-weight: 600;"><?= htmlspecialchars($m['nom']) ?></span>
                                        <?php if (!empty($m['role'])): ?>
                                            <span class="badge bg-secondary" style="margin-left: .35rem;"><?= htmlspecialchars($m['role']) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Liste d'attente (ordre d'arrivée) -->
    <div class="gn-card mt-4">
        <div class="gn-card-header">
            <h3 class="gn-card-title">⏳ Liste d'attente</h3>
        </div>
        <?php if (empty($waitlist)): ?>
            <div style="padding: 1.5rem; color: #999;">
                <p>Aucune personne en attente pour le moment.</p>
            </div>
        <?php else: ?>
            <div style="padding: 1rem 1.5rem;">
                <div style="padding: 0.75rem; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; margin-bottom: 1rem;">
                    <p style="margin: 0; color: #856404; font-size: 0.9rem;">
                        <strong>ℹ️ Information :</strong> Les personnes en liste d'attente rejoindront les équipages dès qu'une place se libérera 
                        (<?= $total_places_disponibles ?> <?= $total_places_disponibles > 1 ? 'places' : 'place' ?> actuellement disponible<?= $total_places_disponibles > 1 ? 's' : '' ?>).
                    </p>
                </div>
            </div>
            <div style="padding: 0;">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:90px;">#</th>
                            <th>Nom</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank=1; foreach ($waitlist as $w): ?>
                            <tr>
                                <td><?= $rank++; ?></td>
                                <td><strong><?= htmlspecialchars(($w['prenom'] ?? '') . ' ' . ($w['nom'] ?? '')) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
