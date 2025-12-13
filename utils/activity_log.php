<?php

function gn_get_client_ip(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos($ip, ',') !== false) {
        $parts = explode(',', $ip);
        $ip = trim($parts[0]);
    }
    return $ip ?: '';
}

/**
 * Enregistre une opération dans operation_logs.
 *
 * @param PDO $pdo
 * @param int $userId
 * @param string $nom
 * @param string $prenom
 * @param string $action
 * @param string|null $details
 */
function gn_log_operation(PDO $pdo, int $userId, string $nom, string $prenom, string $action, ?string $details = null): void {
    try {
        $ip = gn_get_client_ip();
        $stmt = $pdo->prepare('INSERT INTO operation_logs (user_id, nom, prenom, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$userId, $nom, $prenom, $action, $details, $ip]);
    } catch (Throwable $e) {
        // Ne pas interrompre le flux applicatif en cas d'échec de log
    }
}

/**
 * Enregistre une opération pour l'utilisateur courant (depuis la session).
 */
function gn_log_current_user_operation(PDO $pdo, string $action, ?string $details = null): void {
    if (!isset($_SESSION['user_id'])) {
        return; // uniquement pour utilisateurs connectés
    }
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $nom = (string)($_SESSION['nom'] ?? '');
    $prenom = (string)($_SESSION['prenom'] ?? '');
    gn_log_operation($pdo, $userId, $nom, $prenom, $action, $details);
}
