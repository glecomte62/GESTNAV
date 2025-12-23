<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

// Helpers
if (!function_exists('gn_linkify')) {
    function gn_linkify(string $text): string {
        $pattern = '~(https?://[^\s<]+)|(www\.[^\s<]+)~i';
        $result = '';
        $offset = 0; $len = strlen($text);
        while ($offset < $len && preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $url = $m[0][0]; $pos = (int)$m[0][1];
            $before = substr($text, $offset, $pos - $offset);
            $result .= nl2br(htmlspecialchars($before, ENT_QUOTES, 'UTF-8'));
            $href = stripos($url, 'www.') === 0 ? ('http://' . $url) : $url;
            $result .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
                    . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a>';
            $offset = $pos + strlen($url);
        }
        if ($offset < $len) { $result .= nl2br(htmlspecialchars(substr($text, $offset), ENT_QUOTES, 'UTF-8')); }
        return $result;
    }
}
if (!function_exists('gn_get_oaci_coords')) {
    function gn_get_oaci_coords(PDO $pdo, string $oaci): ?array {
        foreach (['aerodromes_fr','aerodromes'] as $t) {
            try {
                $st = $pdo->prepare("SELECT * FROM {$t} WHERE oaci = ? LIMIT 1");
                $st->execute([$oaci]); $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    foreach (['lat','latitude','lat_deg'] as $latKey) foreach (['lon','longitude','lng','lon_deg'] as $lonKey) {
                        if (isset($row[$latKey], $row[$lonKey])) {
                            $lat = (float)$row[$latKey]; $lon = (float)$row[$lonKey];
                            if ($lat !== 0.0 || $lon !== 0.0) return ['lat'=>$lat,'lon'=>$lon];
                        }
                    }
                }
            } catch (Throwable $e) {}
        }
        return null;
    }
}
if (!function_exists('gn_get_ulm_base_coords')) {
    function gn_get_ulm_base_coords(PDO $pdo, int $ulm_base_id): ?array {
        try {
            $stmt = $pdo->prepare("SELECT lat, lon, nom FROM ulm_bases_fr WHERE id = ? LIMIT 1");
            $stmt->execute([$ulm_base_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['lat']) && isset($row['lon'])) {
                $lat = (float)$row['lat'];
                $lon = (float)$row['lon'];
                if ($lat !== 0.0 || $lon !== 0.0) {
                    return ['lat' => $lat, 'lon' => $lon];
                }
            }
        } catch (Throwable $e) {}
        return null;
    }
}

$pdo = $pdo ?? null; // from config.php
if (!$pdo instanceof PDO) { die('Connexion base indisponible.'); }
$sortie_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['sortie_id']) ? (int)$_GET['sortie_id'] : 0);
if ($sortie_id <= 0) { die('Sortie non spécifiée.'); }

$st = $pdo->prepare("SELECT s.* FROM sorties s WHERE s.id = ?");
$st->execute([$sortie_id]);
$sortie = $st->fetch(PDO::FETCH_ASSOC);
if (!$sortie) { die('Sortie introuvable.'); }

// Destination (optionnelle) : détection colonne + récupération
$destination_label = '';
$hasDestinationId = false;
$hasUlmBaseId = false;
$destination_oaci = '';
$destination_icon = '';
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM sorties LIKE 'destination_id'");
    if ($colCheck && $colCheck->fetch()) {
        $hasDestinationId = true;
    }
} catch (Throwable $e) {
    $hasDestinationId = false;
}
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM sorties LIKE 'ulm_base_id'");
    if ($colCheck && $colCheck->fetch()) {
        $hasUlmBaseId = true;
    }
} catch (Throwable $e) {
    $hasUlmBaseId = false;
}

// Priorité : base ULM si présente, sinon aérodrome
if ($hasUlmBaseId && !empty($sortie['ulm_base_id'])) {
    $ubid = (int)$sortie['ulm_base_id'];
    try {
        $stmtUlm = $pdo->prepare("SELECT oaci, nom, ville FROM ulm_bases_fr WHERE id = ? LIMIT 1");
        $stmtUlm->execute([$ubid]);
        $rowUlm = $stmtUlm->fetch(PDO::FETCH_ASSOC);
        if ($rowUlm) {
            $destination_oaci = (string)($rowUlm['oaci'] ?? '');
            $destination_label = '🪂 ' . trim($destination_oaci . ' – ' . ($rowUlm['nom'] ?? '') . ' (' . ($rowUlm['ville'] ?? '') . ')');
            $destination_icon = '🪂';
        }
    } catch (Throwable $e) {}
}
if ($hasDestinationId && !empty($sortie['destination_id']) && empty($destination_label)) {
    $did = (int)$sortie['destination_id'];
    $rowDest = null;
    try {
        // Tenter d'abord aerodromes_fr (schéma local), puis aerodromes (héritage/ancien)
        foreach (['aerodromes_fr','aerodromes'] as $tbl) {
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM $tbl")->fetchAll(PDO::FETCH_COLUMN, 0);
                $colNom = in_array('nom', $cols, true) ? 'nom' : (in_array('name', $cols, true) ? 'name' : 'nom');
                $stmtDest = $pdo->prepare("SELECT oaci, $colNom AS nom FROM $tbl WHERE id = ? LIMIT 1");
                $stmtDest->execute([$did]);
                $tmp = $stmtDest->fetch(PDO::FETCH_ASSOC);
                if ($tmp) { $rowDest = $tmp; break; }
            } catch (Throwable $e2) { /* essayer table suivante */ }
        }
        if ($rowDest) {
            $destination_oaci = (string)($rowDest['oaci'] ?? '');
            $destination_label = '🛩️ ' . trim($destination_oaci . ' – ' . ($rowDest['nom'] ?? ''));
            $destination_icon = '🛩️';
        }
    } catch (Throwable $e) { $destination_label = ''; }
}

// Si pas d'ID ou pas d'OACI via ID, tenter la colonne sorties.destination_oaci
if ($destination_oaci === '' && !empty($sortie['destination_oaci'])) {
    $destination_oaci = (string)$sortie['destination_oaci'];
    if ($destination_label === '') {
        $destination_label = $destination_oaci;
    }
}
$coords_to = null;
if ($hasUlmBaseId && !empty($sortie['ulm_base_id'])) {
    $coords_to = gn_get_ulm_base_coords($pdo, (int)$sortie['ulm_base_id']);
} elseif (!empty($destination_oaci)) {
    $coords_to = gn_get_oaci_coords($pdo, $destination_oaci);
}

// Machines + équipages
$assignationsBySm = [];
try {
    $sql = "SELECT sa.sortie_machine_id AS sm_id, sa.user_id, COALESCE(u.prenom, '?') AS prenom, COALESCE(u.nom, 'Confirmé') AS nom, sa.role_onboard FROM sortie_assignations sa LEFT JOIN users u ON u.id = sa.user_id JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id WHERE sm.sortie_id = ? ORDER BY sa.sortie_machine_id, u.nom, u.prenom";
    $stA = $pdo->prepare($sql); 
    $stA->execute([$sortie_id]);
    $allAssign = $stA->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allAssign as $row) {
        $sid = (int)$row['sm_id'];
        if (!isset($assignationsBySm[$sid])) $assignationsBySm[$sid] = [];
        $assignationsBySm[$sid][] = ['user_id'=>$row['user_id']??null, 'prenom'=>$row['prenom']??'', 'nom'=>$row['nom']??'', 'role'=>$row['role_onboard']??'', 'is_guest'=>false];
    }
} catch (Throwable $e) {
    // Log mais continue
}

// Ajouter les invités
try {
    $sqlGuests = "SELECT g.sortie_machine_id AS sm_id, g.guest_name FROM sortie_assignations_guests g JOIN sortie_machines sm ON sm.id = g.sortie_machine_id WHERE sm.sortie_id = ? ORDER BY g.sortie_machine_id";
    $stG = $pdo->prepare($sqlGuests);
    $stG->execute([$sortie_id]);
    $allGuests = $stG->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allGuests as $row) {
        $sid = (int)$row['sm_id'];
        if (!isset($assignationsBySm[$sid])) $assignationsBySm[$sid] = [];
        $assignationsBySm[$sid][] = ['prenom'=>'', 'nom'=>$row['guest_name']??'Invité', 'role'=>'invité', 'is_guest'=>true];
    }
} catch (Throwable $e) {
    // Table n'existe peut-être pas encore
}

$machRows = [];
try {
    // Récupérer les sortie_machines avec leurs données
    $stmtSm = $pdo->prepare("SELECT sm.id AS sortie_machine_id, m.id AS machine_id, m.nom, m.immatriculation FROM sortie_machines sm JOIN machines m ON m.id = sm.machine_id WHERE sm.sortie_id = ? ORDER BY m.nom");
    $stmtSm->execute([$sortie_id]);
    $machRows = $stmtSm->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Récupérer les inscrits à la sortie
$inscrits = [];
$photoCache = [];
try {
    $stInscrits = $pdo->prepare("SELECT u.id, u.prenom, u.nom, u.photo_path FROM sortie_inscriptions si JOIN users u ON u.id = si.user_id WHERE si.sortie_id = ? ORDER BY u.nom, u.prenom");
    $stInscrits->execute([$sortie_id]);
    $inscrits = $stInscrits->fetchAll(PDO::FETCH_ASSOC);
    
    // Pré-calculer les chemins des photos
    $uploadsDir = __DIR__ . '/uploads/members';
    foreach ($inscrits as $inscrit) {
        $userId = (int)$inscrit['id'];
        $photoPath = $inscrit['photo_path'] ?? null;
        
        if (!empty($photoPath) && file_exists(__DIR__ . '/' . $photoPath)) {
            $photoCache[$userId] = $photoPath;
        } else {
            $found = false;
            foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
                $fs = $uploadsDir . '/member_' . $userId . '.' . $ext;
                if (file_exists($fs)) {
                    $photoCache[$userId] = '/uploads/members/member_' . $userId . '.' . $ext;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $photoCache[$userId] = '/assets/img/avatar-placeholder.svg';
            }
        }
    }
} catch (Throwable $e) {}

// Extraire les user_ids des participants affectés
$affectes_user_ids = [];
foreach ($assignationsBySm as $sm_id => $equipage) {
    foreach ($equipage as $member) {
        if (!empty($member['user_id']) && is_numeric($member['user_id'])) {
            $affectes_user_ids[(int)$member['user_id']] = true;
        }
    }
}

// Séparer les participants affectés de la liste d'attente
$participants_affectes = [];
$participants_waitlist = [];
foreach ($inscrits as $inscrit) {
    if (isset($affectes_user_ids[(int)$inscrit['id']])) {
        $participants_affectes[] = $inscrit;
    } else {
        $participants_waitlist[] = $inscrit;
    }
}

// Calcul distance/ETA si possible (avant header pour utilisation dans header de page)
$club_oaci = defined('GESTNAV_CLUB_OACI') ? GESTNAV_CLUB_OACI : 'LFQJ';
$speed_kmh = (float)(defined('GESTNAV_DEFAULT_SPEED_KMH') ? GESTNAV_DEFAULT_SPEED_KMH : 160);
$coords_from = $coords_to_calc = null;
$distance_km = null; $eta_text = '';
if (!empty($destination_oaci)) {
    try {
        $coords_from = gn_get_oaci_coords($pdo, $club_oaci);
        $coords_to_calc = gn_get_oaci_coords($pdo, $destination_oaci);
        if ($coords_from && $coords_to_calc && isset($coords_from['lat'],$coords_from['lon'],$coords_to_calc['lat'],$coords_to_calc['lon'])) {
            $R = 6371.0;
            $toRad = function($deg){ return $deg * M_PI/180.0; };
            $dLat = $toRad($coords_to_calc['lat'] - $coords_from['lat']);
            $dLon = $toRad($coords_to_calc['lon'] - $coords_from['lon']);
            $lat1 = $toRad($coords_from['lat']);
            $lat2 = $toRad($coords_to_calc['lat']);
            $a = sin($dLat/2)**2 + cos($lat1)*cos($lat2)*sin($dLon/2)**2;
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $distance_km = max(1, (int)round($R * $c));
            if ($speed_kmh > 0 && $distance_km > 0) {
                $hours = $distance_km / $speed_kmh;
                $totalMinutes = (int)round($hours * 60);
                $h = intdiv($totalMinutes, 60);
                $m = $totalMinutes % 60;
                $eta_text = sprintf('%dh%02d (à %d km/h)', $h, $m, (int)$speed_kmh);
            }
        }
    } catch (Throwable $e) {}
}

include 'header.php';
?>

<div class="gn-wrapper">
    <div class="gn-page-header">
        <div>
            <div class="gn-page-title">
                <i class="bi bi-airplane-fill"></i>
                <?= htmlspecialchars($sortie['titre'] ?? 'Sortie') ?>
            </div>
            <div class="gn-page-subtitle">
                Club <?= htmlspecialchars(constant('GESTNAV_CLUB_OACI')) ?><?php if (!empty($destination_oaci)): ?> • Destination <?= htmlspecialchars($destination_oaci) ?><?php endif; ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (!empty($distance_km) || !empty($eta_text)): ?>
                <div class="d-flex align-items-center gap-2 me-2">
                    <?php if (!empty($distance_km)): ?>
                        <span class="gn-badge-pill gn-badge-km"><?= (int)$distance_km ?> km</span>
                        <span class="gn-badge-pill"><?= (int)round($distance_km/1.852) ?> NM</span>
                    <?php endif; ?>
                    <?php if (!empty($eta_text)): ?>
                        <span class="gn-badge-pill gn-badge-eta"><?= htmlspecialchars($eta_text) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($destination_oaci)): ?>
                <?php if ($destination_icon === '🪂'): ?>
                    <a class="btn btn-outline-success btn-sm" href="https://basulm.ffplum.fr/PDF/<?= htmlspecialchars($destination_oaci) ?>.pdf" target="_blank" rel="noopener">📄 Télécharger la fiche BaseULM</a>
                <?php else: ?>
                    <a class="btn btn-outline-success btn-sm" href="https://www.sia.aviation-civile.gouv.fr/media/dvd/eAIP_27_NOV_2025/Atlas-VAC/PDF_AIPparSSection/VAC/AD/AD-2.<?= htmlspecialchars($destination_oaci) ?>.pdf" target="_blank" rel="noopener">📄 Télécharger la carte VAC</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="gn-card">
                <div class="gn-card-header"><h3 class="gn-card-title">📋 Informations pratiques</h3></div>
                <div style="display:flex; flex-wrap:wrap; gap:1.5rem;">
                    <?php if (!empty($sortie['date_sortie'])): ?>
                        <div>
                            <div style="font-size:0.85rem; color:#6b7280; margin-bottom:0.25rem;">📅 Date</div>
                            <div style="font-weight:500;"><?= htmlspecialchars(date('d/m/Y', strtotime($sortie['date_sortie']))) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($sortie['date_sortie'])): ?>
                        <div>
                            <div style="font-size:0.85rem; color:#6b7280; margin-bottom:0.25rem;">🕐 Heure</div>
                            <div style="font-weight:500;"><?= htmlspecialchars(date('H:i', strtotime($sortie['date_sortie']))) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($destination_label)): ?>
                        <div>
                            <div style="font-size:0.85rem; color:#6b7280; margin-bottom:0.25rem;">📍 Destination</div>
                            <div style="font-weight:500;"><?= htmlspecialchars($destination_label) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($sortie['statut'])): ?>
                        <div>
                            <div style="font-size:0.85rem; color:#6b7280; margin-bottom:0.25rem;">🔴 Statut</div>
                            <div style="font-weight:500;">
                                <?php $stat = htmlspecialchars($sortie['statut']); $badgeColor = ($stat === 'prévue') ? '#10b981' : (($stat === 'en étude') ? '#f59e0b' : (($stat === 'terminée') ? '#6b7280' : '#ef4444')); ?>
                                <span style="background:<?= $badgeColor ?>; color:white; padding:0.25rem 0.75rem; border-radius:999px; font-size:0.85rem;"><?= $stat ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($sortie['date_fin']) && !empty($sortie['is_multi_day'])): ?>
                        <div>
                            <div style="font-size:0.85rem; color:#6b7280; margin-bottom:0.25rem;">📆 Fin</div>
                            <div style="font-weight:500;"><?= htmlspecialchars(date('d/m/Y', strtotime($sortie['date_fin']))) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($sortie['repas_prevu'])): ?>
                        <div>
                            <div style="font-size:0.85rem; color:#6b7280; margin-bottom:0.25rem;">🍽️ Repas</div>
                            <div style="font-weight:500; color:#10b981;">Prévu</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="gn-card">
                <div class="gn-card-header"><h3 class="gn-card-title">Infos</h3></div>
                <div>
                    <?php if (!empty($sortie['details'])): ?>
                        <div class="mb-3"><strong>Briefing</strong><div><?= gn_linkify($sortie['details']) ?></div></div>
                    <?php endif; ?>
                    <?php if (!empty($sortie['repas_details'])): ?>
                        <div class="mb-3"><strong>Repas</strong><div><?= gn_linkify($sortie['repas_details']) ?></div></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="gn-card">
                <div class="gn-card-header"><h3 class="gn-card-title">Carte</h3>
                    <div class="gn-card-subtitle">
                        <?php if (!empty($distance_km)): ?>
                            <span class="gn-badge-pill gn-badge-km"><?= (int)$distance_km ?> km</span>
                            <span class="gn-badge-pill"><?= (int)round($distance_km/1.852) ?> NM</span>
                        <?php endif; ?>
                        <?php if (!empty($eta_text)): ?>
                            <span class="gn-badge-pill gn-badge-eta"><?= htmlspecialchars($eta_text) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="map-wrapper">
                    <div id="map"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="gn-card">
                <div class="gn-card-header"><h3 class="gn-card-title">Machines & équipages</h3></div>
                <div>
                    <?php if (!empty($machRows)): ?>
                        <div class="machines-grid">
                            <?php foreach ($machRows as $mr): ?>
                                <?php
                                    $smId = (int)$mr['sortie_machine_id'];
                                    $machineId = (int)$mr['machine_id'];
                                    $machineName = htmlspecialchars($mr['nom'] ?? '');
                                    $photoPath = '/assets/img/machine-placeholder.svg';
                                    if (!empty($machineId)) {
                                        $fsBase = __DIR__ . '/uploads/machines';
                                        foreach (['webp','jpg','jpeg','png'] as $ext) {
                                            $fs = $fsBase . '/machine_' . $machineId . '.' . $ext;
                                            if (@file_exists($fs)) { $photoPath = '/uploads/machines/' . 'machine_' . $machineId . '.' . $ext; break; }
                                        }
                                    }
                                    $list = $assignationsBySm[$smId] ?? [];
                                ?>
                                <div>
                                    <div class="machine-card" id="aff-sm-<?= $smId ?>">
                                        <img src="<?= $photoPath ?>" alt="<?= $machineName ?>" class="machine-photo">
                                        <div class="p-3">
                                            <div class="d-flex justify-content-between align-items-baseline">
                                                <div class="fw-semibold" style="font-size:1rem;"><?= $machineName ?></div>
                                                <?php if (!empty($mr['immatriculation'])): ?>
                                                    <div class="immatriculation-badge"><?= htmlspecialchars($mr['immatriculation']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($list): ?>
                                                <div class="roles-badges mt-2">
                                                    <?php foreach ($list as $p): ?>
                                                        <?php 
                                                            $role = strtolower($p['role'] ?? ''); 
                                                            $roleDisplay = ($role === 'pilote') ? 'pilote' : (($role === 'copilote') ? 'copilote' : (($role === 'invité') ? 'invité' : 'CDB ou COPI'));
                                                        ?>
                                                        <div style="display:flex; gap:0.5rem; align-items:center; margin-bottom:0.5rem; flex-wrap:wrap;">
                                                            <span class="<?= ($role === 'pilote') ? 'role-badge role-pilote' : (($role === 'copilote') ? 'role-badge role-copilote' : 'role-badge role-valider') ?>"><?= htmlspecialchars(trim(($p['prenom'] ?? '') . ' ' . ($p['nom'] ?? ''))) ?></span>
                                                            <span class="role-badge" style="background:#6b7280; color:white; font-size:0.75rem; padding:0.25rem 0.5rem;"><?= htmlspecialchars($roleDisplay) ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted mt-2">Pas encore d'affectation</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-muted">Aucune machine associée.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section Participants -->
<div class="gn-wrapper" style="margin-top: 3rem;">
    <!-- Participants Affectés -->
    <?php if (!empty($participants_affectes)): ?>
    <div class="gn-card" style="margin-bottom: 2rem;">
        <div class="gn-card-header"><h3 class="gn-card-title">✅ Participants confirmés (<?= count($participants_affectes) ?>)</h3></div>
        <div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1.5rem;">
                <?php foreach ($participants_affectes as $inscrit): ?>
                    <?php 
                        // Utiliser le cache pré-calculé
                        $userId = (int)$inscrit['id'];
                        $photoPath = $photoCache[$userId] ?? '/assets/img/avatar-placeholder.svg';
                        $fullName = trim(($inscrit['prenom'] ?? '') . ' ' . ($inscrit['nom'] ?? ''));
                    ?>
                    <div style="text-align: center;">
                        <img src="<?= $photoPath ?>" alt="<?= htmlspecialchars($fullName) ?>" loading="lazy"
                             style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid #10b981; margin-bottom: 0.75rem;">
                        <div style="font-weight: 600; font-size: 0.9rem; color: #1a1a1a; word-break: break-word;">
                            <?= htmlspecialchars($fullName) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Liste d'Attente -->
    <?php if (!empty($participants_waitlist)): ?>
    <div class="gn-card">
        <div class="gn-card-header"><h3 class="gn-card-title">⏳ Liste d'attente (<?= count($participants_waitlist) ?>)</h3></div>
        <div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1.5rem;">
                <?php foreach ($participants_waitlist as $inscrit): ?>
                    <?php 
                        // Utiliser le cache pré-calculé
                        $userId = (int)$inscrit['id'];
                        $photoPath = $photoCache[$userId] ?? '/assets/img/avatar-placeholder.svg';
                        $fullName = trim(($inscrit['prenom'] ?? '') . ' ' . ($inscrit['nom'] ?? ''));
                    ?>
                    <div style="text-align: center;">
                        <img src="<?= $photoPath ?>" alt="<?= htmlspecialchars($fullName) ?>" loading="lazy"
                             style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid #f59e0b; margin-bottom: 0.75rem;">
                        <div style="font-weight: 600; font-size: 0.9rem; color: #1a1a1a; word-break: break-word;">
                            <?= htmlspecialchars($fullName) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Message si aucun inscrit -->
    <?php if (empty($participants_affectes) && empty($participants_waitlist)): ?>
    <div class="gn-card">
        <div class="gn-card-header"><h3 class="gn-card-title">👥 Participants</h3></div>
        <div>
            <div class="text-muted">Aucun inscrit pour le moment.</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="/assets/leaflet/leaflet.css?v=desk-20251203">
<script src="/assets/leaflet/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var mapEl = document.getElementById('map');
    if (!mapEl) return;
    if (window.L) {
        var map = L.map('map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(map);
        <?php if ($coords_to): ?>
        map.setView([<?= json_encode($coords_to['lat']) ?>, <?= json_encode($coords_to['lon']) ?>], 9);
        L.marker([<?= json_encode($coords_to['lat']) ?>, <?= json_encode($coords_to['lon']) ?>]).addTo(map);
        <?php else: ?>
        map.setView([48.8566, 2.3522], 6);
        <?php endif; ?>
    } else {
        mapEl.innerHTML = '<div class="text-muted">Carte indisponible</div>';
    }
});
</script>

<?php include 'footer.php'; ?>