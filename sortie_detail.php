<?php
// Vider le cache OpCache (temporaire pour debug)
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();
require_once 'mail_helper.php';
// Debug temporaire pour page blanche
error_reporting(E_ALL);
ini_set('display_errors', '1');
if (function_exists('ob_start')) { ob_start(); }

// Fonction pour générer un fichier iCalendar (.ics) pour une sortie
if (!function_exists('generate_sortie_ics')) {
    function generate_sortie_ics(array $sortie, string $destination_oaci = ''): string {
        // Date et heure du RDV (fuseau Europe/Paris)
        // Extraire uniquement la date (YYYY-MM-DD) si le champ contient une heure
        $date_only = substr($sortie['date_sortie'], 0, 10);
        $date_rdv = new DateTime($date_only . ' 09:00:00', new DateTimeZone('Europe/Paris'));
        $date_fin = clone $date_rdv;
        $date_fin->modify('+4 hours'); // Durée approximative de 4h
        
        // Format iCalendar
        $dtstart = $date_rdv->format('Ymd\THis');
        $dtend = $date_fin->format('Ymd\THis');
        $dtstamp = gmdate('Ymd\THis\Z');
        $uid = 'sortie-' . $sortie['id'] . '-' . time() . '@gestnav.clubulmevasion.fr';
        
        $titre = $sortie['titre'];
        $destination = $destination_oaci ? " vers $destination_oaci" : '';
        $summary = "SORTIE CLUB$destination";
        
        $location = "La Salmagne, Maubeuge";
        
        $description = "Sortie ULM organisée par le Club ULM Evasion\\n\\n"
                     . "Titre: $titre\\n"
                     . "Date: " . $date_rdv->format('d/m/Y à H:i') . "\\n"
                     . "Lieu de rendez-vous: $location\\n"
                     . ($destination_oaci ? "Destination: $destination_oaci\\n" : '')
                     . "\\nBon vol !";
        
        $ics = "BEGIN:VCALENDAR\r\n"
             . "VERSION:2.0\r\n"
             . "PRODID:-//GESTNAV//Club ULM Evasion//FR\r\n"
             . "CALSCALE:GREGORIAN\r\n"
             . "METHOD:REQUEST\r\n"
             . "BEGIN:VEVENT\r\n"
             . "UID:$uid\r\n"
             . "DTSTAMP:$dtstamp\r\n"
             . "DTSTART;TZID=Europe/Paris:$dtstart\r\n"
             . "DTEND;TZID=Europe/Paris:$dtend\r\n"
             . "SUMMARY:$summary\r\n"
             . "DESCRIPTION:$description\r\n"
             . "LOCATION:$location\r\n"
             . "STATUS:CONFIRMED\r\n"
             . "SEQUENCE:0\r\n"
             . "BEGIN:VALARM\r\n"
             . "TRIGGER:-PT1H\r\n"
             . "ACTION:DISPLAY\r\n"
             . "DESCRIPTION:Rappel: Sortie ULM dans 1 heure\r\n"
             . "END:VALARM\r\n"
             . "END:VEVENT\r\n"
             . "END:VCALENDAR\r\n";
        
        return $ics;
    }
}

// Helper local pour photos machines
if (!function_exists('gestnav_machine_photo_url')) {
    static $machinePhotoCache = [];
    function gestnav_machine_photo_url(int $id): string {
        global $machinePhotoCache;
        
        if (isset($machinePhotoCache[$id])) {
            return $machinePhotoCache[$id];
        }
        
        $relBase = 'uploads/machines';
        $absBase = __DIR__ . '/uploads/machines';
        foreach (['jpg','jpeg','png','webp'] as $ext) {
            $abs = $absBase . '/machine_' . $id . '.' . $ext;
            if (file_exists($abs)) {
                $machinePhotoCache[$id] = $relBase . '/machine_' . $id . '.' . $ext;
                return $machinePhotoCache[$id];
            }
        }
        $machinePhotoCache[$id] = 'assets/img/machine-placeholder.svg';
        return $machinePhotoCache[$id];
    }
}

// Transforme les URLs en liens cliquables en conservant l'échappement
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
            $href = $url;
            if (stripos($url, 'www.') === 0) {
                $href = 'http://' . $url;
            }
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

// Étape d'application directe retirée: pas d'affectation automatique depuis le tableau des pré-inscriptions

// On accepte ?id=... ou ?sortie_id=...
$sortie_id = 0;
if (isset($_GET['id'])) {
    $sortie_id = (int)$_GET['id'];
} elseif (isset($_GET['sortie_id'])) {
    $sortie_id = (int)$_GET['sortie_id'];
} elseif (isset($_POST['sortie_id'])) {
    $sortie_id = (int)$_POST['sortie_id'];
}

if ($sortie_id <= 0) {
    die("Sortie non spécifiée.");
}

// Étape de validation retirée: plus de colonne/état de validation côté pré-inscriptions

// Handler GET pour rendre indisponible une machine (évite les formulaires imbriqués)
if (is_admin() && isset($_GET['make_unavailable'])) {
    $sm_id = (int)($_GET['make_unavailable'] ?? 0);
    if ($sm_id > 0) {
        try {
            // Récupérer machine_id pour blocklist
            $mrow = null;
            try {
                $st = $pdo->prepare("SELECT machine_id FROM sortie_machines WHERE id = ? AND sortie_id = ?");
                $st->execute([$sm_id, $sortie_id]);
                $mrow = $st->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) { $mrow = null; }

            $pdo->prepare("DELETE FROM sortie_machines WHERE id = ? AND sortie_id = ?")->execute([$sm_id, $sortie_id]);

            // Enregistrer l'exclusion pour éviter la ré‑auto‑association
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS sortie_machines_exclusions (
                    sortie_id INT NOT NULL,
                    machine_id INT NOT NULL,
                    blocked_by_user_id INT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (sortie_id, machine_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                if (!empty($mrow['machine_id'])) {
                    $insEx = $pdo->prepare("INSERT IGNORE INTO sortie_machines_exclusions (sortie_id, machine_id, blocked_by_user_id) VALUES (?,?,?)");
                    $insEx->execute([$sortie_id, (int)$mrow['machine_id'], (int)($_SESSION['user_id'] ?? 0)]);
                }
            } catch (Throwable $e) { /* no-op */ }

            header('Location: sortie_detail.php?sortie_id=' . (int)$sortie_id . '&unavailable=1');
            exit;
        } catch (Throwable $e) {
            header('Location: sortie_detail.php?sortie_id=' . (int)$sortie_id . '&error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}

// --- Récupération des infos de la sortie ---
$stmt = $pdo->prepare("
    SELECT s.*, u.nom AS createur_nom, u.prenom AS createur_prenom
    FROM sorties s
    LEFT JOIN users u ON u.id = s.created_by
    WHERE s.id = ?
");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sortie) {
    die("Sortie introuvable.");
}

// Destination (optionnelle) : détection colonne + récupération
$destination_label = '';
$hasDestinationId = false;
$hasUlmBaseId = false;
$destination_oaci = '';
$distance_km = null;
$eta_text = '';

// Helper: récupérer lat/lon par OACI depuis aerodromes_fr ou aerodromes (colonnes variables)
if (!function_exists('gn_get_oaci_coords')) {
    function gn_get_oaci_coords(PDO $pdo, string $oaci): ?array {
        $tables = ['aerodromes_fr', 'aerodromes'];
        foreach ($tables as $t) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM {$t} WHERE oaci = ? LIMIT 1");
                $stmt->execute([$oaci]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    foreach (['lat','latitude','lat_deg'] as $latKey) {
                        foreach (['lon','longitude','lng','lon_deg'] as $lonKey) {
                            if (isset($row[$latKey]) && isset($row[$lonKey])) {
                                $lat = (float)$row[$latKey];
                                $lon = (float)$row[$lonKey];
                                if ($lat !== 0.0 || $lon !== 0.0) {
                                    return ['lat' => $lat, 'lon' => $lon];
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $e) { /* continuer */ }
        }
        return null;
    }
}

// Helper: récupérer lat/lon depuis ulm_bases_fr
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
        } catch (Throwable $e) {
            error_log("DEBUG gn_get_ulm_base_coords - Error: " . $e->getMessage());
        }
        return null;
    }
}

try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM sorties LIKE 'destination_id'");
    if ($colCheck && $colCheck->fetch()) {
        $hasDestinationId = true;
    }
    $colCheck2 = $pdo->query("SHOW COLUMNS FROM sorties LIKE 'ulm_base_id'");
    if ($colCheck2 && $colCheck2->fetch()) {
        $hasUlmBaseId = true;
    }
} catch (Throwable $e) {
    $hasDestinationId = false;
    $hasUlmBaseId = false;
}

// Priorité aux bases ULM si ulm_base_id est renseigné
if ($hasUlmBaseId && !empty($sortie['ulm_base_id'])) {
    $ulm_id = (int)$sortie['ulm_base_id'];
    try {
        $stmt = $pdo->prepare("SELECT oaci, nom, ville FROM ulm_bases_fr WHERE id = ? LIMIT 1");
        $stmt->execute([$ulm_id]);
        $rowUlm = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rowUlm) {
            $destination_oaci = (string)($rowUlm['oaci'] ?? '');
            $destination_label = '🪂 ' . trim(($destination_oaci ? $destination_oaci . ' – ' : '') . ($rowUlm['nom'] ?? ''));
            if (!empty($rowUlm['ville'])) {
                $destination_label .= ' (' . $rowUlm['ville'] . ')';
            }
        }
    } catch (Throwable $e) { /* no-op */ }
} elseif ($hasDestinationId && !empty($sortie['destination_id'])) {
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

// Calcul distance/ETA si on a un OACI de destination OU une base ULM
if ($destination_oaci !== '' || ($hasUlmBaseId && !empty($sortie['ulm_base_id']))) {
    try {
        $origin_oaci = defined('GESTNAV_CLUB_OACI') ? GESTNAV_CLUB_OACI : 'LFQJ';
        $home = gn_get_oaci_coords($pdo, $origin_oaci);
        
        error_log("DEBUG distance - hasUlmBaseId: " . ($hasUlmBaseId ? 'true' : 'false') . ", ulm_base_id: " . ($sortie['ulm_base_id'] ?? 'null'));
        
        // Destination : base ULM ou aérodrome
        $dest = null;
        if ($hasUlmBaseId && !empty($sortie['ulm_base_id'])) {
            $dest = gn_get_ulm_base_coords($pdo, (int)$sortie['ulm_base_id']);
            error_log("DEBUG distance - ULM dest: " . json_encode($dest));
        }
        if (!$dest && $destination_oaci !== '') {
            $dest = gn_get_oaci_coords($pdo, $destination_oaci);
            error_log("DEBUG distance - Aerodrome dest: " . json_encode($dest));
        }
        
        error_log("DEBUG distance - Home: " . json_encode($home) . ", Dest: " . json_encode($dest));
        
        if ($home && $dest) {
            $lat1 = (float)$home['lat'];
            $lon1 = (float)$home['lon'];
            $lat2 = (float)$dest['lat'];
            $lon2 = (float)$dest['lon'];
            $toRad = function($deg){ return $deg * M_PI / 180.0; };
            $R = 6371.0;
            $dLat = $toRad($lat2 - $lat1);
            $dLon = $toRad($lon2 - $lon1);
            $a = sin($dLat/2)**2 + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLon/2)**2;
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $distance_km = max(1, (int)round($R * $c));
            $speed_kmh = (float)(defined('GESTNAV_DEFAULT_SPEED_KMH') ? GESTNAV_DEFAULT_SPEED_KMH : 160);
            $hours = $distance_km / $speed_kmh;
            $totalMinutes = (int)round($hours * 60);
            $h = intdiv($totalMinutes, 60);
            $m = $totalMinutes % 60;
            $eta_text = sprintf('%dh%02d (à %d km/h)', $h, $m, (int)$speed_kmh);
            error_log("DEBUG distance - Calculated: {$distance_km}km, ETA: $eta_text");
        }
    } catch (Throwable $e) {
        error_log("DEBUG distance - Exception: " . $e->getMessage());
    }
}

// --- Liste des machines de la sortie ---
// --- Liste des machines de la sortie (colonnes optionnelles détectées dynamiquement) ---
$smCols = [];
try {
    $smCols = $pdo->query("SHOW COLUMNS FROM sortie_machines")->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Throwable $e) { $smCols = []; }
$hasIsMemberOwned = in_array('is_member_owned', $smCols, true);
$hasProvidedBy = in_array('provided_by_user_id', $smCols, true);

$selectExtra = '';
if ($hasIsMemberOwned) { $selectExtra .= ', sm.is_member_owned'; }
if ($hasProvidedBy) { $selectExtra .= ', sm.provided_by_user_id'; }

$sqlMachines = "\n    SELECT sm.id AS sortie_machine_id" . $selectExtra . ", m.*\n    FROM sortie_machines sm\n    JOIN machines m ON m.id = sm.machine_id\n    WHERE sm.sortie_id = ?\n    ORDER BY m.nom\n";
$stmt = $pdo->prepare($sqlMachines);
$stmt->execute([$sortie_id]);
$machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Liste des inscrits ---
$stmt = $pdo->prepare("
    SELECT si.*, u.nom, u.prenom, u.email
    FROM sortie_inscriptions si
    JOIN users u ON u.id = si.user_id
    WHERE si.sortie_id = ?
    ORDER BY si.created_at ASC
");
$stmt->execute([$sortie_id]);
$inscrits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Pré-inscriptions (préférences machine / coéquipier) ---
$preinscriptions = [];
try {
    $stmt = $pdo->prepare("\n        SELECT p.*, \n               u.id AS u_id, u.prenom AS u_prenom, u.nom AS u_nom, u.email AS u_email,\n               m.nom AS m_nom, m.immatriculation AS m_immat,\n               c.prenom AS c_prenom, c.nom AS c_nom, c.email AS c_email\n        FROM sortie_preinscriptions p\n        JOIN users u ON u.id = p.user_id\n        LEFT JOIN machines m ON m.id = p.preferred_machine_id\n        LEFT JOIN users c ON c.id = p.preferred_coequipier_user_id\n        WHERE p.sortie_id = ?\n        ORDER BY p.created_at ASC\n    ");
    $stmt->execute([$sortie_id]);
    $preinscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $preinscriptions = [];
}

// Index des pré-inscriptions par user_id (pour afficher leurs préférences dans la liste des inscrits)
$pre_by_user = [];
foreach ($preinscriptions as $p) {
    $uid = (int)($p['user_id'] ?? 0);
    if ($uid > 0) { $pre_by_user[$uid] = $p; }
}

// Récupérer la prochaine sortie chronologique après celle-ci (pour badges prioritaires)
$next_sortie = null;
try {
    $stmt_next = $pdo->prepare("SELECT id, titre, date_sortie FROM sorties 
        WHERE date_sortie > ? AND statut != 'annulée' 
        ORDER BY date_sortie ASC LIMIT 1");
    $stmt_next->execute([$sortie['date_sortie']]);
    $next_sortie = $stmt_next->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $next_sortie = null;
}

// Récupérer la sortie précédente chronologique avant celle-ci (pour badge "Précédente sortie")
$previous_sortie = null;
try {
    $stmt_prev = $pdo->prepare("SELECT id, titre, date_sortie FROM sorties 
        WHERE date_sortie < ? AND statut != 'annulée' 
        ORDER BY date_sortie DESC LIMIT 1");
    $stmt_prev->execute([$sortie['date_sortie']]);
    $previous_sortie = $stmt_prev->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $previous_sortie = null;
}

// Auto-associer les machines des membres inscrits (si liées via machines_owners)
try {
    $inscrits_ids = array_map(fn($r) => (int)$r['user_id'], $inscrits);
    if (!empty($inscrits_ids)) {
        $in = implode(',', array_fill(0, count($inscrits_ids), '?'));
        $stmtMO = $pdo->prepare("SELECT machine_id, user_id FROM machines_owners WHERE user_id IN ($in)");
        $stmtMO->execute($inscrits_ids);
        $owned = $stmtMO->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($owned)) {
            // Charger les exclusions pour ne pas ré‑ajouter une machine rendue indisponible
            $blocked = [];
            try {
                $stb = $pdo->prepare("SELECT machine_id FROM sortie_machines_exclusions WHERE sortie_id = ?");
                $stb->execute([$sortie_id]);
                $blocked = array_map('intval', $stb->fetchAll(PDO::FETCH_COLUMN, 0) ?: []);
            } catch (Throwable $e) { $blocked = []; }

            $stmtSM = $pdo->prepare("SELECT machine_id FROM sortie_machines WHERE sortie_id = ?");
            $stmtSM->execute([$sortie_id]);
            $already = $stmtSM->fetchAll(PDO::FETCH_COLUMN, 0);
            $already = array_map('intval', $already ?: []);
            foreach ($owned as $ow) {
                $mid = (int)$ow['machine_id'];
                $uid = (int)$ow['user_id'];
                if (!in_array($mid, $already, true) && !in_array($mid, $blocked, true)) {
                    $hasIsMemberOwned = in_array('is_member_owned', $smCols, true);
                    $hasProvidedBy = in_array('provided_by_user_id', $smCols, true);
                    if ($hasIsMemberOwned && $hasProvidedBy) {
                        $pdo->prepare("INSERT INTO sortie_machines (sortie_id, machine_id, is_member_owned, provided_by_user_id) VALUES (?,?,1,?)")->execute([$sortie_id, $mid, $uid]);
                    } else {
                        $pdo->prepare("INSERT INTO sortie_machines (sortie_id, machine_id) VALUES (?,?)")->execute([$sortie_id, $mid]);
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
    // ignorer si table absente
}

// Recharger les machines après auto-association
$stmt = $pdo->prepare($sqlMachines);
$stmt->execute([$sortie_id]);
$machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Photos ---
$stmt = $pdo->prepare("
    SELECT *
    FROM sortie_photos
    WHERE sortie_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$sortie_id]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$uploadUrl = 'uploads/sorties';

// --- Affectations existantes ---
$stmt = $pdo->prepare("
    SELECT sa.*, sm.id AS sortie_machine_id, m.nom AS machine_nom, m.immatriculation,
           u.nom AS user_nom, u.prenom AS user_prenom
    FROM sortie_assignations sa
    JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id
    JOIN machines m ON m.id = sm.machine_id
    JOIN users u ON u.id = sa.user_id
    WHERE sm.sortie_id = ?
");
$stmt->execute([$sortie_id]);
$assignations_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map : sortie_machine_id => [user_id1, user_id2]
$assignations = [];
foreach ($assignations_rows as $row) {
    $smid = (int)$row['sortie_machine_id'];
    if (!isset($assignations[$smid])) {
        $assignations[$smid] = [];
    }
    $assignations[$smid][] = (int)$row['user_id'];
}

// Précharger d'éventuels invités déjà saisis (un invité possible par machine)
$existing_guests = [];
try {
    // Construire la liste des smid
    $sm_ids_for_guests = array_map(fn($m) => (int)$m['sortie_machine_id'], $machines);
    $sm_ids_for_guests = array_values(array_unique(array_filter($sm_ids_for_guests)));
    if (!empty($sm_ids_for_guests)) {
        $in = implode(',', array_fill(0, count($sm_ids_for_guests), '?'));
        $stmtG = $pdo->prepare("SELECT sortie_machine_id, guest_name FROM sortie_assignations_guests WHERE sortie_machine_id IN ($in)");
        $stmtG->execute($sm_ids_for_guests);
        foreach ($stmtG->fetchAll(PDO::FETCH_ASSOC) as $gr) {
            $existing_guests[(int)$gr['sortie_machine_id']] = (string)$gr['guest_name'];
        }
    }
} catch (Throwable $e) {
    $existing_guests = [];
}

$flash = null;

// --- TRAITEMENT FORMULAIRE : AFFECTATIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    try {
        // Créer la table de logs si elle n'existe pas (AVANT la transaction)
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS affectations_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sortie_id INT NOT NULL,
                sortie_machine_id INT NOT NULL,
                user_id INT NOT NULL,
                assigned_user_id INT NULL,
                assigned_guest_name VARCHAR(255) NULL,
                slot_number TINYINT NOT NULL,
                action ENUM('add', 'remove', 'update') NOT NULL,
                modified_by_user_id INT NOT NULL,
                modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                notes TEXT NULL,
                INDEX idx_sortie (sortie_id),
                INDEX idx_modified_at (modified_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            // Table existe déjà, c'est OK
        }
        
        $pdo->beginTransaction();
        
        // Récupérer les affectations soumises
        $assign_post = $_POST['assign'] ?? [];
        $has_new_assignments = false;
        
        // Vérifier s'il y a au moins une affectation valide
        foreach ($assign_post as $sm_id => $user_ids) {
            if (!is_array($user_ids)) continue;
            $user_ids = array_filter($user_ids, fn($v) => $v !== '' && $v !== null && (int)$v > 0);
            if (!empty($user_ids)) {
                $has_new_assignments = true;
                break;
            }
        }
        
        // Ne supprimer les anciennes affectations QUE si on a de nouvelles à enregistrer
        if ($has_new_assignments) {
            // Supprimer affectations existantes
            $stmt = $pdo->prepare("DELETE FROM sortie_assignations WHERE sortie_machine_id IN (SELECT id FROM sortie_machines WHERE sortie_id = ?)");
            $stmt->execute([$sortie_id]);
        }
        
        // Insérer les nouvelles affectations
        $sent_count = 0;
        $failed_count = 0;
        
        $slot_counter = [];
        foreach ($assign_post as $sm_id => $user_ids) {
            $sm_id = (int)$sm_id;
            if ($sm_id <= 0 || !is_array($user_ids)) continue;
            
            // Filtrer et limiter à 2
            $user_ids = array_filter($user_ids, fn($v) => $v !== '' && $v !== null);
            $user_ids = array_map('intval', $user_ids);
            $user_ids = array_unique($user_ids);
            $user_ids = array_slice($user_ids, 0, 2);
            
            if (!isset($slot_counter[$sm_id])) {
                $slot_counter[$sm_id] = 1;
            }
            
            foreach ($user_ids as $uid) {
                if ($uid <= 0) continue;
                
                // Insérer l'affectation
                $pdo->prepare("INSERT INTO sortie_assignations (sortie_machine_id, user_id, role_onboard) VALUES (?, ?, 'passager')")
                    ->execute([$sm_id, $uid]);
                
                // Logger l'affectation
                try {
                    $pdo->prepare("INSERT INTO affectations_logs 
                        (sortie_id, sortie_machine_id, user_id, assigned_user_id, slot_number, action, modified_by_user_id) 
                        VALUES (?, ?, ?, ?, ?, 'add', ?)")
                        ->execute([
                            $sortie_id, 
                            $sm_id, 
                            $_SESSION['user_id'] ?? 0, 
                            $uid, 
                            $slot_counter[$sm_id],
                            $_SESSION['user_id'] ?? 0
                        ]);
                } catch (Throwable $e) {
                    error_log("Erreur log affectation: " . $e->getMessage());
                }
                
                $slot_counter[$sm_id]++;
            }
        }
        
        // Récupérer toutes les affectations qu'on vient de faire
        $stmt = $pdo->prepare("
            SELECT sa.user_id, u.email, u.prenom, u.nom, 
                   m.nom AS machine_nom, m.immatriculation, sa.sortie_machine_id,
                   u2.prenom AS coequipier_prenom, u2.nom AS coequipier_nom
            FROM sortie_assignations sa
            JOIN users u ON u.id = sa.user_id
            JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id
            JOIN machines m ON m.id = sm.machine_id
            LEFT JOIN sortie_assignations sa2 ON sa2.sortie_machine_id = sa.sortie_machine_id AND sa2.user_id != sa.user_id
            LEFT JOIN users u2 ON u2.id = sa2.user_id
            WHERE sm.sortie_id = ?
            ORDER BY u.nom, u.prenom
        ");
        $stmt->execute([$sortie_id]);
        $assignations_to_notify = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Détecter les inscrits NON affectés pour leur envoyer un mail d'excuse
        $stmt_all_inscrits = $pdo->prepare("SELECT si.user_id, u.email, u.prenom, u.nom 
            FROM sortie_inscriptions si 
            JOIN users u ON u.id = si.user_id 
            WHERE si.sortie_id = ?");
        $stmt_all_inscrits->execute([$sortie_id]);
        $all_inscrits = $stmt_all_inscrits->fetchAll(PDO::FETCH_ASSOC);
        
        $affectes_ids = array_column($assignations_to_notify, 'user_id');
        $non_affectes = array_filter($all_inscrits, fn($i) => !in_array($i['user_id'], $affectes_ids));
        
        // Envoyer mails de confirmation aux AFFECTÉS
        foreach ($assignations_to_notify as $aff) {
            if (empty($aff['email'])) {
                $failed_count++;
                continue;
            }
            
            $full_name = trim($aff['prenom'] . ' ' . $aff['nom']);
            $machine_label = $aff['machine_nom'] . ' (' . $aff['immatriculation'] . ')';
            
            // Coéquipier
            $coequipier_info = '';
            if (!empty($aff['coequipier_prenom']) || !empty($aff['coequipier_nom'])) {
                $coequipier_nom = trim(($aff['coequipier_prenom'] ?? '') . ' ' . ($aff['coequipier_nom'] ?? ''));
                $coequipier_info = "<br><strong>👥 Coéquipier :</strong> " . htmlspecialchars($coequipier_nom);
                $coequipier_text = "\nCoéquipier : $coequipier_nom";
            } else {
                $coequipier_text = '';
            }
            
            // Générer un token d'action si absent
            $stmt_token = $pdo->prepare("SELECT action_token FROM sortie_inscriptions WHERE sortie_id = ? AND user_id = ? LIMIT 1");
            $stmt_token->execute([$sortie_id, $aff['user_id']]);
            $token_row = $stmt_token->fetch(PDO::FETCH_ASSOC);
            $action_token = $token_row['action_token'] ?? bin2hex(random_bytes(32));
            
            if (!$token_row) {
                $upd = $pdo->prepare("UPDATE sortie_inscriptions SET action_token = ? WHERE sortie_id = ? AND user_id = ?");
                $upd->execute([$action_token, $sortie_id, $aff['user_id']]);
            }
            
            // Construire les URLs d'action
            $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $baseUrl = $scheme . $host . $baseDir;
            
            $action_annuler    = htmlspecialchars($baseUrl . '/action_inscription.php?action=annuler&token=' . urlencode($action_token), ENT_QUOTES, 'UTF-8');
            $action_machine    = htmlspecialchars($baseUrl . '/action_inscription.php?action=changer_machine&token=' . urlencode($action_token), ENT_QUOTES, 'UTF-8');
            $action_coequipier = htmlspecialchars($baseUrl . '/action_inscription.php?action=changer_coequipier&token=' . urlencode($action_token), ENT_QUOTES, 'UTF-8');
            
            // Créer fichier iCalendar (.ics)
            $ics_content = generate_sortie_ics($sortie, $destination_oaci);
            $ics_filename = 'sortie_' . $sortie_id . '_' . date('Ymd', strtotime($sortie['date_sortie'])) . '.ics';
            
            $subject = 'Confirmation sortie ULM : ' . htmlspecialchars($sortie['titre']);
            $html_body = "
                <html>
                <head><meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #222; }
                    .container { max-width: 600px; margin: 0 auto; }
                    .header { background: linear-gradient(135deg, #004b8d, #00a0c6); color: #ffffff; padding: 20px; border-radius: 8px 8px 0 0; }
                    .content { padding: 20px; background: #f7f9fc; }
                    .actions { background: #ffffff; padding: 20px; border-radius: 0 0 8px 8px; }
                    .btn { display: inline-block; padding: 12px 18px; border-radius: 6px; font-weight: 600; text-decoration: none; margin: 5px 5px 5px 0; }
                    .btn-cancel { background: #b00020; color: #ffffff; }
                    .btn-machine { background: #115e38; color: #ffffff; }
                    .btn-coequipier { background: #1a73e8; color: #ffffff; }
                    .info { margin: 16px 0; padding: 12px; background: #e8f4f8; border-left: 4px solid #00a0c6; border-radius: 4px; }
                </style>
                </head>
                <body>
                <div class='container'>
                    <div class='header'>
                        <h2 style='margin: 0;'>✈️ Confirmation de votre participation</h2>
                    </div>
                    
                    <div class='content'>
                        <p>Bonjour <strong>" . htmlspecialchars($full_name) . "</strong>,</p>
                        <p>Votre participation à la sortie ULM <strong>" . htmlspecialchars($sortie['titre']) . "</strong> est confirmée !</p>
                        
                        <div class='info'>
                            <strong>📅 Date :</strong> " . htmlspecialchars($sortie['date_sortie']) . "<br>
                            <strong>✈️ Machine :</strong> " . htmlspecialchars($machine_label) . $coequipier_info . "
                        </div>
                        
                        <p>Merci d'être présent à l'heure prévue pour le briefing.</p>
                    </div>
                    
                    <div class='actions'>
                        <p style='margin: 0 0 10px 0;'><strong>Gérer votre participation :</strong></p>
                        <div style='margin-bottom: 20px;'>
                            <a href='" . $action_annuler . "' class='btn btn-cancel' style='color: #ffffff !important;'>❌ Annuler</a>
                            <a href='" . $action_machine . "' class='btn btn-machine' style='color: #ffffff !important;'>🛠️ Changer machine</a>
                            <a href='" . $action_coequipier . "' class='btn btn-coequipier' style='color: #ffffff !important;'>👥 Changer coéquipier</a>
                        </div>
                        <p style='font-size: 12px; color: #666;'>
                            Ce mail a été envoyé automatiquement. Veuillez ne pas répondre directement.<br>
                            Pour toute question, contactez: <strong>info@clubulmevasion.fr</strong>
                        </p>
                    </div>
                </div>
                </body>
                </html>
            ";
            
            $text_body = "Bonjour $full_name,\n\n"
                       . "Votre participation à la sortie ULM " . $sortie['titre'] . " est confirmée !\n"
                       . "Date : " . $sortie['date_sortie'] . "\n"
                       . "Machine : $machine_label" . $coequipier_text . "\n\n"
                       . "Merci d'être présent à l'heure prévue pour le briefing.\n\n"
                       . "Gérer votre participation :\n"
                       . "- Annuler : " . strip_tags($action_annuler) . "\n"
                       . "- Changer machine : " . strip_tags($action_machine) . "\n"
                       . "- Changer coéquipier : " . strip_tags($action_coequipier) . "\n\n"
                       . "À bientôt,\nLe club ULM";
            
            $result = gestnav_send_mail($pdo, $aff['email'], $subject, $html_body, $text_body, [$ics_filename => $ics_content]);
            if ($result['success']) {
                $sent_count++;
            } else {
                $failed_count++;
            }
        }
        
        // Envoyer emails d'excuse aux NON-AFFECTÉS et les marquer prioritaires
        $excuse_sent = 0;
        $excuse_failed = 0;
        
        foreach ($non_affectes as $na) {
            if (empty($na['email'])) {
                $excuse_failed++;
                continue;
            }
            
            $full_name = trim($na['prenom'] . ' ' . $na['nom']);
            
            // Marquer comme prioritaire pour la prochaine sortie
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS sortie_priorites (
                    user_id INT PRIMARY KEY,
                    active TINYINT(1) DEFAULT 1,
                    reason VARCHAR(255) DEFAULT 'Non affecté sortie précédente',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB");
                
                $stmt_prio = $pdo->prepare("INSERT INTO sortie_priorites (user_id, active, reason) 
                    VALUES (?, 1, 'Non affecté sortie précédente') 
                    ON DUPLICATE KEY UPDATE active = 1, reason = 'Non affecté sortie précédente'");
                $stmt_prio->execute([$na['user_id']]);
            } catch (Throwable $e) {
                error_log("Erreur création priorité: " . $e->getMessage());
            }
            
            // Vérifier si inscrit à la prochaine sortie
            $inscrit_next = false;
            $next_sortie_info = '';
            if ($next_sortie) {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM sortie_inscriptions WHERE sortie_id = ? AND user_id = ?");
                $stmt_check->execute([$next_sortie['id'], $na['user_id']]);
                $inscrit_next = (bool)$stmt_check->fetchColumn();
                
                if ($inscrit_next) {
                    $next_sortie_info = '<div style="margin-top: 20px; padding: 15px; background: #e7f7ec; border-left: 4px solid #22c55e; border-radius: 4px;">
                        <strong style="color: #166534;">✅ Bonne nouvelle !</strong><br>
                        Tu es déjà inscrit(e) à la prochaine sortie "<strong>' . htmlspecialchars($next_sortie['titre']) . '</strong>" 
                        le ' . htmlspecialchars(date('d/m/Y', strtotime($next_sortie['date_sortie']))) . '.<br>
                        Tu seras <strong style="color: #166534;">PRIORITAIRE</strong> pour cette sortie ! 🎯
                    </div>';
                } else {
                    $next_sortie_info = '<div style="margin-top: 20px; padding: 15px; background: #fff8e1; border-left: 4px solid #f59e0b; border-radius: 4px;">
                        <strong style="color: #92400e;">💡 Prochaine sortie disponible</strong><br>
                        "<strong>' . htmlspecialchars($next_sortie['titre']) . '</strong>" 
                        le ' . htmlspecialchars(date('d/m/Y', strtotime($next_sortie['date_sortie']))) . '.<br>
                        Inscris-toi pour être <strong style="color: #92400e;">PRIORITAIRE</strong> ! 🎯
                    </div>';
                }
            }
            
            $subject = '😔 Sortie ULM complète : ' . htmlspecialchars($sortie['titre']);
            $html_body = "
                <html>
                <head><meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #222; }
                    .container { max-width: 600px; margin: 0 auto; }
                    .header { background: linear-gradient(135deg, #b00020, #d32f2f); color: #ffffff; padding: 20px; border-radius: 8px 8px 0 0; }
                    .content { padding: 20px; background: #f7f9fc; }
                    .info { margin: 16px 0; padding: 12px; background: #fde8e8; border-left: 4px solid #b00020; border-radius: 4px; }
                    .priority-badge { display: inline-block; padding: 8px 16px; background: #22c55e; color: white; border-radius: 999px; font-weight: 700; margin: 10px 0; }
                    .footer { background: #ffffff; padding: 20px; border-radius: 0 0 8px 8px; text-align: center; }
                </style>
                </head>
                <body>
                <div class='container'>
                    <div class='header'>
                        <h2 style='margin: 0;'>😔 Sortie complète</h2>
                    </div>
                    
                    <div class='content'>
                        <p>Bonjour <strong>" . htmlspecialchars($full_name) . "</strong>,</p>
                        
                        <div class='info'>
                            Nous sommes sincèrement désolés de t'informer que la sortie <strong>" . htmlspecialchars($sortie['titre']) . "</strong> 
                            du " . htmlspecialchars($sortie['date_sortie']) . " est complète et que nous n'avons pas pu t'affecter à une machine.
                        </div>
                        
                        <p>Toutes les places disponibles ont été attribuées en fonction des capacités de nos machines et des contraintes d'organisation.</p>
                        
                        <div style='text-align: center; margin: 20px 0;'>
                            <div class='priority-badge'>🎯 TU ES MAINTENANT PRIORITAIRE !</div>
                        </div>
                        
                        <p><strong>Pour compenser cette déception</strong>, tu bénéficieras d'une <strong style='color: #22c55e;'>priorité automatique</strong> 
                        sur la prochaine sortie à laquelle tu seras inscrit(e).</p>
                        
                        " . $next_sortie_info . "
                        
                        <p style='margin-top: 20px;'>Nous faisons notre maximum pour que chaque membre puisse participer régulièrement aux sorties. 
                        Le système de priorité garantit une répartition équitable sur le long terme.</p>
                        
                        <p>Merci de ta compréhension et à très bientôt dans les airs ! ✈️</p>
                    </div>
                    
                    <div class='footer'>
                        <p style='font-size: 12px; color: #666; margin: 0;'>
                            Ce mail a été envoyé automatiquement.<br>
                            Pour toute question : <strong>info@clubulmevasion.fr</strong>
                        </p>
                    </div>
                </div>
                </body>
                </html>
            ";
            
            $text_body = "Bonjour $full_name,\n\n"
                       . "Nous sommes désolés de t'informer que la sortie " . $sortie['titre'] . " du " . $sortie['date_sortie'] . " est complète.\n\n"
                       . "🎯 TU ES MAINTENANT PRIORITAIRE !\n\n"
                       . "Tu bénéficieras d'une priorité automatique sur la prochaine sortie à laquelle tu seras inscrit(e).\n\n"
                       . "Merci de ta compréhension.\n\n"
                       . "Le club ULM Evasion";
            
            $result = gestnav_send_mail($pdo, $na['email'], $subject, $html_body, $text_body);
            if ($result['success']) {
                $excuse_sent++;
            } else {
                $excuse_failed++;
            }
        }
        
        $pdo->commit();
        
        $flash_text = "Affectations enregistrées. $sent_count confirmation(s) envoyée(s).";
        if ($excuse_sent > 0) {
            $flash_text .= " $excuse_sent email(s) d'excuse envoyé(s) aux non-affectés (désormais prioritaires).";
        }
        if ($failed_count + $excuse_failed > 0) {
            $flash_text .= " " . ($failed_count + $excuse_failed) . " en erreur.";
        }
        
        $flash = [
            'type' => 'success',
            'text' => $flash_text
        ];
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flash = [
            'type' => 'error',
            'text' => "Erreur: " . $e->getMessage()
        ];
    }
    
    // Recharger affectations
    $stmt = $pdo->prepare("
        SELECT sa.*, sm.id AS sortie_machine_id, u.id AS user_id
        FROM sortie_assignations sa
        JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id
        JOIN users u ON u.id = sa.user_id
        WHERE sm.sortie_id = ?
    ");
    $stmt->execute([$sortie_id]);
    $assignations_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $assignations = [];
    foreach ($assignations_rows as $row) {
        $smid = (int)$row['sortie_machine_id'];
        if (!isset($assignations[$smid])) $assignations[$smid] = [];
        $assignations[$smid][] = (int)$row['user_id'];
    }
}
?>

<?php // Inclure le header APRÈS les traitements pour permettre les redirections correctement
require 'header.php'; ?>

<style>
    .sortie-detail-page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 2rem 1rem 3rem;
    }
    .sortie-detail-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 2rem;
        padding: 1.5rem 1.75rem;
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #004b8d, #00a0c6);
        color: #fff;
        box-shadow: 0 12px 30px rgba(0,0,0,0.25);
    }
    .sortie-detail-header h1 {
        font-size: 1.6rem;
        margin: 0;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    .sortie-detail-header p {
        margin: 0.25rem 0 0;
        opacity: 0.9;
        font-size: 0.95rem;
    }
    .sortie-detail-header-icon {
        font-size: 2.4rem;
        opacity: 0.9;
    }
    .btn-primary-gestnav {
        border: none;
        border-radius: 999px;
        padding: 0.55rem 1.3rem;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        background: linear-gradient(135deg, #004b8d, #00a0c6);
        color: #fff;
        box-shadow: 0 8px 16px rgba(0, 75, 141, 0.35);
        transition: transform 0.1s ease, box-shadow 0.1s ease, filter 0.1s ease;
    }
    .btn-primary-gestnav:hover {
        filter: brightness(1.05);
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(0, 75, 141, 0.4);
    }
    .btn-secondary-link {
        border: none;
        padding: 0.4rem 0.8rem;
        border-radius: 999px;
        background: transparent;
        font-size: 0.8rem;
        color: #fff;
        cursor: pointer;
        text-decoration: underline;
    }
    .card {
        background: #ffffff;
        border-radius: 1.25rem;
        padding: 1.75rem 1.5rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.03);
        margin-bottom: 1.5rem;
    }
    .card-title {
        font-size: 1.15rem;
        font-weight: 600;
        margin: 0 0 0.35rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .card-subtitle {
        font-size: 0.85rem;
        color: #666;
        margin: 0.15rem 0 0.75rem;
    }
    .badge-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.2rem 0.6rem;
        border-radius: 999px;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        background: rgba(0, 75, 141, 0.08);
        color: #004b8d;
        font-weight: 600;
    }
    .flash-message {
        margin-bottom: 1rem;
        font-size: 0.9rem;
        padding: 0.6rem 0.8rem;
        border-radius: 999px;
        background: #e7f7ec;
        color: #0a8a0a;
    }
    .flash-message.error {
        background: #fde8e8;
        color: #b02525;
    }
    .detail-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
        gap: 1.5rem;
    }
    @media (max-width: 900px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }
    .detail-item {
        margin-bottom: 0.35rem;
        font-size: 0.9rem;
    }
    .detail-label {
        font-weight: 600;
        margin-right: 0.25rem;
    }
    .briefing-text, .repas-text {
        color: #0b3d91; /* bleu foncé lisible */
        font-weight: 700;
    }
    /* Forcer l'alignement à gauche avec une spécificité élevée */
    .detail-grid > div:first-child .detail-item .briefing-text,
    .detail-grid > div:first-child .detail-item .repas-text {
        text-align: left !important;
        display: block;
        width: 100%;
    }
    .photos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 0.75rem;
        margin-top: 0.5rem;
    }
    .photos-grid img {
        width: 100%;
        border-radius: 0.75rem;
        display: block;
        object-fit: cover;
        max-height: 140px;
    }
    .inscrits-list {
        font-size: 0.9rem;
    }
    .inscrit-item {
        display: flex;
        justify-content: space-between;
        padding: 0.3rem 0;
        border-bottom: 1px solid #eef1f7;
    }
    .inscrit-item:last-child {
        border-bottom: none;
    }
    .inscrit-name {
        font-weight: 500;
    }
    .inscrit-email {
        color: #666;
        font-size: 0.85rem;
    }

    .machines-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1rem;
    }
    .machine-card {
        border: 1px solid #e6ebf2;
        border-radius: 1rem;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        display: flex;
        flex-direction: column;
    }
    .machine-card .machine-img { width:100%; aspect-ratio:16/9; object-fit:cover; background:#f2f6fc; }
    .machine-card .machine-body { padding:0.9rem; }
    .machine-header {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 0.5rem;
        gap: 0.5rem;
    }
    .machine-title {
        font-weight: 600;
    }
    .machine-sub {
        font-size: 0.8rem;
        color: #666;
    }
    .machine-selects {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        margin-top: 0.4rem;
    }
    .machine-selects label {
        font-size: 0.85rem;
        margin-bottom: 0.15rem;
    }
    select {
        width: 100%;
        border-radius: 999px;
        border: 1px solid #d0d7e2;
        padding: 0.4rem 0.7rem;
        font-size: 0.85rem;
        background: #fff;
        outline: none;
    }
    select:focus {
        border-color: #00a0c6;
        box-shadow: 0 0 0 2px rgba(0,160,198,0.2);
    }
    /* Couleurs pour les options dans les dropdowns d'affectation */
    .option-priority {
        background-color: #fde8e8 !important;
        color: #b00020 !important;
        font-weight: 600;
    }
    .option-previous {
        background-color: #e3f2fd !important;
        color: #0277bd !important;
    }
    .form-footer {
        margin-top: 1rem;
        text-align: right;
    }
</style>

<div class="sortie-detail-page">
    <div class="sortie-detail-header">
        <div>
            <h1>Détail de la sortie</h1>
            <p><?= htmlspecialchars($sortie['titre']) ?></p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
            <button class="btn-secondary-link" onclick="window.location.href='sorties.php'">
                ← Retour aux sorties
            </button>
            <button class="btn-primary-gestnav" onclick="window.location.href='sortie_edit.php?id=<?= (int)$sortie_id ?>'">
                ✏️ Éditer la sortie
            </button>
        </div>
    </div>

    <?php if (!empty($preinscriptions)): ?>
    <div class="card">
        <h2 class="card-title">Pré-inscriptions (préférences)</h2>
        <p class="card-subtitle">Soumissions des membres: préférences de machine et/ou de coéquipier. À titre indicatif pour aider aux affectations.</p>
        <div style="overflow-x: auto;">
            <table class="table table-hover" style="margin:0;">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:200px;">Membre</th>
                        <th>Préférence machine</th>
                        <th>Préférence coéquipier</th>
                        <th style="min-width:300px;">Notes</th>
                        <th style="white-space:nowrap;">Soumis le</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preinscriptions as $p): ?>
                    <?php
                        // Vérifier priorité pour cette pré-inscription
                        $isPriority = false;
                        $isInscritNext = false;
                        $wasInPrevious = false;
                        try {
                            $stP = $pdo->prepare('SELECT active FROM sortie_priorites WHERE user_id = ?');
                            $stP->execute([(int)($p['u_id'] ?? 0)]);
                            $isPriority = (bool)($stP->fetchColumn() ?? 0);
                            
                            // Vérifier si inscrit à la prochaine sortie
                            if ($isPriority && $next_sortie) {
                                $stNext = $pdo->prepare('SELECT COUNT(*) FROM sortie_inscriptions WHERE sortie_id = ? AND user_id = ?');
                                $stNext->execute([$next_sortie['id'], (int)($p['u_id'] ?? 0)]);
                                $isInscritNext = (bool)$stNext->fetchColumn();
                            }
                            
                            // Vérifier si inscrit ou affecté à la sortie précédente
                            if ($previous_sortie) {
                                $stPrev = $pdo->prepare('SELECT COUNT(*) FROM sortie_inscriptions WHERE sortie_id = ? AND user_id = ?');
                                $stPrev->execute([$previous_sortie['id'], (int)($p['u_id'] ?? 0)]);
                                $wasInPrevious = (bool)$stPrev->fetchColumn();
                            }
                        } catch (Throwable $e) { $isPriority = false; }
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars(trim(($p['u_prenom'] ?? '').' '.($p['u_nom'] ?? ''))) ?></strong>
                            <?php if ($isPriority): ?>
                                <span class="badge-pill" style="background:#fde8e8;color:#b00020;border:1px solid #f5b5b5; margin-left:.4rem;" title="Prioritaire sur la prochaine sortie (était inscrit à la précédente)">🎯 PRIORITAIRE</span>
                                <?php if ($isInscritNext): ?>
                                    <span class="badge-pill" style="background:#e7f7ec;color:#166534;border:1px solid #bbf7d0; margin-left:.4rem;" title="Inscrit à la prochaine sortie">✅ Inscrit prochaine</span>
                                <?php endif; ?>
                            <?php elseif ($wasInPrevious): ?>
                                <span class="badge-pill" style="background:#e3f2fd;color:#0277bd;border:1px solid #90caf9; margin-left:.4rem;" title="Était inscrit à la précédente sortie">📅 Précédente sortie</span>
                            <?php endif; ?>
                            <div style="color:#666;font-size:.85rem;"><?= htmlspecialchars($p['u_email'] ?? '') ?></div>
                        </td>
                        <td>
                            <?php if (!empty($p['m_nom'])): ?>
                                <?= htmlspecialchars($p['m_nom']) ?><?= !empty($p['m_immat']) ? ' ('.htmlspecialchars($p['m_immat']).')' : '' ?>
                            <?php else: ?>
                                <span style="color:#999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($p['c_nom']) || !empty($p['c_prenom'])): ?>
                                <?= htmlspecialchars(trim(($p['c_prenom'] ?? '').' '.($p['c_nom'] ?? ''))) ?>
                                <?php if (!empty($p['c_email'])): ?><div style="color:#666;font-size:.85rem;"><?= htmlspecialchars($p['c_email']) ?></div><?php endif; ?>
                            <?php else: ?>
                                <span style="color:#999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($p['notes'])): ?>
                                <small style="color:#555; white-space: pre-wrap; word-break: break-word; display: block; max-width: 300px;"><?= htmlspecialchars($p['notes']) ?></small>
                            <?php else: ?>
                                <span style="color:#999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['created_at'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="flash-message <?= $flash['type'] === 'error' ? 'error' : '' ?>">
            <?= htmlspecialchars($flash['text']) ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['unavailable'])): ?>
        <div class="flash-message">
            La machine a été rendue indisponible pour cette sortie.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['preapplied'])): ?>
        <div class="flash-message <?= ($_GET['preapplied'] == '1') ? '' : 'error' ?>">
            <?= htmlspecialchars($_GET['msg'] ?? (($_GET['preapplied']=='1') ? 'Pré-inscription appliquée.' : 'Action impossible.')) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 class="card-title">
            Informations générales
            <span class="badge-pill"><?= htmlspecialchars(ucfirst($sortie['statut'])) ?></span>
        </h2>

        <div class="detail-grid">
            <div class="text-start" style="text-align:left !important;">
                <div class="detail-item">
                    <span class="detail-label">Date / heure :</span>
                    <?php if (!empty($sortie['is_multi_day']) && !empty($sortie['date_fin'])): ?>
                        <?= htmlspecialchars('Du ' . date('d/m/Y', strtotime($sortie['date_sortie'])) . ' au ' . date('d/m/Y', strtotime($sortie['date_fin']))) ?>
                    <?php else: ?>
                        <?= htmlspecialchars($sortie['date_sortie']) ?>
                    <?php endif; ?>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Créée par :</span>
                    <?= htmlspecialchars(trim(($sortie['createur_prenom'] ?? '') . ' ' . ($sortie['createur_nom'] ?? ''))) ?>
                </div>
                <?php if (!empty($destination_label)): ?>
                    <div class="detail-item">
                        <span class="detail-label">Destination :</span>
                        <span style="display:inline-flex;align-items:center;gap:0.4rem;">
                            <span style="background:#004b8d;color:#fff;padding:0.18rem 0.55rem;border-radius:999px;font-size:0.6rem;letter-spacing:0.05em;font-weight:600;">DEST</span>
                            <strong style="color:#004b8d;"><?= htmlspecialchars($destination_label) ?></strong>
                        </span>
                    </div>
                    <?php if ($destination_oaci !== '' || ($hasUlmBaseId && !empty($sortie['ulm_base_id']))): ?>
                    <div class="detail-item" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                        <span class="detail-label">Distance (<?= htmlspecialchars($origin_oaci ?? 'LFQJ') ?> → <?= htmlspecialchars($destination_oaci) ?>) :</span>
                        <?php if (!empty($distance_km)): ?>
                            <?php $distance_nm = (int)round(((int)$distance_km) / 1.852); ?>
                            <span><strong><?= (int)$distance_km ?> km</strong> (<?= $distance_nm ?> NM)</span>
                            <span class="badge-pill" title="Estimation à vol d'oiseau">approx.</span>
                        <?php else: ?>
                            <span class="badge-pill" style="background:#f2f6fc;color:#6c757d;" title="Coordonnées non disponibles">indisponible</span>
                        <?php endif; ?>
                        <?php if (!empty($eta_text)): ?>
                            <span class="badge-pill" style="background:#f2f6fc;color:#004b8d;" title="Temps de vol théorique sans vent ni déroutement">
                                <?= htmlspecialchars($eta_text) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($sortie['description'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Description courte :</span>
                        <?= htmlspecialchars($sortie['description']) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($sortie['repas_prevu'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Repas :</span>
                        <span class="badge-pill" style="background:#e7f7ec;color:#0a8a0a;">Repas prévu</span>
                    </div>
                <?php endif; ?>
                            <?php if (!empty($sortie['details'])): ?>
                                <div class="detail-item" style="margin-top:0.5rem;">
                                    <span class="detail-label">Briefing / détails :</span><br>
                                    <div class="briefing-text" style="margin-top:0.25rem;white-space:pre-wrap;display:block;width:100%;text-align:left !important;direction:ltr;">
                                        <?= gn_linkify($sortie['details']) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                <?php if (!empty($sortie['repas_details'])): ?>
                    <div class="detail-item" style="margin-top:0.5rem;">
                        <span class="detail-label">Infos repas :</span><br>
                        <div class="repas-text" style="margin-top:0.25rem;white-space:pre-wrap;display:block;width:100%;text-align:left !important;direction:ltr;">
                            <?= gn_linkify($sortie['repas_details']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="detail-item" style="margin-top:0.75rem;">
                    <div style="padding:0.6rem 0.8rem;border:1px solid #f0d48a;background:#fff8e1;border-radius:0.75rem;color:#6b5800;">
                        <strong>Note organisation&nbsp;:</strong> le club vise <strong>2 sorties par mois</strong>. Les membres inscrits aux deux sorties qui n'ont pas pu participer à la première sont <strong>prioritaires sur la seconde</strong>, sous réserve de s'y être inscrits.
                    </div>
                </div>
            </div>

            <div>
                <div class="detail-item">
                    <span class="detail-label">Machines prévues :</span>
                    <?php if ($machines): ?>
                        <?= implode(', ', array_map(fn($m) => htmlspecialchars($m['nom']), $machines)) ?>
                    <?php else: ?>
                        Aucune machine associée.
                    <?php endif; ?>
                </div>

                <?php if ($photos): ?>
                    <div class="detail-item" style="margin-top:0.5rem;">
                        <span class="detail-label">Photos :</span>
                        <div class="photos-grid">
                            <?php foreach ($photos as $p): ?>
                                <img src="<?= $uploadUrl . '/' . htmlspecialchars($p['filename']) ?>" alt="">
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                // Carte: afficher une carte centrée sur la destination si coordonnées disponibles
                $__destCoords = null;
                // Priorité aux bases ULM si ulm_base_id est renseigné
                if ($hasUlmBaseId && !empty($sortie['ulm_base_id'])) {
                    try {
                        $__c = gn_get_ulm_base_coords($pdo, (int)$sortie['ulm_base_id']);
                        if ($__c && isset($__c['lat']) && isset($__c['lon'])) {
                            $__destCoords = $__c;
                        }
                    } catch (Throwable $e) { /* no-op */ }
                }
                // Sinon, essayer avec destination_oaci
                if (!$__destCoords && !empty($destination_oaci)) {
                    try {
                        $__c = gn_get_oaci_coords($pdo, $destination_oaci);
                        if ($__c && isset($__c['lat']) && isset($__c['lon'])) {
                            $__destCoords = $__c;
                        }
                    } catch (Throwable $e) { /* no-op */ }
                }
                ?>
                <?php if ($__destCoords): ?>
                    <div class="detail-item" style="margin-top:0.75rem;">
                        <span class="detail-label">Localisation :</span>
                        <div id="map-destination" style="height:320px;border:1px solid #e6ebf2;border-radius:0.75rem;"></div>
                    </div>
                    <link rel="stylesheet" href="assets/leaflet/leaflet.css" />
                    <script src="assets/leaflet/leaflet.js"></script>
                    <script>
                    (function(){
                        var lat = <?= json_encode($__destCoords['lat']) ?>;
                        var lon = <?= json_encode($__destCoords['lon']) ?>;
                        function initMap(){
                            if (typeof L === 'undefined') {
                                console.error('Leaflet non chargé');
                                var el = document.getElementById('map-destination');
                                if (el) {
                                    var zoom = 10;
                                    var osmUrl = 'https://www.openstreetmap.org/?mlat=' + encodeURIComponent(lat) + '&mlon=' + encodeURIComponent(lon) + '#map=' + zoom + '/' + encodeURIComponent(lat) + '/' + encodeURIComponent(lon);
                                    el.innerHTML = '<div style="padding:.4rem 0;color:#6c757d;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">' +
                                                   '<span>Carte indisponible (Leaflet non chargé)</span>' +
                                                   '<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="' + osmUrl + '">Ouvrir dans OpenStreetMap</a>' +
                                                   '</div>' +
                                                   '<iframe src="' + osmUrl + '" style="width:100%;height:320px;border:1px solid #e6ebf2;border-radius:0.75rem;" loading="lazy"></iframe>';
                                }
                                return;
                            }
                            var map = L.map('map-destination', { zoomControl: true, attributionControl: true }).setView([lat, lon], 10);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                maxZoom: 18,
                                attribution: '&copy; OpenStreetMap contributors'
                            }).addTo(map);
                            var popupText = <?= json_encode($destination_label !== '' ? $destination_label : 'Destination') ?>;
                            var canUseImageMarker = !!(L.Icon && L.Icon.Default && L.Icon.Default.prototype && L.Icon.Default.prototype.options && L.Icon.Default.prototype.options.iconUrl);
                            if (canUseImageMarker) {
                                var marker = L.marker([lat, lon]).addTo(map);
                                marker.bindPopup(popupText);
                            } else {
                                var cm = L.circleMarker([lat, lon], {
                                    radius: 8,
                                    color: '#d32f2f',
                                    weight: 2,
                                    fillColor: '#ef5350',
                                    fillOpacity: 0.9
                                }).addTo(map);
                                cm.bindPopup(popupText);
                            }
                        }
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', initMap);
                        } else { initMap(); }
                    })();
                    </script>
                <?php else: ?>
                    <div class="detail-item" style="margin-top:0.75rem;">
                        <span class="detail-label">Localisation :</span>
                        <span class="badge-pill" style="background:#f2f6fc;color:#6c757d;" title="Coordonnées non disponibles">carte indisponible</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Inscriptions</h2>
        <p class="card-subtitle">
            Liste des membres qui se sont inscrits à cette sortie.
        </p>

        <?php if ($inscrits): ?>
            <div class="inscrits-list">
                <?php foreach ($inscrits as $i): ?>
                    <?php
                        // Vérifier priorité (badge) pour cet inscrit
                        $isPriority = false;
                        $wasInPrevious = false;
                        try {
                            $stP = $pdo->prepare('SELECT active FROM sortie_priorites WHERE user_id = ?');
                            $stP->execute([ (int)$i['user_id'] ]);
                            $isPriority = (bool)($stP->fetchColumn() ?? 0);
                            
                            // Vérifier si inscrit ou affecté à la sortie précédente
                            if ($previous_sortie) {
                                $stPrev = $pdo->prepare('SELECT COUNT(*) FROM sortie_inscriptions WHERE sortie_id = ? AND user_id = ?');
                                $stPrev->execute([$previous_sortie['id'], (int)$i['user_id']]);
                                $wasInPrevious = (bool)$stPrev->fetchColumn();
                            }
                        } catch (Throwable $e) { $isPriority = false; }
                    ?>
                    <div class="inscrit-item">
                        <div>
                            <div class="inscrit-name">
                                <?= htmlspecialchars($i['prenom'] . ' ' . $i['nom']) ?>
                                <?php if ($isPriority): ?>
                                    <span class="badge-pill" style="background:#fde8e8;color:#b00020;border:1px solid #f5b5b5; margin-left:.4rem;" title="Prioritaire sur la prochaine sortie (était inscrit à la précédente)">PRIORITAIRE</span>
                                <?php elseif ($wasInPrevious): ?>
                                    <span class="badge-pill" style="background:#e3f2fd;color:#0277bd;border:1px solid #90caf9; margin-left:.4rem;" title="Était inscrit à la précédente sortie">📅 Précédente sortie</span>
                                <?php endif; ?>
                            </div>
                            <div class="inscrit-email">
                                <?= htmlspecialchars($i['email']) ?>
                            </div>
                            <?php $pref = $pre_by_user[(int)($i['user_id'] ?? 0)] ?? null; ?>
                            <?php if ($pref): ?>
                                <div class="text-muted" style="font-size:0.8rem;">
                                    Préférences :
                                    <?php
                                        $parts = [];
                                        $m_label = '';
                                        if (!empty($pref['m_nom']) || !empty($pref['m_immat'])) {
                                            $m_label = trim(($pref['m_nom'] ?? ''));
                                            if (!empty($pref['m_immat'])) { $m_label .= ' (' . ($pref['m_immat']) . ')'; }
                                            $parts[] = 'Machine ' . htmlspecialchars($m_label, ENT_QUOTES, 'UTF-8');
                                        }
                                        if (!empty($pref['c_prenom']) || !empty($pref['c_nom'])) {
                                            $c_label = trim(($pref['c_prenom'] ?? '') . ' ' . ($pref['c_nom'] ?? ''));
                                            $parts[] = 'Coéquipier ' . htmlspecialchars($c_label, ENT_QUOTES, 'UTF-8');
                                        }
                                        echo implode(' · ', $parts);
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.8rem;color:#777;">
                            Inscrit le <?= htmlspecialchars($i['created_at']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-muted" style="font-size:0.85rem;margin-top:0.5rem;">
                <span style="display:inline-flex;align-items:center;padding:0.2rem 0.5rem;border:1px solid #f5b5b5;border-radius:999px;background:#fde8e8;color:#b00020;margin-right:.4rem;">PRIORITAIRE</span>
                indique un membre prioritaire pour la prochaine sortie (non affecté après validation et inscrit aux deux sorties).
            </p>
        <?php else: ?>
            <p>Aucun inscrit pour le moment.</p>
        <?php endif; ?>
    </div>

    <?php
    // Machines des membres inscrits
    $memberMachines = [];
    try {
        $stmtM = $pdo->prepare("SELECT m.id AS machine_id, m.nom, m.immatriculation, mo.user_id AS owner_user_id, u.prenom, u.nom
                                 FROM machines m
                                 JOIN machines_owners mo ON mo.machine_id = m.id
                                 JOIN users u ON u.id = mo.user_id
                                 WHERE mo.user_id IN (SELECT si.user_id FROM sortie_inscriptions si WHERE si.sortie_id = ?)
                                 ORDER BY m.nom, m.immatriculation");
        $stmtM->execute([$sortie_id]);
        $memberMachines = $stmtM->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $memberMachines = []; }

    // Ajout d'une machine de membre à la sortie
    if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member_machine'])) {
        $machine_id = (int)($_POST['machine_id'] ?? 0);
        $owner_user_id = (int)($_POST['owner_user_id'] ?? 0);
        if ($machine_id > 0 && $owner_user_id > 0) {
            try {
                $chk = $pdo->prepare("SELECT id FROM sortie_machines WHERE sortie_id = ? AND machine_id = ?");
                $chk->execute([$sortie_id, $machine_id]);
                if (!$chk->fetch()) {
                    $ins = $pdo->prepare("INSERT INTO sortie_machines (sortie_id, machine_id, is_member_owned, provided_by_user_id) VALUES (?,?,1,?)");
                    $ins->execute([$sortie_id, $machine_id, $owner_user_id]);
                }
                // Si la machine était exclue, la retirer de la blocklist
                try {
                    $pdo->prepare("DELETE FROM sortie_machines_exclusions WHERE sortie_id = ? AND machine_id = ?")->execute([$sortie_id, $machine_id]);
                } catch (Throwable $e) { /* no-op */ }
                header('Location: sortie_detail.php?sortie_id='.(int)$sortie_id.'&added=1');
                exit;
            } catch (Throwable $e) {
                header('Location: sortie_detail.php?sortie_id='.(int)$sortie_id.'&added=0&error='.urlencode($e->getMessage()));
                exit;
            }
        }
    }

    if (is_admin()): ?>
    <div class="card">
        <h2 class="card-title">Machines des membres inscrits</h2>
        <p class="card-subtitle">Ajoutez une machine possédée par un membre inscrit à cette sortie.</p>
        <?php if ($memberMachines): ?>
        <form method="post" class="row g-2">
            <input type="hidden" name="add_member_machine" value="1">
            <div class="col-md-6">
                <label class="form-label">Machine</label>
                <select name="machine_id" class="form-select" required>
                    <?php foreach ($memberMachines as $mm): ?>
                    <option value="<?= (int)$mm['machine_id'] ?>">
                        <?= htmlspecialchars(($mm['immatriculation']?($mm['immatriculation'].' – '):'').$mm['nom']) ?> (propriétaire: <?= htmlspecialchars($mm['prenom'].' '.$mm['nom']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Propriétaire</label>
                <select name="owner_user_id" class="form-select" required>
                    <?php foreach ($memberMachines as $mm): ?>
                    <option value="<?= (int)$mm['owner_user_id'] ?>">
                        <?= htmlspecialchars($mm['prenom'].' '.$mm['nom']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button class="btn btn-primary">Ajouter la machine à la sortie</button>
            </div>
        </form>
        <?php else: ?>
            <p>Aucune machine de membre correspondante.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    // Récupérer l'historique des emails pour cette sortie
    $sortie_emails = [];
    try {
        $stmtEmails = $pdo->prepare("
            SELECT el.*, u.nom, u.prenom 
            FROM email_logs el
            LEFT JOIN users u ON u.id = el.sender_id
            WHERE el.subject LIKE ? OR el.message_html LIKE ?
            ORDER BY el.created_at DESC
            LIMIT 50
        ");
        $search_pattern = '%' . $sortie['titre'] . '%';
        $stmtEmails->execute([$search_pattern, $search_pattern]);
        $sortie_emails = $stmtEmails->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table n'existe pas encore
        $sortie_emails = [];
    }
    ?>

    <?php if (!empty($sortie_emails)): ?>
    <div class="card">
        <h2 class="card-title">📧 Historique des emails envoyés</h2>
        <p class="card-subtitle">Emails envoyés en rapport avec cette sortie</p>
        <div style="overflow-x: auto;">
            <table class="table table-hover" style="margin:0;">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Expéditeur</th>
                        <th>Destinataire(s)</th>
                        <th>Sujet</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sortie_emails as $email): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($email['created_at']))) ?></td>
                        <td>
                            <?php if (!empty($email['nom']) || !empty($email['prenom'])): ?>
                                <?= htmlspecialchars(trim(($email['prenom'] ?? '') . ' ' . ($email['nom'] ?? ''))) ?>
                            <?php else: ?>
                                <span style="color:#999;">Système</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                                $recipients = explode(', ', $email['recipient_email']);
                                if (count($recipients) > 2) {
                                    echo htmlspecialchars($recipients[0]) . ', ' . htmlspecialchars($recipients[1]) . ' <span style="color:#666;">et ' . (count($recipients) - 2) . ' autre(s)</span>';
                                } else {
                                    echo htmlspecialchars($email['recipient_email']);
                                }
                            ?>
                        </td>
                        <td><?= htmlspecialchars(mb_substr($email['subject'], 0, 60)) ?><?= mb_strlen($email['subject']) > 60 ? '...' : '' ?></td>
                        <td>
                            <span class="badge-pill" style="background:<?= $email['status'] === 'sent' ? '#e7f7ec' : '#fde8e8' ?>;color:<?= $email['status'] === 'sent' ? '#0a8a0a' : '#b02525' ?>;">
                                <?= $email['status'] === 'sent' ? '✓ Envoyé' : '✗ Erreur' ?>
                            </span>
                        </td>
                        <td>
                            <a href="get_email_detail.php?id=<?= (int)$email['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" style="padding:0.25rem 0.5rem;font-size:0.8rem;">
                                👁️ Voir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <form method="post" id="affectationsForm" action="sortie_detail.php?sortie_id=<?= (int)$sortie_id ?>">
        <input type="hidden" name="sortie_id" value="<?= (int)$sortie_id ?>">
        <div class="card">
            <h2 class="card-title">Affectations par machine</h2>
            <p class="card-subtitle">
                Choisissez jusqu’à <strong>2 inscrits par machine</strong>, puis validez pour enregistrer et envoyer les mails.
            </p>

            <?php
            // Pré-affectation rapide depuis la liste d'attente (sans emails)
            $waitlist = [];
            try {
                require_once __DIR__ . '/utils/waitlist.php';
                if (function_exists('gestnav_get_waitlist')) {
                    $waitlist = gestnav_get_waitlist($pdo, $sortie_id);
                }
            } catch (Throwable $e) { $waitlist = []; }
            ?>
            <?php if (false && is_admin() && $waitlist && $machines): ?>
            <div class="alert alert-info" role="alert" style="border-radius: .75rem; display:none;">
                Pré-affecter certains inscrits en attente sans envoyer d’emails.
            </div>
            <form method="post" action="sortie_detail.php?sortie_id=<?= (int)$sortie_id ?>" class="mb-3" style="display:none;">
                <input type="hidden" name="pre_assign" value="1">
                <div class="row g-3">
                    <?php foreach ($machines as $m): $smid = (int)$m['sortie_machine_id']; ?>
                    <div class="col-md-6">
                        <div class="card" style="border:1px solid #e6ebf2;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <div class="fw-semibold" style="font-size:1rem;"><?= htmlspecialchars($m['nom']) ?></div>
                                    <?php if (!empty($m['immatriculation'])): ?>
                                        <div class="text-muted" style="font-size:.9rem;"><?= htmlspecialchars($m['immatriculation']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2">
                                    <label class="form-label">Sélectionner jusqu’à 2 en attente</label>
                                    <select name="assign[<?= $smid ?>][]" class="form-select mb-2">
                                        <option value="">— choisir —</option>
                                        <?php foreach ($waitlist as $w): ?>
                                            <option value="<?= (int)$w['user_id'] ?>"><?= htmlspecialchars(trim(($w['prenom']??'').' '.($w['nom']??''))) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="assign[<?= $smid ?>][]" class="form-select">
                                        <option value="">— choisir —</option>
                                        <?php foreach ($waitlist as $w): ?>
                                            <option value="<?= (int)$w['user_id'] ?>"><?= htmlspecialchars(trim(($w['prenom']??'').' '.($w['nom']??''))) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2">
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-save"></i> Enregistrer ces pré-affectations (sans email)</button>
                </div>
            </form>
            <?php endif; ?>

            <?php if (!$machines): ?>
                <p>Aucune machine configurée pour cette sortie.</p>
            <?php elseif (!$inscrits): ?>
                <p>Aucun inscrit pour le moment, vous ne pouvez pas encore affecter les machines.</p>
            <?php else: ?>
                <div class="machines-grid">
                    <?php foreach ($machines as $m): ?>
                        <?php
                            $smid = (int)$m['sortie_machine_id'];
                            $assigned = $assignations[$smid] ?? [];
                            $user1 = $assigned[0] ?? 0;
                            $user2 = $assigned[1] ?? 0;
                        ?>
                        <div class="machine-card">
                            <?php $photo = gestnav_machine_photo_url((int)$m['id']); ?>
                            <img class="machine-img" src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($m['nom']) ?>" loading="lazy" width="640" height="360">
                            <div class="machine-body">
                            <div class="machine-header">
                                <div>
                                    <div class="machine-title">
                                        <?= htmlspecialchars($m['nom']) ?>
                                    </div>
                                    <div class="machine-sub" style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
                                        <span><?= htmlspecialchars($m['immatriculation']) ?></span>
                                        <?php
                                            // Déterminer si machine membre: priorité à la colonne is_member_owned si présente,
                                            // sinon fallback via existence dans machines_owners
                                            $isMemberOwned = !empty($m['is_member_owned']);
                                            $ownerName = '';
                                            if (!$isMemberOwned) {
                                                try {
                                                    $stM = $pdo->prepare('SELECT mo.user_id, u.prenom, u.nom FROM machines_owners mo JOIN users u ON u.id = mo.user_id WHERE mo.machine_id = ? LIMIT 1');
                                                    $stM->execute([ (int)$m['id'] ]);
                                                    $rowM = $stM->fetch(PDO::FETCH_ASSOC);
                                                    if ($rowM) {
                                                        $isMemberOwned = true;
                                                        $ownerName = trim(($rowM['prenom']??'').' '.($rowM['nom']??''));
                                                    }
                                                } catch (Throwable $e) {}
                                            }
                                            if ($isMemberOwned):
                                        ?>
                                            <span class="badge-pill" style="background:rgba(156,39,176,0.10);color:#6a1b9a;border:1px solid rgba(156,39,176,0.25);">Machine membre</span>
                                            <?php
                                                if (!empty($m['provided_by_user_id'])) {
                                                    try {
                                                        $stO = $pdo->prepare('SELECT prenom, nom FROM users WHERE id = ?');
                                                        $stO->execute([ (int)$m['provided_by_user_id'] ]);
                                                        $rowO = $stO->fetch(PDO::FETCH_ASSOC);
                                                        if ($rowO) { $ownerName = trim(($rowO['prenom']??'').' '.($rowO['nom']??'')); }
                                                    } catch (Throwable $e) {}
                                                }
                                                if ($ownerName):
                                            ?>
                                                    <span class="badge-pill" style="background:#f2f6fc;color:#004b8d;">Propriétaire: <?= htmlspecialchars($ownerName) ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="machine-sub">
                                    Max 2 personnes
                                </div>
                            </div>

                            <div class="machine-actions" style="margin-top:.5rem;display:flex;gap:.5rem;flex-wrap:wrap;">
                                <a class="btn btn-outline-danger btn-sm" href="sortie_detail.php?sortie_id=<?= (int)$sortie_id ?>&make_unavailable=<?= $smid ?>" onclick="return confirm('Rendre cette machine indisponible pour cette sortie ?');">Rendre indisponible</a>
                            </div>

                            <div class="machine-selects">
                                <div>
                                    <label>Personne 1</label>
                                    <select name="assign[<?= $smid ?>][]" class="person-select" data-machine="<?= $smid ?>" data-slot="1">
                                        <option value="">— Aucune —</option>
                                        <?php foreach ($inscrits as $i): ?>
                                            <?php
                                                // Vérifier le statut de cette personne
                                                $isPrio = false;
                                                $wasPrev = false;
                                                try {
                                                    $stP = $pdo->prepare('SELECT active FROM sortie_priorites WHERE user_id = ?');
                                                    $stP->execute([(int)$i['user_id']]);
                                                    $isPrio = (bool)($stP->fetchColumn() ?? 0);
                                                    if (!$isPrio && $previous_sortie) {
                                                        $stPrev = $pdo->prepare('SELECT COUNT(*) FROM sortie_inscriptions WHERE sortie_id = ? AND user_id = ?');
                                                        $stPrev->execute([$previous_sortie['id'], (int)$i['user_id']]);
                                                        $wasPrev = (bool)$stPrev->fetchColumn();
                                                    }
                                                } catch (Throwable $e) {}
                                                $optionClass = $isPrio ? 'option-priority' : ($wasPrev ? 'option-previous' : '');
                                            ?>
                                            <option value="<?= (int)$i['user_id'] ?>" data-user-id="<?= (int)$i['user_id'] ?>" class="<?= $optionClass ?>"
                                                <?= $user1 === (int)$i['user_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($i['prenom'] . ' ' . $i['nom']) ?><?= $isPrio ? ' 🎯' : ($wasPrev ? ' 📅' : '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label>Personne 2</label>
                                    <select name="assign[<?= $smid ?>][]" class="person-select" data-machine="<?= $smid ?>" data-slot="2">
                                        <option value="">— Aucune —</option>
                                        <?php foreach ($inscrits as $i): ?>
                                            <?php
                                                // Vérifier le statut de cette personne
                                                $isPrio = false;
                                                $wasPrev = false;
                                                try {
                                                    $stP = $pdo->prepare('SELECT active FROM sortie_priorites WHERE user_id = ?');
                                                    $stP->execute([(int)$i['user_id']]);
                                                    $isPrio = (bool)($stP->fetchColumn() ?? 0);
                                                    if (!$isPrio && $previous_sortie) {
                                                        $stPrev = $pdo->prepare('SELECT COUNT(*) FROM sortie_inscriptions WHERE sortie_id = ? AND user_id = ?');
                                                        $stPrev->execute([$previous_sortie['id'], (int)$i['user_id']]);
                                                        $wasPrev = (bool)$stPrev->fetchColumn();
                                                    }
                                                } catch (Throwable $e) {}
                                                $optionClass = $isPrio ? 'option-priority' : ($wasPrev ? 'option-previous' : '');
                                            ?>
                                            <option value="<?= (int)$i['user_id'] ?>" data-user-id="<?= (int)$i['user_id'] ?>" class="<?= $optionClass ?>" <?= $user2 === (int)$i['user_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($i['prenom'] . ' ' . $i['nom']) ?><?= $isPrio ? ' 🎯' : ($wasPrev ? ' 📅' : '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="GUEST" <?= ($user2 ? '' : (isset($existing_guests[$smid]) ? 'selected' : '')) ?>>INVITÉ</option>
                                    </select>
                                    <input type="text" class="form-control" name="guest_name[<?= $smid ?>]" placeholder="Nom de l'invité (optionnel)" value="<?= htmlspecialchars($existing_guests[$smid] ?? '') ?>" style="margin-top: .35rem;">
                                </div>
                            </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-footer">
                    <button type="submit" class="btn-primary-gestnav" id="affectationsSubmit">
                        Valider les affectations et envoyer les confirmations
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php require 'footer.php'; ?>
<script>
(function(){
  var form = document.getElementById('affectationsForm');
  var btn = document.getElementById('affectationsSubmit');
  if (!form || !btn) return;
  form.addEventListener('submit', function(){
    btn.disabled = true;
    btn.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>');
    btn.textContent = 'Envoi des confirmations…';
  });
  
  // Masquer les personnes déjà affectées dans les autres dropdowns
  function updateAvailablePersons() {
    var allSelects = document.querySelectorAll('.person-select');
    var assignedUsers = new Set();
    
    // Collecter tous les user_id déjà sélectionnés
    allSelects.forEach(function(select) {
      var val = select.value;
      if (val && val !== '' && val !== 'GUEST') {
        assignedUsers.add(val);
      }
    });
    
    // Pour chaque select, masquer les options déjà assignées ailleurs
    allSelects.forEach(function(select) {
      var currentValue = select.value;
      var options = select.querySelectorAll('option[data-user-id]');
      
      options.forEach(function(option) {
        var userId = option.getAttribute('data-user-id');
        // Masquer si assigné ailleurs ET ce n'est pas la valeur actuelle de ce select
        if (assignedUsers.has(userId) && userId !== currentValue) {
          option.style.display = 'none';
          option.disabled = true;
        } else {
          option.style.display = '';
          option.disabled = false;
        }
      });
    });
  }
  
  // Écouter les changements sur tous les selects
  var personSelects = document.querySelectorAll('.person-select');
  personSelects.forEach(function(select) {
    select.addEventListener('change', updateAvailablePersons);
  });
  
  // Appliquer au chargement
  updateAvailablePersons();
})();
</script>
<!-- alignement: revenir à l'état précédent, pas de JS forcé -->
