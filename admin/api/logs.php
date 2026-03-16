<?php
/**
 * Marlow FM Photobooth - Admin Logs API
 */

header('Content-Type: text/plain');
session_start();
require_once '/home/marlowfm/photobooth-config/config.php';

// Verify auth
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if (!$token || !isset($_SESSION['admin_token']) || $_SESSION['admin_token'] !== $token) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$type = $_GET['type'] ?? 'email';
$logFile = '';

switch ($type) {
    case 'email':
        $logFile = '/home/marlowfm/marlowfm-photobooth/logs/email.log';
        break;
    case 'capture':
        $logFile = '/home/marlowfm/marlowfm-photobooth/logs/capture.log';
        break;
    case 'system':
        $logFile = '/var/log/apache2/error.log';
        break;
}

if ($logFile && file_exists($logFile)) {
    // Get last 100 lines
    echo shell_exec("tail -100 $logFile");
} else {
    echo "No log entries found for: $type";
}
