<?php
/**
 * Marlow FM Photobooth - Admin Photos API
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

// Get filters
$filterDate = $_GET['date'] ?? '';
$filterShow = $_GET['show'] ?? '';
$filterName = $_GET['name'] ?? '';

// Load metadata
$metadataFile = PHOTO_BASE_DIR . '/.metadata.json';
$allMetadata = file_exists($metadataFile) ? json_decode(file_get_contents($metadataFile), true) : [];

$photos = [];
foreach ($allMetadata as $token => $data) {
    // Apply filters
    if ($filterDate && date('Y-m-d', strtotime($data['created'])) !== $filterDate) continue;
    if ($filterShow && stripos($data['show'], $filterShow) === false) continue;
    if ($filterName && stripos($data['presenter'] . ' ' . $data['guests'], $filterName) === false) continue;
    
    $photos[] = [
        'token' => $token,
        'created' => $data['created'],
        'show' => $data['show'],
        'presenter' => $data['presenter'] ?? '',
        'guests' => $data['guests'] ?? '',
        'filename_clean' => $data['filename_clean'],
        'filename_branded' => $data['filename_branded'],
        'path' => date('Y/m/d', strtotime($data['created']))
    ];
}

// Sort by date descending
usort($photos, function($a, $b) {
    return strtotime($b['created']) - strtotime($a['created']);
});

echo json_encode($photos);
