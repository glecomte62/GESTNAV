<?php
// à inclure après config.php

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function is_admin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function require_admin(): void {
    if (!is_admin()) {
        // Déterminer la page de retour
        $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
        // Rediriger vers la page d'accès refusé
        header('Location: acces_refuse.php?message=' . urlencode('Cette fonctionnalité est réservée aux administrateurs du club.') . '&redirect=' . urlencode($redirect));
        exit;
    }
}