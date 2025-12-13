<?php
require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $stmt = $pdo->query('DESCRIBE sorties');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== Structure complète de la table SORTIES ===\n\n";
    printf("%-20s %-30s %-10s %-10s %-15s %-20s\n", "Field", "Type", "Null", "Key", "Default", "Extra");
    echo str_repeat("-", 105) . "\n";
    
    foreach ($columns as $col) {
        printf("%-20s %-30s %-10s %-10s %-15s %-20s\n",
            $col['Field'],
            $col['Type'],
            $col['Null'],
            $col['Key'] ?? '',
            $col['Default'] ?? '',
            $col['Extra'] ?? ''
        );
    }
    
    echo "\n\nJSON Export:\n";
    echo json_encode($columns, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
?>
