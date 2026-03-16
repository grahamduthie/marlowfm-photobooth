<?php
/**
 * Marlow FM Photobooth - Update Photo Details API
 * Updates show/people metadata after auto-save
 */

header('Content-Type: application/json');
require_once '/home/marlowfm/photobooth-config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$token  = trim($input['token']  ?? '');
$show   = trim($input['show']   ?? '');
$people = trim($input['people'] ?? '');
$title  = trim($input['title']  ?? '');

if (!$token) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing token']);
    exit;
}

if (!$show) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Show name is required']);
    exit;
}

$metadataFile = PHOTO_BASE_DIR . '/.metadata.json';
if (!file_exists($metadataFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Metadata not found']);
    exit;
}

$allMetadata = json_decode(file_get_contents($metadataFile), true) ?? [];

if (!isset($allMetadata[$token])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Token not found']);
    exit;
}

// Update metadata - store in 'people' field and keep legacy fields for compatibility
$allMetadata[$token]['title']     = $title;
$allMetadata[$token]['show']      = $show;
$allMetadata[$token]['people']    = $people;
$allMetadata[$token]['presenter'] = $people;  // keep for email template compatibility
$allMetadata[$token]['guests']    = '';

file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));

echo json_encode(['success' => true]);
