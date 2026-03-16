<?php
/**
 * Marlow FM Photobooth - Admin Schedule API
 */

header('Content-Type: application/json');
session_start();
require_once '/home/marlowfm/photobooth-config/config.php';

// Verify auth
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if (!$token || !isset($_SESSION['admin_token']) || $_SESSION['admin_token'] !== $token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$schedule = json_decode(file_get_contents(SCHEDULE_FILE), true);
echo json_encode($schedule ?: []);
