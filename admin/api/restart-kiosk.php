<?php
/**
 * Marlow FM Photobooth - Admin Restart Kiosk API
 */

header('Content-Type: application/json');
session_start();
require_once '/home/marlowfm/photobooth-config/config.php';

// Verify auth
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if (!$token || !isset($_SESSION['admin_token']) || $_SESSION['admin_token'] !== $token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Kill existing browser and restart
exec('pkill -f "chromium.*kiosk" 2>/dev/null');
exec('nohup chromium-browser --kiosk --app=http://localhost/photobooth /dev/null 2>&1 &');

echo json_encode(['success' => true, 'message' => 'Kiosk browser restarting...']);
