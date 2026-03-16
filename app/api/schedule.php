<?php
/**
 * Marlow FM Photobooth - Schedule API
 * Returns the full weekly schedule
 */

header('Content-Type: application/json');
require_once '/home/marlowfm/photobooth-config/config.php';

$schedule = json_decode(file_get_contents(SCHEDULE_FILE), true);

if (!$schedule) {
    echo json_encode(['error' => 'Schedule not found']);
    exit;
}

echo json_encode($schedule);
