<?php
/**
 * Marlow FM Photobooth - Random Photos API
 * Returns a selection of random photos from the gallery
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$photosDir = '/photos';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 6;

// Find all branded photos (they have the logo overlay)
$photos = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($photosDir)
);

foreach ($iterator as $file) {
    if ($file->isFile() && 
        $file->getExtension() === 'jpg' && 
        strpos($file->getFilename(), '_branded.jpg') !== false) {
        // Get web-accessible URL path
        $relativePath = '/photos' . str_replace($photosDir, '', $file->getPathname());
        $photos[] = $relativePath;
    }
}

// Shuffle and pick random photos
shuffle($photos);
$selected = array_slice($photos, 0, $limit);

echo json_encode([
    'photos' => $selected,
    'total' => count($photos)
]);
