<?php
/**
 * Marlow FM Photobooth - Current Show Detection API
 * Returns the currently broadcasting show based on schedule
 */

header('Content-Type: application/json');
require_once '/home/marlowfm/photobooth-config/config.php';

function getCurrentShow() {
    $schedule = json_decode(file_get_contents(SCHEDULE_FILE), true);

    if (!$schedule) {
        return ['current' => 'The Jukebox', 'previous' => 'The Jukebox'];
    }

    $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $now = new DateTime();
    $day = $days[$now->format('w')];
    $currentHour = (int)$now->format('H');

    // Get previous hour
    $previousHour = $currentHour - 1;
    if ($previousHour < 0) {
        $previousHour = 23;
    }

    $daySchedule = $schedule[$day] ?? [];

    // Find current show - get all show start hours and find the most recent one
    $currentShow = 'The Jukebox';
    $previousShow = 'The Jukebox';
    
    // Sort hours numerically
    $hours = array_keys($daySchedule);
    usort($hours, function($a, $b) {
        return (int)$a - (int)$b;
    });
    
    // Find the latest show that started at or before current hour
    // Only consider shows starting between 05:00 and current hour (avoid next-day early shows)
    $relevantHours = array_filter($hours, function($h) use ($currentHour) {
        $hourInt = (int)$h;
        // For evening/night (18+), only consider shows from 05:00 onwards
        // For early morning (00-05), also consider late night shows from previous day
        if ($currentHour >= 18) {
            return $hourInt >= 5 && $hourInt <= $currentHour;
        } else {
            return $hourInt <= $currentHour;
        }
    });
    
    if (!empty($relevantHours)) {
        $latestHour = max($relevantHours);
        $currentShow = $daySchedule[$latestHour];
        
        // Find previous show
        $prevRelevantHours = array_filter($relevantHours, function($h) use ($latestHour) {
            return (int)$h < (int)$latestHour;
        });
        if (!empty($prevRelevantHours)) {
            $prevLatestHour = max($prevRelevantHours);
            $previousShow = $daySchedule[$prevLatestHour];
        }
    }

    return [
        'current' => $currentShow,
        'previous' => $previousShow,
        'day' => $day,
        'hour' => $currentHour
    ];
}

echo json_encode(getCurrentShow());
