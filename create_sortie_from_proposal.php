<?php
/**
 * Creer une sortie officielle a partir d une proposal validee
 * Appele par sortie_proposals_admin.php quand statut passe a 'validee'
 */

require_once 'config.php';
require_once 'auth.php';
require_login();

if (empty($_SESSION['is_admin'])) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$proposal_id = (int)($_POST['proposal_id'] ?? 0);

if (!$proposal_id) {
    die('Proposal ID manquant');
}

try {
    // Charger la proposal
    $stmt = $pdo->prepare("SELECT * FROM sortie_proposals WHERE id = ?");
    $stmt->execute([$proposal_id]);
    $proposal = $stmt->fetch();
    
    if (!$proposal) {
        die('Proposal non trouvee');
    }
    
    if ($proposal['status'] !== 'validee') {
        die('Seules les proposals validees peuvent etre transformees en sorties');
    }
    
    // Convertir month_proposed en date approximative (premier du mois)
    $monthMap = [
        'janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4,
        'mai' => 5, 'juin' => 6, 'juillet' => 7, 'aout' => 8,
        'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12
    ];
    
    $month = $monthMap[strtolower($proposal['month_proposed'])] ?? date('m');
    $year = date('Y');
    if ($month < date('m')) {
        $year += 1;
    }
    
    $date_sortie = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    
    // Creer la sortie
    $stmt = $pdo->prepare("
        INSERT INTO sorties (
            titre, description, date_sortie, aerodrome_depart_id, aerodrome_arrivee_id,
            restaurant, notes, created_by, updated_by, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $proposal['titre'],
        $proposal['description'] . "\n\n--- Proposee par: ---\n(Voir sortie_proposals pour details restauration et activites)",
        $date_sortie,
        $proposal['aerodrome_id'],
        $proposal['aerodrome_id'],
        $proposal['restaurant_choice'],
        $proposal['activity_details'],
        $_SESSION['user_id'],
        $_SESSION['user_id']
    ]);
    
    $sortie_id = $pdo->lastInsertId();
    
    // Marquer la proposal comme ayant ete convertie
    $stmt = $pdo->prepare("UPDATE sortie_proposals SET status = 'validee' WHERE id = ?");
    $stmt->execute([$proposal_id]);
    
    $response = [
        'success' => true,
        'message' => 'Sortie creee avec succes',
        'sortie_id' => $sortie_id,
        'redirect' => "sortie_edit.php?id=$sortie_id"
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
?>
