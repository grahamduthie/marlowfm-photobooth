<?php
/**
 * Marlow FM Photobooth - Photo Capture API
 * Handles photo saving, branding, and token generation
 */

header('Content-Type: application/json');
require_once '/home/marlowfm/photobooth-config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get form data
$show = $_POST['show'] ?? 'Unknown';
$presenter = $_POST['presenter'] ?? '';
$guests = $_POST['guests'] ?? '';

// Handle file upload
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No photo uploaded']);
    exit;
}

// Generate unique token
$token = bin2hex(random_bytes(16));

// Create filename
$dateStr  = date(FILENAME_FORMAT);
$seqNum   = getPhotoSequence($dateStr, $show);
$sequence = str_pad($seqNum, 3, '0', STR_PAD_LEFT);
$defaultTitle = date('d M Y') . ' - Photo ' . $sequence;

// Build filename parts
$filenameParts = [$dateStr, sanitizeFilename($show)];
if ($presenter) $filenameParts[] = sanitizeFilename($presenter);
if ($guests) $filenameParts[] = sanitizeFilename($guests);
$filenameParts[] = $sequence;

$baseFilename = implode('_', $filenameParts);

// Create directory structure
$dirPath = PHOTO_BASE_DIR . '/' . date('Y/m/d');
if (!file_exists($dirPath)) {
    mkdir($dirPath, 0755, true);
}

// Save clean version
$cleanPath = $dirPath . '/' . $baseFilename . '_clean.jpg';
if (!move_uploaded_file($_FILES['photo']['tmp_name'], $cleanPath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save photo']);
    exit;
}

// Logo is already composited client-side; branded = clean
$brandedPath = $dirPath . '/' . $baseFilename . '_branded.jpg';
copy($cleanPath, $brandedPath);

// Set permissions
chmod($cleanPath, 0644);
chmod($brandedPath, 0644);

// Store metadata for download
$metadata = [
    'token'            => $token,
    'filename_clean'   => $baseFilename . '_clean.jpg',
    'filename_branded' => $baseFilename . '_branded.jpg',
    'title'            => $defaultTitle,
    'show'             => $show,
    'presenter'        => $presenter,
    'guests'           => $guests,
    'created'          => date('Y-m-d H:i:s'),
    'expires'          => date('Y-m-d H:i:s', strtotime('+' . QR_EXPIRY_DAYS . ' days'))
];

saveMetadata($token, $metadata);

// Return URLs
echo json_encode([
    'success'      => true,
    'token'        => $token,
    'title'        => $defaultTitle,
    'sequence'     => $seqNum,
    'clean_url'    => '/photobooth/photos/' . date('Y/m/d') . '/' . $baseFilename . '_clean.jpg',
    'branded_url'  => '/photobooth/photos/' . date('Y/m/d') . '/' . $baseFilename . '_branded.jpg',
    'download_url' => 'https://photobooth.marlowfm.co.uk:8444/download.php?token=' . $token
]);

/**
 * Get sequence number for today's photos
 */
function getPhotoSequence($dateStr, $show) {
    $dirPath = PHOTO_BASE_DIR . '/' . date('Y/m/d');
    if (!file_exists($dirPath)) {
        return 1;
    }
    
    $files = glob($dirPath . '/' . $dateStr . '_' . sanitizeFilename($show) . '_*.jpg');
    return count($files) + 1;
}

/**
 * Sanitize filename - remove special characters
 */
function sanitizeFilename($str) {
    $str = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $str);
    $str = str_replace(' ', '-', $str);
    return $str;
}

/**
 * Add Marlow FM logo to photo
 */
function addLogoToPhoto($source, $destination) {
    // Check if ImageMagick is available
    if (!class_exists('Imagick')) {
        // Fallback: just copy the file
        copy($source, $destination);
        return;
    }
    
    try {
        $photo = new Imagick($source);
        $logo = new Imagick(LOGO_PATH);
        
        // Resize logo
        $logoWidth = LOGO_SIZE;
        $logoRatio = $logo->getImageHeight() / $logo->getImageWidth();
        $logo->thumbnailImage($logoWidth, $logoWidth * $logoRatio);
        
        // Position logo (bottom-right)
        $photoWidth = $photo->getImageWidth();
        $photoHeight = $photo->getImageHeight();
        $logoX = $photoWidth - $logo->getImageWidth() - LOGO_MARGIN;
        $logoY = $photoHeight - $logo->getImageHeight() - LOGO_MARGIN;
        
        // Composite logo onto photo
        $photo->compositeImage($logo, Imagick::COMPOSITE_OVER, $logoX, $logoY);
        
        // Save with quality
        $photo->setImageFormat('jpeg');
        $photo->setImageCompressionQuality(JPEG_QUALITY);
        $photo->writeImage($destination);
        
        $photo->destroy();
        $logo->destroy();
    } catch (Exception $e) {
        // Fallback: just copy
        copy($source, $destination);
    }
}

/**
 * Save metadata for download token
 */
function saveMetadata($token, $metadata) {
    $metadataFile = PHOTO_BASE_DIR . '/.metadata.json';
    
    $allMetadata = [];
    if (file_exists($metadataFile)) {
        $allMetadata = json_decode(file_get_contents($metadataFile), true) ?? [];
    }
    
    $allMetadata[$token] = $metadata;
    
    file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));
}
