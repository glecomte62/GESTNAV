<?php
/**
 * Script pour envoyer les alertes email automatiquement
 * Peut être appelé via cron ou webhook après publication d'une sortie/événement
 * Usage: php send_event_alerts.php --event-type=sortie --event-id=9
 */

require_once 'config.php';
require_once 'utils/event_alerts_helper.php';

// Parser les paramètres
$event_type = null;
$event_id = 0;

// Format 1: --event-type=sortie --event-id=9
// Format 2: sortie 9
foreach ($argv as $arg) {
    if (strpos($arg, '--event-type=') === 0) {
        $event_type = substr($arg, 13);
    } elseif (strpos($arg, '--event-id=') === 0) {
        $event_id = (int)substr($arg, 11);
    }
}

// Format 2: positional arguments
if (!$event_type && isset($argv[1]) && !str_starts_with($argv[1], '--')) {
    $event_type = $argv[1];
}
if (!$event_id && isset($argv[2]) && !str_starts_with($argv[2], '--')) {
    $event_id = (int)$argv[2];
}

if (empty($event_type) || $event_id <= 0) {
    echo "Usage: php send_event_alerts.php --event-type=sortie --event-id=9\n";
    echo "or: php send_event_alerts.php sortie 9\n";
    exit(1);
}

// Valider le type
if (!in_array($event_type, ['sortie', 'evenement'])) {
    echo "Erreur: event_type doit être 'sortie' ou 'evenement'\n";
    exit(1);
}

try {
    // Récupérer les données de l'événement
    if ($event_type === 'sortie') {
        $stmt = $pdo->prepare("
            SELECT s.id, s.titre, s.destination, s.description, s.statut
            FROM sorties s
            WHERE s.id = ? AND s.statut != 'en étude'
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT e.id, e.titre, e.description, e.statut
            FROM evenements e
            WHERE e.id = ? AND e.statut != 'en étude'
        ");
    }

    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        echo "Erreur: Événement non trouvé ou encore en étude\n";
        exit(1);
    }

    // Construire l'URL de destination
    $scheme = 'https://';
    $host = 'gestnav.clubulmevasion.fr';
    
    if ($event_type === 'sortie') {
        $event_url = $scheme . $host . '/sortie_info.php?id=' . $event_id;
    } else {
        $event_url = $scheme . $host . '/evenement_detail.php?id=' . $event_id;
    }

    // Préparer les données
    $event_data = [
        'titre' => $event['titre'],
        'description' => $event['description'],
        'destination_label' => $event['destination'] ?? 'À définir'
    ];

    echo "Envoi des alertes pour: " . $event['titre'] . "\n";
    echo "Type: $event_type\n";
    echo "ID: $event_id\n";
    echo "URL: $event_url\n\n";

    // Envoyer les alertes
    $result = gestnav_send_event_alert($pdo, $event_type, $event_id, $event_data, $event_url);

    echo "=== Résultats ===\n";
    echo "Alert ID: " . $result['alert_id'] . "\n";
    echo "Envoyés: " . $result['sent'] . "\n";
    echo "Échoués: " . $result['failed'] . "\n";
    echo "Ignorés (optout): " . $result['skipped'] . "\n";
    echo "\nAlertes envoyées avec succès !\n";

} catch (Throwable $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
