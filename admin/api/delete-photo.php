<?php
/**
 * Marlow FM Photobooth - Admin Delete Photo API
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

$input = json_decode(file_get_contents('php://input'), true);
$deleteToken = $input['token'] ?? '';

if (!$deleteToken) {
    echo json_encode(['success' => false, 'error' => 'No token provided']);
    exit;
}

$metadataFile = PHOTO_BASE_DIR . '/.metadata.json';
$allMetadata = file_exists($metadataFile) ? json_decode(file_get_contents($metadataFile), true) : [];

if (!isset($allMetadata[$deleteToken])) {
    echo json_encode(['success' => false, 'error' => 'Photo not found']);
    exit;
}

$data = $allMetadata[$deleteToken];
$dirPath = PHOTO_BASE_DIR . '/' . date('Y/m/d', strtotime($data['created']));
$cleanFile = $dirPath . '/' . $data['filename_clean'];
$brandedFile = $dirPath . '/' . $data['filename_branded'];

// Delete files
if (file_exists($cleanFile)) unlink($cleanFile);
if (file_exists($brandedFile)) unlink($brandedFile);

// Remove from metadata
unset($allMetadata[$deleteToken]);
file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'message' => 'Photo deleted']);
