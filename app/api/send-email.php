<?php
/**
 * Marlow FM Photobooth - Email Sender API
 * Sends photos via email using IONOS SMTP.
 * If the internet is not reachable, the request is queued and sent by
 * process-email-queue.php (run by cron) when connectivity is restored.
 */

header('Content-Type: application/json');
require_once '/home/marlowfm/photobooth-config/config.php';
require_once '/home/marlowfm/marlowfm-photobooth/app/vendor/autoload.php';
require_once __DIR__ . '/_email-core.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$token = $input['token'] ?? '';

if (!$email || !$token) {
    echo json_encode(['success' => false, 'error' => 'Missing email or token']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

// Load metadata
$metadataFile = PHOTO_BASE_DIR . '/.metadata.json';
if (!file_exists($metadataFile)) {
    echo json_encode(['success' => false, 'error' => 'Photo not found']);
    exit;
}

$allMetadata = json_decode(file_get_contents($metadataFile), true);
$metadata    = $allMetadata[$token] ?? null;

if (!$metadata) {
    echo json_encode(['success' => false, 'error' => 'Photo not found']);
    exit;
}

$filePath = PHOTO_BASE_DIR . '/' . date('Y/m/d', strtotime($metadata['created'])) . '/' . $metadata['filename_branded'];
if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'error' => 'Photo file not found']);
    exit;
}

// If offline, queue and return immediately rather than waiting for SMTP timeout
if (!isInternetAvailable()) {
    queueEmail($email, $token);
    logEmailSend($email, $token, false, 'queued (offline)');
    echo json_encode(['success' => true, 'queued' => true]);
    exit;
}

// Online: send immediately
$result = sendWithPHPMailer($email, $filePath, $metadata);

if ($result['success']) {
    $allMetadata[$token]['emailed'] = true;
    file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));
}

echo json_encode($result);
