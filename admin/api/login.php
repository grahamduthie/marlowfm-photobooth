<?php
/**
 * Marlow FM Photobooth - Admin Login API
 */

header('Content-Type: application/json');
session_start();

require_once '/home/marlowfm/photobooth-config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (!$username || !$password) {
    echo json_encode(['success' => false, 'error' => 'Username and password required']);
    exit;
}

// Check credentials
if (!isset(ADMIN_USERS[$username]) || ADMIN_USERS[$username] !== $password) {
    echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
    exit;
}

// Generate session token
$token = bin2hex(random_bytes(32));
$_SESSION['admin_token'] = $token;
$_SESSION['admin_user'] = $username;
$_SESSION['admin_expires'] = time() + (SESSION_TIMEOUT * 60);

echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => $username
]);
