<?php
require 'header.php';
require_login();

// Afficher le bandeau démo si compte de démonstration
require_once 'demo_helper.php';
show_demo_banner();

$now = date('Y-m-d H:i:s');

// Récupérer les sorties en cours et à venir
try {
    $whereStatuses = "s.statut IN ('prévue','terminée')";
    if (is_admin()) {
        $whereStatuses = "LOWER(REPLACE(s.statut,'_',' ')) IN ('prévue','prevue','terminée','terminee','en étude','en etude')";
    }
    
    // Vérifier si les colonnes destination_id et ulm_base_id existent
    $hasDestinationId = false;
    $hasUlmBaseId = false;
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM sorties')->fetchAll(PDO::FETCH_COLUMN, 0);
        $hasDestinationId = in_array('destination_id', $cols, true);
        $hasUlmBaseId = in_array('ulm_base_id', $cols, true);
    } catch (Throwable $e) {}
    
    $sqlSorties = "
        SELECT 
            s.id,
            s.titre,
            s.description,
            s.details,
            s.date_sortie,
            s.date_fin,
            s.is_multi_day,
            s.destination_oaci,
            s.statut,
            s.repas_prevu,
            s.repas_details,"
            . ($hasDestinationId ? " s.destination_id," : "")
            . ($hasUlmBaseId ? " s.ulm_base_id," : "")
            . ($hasDestinationId ? " ad.nom AS dest_nom, ad.lat AS dest_lat, ad.lon AS dest_lon," : "")
            . ($hasUlmBaseId ? " ub.nom AS ulm_nom, ub.lat AS ulm_lat, ub.lon AS ulm_lon," : "")
            . "
            (SELECT sp.filename FROM sortie_photos sp WHERE sp.sortie_id = s.id ORDER BY sp.created_at DESC LIMIT 1) AS photo_filename,
            COUNT(DISTINCT si.id) as nb_inscrits
        FROM sorties s"
        . ($hasDestinationId ? "\n        LEFT JOIN aerodromes_fr ad ON ad.id = s.destination_id" : "")
        . ($hasUlmBaseId ? "\n        LEFT JOIN ulm_bases_fr ub ON ub.id = s.ulm_base_id" : "")
        . "
        LEFT JOIN sortie_inscriptions si ON si.sortie_id = s.id
        WHERE $whereStatuses AND s.date_sortie >= ?
        GROUP BY s.id
        ORDER BY s.date_sortie ASC
    ";
    $stmt = $pdo->prepare($sqlSorties);
    $stmt->execute([$now]);
    $sorties = $stmt->fetchAll();
} catch (Exception $e) {
    $sorties = [];
    error_log("Erreur requête sorties: " . $e->getMessage());
}

// Récupérer les sorties EN ÉTUDE (pour le panneau d'affichage)
$sortiesEnEtude = [];
try {
    $sqlEnEtude = "
        SELECT 
            s.id,
            s.titre,
            s.date_sortie,
            s.destination_oaci,"
            . ($hasDestinationId ? " ad.nom AS dest_nom," : "")
            . ($hasUlmBaseId ? " ub.nom AS ulm_nom," : "")
            . "
            s.statut
        FROM sorties s"
        . ($hasDestinationId ? "\n        LEFT JOIN aerodromes_fr ad ON ad.id = s.destination_id" : "")
        . ($hasUlmBaseId ? "\n        LEFT JOIN ulm_bases_fr ub ON ub.id = s.ulm_base_id" : "")
        . "
        WHERE LOWER(REPLACE(s.statut,'_',' ')) IN ('en étude','en etude')
          AND s.date_sortie >= ?
        ORDER BY s.date_sortie ASC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($sqlEnEtude);
    $stmt->execute([$now]);
    $sortiesEnEtude = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur requête sorties en étude: " . $e->getMessage());
}

// Récupérer les sorties PASSÉES
try {
    $sqlSortiesPassees = "
        SELECT 
            s.id,
            s.titre,
            s.description,
            s.details,
            s.date_sortie,
            s.date_fin,
            s.is_multi_day,
            s.destination_oaci,
            s.statut,
            s.repas_prevu,
            s.repas_details,
            (SELECT sp.filename FROM sortie_photos sp WHERE sp.sortie_id = s.id ORDER BY sp.created_at DESC LIMIT 1) AS photo_filename,
            COUNT(DISTINCT si.id) as nb_inscrits
        FROM sorties s
        LEFT JOIN sortie_inscriptions si ON si.sortie_id = s.id
        WHERE $whereStatuses AND s.date_sortie < ?
        GROUP BY s.id
        ORDER BY s.date_sortie DESC
    ";
    $stmt = $pdo->prepare($sqlSortiesPassees);
    $stmt->execute([$now]);
    $sortiesPassees = $stmt->fetchAll();
} catch (Exception $e) {
    $sortiesPassees = [];
    error_log("Erreur requête sorties passées: " . $e->getMessage());
}

// Récupérer les événements à venir
try {
    $stmt = $pdo->prepare("SELECT 
        e.id,
        e.titre,
        e.description,
        e.date_evenement,
        e.date_fin,
        e.is_multi_day,
        e.type,
        e.lieu,
        e.cover_filename,
        COUNT(DISTINCT ei.id) as nb_inscrits,
        SUM(CASE WHEN ei.statut = 'confirmée' THEN 1 ELSE 0 END) as confirmees,
        SUM(CASE WHEN ei.statut = 'confirmée' THEN ei.nb_accompagnants ELSE 0 END) as total_accompagnants
     FROM evenements e
     LEFT JOIN evenement_inscriptions ei ON ei.evenement_id = e.id
     WHERE e.statut IN ('prévu', 'en_cours') AND e.date_evenement >= ?
     GROUP BY e.id
     ORDER BY e.date_evenement ASC
     LIMIT 3");
    $stmt->execute([$now]);
    $evenements = $stmt->fetchAll();
} catch (Exception $e) {
    $evenements = [];
    error_log("Erreur requête événements: " . $e->getMessage());
}

// Récupérer les événements PASSÉS
try {
    $stmt = $pdo->prepare("SELECT 
        e.id,
        e.titre,
        e.description,
        e.date_evenement,
        e.date_fin,
        e.is_multi_day,
        e.type,
        e.lieu,
        e.cover_filename,
        COUNT(DISTINCT ei.id) as nb_inscrits,
        SUM(CASE WHEN ei.statut = 'confirmée' THEN 1 ELSE 0 END) as confirmees,
        SUM(CASE WHEN ei.statut = 'confirmée' THEN ei.nb_accompagnants ELSE 0 END) as total_accompagnants
     FROM evenements e
     LEFT JOIN evenement_inscriptions ei ON ei.evenement_id = e.id
     WHERE e.statut IN ('prévu', 'en_cours', 'terminé') AND e.date_evenement < ?
     GROUP BY e.id
     ORDER BY e.date_evenement DESC");
    $stmt->execute([$now]);
    $evenementsPassees = $stmt->fetchAll();
} catch (Exception $e) {
    $evenementsPassees = [];
    error_log("Erreur requête événements passés: " . $e->getMessage());
}

// Stats
try {
    $nb_machines = $pdo->query("SELECT COUNT(*) as c FROM machines WHERE actif = 1")->fetch()['c'] ?? 0;
    $nb_membres = $pdo->query("SELECT COUNT(*) as c FROM users WHERE actif = 1")->fetch()['c'] ?? 0;
} catch (Exception $e) {
    $nb_machines = 0;
    $nb_membres = 0;
}
$nb_sorties_total = count($sorties);
// Compter les événements à venir (toutes, pas seulement les 3 affichés)
try {
    $nb_evenements_total = $pdo->query(
        "SELECT COUNT(*) as c FROM evenements WHERE statut IN ('prévu','en_cours') AND date_evenement >= ?"
    )->fetch()['c'] ?? 0;
} catch (Exception $e) {
    $nb_evenements_total = 0;
}
?>

<div class="container mt-5">
<div class="gn-hero">
    <div class="gn-hero-content">
        <div class="gn-badge">ESPACE MEMBRES</div>
        <h1 class="gn-hero-title mt-2">Bienvenue <?= htmlspecialchars($_SESSION['prenom']) ?> ✈️</h1>
        <p class="gn-hero-text">
            Découvrez les sorties en cours et à venir du club ULM Evasion.
            Inscrivez-vous aux navigations qui vous intéressent !
        </p>
        <div class="gn-hero-badges">
            <span class="gn-badge"><i class="bi bi-airplane"></i> <?= $nb_sorties_total ?> sorties à venir</span>
            <span class="gn-badge"><i class="bi bi-airplane-engines"></i> <?= $nb_machines ?> machines actives</span>
            <span class="gn-badge"><i class="bi bi-people"></i> <?= $nb_membres ?> membres</span>
            <span class="gn-badge"><i class="bi bi-calendar-event"></i> <?= (int)$nb_evenements_total ?> événements à venir</span>
        </div>
    </div>
    
    <?php if (count($sortiesEnEtude) > 0): ?>
    <!-- Panneau "En préparation" -->
    <div class="gn-hero-aside">
        <div class="preparation-panel">
            <div class="preparation-header">
                <div class="preparation-icon">✨</div>
                <div>
                    <h3 class="preparation-title">Actuellement en préparation</h3>
                    <p class="preparation-subtitle">Prochaines destinations qui seront bientôt disponibles...</p>
                </div>
            </div>
            
            <div class="preparation-slider">
                <div class="preparation-track">
                    <?php foreach ($sortiesEnEtude as $idx => $se): 
                        // Déterminer la destination
                        $destination = '';
                        if ($hasUlmBaseId && !empty($se['ulm_nom'])) {
                            $destination = $se['ulm_nom'];
                        } elseif ($hasDestinationId && !empty($se['dest_nom'])) {
                            $destination = $se['dest_nom'];
                        } elseif (!empty($se['destination_oaci'])) {
                            $destination = $se['destination_oaci'];
                        } else {
                            $destination = 'Destination surprise';
                        }
                        
                        $dateFormatted = date('d/m/Y', strtotime($se['date_sortie']));
                        $mois = strftime('%B', strtotime($se['date_sortie']));
                    ?>
                    <div class="preparation-item">
                        <div class="preparation-date">
                            <div class="preparation-day"><?= date('d', strtotime($se['date_sortie'])) ?></div>
                            <div class="preparation-month"><?= strtoupper(substr($mois, 0, 3)) ?></div>
                        </div>
                        <div class="preparation-details">
                            <div class="preparation-destination">
                                <i class="bi bi-geo-alt-fill"></i>
                                <?= htmlspecialchars($destination) ?>
                            </div>
                            <div class="preparation-title-small"><?= htmlspecialchars($se['titre']) ?></div>
                        </div>
                        <div class="preparation-status">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Dupliquer pour le défilement infini -->
                    <?php foreach ($sortiesEnEtude as $se): 
                        $destination = '';
                        if ($hasUlmBaseId && !empty($se['ulm_nom'])) {
                            $destination = $se['ulm_nom'];
                        } elseif ($hasDestinationId && !empty($se['dest_nom'])) {
                            $destination = $se['dest_nom'];
                        } elseif (!empty($se['destination_oaci'])) {
                            $destination = $se['destination_oaci'];
                        } else {
                            $destination = 'Destination surprise';
                        }
                        
                        $dateFormatted = date('d/m/Y', strtotime($se['date_sortie']));
                        $mois = strftime('%B', strtotime($se['date_sortie']));
                    ?>
                    <div class="preparation-item">
                        <div class="preparation-date">
                            <div class="preparation-day"><?= date('d', strtotime($se['date_sortie'])) ?></div>
                            <div class="preparation-month"><?= strtoupper(substr($mois, 0, 3)) ?></div>
                        </div>
                        <div class="preparation-details">
                            <div class="preparation-destination">
                                <i class="bi bi-geo-alt-fill"></i>
                                <?= htmlspecialchars($destination) ?>
                            </div>
                            <div class="preparation-title-small"><?= htmlspecialchars($se['titre']) ?></div>
                        </div>
                        <div class="preparation-status">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="preparation-footer">
                <i class="bi bi-info-circle me-1"></i>
                Ces sorties seront bientôt ouvertes aux inscriptions
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (count($evenements) > 0): ?>
    <!-- Panneau "Événements à venir" -->
    <div class="gn-hero-aside" style="margin-top: 1.5rem;">
        <div class="preparation-panel">
            <div class="preparation-header">
                <div class="preparation-icon">📅</div>
                <div>
                    <h3 class="preparation-title">Prochains événements</h3>
                    <p class="preparation-subtitle">Découvrez les événements à venir du club...</p>
                </div>
            </div>
            
            <div class="preparation-slider">
                <div class="preparation-track">
                    <?php foreach ($evenements as $ev): ?>
                    <div class="preparation-item">
                        <div class="preparation-date">
                            <div class="preparation-day"><?= date('d', strtotime($ev['date_evenement'])) ?></div>
                            <div class="preparation-month"><?= strtoupper(substr(strftime('%B', strtotime($ev['date_evenement'])), 0, 3)) ?></div>
                        </div>
                        <div class="preparation-details">
                            <div class="preparation-destination">
                                <i class="bi bi-calendar-event"></i>
                                <?= htmlspecialchars($ev['lieu']) ?>
                            </div>
                            <div class="preparation-title-small"><?= htmlspecialchars($ev['titre']) ?></div>
                            <?php if (!empty($ev['is_multi_day']) && !empty($ev['date_fin'])): ?>
                                <div style="font-size: 0.7rem; color: #9c27b0; margin-top: 0.25rem;">
                                    <i class="bi bi-arrow-right"></i> jusqu'au <?= date('d/m', strtotime($ev['date_fin'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="preparation-status">
                            <span class="badge" style="background-color: #9c27b0; font-size: 0.7rem;">
                                <?= htmlspecialchars(ucfirst($ev['type'])) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Dupliquer pour le défilement infini -->
                    <?php foreach ($evenements as $ev): ?>
                    <div class="preparation-item">
                        <div class="preparation-date">
                            <div class="preparation-day"><?= date('d', strtotime($ev['date_evenement'])) ?></div>
                            <div class="preparation-month"><?= strtoupper(substr(strftime('%B', strtotime($ev['date_evenement'])), 0, 3)) ?></div>
                        </div>
                        <div class="preparation-details">
                            <div class="preparation-destination">
                                <i class="bi bi-calendar-event"></i>
                                <?= htmlspecialchars($ev['lieu']) ?>
                            </div>
                            <div class="preparation-title-small"><?= htmlspecialchars($ev['titre']) ?></div>
                            <?php if (!empty($ev['is_multi_day']) && !empty($ev['date_fin'])): ?>
                                <div style="font-size: 0.7rem; color: #9c27b0; margin-top: 0.25rem;">
                                    <i class="bi bi-arrow-right"></i> jusqu'au <?= date('d/m', strtotime($ev['date_fin'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="preparation-status">
                            <span class="badge" style="background-color: #9c27b0; font-size: 0.7rem;">
                                <?= htmlspecialchars(ucfirst($ev['type'])) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="preparation-footer">
                <a href="evenements_list.php" style="color: inherit; text-decoration: none;">
                    <i class="bi bi-arrow-right-circle me-1"></i>
                    Voir tous les événements
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Carte de France avec les sorties -->
<?php
// Préparer les données pour la carte
$mapMarkers = [];
foreach ($sorties as $s) {
    $lat = null;
    $lon = null;
    $dest_name = '';
    $icon = '📍';
    
    // Priorité aux bases ULM
    if ($hasUlmBaseId && !empty($s['ulm_lat']) && !empty($s['ulm_lon'])) {
        $lat = (float)$s['ulm_lat'];
        $lon = (float)$s['ulm_lon'];
        $dest_name = $s['ulm_nom'] ?? '';
        $icon = '🪂';
    } elseif ($hasDestinationId && !empty($s['dest_lat']) && !empty($s['dest_lon'])) {
        $lat = (float)$s['dest_lat'];
        $lon = (float)$s['dest_lon'];
        $dest_name = $s['dest_nom'] ?? '';
        $icon = '🛩️';
    }
    
    if ($lat && $lon && $lat != 0 && $lon != 0) {
        $mapMarkers[] = [
            'id' => $s['id'],
            'lat' => $lat,
            'lon' => $lon,
            'title' => $s['titre'],
            'dest' => $dest_name,
            'date' => date('d/m/Y', strtotime($s['date_sortie'])),
            'icon' => $icon
        ];
    }
}
?>

<?php if (count($mapMarkers) > 0): ?>
<div class="mb-5">
    <h2 class="mb-3" style="color: var(--gn-primary); font-weight: 600;">
        <i class="bi bi-map"></i> Carte des destinations
    </h2>
    <div class="gn-card" style="padding: 0; overflow: hidden;">
        <div id="map-sorties" style="height: 500px; width: 100%;"></div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const markers = <?= json_encode($mapMarkers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    
    if (markers.length === 0) return;
    
    // Centrer sur la France
    const map = L.map('map-sorties').setView([46.603354, 1.888334], 6);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18
    }).addTo(map);
    
    // Ajouter les marqueurs
    markers.forEach(function(m) {
        const marker = L.marker([m.lat, m.lon]).addTo(map);
        
        const popupContent = `
            <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <div style="font-weight: 600; color: #004b8d; margin-bottom: 0.5rem; font-size: 0.95rem;">
                    ${m.icon} ${m.title}
                </div>
                <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.3rem;">
                    📅 ${m.date}
                </div>
                <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.75rem;">
                    📍 ${m.dest}
                </div>
                <a href="sortie_info.php?id=${m.id}" 
                   style="display: inline-block; padding: 0.4rem 0.8rem; background: linear-gradient(135deg, #004b8d, #00a0c6); 
                          color: white; text-decoration: none; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 500;
                          transition: filter 0.2s;"
                   onmouseover="this.style.filter='brightness(1.1)'"
                   onmouseout="this.style.filter='brightness(1)'">
                    Voir la sortie →
                </a>
            </div>
        `;
        
        marker.bindPopup(popupContent);
    });
    
    // Ajuster la vue pour montrer tous les marqueurs
    if (markers.length > 1) {
        const bounds = L.latLngBounds(markers.map(m => [m.lat, m.lon]));
        map.fitBounds(bounds, { padding: [50, 50] });
    }
});
</script>
<?php endif; ?>

<?php
// Construire une liste unifiée Agenda (sorties + événements) triée par date croissante
$agenda = [];
foreach ($sorties as $s) {
    $agenda[] = [
        'kind' => 'sortie',
        'id' => $s['id'],
        'title' => $s['titre'],
        'date' => $s['date_sortie'],
        'end'  => $s['date_fin'] ?? null,
        'multi'=> (int)($s['is_multi_day'] ?? 0),
        'location' => $s['destination_oaci'],
        'desc' => $s['description'] ?? '',
        'brief' => $s['details'] ?? '',
        'statut' => $s['statut'] ?? '',
        'repas' => (int)($s['repas_prevu'] ?? 0),
        'repas_details' => $s['repas_details'] ?? '',
        'inscrits' => (int)($s['nb_inscrits'] ?? 0),
        'photo' => $s['photo_filename'] ?? null,
    ];
}
foreach ($evenements as $e) {
    $agenda[] = [
        'kind' => 'evenement',
        'id' => $e['id'],
        'title' => $e['titre'],
        'date' => $e['date_evenement'],
        'end'  => $e['date_fin'] ?? null,
        'multi'=> (int)($e['is_multi_day'] ?? 0),
        'location' => $e['lieu'],
        'desc' => $e['description'] ?? '',
        'inscrits' => (int)($e['confirmees'] ?? 0),
        'accompagnants' => (int)($e['total_accompagnants'] ?? 0),
        'cover' => $e['cover_filename'] ?? null,
        'type' => $e['type'] ?? 'événement',
    ];
}
usort($agenda, function($a, $b) {
    return strtotime($a['date']) <=> strtotime($b['date']);
});
?>

<div class="mb-5">
    <h2 class="mb-3" style="color: var(--gn-primary); font-weight: 600;">
        <i class="bi bi-calendar-week"></i> Prochaines activités
    </h2>

    <?php if (!$agenda): ?>
        <div class="gn-card" style="text-align: center; padding: 3rem 2rem;">
            <div style="font-size: 4rem; margin-bottom: 1rem; color: var(--gn-muted);">
                <i class="bi bi-inbox"></i>
            </div>
            <h3 style="color: var(--gn-dark); margin-bottom: 0.5rem;">Rien à afficher</h3>
            <p class="gn-card-subtitle">Aucune sortie ni événement pour le moment.</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($agenda as $it): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="gn-card" style="height: 100%; display: flex; flex-direction: column; overflow:hidden; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 16px rgba(26,135,203,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
                        <?php if ($it['kind'] === 'evenement'): ?>
                            <div style="width:100%; aspect-ratio:16/9; background:#f2f6fc; overflow:hidden;">
                                <?php if (!empty($it['cover'])): ?>
                                    <img src="uploads/events/<?= htmlspecialchars($it['cover']) ?>" alt="Image événement" style="width:100%; height:100%; object-fit:cover; display:block;">
                                <?php else: ?>
                                    <img src="assets/img/Ulm.jpg" alt="Illustration ULM" style="width:100%; height:100%; object-fit:cover; display:block;">
                                <?php endif; ?>
                            </div>
                        <?php elseif ($it['kind'] === 'sortie'): ?>
                            <div style="width:100%; aspect-ratio:16/9; background:#f2f6fc; overflow:hidden;">
                                <?php if (!empty($it['photo'])): ?>
                                    <img src="uploads/sorties/<?= htmlspecialchars($it['photo']) ?>" alt="Photo sortie" style="width:100%; height:100%; object-fit:cover; display:block;">
                                <?php else: ?>
                                    <img src="assets/img/Ulm.jpg" alt="Illustration ULM" style="width:100%; height:100%; object-fit:cover; display:block;">
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="gn-card-header" style="border-bottom: 2px solid var(--gn-border);">
                            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; gap: 1rem;">
                                <div class="gn-card-title" style="flex: 1;">
                                    <?= htmlspecialchars($it['title']) ?>
                                </div>
                                <?php if ($it['kind'] === 'sortie'): ?>
                                    <span class="badge bg-info">Sortie</span>
                                    <?php if (is_admin()) {
                                        $stRaw = (string)($it['statut'] ?? '');
                                        $st = strtolower(trim(str_replace('_',' ', $stRaw)));
                                        $stNoAccent = strtr($st, ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e']);
                                        $isStudy = (strpos($stNoAccent, 'etude') !== false);
                                        if ($isStudy): ?>
                                        <span class="badge" style="background-color:#9c27b0;">En étude</span>
                                    <?php endif; } ?>
                                    <?php if (!empty($it['repas'])): ?>
                                        <span class="badge bg-success" title="Repas prévu">🍽️ Repas</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge" style="background-color:#9c27b0;"><?= htmlspecialchars(ucfirst($it['type'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="flex-grow: 1; padding: 1rem;">
                            <p style="margin: 0.5rem 0; font-size: 0.9rem; color: #666;">
                                <i class="bi bi-calendar3"></i>
                                <?php if (!empty($it['multi']) && !empty($it['end'])): ?>
                                    <strong>Du <?= date('d/m/Y', strtotime($it['date'])) ?> au <?= date('d/m/Y', strtotime($it['end'])) ?></strong>
                                <?php else: ?>
                                    <strong><?= date('d/m/Y à H:i', strtotime($it['date'])) ?></strong>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($it['location'])): ?>
                                <p style="margin: 0.5rem 0; font-size: 0.9rem; color: #666;">
                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($it['location']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($it['kind'] === 'sortie'): ?>
                                <?php if (!empty($it['brief'])): ?>
                                    <p style="margin: 1rem 0; font-size: 0.85rem; color: #555;">
                                        <span style="font-weight:600; color:#444;">Briefing&nbsp;:</span>
                                        <?= htmlspecialchars(substr($it['brief'], 0, 140)) ?><?= strlen($it['brief']) > 140 ? '...' : '' ?>
                                    </p>
                                <?php elseif (!empty($it['desc'])): ?>
                                    <p style="margin: 1rem 0; font-size: 0.85rem; color: #555;">
                                        <span style="font-weight:600; color:#444;">Descriptif&nbsp;:</span>
                                        <?= htmlspecialchars(substr($it['desc'], 0, 120)) ?><?= strlen($it['desc']) > 120 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($it['repas']) && !empty($it['repas_details'])): ?>
                                    <p style="margin: 0.5rem 0; font-size: 0.85rem; color: #555;">
                                        <span style="font-weight:600; color:#444;">Repas&nbsp;:</span>
                                        <?= htmlspecialchars(substr($it['repas_details'], 0, 100)) ?><?= strlen($it['repas_details']) > 100 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($it['desc'])): ?>
                                    <p style="margin: 1rem 0; font-size: 0.85rem; color: #555;">
                                        <?= htmlspecialchars(substr($it['desc'], 0, 100)) ?><?= strlen($it['desc']) > 100 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid #eee;">
                                <small style="color: #888;">
                                    <i class="bi bi-people"></i> <?= (int)$it['inscrits'] ?> inscrit(s)
                                    <?php if (($it['accompagnants'] ?? 0) > 0): ?>
                                        + <?= (int)$it['accompagnants'] ?> accompagnant(s)
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>

                        <div style="padding: 1rem; border-top: 1px solid #eee;">
                            <?php if ($it['kind'] === 'sortie'): ?>
                                <a href="sortie_info.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-primary w-100 mb-2">
                                    <i class="bi bi-eye"></i> Voir les infos
                                </a>
                                <?php if (is_admin()): ?>
                                    <a href="sortie_detail.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-outline-primary w-100 mb-2">
                                        <i class="bi bi-tools"></i> Gestion (affectations)
                                    </a>
                                <?php endif; ?>
                                <a href="sortie_participants.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-outline-primary w-100 mb-2">
                                    <i class="bi bi-people"></i> Participants
                                    <span class="badge bg-secondary ms-1"><?= (int)$it['inscrits'] ?></span>
                                </a>
                                <a href="preinscription_sortie.php?sortie_id=<?= $it['id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-pencil"></i> S'inscrire
                                </a>
                                <?php if (is_admin()): ?>
                                    <a href="sortie_edit.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-warning w-100 mt-2">
                                        <i class="bi bi-pencil"></i> Éditer
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="evenement_inscription_detail.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-primary w-100 mb-2">
                                    <i class="bi bi-check-circle"></i> S'inscrire
                                </a>
                                <a href="evenement_participants.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-outline-primary w-100 mb-2"
                                   title="<?= (int)$it['inscrits'] ?> inscrit(s)<?= (($it['accompagnants'] ?? 0) > 0) ? ' + ' . (int)$it['accompagnants'] . ' accompagnant(s)' : '' ?>">
                                    <i class="bi bi-people"></i> Participants
                                    <span class="badge bg-secondary ms-1"><?= (int)$it['inscrits'] ?></span>
                                </a>
                                <?php if (is_admin()): ?>
                                    <a href="evenement_edit.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-warning w-100">
                                        <i class="bi bi-pencil"></i> Éditer
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-3 text-center">
            <a href="sorties.php" class="btn btn-outline-primary me-2">
                <i class="bi bi-calendar2-event"></i> Toutes les sorties
            </a>
            <a href="evenements_list.php" class="btn btn-outline-primary">
                <i class="bi bi-calendar-event"></i> Tous les événements
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Section Événements passés -->
<?php
// Construire une liste unifiée Agenda passée (sorties + événements passés) triée par date décroissante
$agendaPassee = [];
foreach ($sortiesPassees as $s) {
    $agendaPassee[] = [
        'kind' => 'sortie',
        'id' => $s['id'],
        'title' => $s['titre'],
        'date' => $s['date_sortie'],
        'end'  => $s['date_fin'] ?? null,
        'multi'=> (int)($s['is_multi_day'] ?? 0),
        'location' => $s['destination_oaci'],
        'desc' => $s['description'] ?? '',
        'brief' => $s['details'] ?? '',
        'statut' => $s['statut'] ?? '',
        'repas' => (int)($s['repas_prevu'] ?? 0),
        'repas_details' => $s['repas_details'] ?? '',
        'inscrits' => (int)($s['nb_inscrits'] ?? 0),
        'photo' => $s['photo_filename'] ?? null,
    ];
}
foreach ($evenementsPassees as $e) {
    $agendaPassee[] = [
        'kind' => 'evenement',
        'id' => $e['id'],
        'title' => $e['titre'],
        'date' => $e['date_evenement'],
        'end'  => $e['date_fin'] ?? null,
        'multi'=> (int)($e['is_multi_day'] ?? 0),
        'location' => $e['lieu'],
        'desc' => $e['description'] ?? '',
        'inscrits' => (int)($e['confirmees'] ?? 0),
        'accompagnants' => (int)($e['total_accompagnants'] ?? 0),
        'cover' => $e['cover_filename'] ?? null,
        'type' => $e['type'] ?? 'événement',
    ];
}
usort($agendaPassee, function($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']); // Décroissant pour les passés
});
?>

<div class="mb-5">
    <h2 class="mb-3" style="color: var(--gn-primary); font-weight: 600;">
        <i class="bi bi-clock-history"></i> Événements passés
    </h2>

    <?php if (!$agendaPassee): ?>
        <div class="gn-card" style="text-align: center; padding: 3rem 2rem;">
            <div style="font-size: 4rem; margin-bottom: 1rem; color: var(--gn-muted);">
                <i class="bi bi-inbox"></i>
            </div>
            <h3 style="color: var(--gn-dark); margin-bottom: 0.5rem;">Rien à afficher</h3>
            <p class="gn-card-subtitle">Aucune sortie ni événement passé.</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($agendaPassee as $it): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="gn-card" style="height: 100%; display: flex; flex-direction: column; overflow:hidden; transition: transform 0.2s, box-shadow 0.2s; opacity: 0.8;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 16px rgba(26,135,203,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
                        <?php if ($it['kind'] === 'evenement'): ?>
                            <div style="width:100%; aspect-ratio:16/9; background:#f2f6fc; overflow:hidden; position:relative;">
                                <?php if (!empty($it['cover'])): ?>
                                    <img src="uploads/events/<?= htmlspecialchars($it['cover']) ?>" alt="Image événement" style="width:100%; height:100%; object-fit:cover; display:block;">
                                <?php else: ?>
                                    <img src="assets/img/Ulm.jpg" alt="Illustration ULM" style="width:100%; height:100%; object-fit:cover; display:block;">
                                <?php endif; ?>
                                <div style="position: absolute; top: 8px; right: 8px; background-color: #dc3545; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600; font-size: 0.85rem; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                                    Terminé
                                </div>
                            </div>
                        <?php elseif ($it['kind'] === 'sortie'): ?>
                            <div style="width:100%; aspect-ratio:16/9; background:#f2f6fc; overflow:hidden; position:relative;">
                                <?php if (!empty($it['photo'])): ?>
                                    <img src="uploads/sorties/<?= htmlspecialchars($it['photo']) ?>" alt="Photo sortie" style="width:100%; height:100%; object-fit:cover; display:block;">
                                <?php else: ?>
                                    <img src="assets/img/Ulm.jpg" alt="Illustration ULM" style="width:100%; height:100%; object-fit:cover; display:block;">
                                <?php endif; ?>
                                <div style="position: absolute; top: 8px; right: 8px; background-color: #dc3545; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600; font-size: 0.85rem; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                                    Terminé
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="gn-card-header" style="border-bottom: 2px solid var(--gn-border);">
                            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; gap: 1rem;">
                                <div class="gn-card-title" style="flex: 1;">
                                    <?= htmlspecialchars($it['title']) ?>
                                </div>
                                <?php if ($it['kind'] === 'sortie'): ?>
                                    <span class="badge bg-info">Sortie</span>
                                    <?php if (!empty($it['repas'])): ?>
                                        <span class="badge bg-success" title="Repas prévu">🍽️ Repas</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge" style="background-color:#9c27b0;"><?= htmlspecialchars(ucfirst($it['type'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="flex-grow: 1; padding: 1rem;">
                            <p style="margin: 0.5rem 0; font-size: 0.9rem; color: #666;">
                                <i class="bi bi-calendar3"></i>
                                <?php if (!empty($it['multi']) && !empty($it['end'])): ?>
                                    <strong>Du <?= date('d/m/Y', strtotime($it['date'])) ?> au <?= date('d/m/Y', strtotime($it['end'])) ?></strong>
                                <?php else: ?>
                                    <strong><?= date('d/m/Y à H:i', strtotime($it['date'])) ?></strong>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($it['location'])): ?>
                                <p style="margin: 0.5rem 0; font-size: 0.9rem; color: #666;">
                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($it['location']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($it['kind'] === 'sortie'): ?>
                                <?php if (!empty($it['brief'])): ?>
                                    <p style="margin: 1rem 0; font-size: 0.85rem; color: #555;">
                                        <span style="font-weight:600; color:#444;">Briefing&nbsp;:</span>
                                        <?= htmlspecialchars(substr($it['brief'], 0, 140)) ?><?= strlen($it['brief']) > 140 ? '...' : '' ?>
                                    </p>
                                <?php elseif (!empty($it['desc'])): ?>
                                    <p style="margin: 1rem 0; font-size: 0.85rem; color: #555;">
                                        <span style="font-weight:600; color:#444;">Descriptif&nbsp;:</span>
                                        <?= htmlspecialchars(substr($it['desc'], 0, 120)) ?><?= strlen($it['desc']) > 120 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($it['repas']) && !empty($it['repas_details'])): ?>
                                    <p style="margin: 0.5rem 0; font-size: 0.85rem; color: #555;">
                                        <span style="font-weight:600; color:#444;">Repas&nbsp;:</span>
                                        <?= htmlspecialchars(substr($it['repas_details'], 0, 100)) ?><?= strlen($it['repas_details']) > 100 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($it['desc'])): ?>
                                    <p style="margin: 1rem 0; font-size: 0.85rem; color: #555;">
                                        <?= htmlspecialchars(substr($it['desc'], 0, 100)) ?><?= strlen($it['desc']) > 100 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid #eee;">
                                <small style="color: #888;">
                                    <i class="bi bi-people"></i> <?= (int)$it['inscrits'] ?> inscrit(s)
                                    <?php if (($it['accompagnants'] ?? 0) > 0): ?>
                                        + <?= (int)$it['accompagnants'] ?> accompagnant(s)
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>

                        <div style="padding: 1rem; border-top: 1px solid #eee;">
                            <?php if ($it['kind'] === 'sortie'): ?>
                                <a href="sortie_info.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-primary w-100 mb-2">
                                    <i class="bi bi-eye"></i> Voir les infos
                                </a>
                                <a href="sortie_participants.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-people"></i> Participants
                                    <span class="badge bg-secondary ms-1"><?= (int)$it['inscrits'] ?></span>
                                </a>
                            <?php else: ?>
                                <a href="evenement_participants.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-outline-primary w-100"
                                   title="<?= (int)$it['inscrits'] ?> inscrit(s)<?= (($it['accompagnants'] ?? 0) > 0) ? ' + ' . (int)$it['accompagnants'] . ' accompagnant(s)' : '' ?>">
                                    <i class="bi bi-people"></i> Participants
                                    <span class="badge bg-secondary ms-1"><?= (int)$it['inscrits'] ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php if (is_admin()): ?>
    <div class="mt-5">
        <div class="gn-card" style="background: linear-gradient(135deg, rgba(26, 135, 203, 0.05) 0%, rgba(26, 135, 203, 0.02) 100%); border: 1px solid rgba(26, 135, 203, 0.1);">
            <div class="gn-card-header">
                <div class="gn-card-title">
                    <i class="bi bi-shield-check"></i> Espace Administration
                </div>
            </div>
            <div class="row g-2" style="padding: 0 1rem 1rem;">
                <div class="col-md-3 col-sm-6">
                    <a href="machines.php" class="gn-btn gn-btn-outline" style="width: 100%;">
                        <i class="bi bi-airplane-engines"></i> Machines
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="sorties.php" class="gn-btn gn-btn-outline" style="width: 100%;">
                        <i class="bi bi-calendar2-event"></i> Sorties
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="membres.php" class="gn-btn gn-btn-outline" style="width: 100%;">
                        <i class="bi bi-people"></i> Membres
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="stats.php" class="gn-btn gn-btn-outline" style="width: 100%;">
                        <i class="bi bi-bar-chart"></i> Statistiques
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

</div><!-- /container -->

<style>
.gn-hero {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

@media (min-width: 992px) {
    .gn-hero {
        grid-template-columns: 1fr 450px;
    }
}

.gn-hero-aside {
    display: flex;
    align-items: stretch;
}

.preparation-panel {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
    backdrop-filter: blur(10px);
    border-radius: 1.5rem;
    border: 2px solid rgba(0, 75, 141, 0.1);
    box-shadow: 0 8px 32px rgba(0, 75, 141, 0.15);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: 100%;
    overflow: hidden;
}

.preparation-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid rgba(0, 75, 141, 0.1);
}

.preparation-icon {
    font-size: 2.5rem;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.preparation-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #004b8d;
    margin: 0;
    line-height: 1.2;
}

.preparation-subtitle {
    font-size: 0.85rem;
    color: #666;
    margin: 0.25rem 0 0 0;
    line-height: 1.3;
}

.preparation-slider {
    position: relative;
    overflow: hidden;
    max-height: 180px;
    mask-image: linear-gradient(to bottom, black 0%, black 85%, transparent 100%);
    -webkit-mask-image: linear-gradient(to bottom, black 0%, black 85%, transparent 100%);
}

.preparation-track {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    animation: scrollUp 20s linear infinite;
}

.preparation-track:hover {
    animation-play-state: paused;
}

@keyframes scrollUp {
    0% {
        transform: translateY(0);
    }
    100% {
        transform: translateY(-50%);
    }
}

.preparation-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 1rem;
    border: 1px solid rgba(0, 75, 141, 0.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.preparation-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 16px rgba(0, 75, 141, 0.15);
    border-color: rgba(0, 160, 198, 0.3);
}

.preparation-date {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #004b8d, #00a0c6);
    color: white;
    border-radius: 0.75rem;
    padding: 0.5rem;
    min-width: 60px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 75, 141, 0.3);
}

.preparation-day {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
}

.preparation-month {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.9;
    margin-top: 0.25rem;
}

.preparation-details {
    flex: 1;
    min-width: 0;
}

.preparation-destination {
    font-size: 1rem;
    font-weight: 600;
    color: #004b8d;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.preparation-destination i {
    color: #00a0c6;
    flex-shrink: 0;
}

.preparation-title-small {
    font-size: 0.85rem;
    color: #666;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.preparation-status {
    font-size: 1.5rem;
    color: #ffc107;
    animation: rotate 3s linear infinite;
    flex-shrink: 0;
}

@keyframes rotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.preparation-footer {
    font-size: 0.8rem;
    color: #666;
    text-align: center;
    padding: 0.75rem;
    background: rgba(0, 160, 198, 0.1);
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.preparation-footer i {
    color: #00a0c6;
}

/* Responsive */
@media (max-width: 991px) {
    .gn-hero {
        grid-template-columns: 1fr;
    }
    
    .preparation-panel {
        max-width: 100%;
        margin: 0;
        padding: 1rem;
    }
    
    .preparation-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
        padding-bottom: 0.75rem;
    }
    
    .preparation-icon {
        font-size: 2rem;
    }
    
    .preparation-title {
        font-size: 1.1rem;
    }
    
    .preparation-subtitle {
        font-size: 0.8rem;
    }
    
    .preparation-slider {
        max-height: 200px;
    }
    
    .preparation-item {
        padding: 0.75rem;
        gap: 0.75rem;
    }
    
    .preparation-date {
        min-width: 50px;
        padding: 0.4rem;
    }
    
    .preparation-day {
        font-size: 1.2rem;
    }
    
    .preparation-month {
        font-size: 0.65rem;
    }
    
    .preparation-destination {
        font-size: 0.9rem;
    }
    
    .preparation-title-small {
        font-size: 0.8rem;
    }
    
    .preparation-status {
        font-size: 1.2rem;
    }
    
    .preparation-footer {
        font-size: 0.75rem;
        padding: 0.5rem;
    }
}

@media (max-width: 576px) {
    .preparation-panel {
        border-radius: 1rem;
        padding: 0.875rem;
    }
    
    .preparation-header {
        flex-direction: row;
        align-items: center;
    }
    
    .preparation-icon {
        font-size: 1.75rem;
    }
    
    .preparation-title {
        font-size: 1rem;
    }
    
    .preparation-subtitle {
        font-size: 0.75rem;
    }
    
    .preparation-slider {
        max-height: 180px;
    }
    
    .preparation-item {
        padding: 0.625rem;
        gap: 0.5rem;
    }
    
    .preparation-date {
        min-width: 45px;
        padding: 0.35rem;
        border-radius: 0.5rem;
    }
    
    .preparation-day {
        font-size: 1.1rem;
    }
    
    .preparation-month {
        font-size: 0.6rem;
    }
    
    .preparation-destination {
        font-size: 0.85rem;
    }
    
    .preparation-title-small {
        font-size: 0.75rem;
    }
    
    .preparation-status {
        font-size: 1rem;
    }
    
    .preparation-footer {
        font-size: 0.7rem;
        padding: 0.4rem;
    }
}
</style>

<?php require 'footer.php'; ?>
