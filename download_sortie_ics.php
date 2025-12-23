<?php
require 'config.php';
require 'auth.php';
require_login();

$sortie_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sortie_id <= 0) {
    http_response_code(400);
    die('ID de sortie invalide');
}

// Vérifier que l'utilisateur est inscrit à cette sortie
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM sortie_inscriptions 
    WHERE sortie_id = ? AND user_id = ?
");
$stmt->execute([$sortie_id, $_SESSION['user_id']]);
$isInscrit = $stmt->fetchColumn() > 0;

if (!$isInscrit) {
    http_response_code(403);
    die('Vous devez être inscrit à cette sortie pour télécharger le calendrier');
}

// Récupérer les informations de la sortie
$hasDestinationId = false;
$hasUlmBaseId = false;
try {
    $cols = $pdo->query('SHOW COLUMNS FROM sorties')->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasDestinationId = in_array('destination_id', $cols, true);
    $hasUlmBaseId = in_array('ulm_base_id', $cols, true);
} catch (Throwable $e) {}

$sql = "
    SELECT 
        s.id,
        s.titre,
        s.description,
        s.details,
        s.date_sortie,
        s.date_fin,
        s.is_multi_day,
        s.destination_oaci,
        s.repas_prevu,
        s.repas_details"
        . ($hasDestinationId ? ", s.destination_id, ad.nom AS dest_nom, ad.oaci AS dest_oaci, ad.lat AS dest_lat, ad.lon AS dest_lon" : "")
        . ($hasUlmBaseId ? ", s.ulm_base_id, ub.nom AS ulm_nom, ub.lat AS ulm_lat, ub.lon AS ulm_lon" : "")
        . "
    FROM sorties s"
    . ($hasDestinationId ? "\n    LEFT JOIN aerodromes_fr ad ON ad.id = s.destination_id" : "")
    . ($hasUlmBaseId ? "\n    LEFT JOIN ulm_bases_fr ub ON ub.id = s.ulm_base_id" : "")
    . "
    WHERE s.id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sortie) {
    http_response_code(404);
    die('Sortie non trouvée');
}

// Fonction pour formater une date en format iCalendar
function formatDateICS($datetime) {
    $dt = new DateTime($datetime);
    return $dt->format('Ymd\THis');
}

// Fonction pour échapper le texte pour iCalendar
function escapeICS($text) {
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(',', '\\,', $text);
    $text = str_replace(';', '\\;', $text);
    $text = str_replace("\n", '\\n', $text);
    return $text;
}

// Déterminer la date de début et de fin
$dtstart = formatDateICS($sortie['date_sortie']);
// Heure de fin : 18h le même jour (ou date_fin si spécifiée)
if ($sortie['date_fin']) {
    $dtend = formatDateICS($sortie['date_fin']);
} else {
    $date_sortie_obj = new DateTime($sortie['date_sortie']);
    $date_fin_obj = clone $date_sortie_obj;
    $date_fin_obj->setTime(18, 0, 0);
    // Si l'heure de début est après 18h, on met la fin à 23h59
    if ($date_sortie_obj->format('H') >= 18) {
        $date_fin_obj->setTime(23, 59, 59);
    }
    $dtend = formatDateICS($date_fin_obj->format('Y-m-d H:i:s'));
}

// Construire la description avec un maximum de détails
$description = '';
if (!empty($sortie['description'])) {
    $description .= $sortie['description'];
}
if (!empty($sortie['details'])) {
    $description .= "\n\n" . $sortie['details'];
}
if (!empty($sortie['repas_prevu'])) {
    $repas_text = "\n\n🍽️ Repas prévu";
    if (!empty($sortie['repas_details'])) {
        $repas_text .= ": " . $sortie['repas_details'];
    }
    $description .= $repas_text;
}

// Ajouter les machines et assignations
try {
    $stmtMachines = $pdo->prepare("
        SELECT 
            m.nom,
            m.immatriculation,
            sm.id as sortie_machine_id
        FROM sortie_machines sm
        JOIN machines m ON m.id = sm.machine_id
        WHERE sm.sortie_id = ?
        ORDER BY m.immatriculation
    ");
    $stmtMachines->execute([$sortie_id]);
    $machines = $stmtMachines->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($machines) > 0) {
        $description .= "\n\n✈️ MACHINES DISPONIBLES:\n";
        
        foreach ($machines as $machine) {
            $description .= "\n• " . $machine['immatriculation'];
            if (!empty($machine['nom'])) {
                $description .= " (" . $machine['nom'] . ")";
            }
            
            // Récupérer les assignations pour cette machine
            $stmtAssign = $pdo->prepare("
                SELECT 
                    sa.role,
                    u.prenom,
                    u.nom
                FROM sortie_assignations sa
                JOIN users u ON u.id = sa.user_id
                WHERE sa.sortie_machine_id = ?
                ORDER BY sa.role DESC
            ");
            $stmtAssign->execute([$machine['sortie_machine_id']]);
            $assignations = $stmtAssign->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($assignations) > 0) {
                foreach ($assignations as $assign) {
                    $role_icon = ($assign['role'] === 'pilote') ? '👨‍✈️' : '👤';
                    $role_label = ($assign['role'] === 'pilote') ? 'Pilote' : 'Passager';
                    $description .= "\n  " . $role_icon . " " . $role_label . ": " . $assign['prenom'] . " " . $assign['nom'];
                }
            } else {
                $description .= "\n  (Places disponibles)";
            }
        }
    }
} catch (Exception $e) {
    error_log("Erreur récupération machines pour ICS: " . $e->getMessage());
}

// Ajouter la liste des inscrits
try {
    $stmtInscrits = $pdo->prepare("
        SELECT 
            u.prenom,
            u.nom,
            si.created_at
        FROM sortie_inscriptions si
        JOIN users u ON u.id = si.user_id
        WHERE si.sortie_id = ?
        ORDER BY si.created_at ASC
    ");
    $stmtInscrits->execute([$sortie_id]);
    $inscrits = $stmtInscrits->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($inscrits) > 0) {
        $description .= "\n\n👥 PARTICIPANTS INSCRITS (" . count($inscrits) . "):\n";
        foreach ($inscrits as $inscrit) {
            $description .= "\n• " . $inscrit['prenom'] . " " . $inscrit['nom'];
        }
    }
} catch (Exception $e) {
    error_log("Erreur récupération inscrits pour ICS: " . $e->getMessage());
}

// Déterminer le lieu
$location = '';
if (!empty($sortie['ulm_nom'])) {
    $location = $sortie['ulm_nom'];
} elseif (!empty($sortie['dest_nom'])) {
    $location = $sortie['dest_nom'];
    if (!empty($sortie['dest_oaci'])) {
        $location = $sortie['dest_oaci'] . ' - ' . $location;
    }
} elseif (!empty($sortie['destination_oaci'])) {
    $location = $sortie['destination_oaci'];
}

// Générer le contenu ICS
$ics = "BEGIN:VCALENDAR\r\n";
$ics .= "VERSION:2.0\r\n";
$ics .= "PRODID:-//Club ULM Evasion//GESTNAV//FR\r\n";
$ics .= "CALSCALE:GREGORIAN\r\n";
$ics .= "METHOD:PUBLISH\r\n";
$ics .= "BEGIN:VEVENT\r\n";
$ics .= "UID:" . md5($sortie['id'] . $sortie['date_sortie']) . "@gestnav.clubulmevasion.fr\r\n";
$ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
$ics .= "DTSTART:" . $dtstart . "\r\n";
$ics .= "DTEND:" . $dtend . "\r\n";
$ics .= "SUMMARY:" . escapeICS($sortie['titre']) . "\r\n";
if (!empty($description)) {
    $ics .= "DESCRIPTION:" . escapeICS($description) . "\r\n";
}
if (!empty($location)) {
    $ics .= "LOCATION:" . escapeICS($location) . "\r\n";
}
$ics .= "STATUS:CONFIRMED\r\n";
$ics .= "TRANSP:OPAQUE\r\n";
$ics .= "BEGIN:VALARM\r\n";
$ics .= "TRIGGER:-P1D\r\n";
$ics .= "ACTION:DISPLAY\r\n";
$ics .= "DESCRIPTION:Rappel: " . escapeICS($sortie['titre']) . " demain\r\n";
$ics .= "END:VALARM\r\n";
$ics .= "END:VEVENT\r\n";
$ics .= "END:VCALENDAR\r\n";

// Envoyer le fichier ICS
$filename = 'sortie_' . $sortie['id'] . '_' . date('Y-m-d', strtotime($sortie['date_sortie'])) . '.ics';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

echo $ics;
exit;
