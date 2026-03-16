<?php
/**
 * Marlow FM Photobooth - Admin Verify Token API
 */

header('Content-Type: application/json');
session_start();

require_once '/home/marlowfm/photobooth-config/config.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (!$token || !isset($_SESSION['admin_token']) || $_SESSION['admin_token'] !== $token) {
    echo json_encode(['valid' => false]);
    exit;
}

// Check expiry
if (isset($_SESSION['admin_expires']) && $_SESSION['admin_expires'] < time()) {
    session_destroy();
    echo json_encode(['valid' => false]);
    exit;
}

echo json_encode([
    'valid' => true,
    'user' => $_SESSION['admin_user'] ?? 'unknown'
]);
