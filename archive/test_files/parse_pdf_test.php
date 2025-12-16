<?php
// Version minimale pour tester
header('Content-Type: application/json');

try {
    $jsonInput = file_get_contents('php://input');
    $jsonData = json_decode($jsonInput, true);
    
    if ($jsonData && isset($jsonData['pdf_base64'])) {
        echo json_encode([
            'success' => true,
            'test' => 'OK',
            'base64_length' => strlen($jsonData['pdf_base64']),
            'filename' => $jsonData['filename'] ?? 'unknown'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No base64 data',
            'received_keys' => $jsonData ? array_keys($jsonData) : []
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
