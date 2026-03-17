<?php
/**
 * Marlow FM Photobooth - Internet Connectivity Check
 * Called by the browser after a photo is captured to decide whether to
 * show the QR code (requires internet) or the offline message.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once '/home/marlowfm/photobooth-config/config.php';
require_once __DIR__ . '/_email-core.php';

echo json_encode(['online' => isInternetAvailable()]);
