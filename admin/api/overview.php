<?php
/**
 * Marlow FM Photobooth - Admin Overview API
 * Returns dashboard statistics
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

// Get photo statistics
$metadataFile = PHOTO_BASE_DIR . '/.metadata.json';
$allMetadata = file_exists($metadataFile) ? json_decode(file_get_contents($metadataFile), true) : [];

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-7 days'));

$photosToday = 0;
$photosWeek = 0;
$recentPhotos = [];

foreach ($allMetadata as $token => $data) {
    $photoDate = date('Y-m-d', strtotime($data['created']));
    
    if ($photoDate === $today) {
        $photosToday++;
    }
    if ($photoDate >= $weekAgo) {
        $photosWeek++;
    }
    
    // Collect recent photos (last 10)
    $recentPhotos[] = [
        'token' => $token,
        'created' => $data['created'],
        'show' => $data['show'],
        'presenter' => $data['presenter'] ?? '',
        'guests' => $data['guests'] ?? '',
        'path' => date('Y/m/d', strtotime($data['created']))
    ];
}

// Sort by date descending
usort($recentPhotos, function($a, $b) {
    return strtotime($b['created']) - strtotime($a['created']);
});
$recentPhotos = array_slice($recentPhotos, 0, 10);

// Get disk usage
$diskTotal = disk_total_space(PHOTO_BASE_DIR) / (1024 * 1024 * 1024);
$diskFree = disk_free_space(PHOTO_BASE_DIR) / (1024 * 1024 * 1024);
$diskUsed = $diskTotal - $diskFree;
$diskUsage = round(($diskUsed / $diskTotal) * 100, 1) . '%';

// Check camera
$cameraStatus = 'Unknown';
if (file_exists('/dev/video2')) {
    $cameraStatus = 'OK';
} elseif (file_exists('/dev/video0')) {
    $cameraStatus = 'OK (fallback)';
} else {
    $cameraStatus = 'Not detected';
}

// Check Apache
$apacheStatus = 'Unknown';
exec('systemctl is-active apache2 2>/dev/null', $output, $return);
$apacheStatus = ($return === 0) ? 'OK' : 'Not running';

// Check SMTP (basic connection test)
$smtpStatus = 'Unknown';
$smtpTest = @fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 2);
if ($smtpTest) {
    $smtpStatus = 'OK';
    fclose($smtpTest);
} else {
    $smtpStatus = 'Connection failed';
}

echo json_encode([
    'photos_today' => $photosToday,
    'photos_week' => $photosWeek,
    'disk_usage' => $diskUsage,
    'email_queue' => 0, // Would need email queue implementation
    'recent_photos' => $recentPhotos,
    'camera_status' => $cameraStatus,
    'apache_status' => $apacheStatus,
    'smtp_status' => $smtpStatus
]);
