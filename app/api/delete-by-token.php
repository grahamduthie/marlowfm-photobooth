<?php
/**
 * Marlow FM Photobooth - Delete Photo by Token
 * Removes both photo files and the metadata entry for a given token.
 */

header('Content-Type: application/json');
require_once '/home/marlowfm/photobooth-config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');

if (!$token) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing token']);
    exit;
}

$metadataFile = PHOTO_BASE_DIR . '/.metadata.json';
if (!file_exists($metadataFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Metadata not found']);
    exit;
}

$allMetadata = json_decode(file_get_contents($metadataFile), true) ?? [];
$meta = $allMetadata[$token] ?? null;

if (!$meta) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Photo not found']);
    exit;
}

// If this photo was emailed, keep the files so the download link still works.
// Just mark it as deleted so it no longer appears in the gallery.
if (!empty($meta['emailed'])) {
    $allMetadata[$token]['deleted'] = true;
    file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true, 'deleted' => [], 'note' => 'Photo was emailed; files retained for download link']);
    exit;
}

$datePath    = date('Y/m/d', strtotime($meta['created']));
$dir         = PHOTO_BASE_DIR . '/' . $datePath;
$deleted     = [];

// Delete branded and clean versions
foreach (['filename_branded', 'filename_clean'] as $key) {
    $file = $dir . '/' . ($meta[$key] ?? '');
    if ($file !== $dir . '/' && file_exists($file)) {
        unlink($file) && $deleted[] = basename($file);
    }
}

// Delete any cached thumbnails for this photo
$thumbBase = PHOTO_BASE_DIR . '/thumbs/' . $datePath . '/'
           . pathinfo($meta['filename_branded'] ?? '', PATHINFO_FILENAME);
foreach (glob($thumbBase . '_w*.jpg') ?: [] as $thumbFile) {
    unlink($thumbFile);
}

// Remove from metadata
unset($allMetadata[$token]);
file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'deleted' => $deleted]);
