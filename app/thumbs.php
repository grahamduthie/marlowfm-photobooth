<?php
/**
 * Marlow FM Photobooth - Thumbnail Server
 * Generates resized thumbnails on demand and caches them.
 * Usage: /photobooth/thumbs.php?path=YYYY/MM/DD/filename.jpg&w=300
 */

$photosBase = '/photos';
$requestedPath = $_GET['path'] ?? '';
$width = min(600, max(60, (int)($_GET['w'] ?? 300)));

// Sanitise: strip traversal attempts and normalise
$requestedPath = ltrim(preg_replace(['#\.\.#', '#/+#'], ['', '/'], $requestedPath), '/');
$fullPath = $photosBase . '/' . $requestedPath;

// Resolve real path and verify it stays inside the photos dir
$realFull = realpath($fullPath);
if ($realFull === false || strpos($realFull, realpath($photosBase)) !== 0) {
    http_response_code(403);
    exit;
}

if (!file_exists($realFull)) {
    http_response_code(404);
    exit;
}

// ── Thumbnail cache location ──────────────────────────────────────────────
$thumbDir  = $photosBase . '/thumbs/' . dirname($requestedPath);
$thumbFile = $thumbDir . '/' . pathinfo($requestedPath, PATHINFO_FILENAME) . "_w{$width}.jpg";

if (file_exists($thumbFile)) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=604800'); // 1 week
    header('Content-Length: ' . filesize($thumbFile));
    readfile($thumbFile);
    exit;
}

// ── Generate thumbnail with GD ─────────────────────────────────────────────
if (!is_dir($thumbDir)) {
    mkdir($thumbDir, 0755, true);
}

$src = @imagecreatefromjpeg($realFull);
if (!$src) {
    // Serve original if GD can't read it
    header('Content-Type: image/jpeg');
    readfile($realFull);
    exit;
}

$origW = imagesx($src);
$origH = imagesy($src);
$newH  = (int) round($origH * $width / $origW);

$dst = imagecreatetruecolor($width, $newH);

// Preserve white background (in case of any transparency artefacts)
$white = imagecolorallocate($dst, 255, 255, 255);
imagefill($dst, 0, 0, $white);

imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $newH, $origW, $origH);
imagejpeg($dst, $thumbFile, 82);
imagedestroy($src);
imagedestroy($dst);

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=604800');
header('Content-Length: ' . filesize($thumbFile));
readfile($thumbFile);
