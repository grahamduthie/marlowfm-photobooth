<?php
/**
 * Marlow FM Photobooth - Admin Cleanup API
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

$cutoffDate = strtotime('-' . RETENTION_DAYS . ' days');
$deleted = 0;
$errors = 0;

$metadataFile = PHOTO_BASE_DIR . '/.metadata.json';
$allMetadata = file_exists($metadataFile) ? json_decode(file_get_contents($metadataFile), true) : [];

foreach ($allMetadata as $token => $data) {
    $photoTime = strtotime($data['created']);
    
    if ($photoTime < $cutoffDate) {
        // Delete photo files
        $dirPath = PHOTO_BASE_DIR . '/' . date('Y/m/d', $photoTime);
        $cleanFile = $dirPath . '/' . $data['filename_clean'];
        $brandedFile = $dirPath . '/' . $data['filename_branded'];
        
        if (file_exists($cleanFile)) unlink($cleanFile);
        if (file_exists($brandedFile)) unlink($brandedFile);
        
        unset($allMetadata[$token]);
        $deleted++;
    }
}

// Save updated metadata
file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'message' => "Cleanup complete. Deleted $deleted photos older than " . RETENTION_DAYS . " days.",
    'deleted' => $deleted
]);
