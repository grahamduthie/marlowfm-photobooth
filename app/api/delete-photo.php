<?php
/**
 * Marlow FM Photobooth - Delete Photo API
 * Deletes a photo and its associated files
 */

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$path = $data['path'] ?? '';
$filename = $data['filename'] ?? '';

if (empty($path) || empty($filename)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing path or filename']);
    exit;
}

// Security: ensure path is within photos directory
$photosDir = '/photos';
$realPath = realpath($path);
if ($realPath === false || strpos($realPath, $photosDir) !== 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid path']);
    exit;
}

// Check file exists
if (!file_exists($realPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Extract base filename (without _branded.jpg)
$baseFilename = str_replace('_branded.jpg', '', $filename);
$dir = dirname($realPath);

$deleted = [];
$errors = [];

// Delete branded version
if (file_exists($realPath)) {
    if (unlink($realPath)) {
        $deleted[] = basename($realPath);
    } else {
        $errors[] = 'Could not delete branded version';
    }
}

// Delete clean version if exists
$cleanPath = $dir . '/' . str_replace('_branded.jpg', '_clean.jpg', $filename);
if (file_exists($cleanPath)) {
    if (unlink($cleanPath)) {
        $deleted[] = basename($cleanPath);
    } else {
        $errors[] = 'Could not delete clean version';
    }
}

// Update metadata file
$metadataFile = $photosDir . '/.metadata.json';
if (file_exists($metadataFile)) {
    $allMetadata = json_decode(file_get_contents($metadataFile), true) ?? [];
    
    // Find and remove token for this photo
    $tokenToRemove = null;
    foreach ($allMetadata as $token => $meta) {
        if ($meta['filename_branded'] === $filename) {
            $tokenToRemove = $token;
            break;
        }
    }
    
    if ($tokenToRemove) {
        unset($allMetadata[$tokenToRemove]);
        file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));
        $deleted[] = 'metadata entry';
    }
}

if (!empty($deleted)) {
    echo json_encode([
        'success' => true,
        'deleted' => $deleted,
        'errors' => $errors
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to delete files',
        'errors' => $errors
    ]);
}
