<?php
/**
 * Marlow FM Photobooth - Email Queue Processor
 *
 * Run by cron every minute:
 *   * * * * * /usr/bin/php /home/marlowfm/marlowfm-photobooth/app/api/process-email-queue.php >> /home/marlowfm/marlowfm-photobooth/logs/email.log 2>&1
 */

require_once '/home/marlowfm/photobooth-config/config.php';
require_once '/home/marlowfm/marlowfm-photobooth/app/vendor/autoload.php';
require_once __DIR__ . '/_email-core.php';

// Nothing to do if the queue file doesn't exist yet
if (!file_exists(EMAIL_QUEUE_FILE)) exit(0);

// Quick connectivity check — don't bother loading the queue if we're still offline
if (!isInternetAvailable()) exit(0);

// Load queue with exclusive lock
$fp = fopen(EMAIL_QUEUE_FILE, 'c+');
if (!$fp) exit(1);

flock($fp, LOCK_EX);
$contents = stream_get_contents($fp);
$queue    = $contents ? (json_decode($contents, true) ?? []) : [];

if (empty($queue)) {
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(0);
}

// Sync photos to remote before sending emails.
// This ensures the download link in every email resolves to a file that actually exists
// on the remote server — critical when photos were taken while offline.
exec(
    'rsync -aq --delete'
    . ' --include="*/" --include="*.jpg" --include=".metadata.json"'
    . ' --exclude="thumbs/" --exclude="*"'
    . ' /photos/ broadcast@10.10.0.165:/var/www/photobooth/photos/'
    . ' 2>/dev/null',
    $rsyncOut,
    $rsyncExit
);
if ($rsyncExit !== 0) {
    echo '[' . date('Y-m-d H:i:s') . "] Sync failed (exit $rsyncExit) — deferring email queue\n";
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(0);
}

// Load metadata so we can find photo paths and mark emails as sent
$metadataFile    = PHOTO_BASE_DIR . '/.metadata.json';
$allMetadata     = [];
if (file_exists($metadataFile)) {
    $allMetadata = json_decode(file_get_contents($metadataFile), true) ?? [];
}

$remaining       = [];
$metadataChanged = false;

foreach ($queue as $item) {
    $email = $item['email'];
    $token = $item['token'];

    $metadata = $allMetadata[$token] ?? null;
    if (!$metadata) {
        // Photo was deleted before we could send — drop from queue
        echo '[' . date('Y-m-d H:i:s') . "] SKIPPED (no metadata) - Token: $token\n";
        continue;
    }

    $filePath = PHOTO_BASE_DIR . '/' . date('Y/m/d', strtotime($metadata['created'])) . '/' . $metadata['filename_branded'];
    if (!file_exists($filePath)) {
        // File was hard-deleted — drop from queue
        echo '[' . date('Y-m-d H:i:s') . "] SKIPPED (file missing) - Token: $token\n";
        continue;
    }

    $result = sendWithPHPMailer($email, $filePath, $metadata);

    if ($result['success']) {
        $allMetadata[$token]['emailed'] = true;
        $metadataChanged = true;
        echo '[' . date('Y-m-d H:i:s') . "] SENT (was queued) - To: $email, Token: $token\n";
    } else {
        // Send failed despite connectivity check passing — keep for next attempt
        $remaining[] = $item;
        echo '[' . date('Y-m-d H:i:s') . "] RETRY - To: $email, Token: $token - " . ($result['error'] ?? '') . "\n";
    }
}

// Write remaining items back (or empty array to clear the file)
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($remaining, JSON_PRETTY_PRINT));
flock($fp, LOCK_UN);
fclose($fp);

// Persist metadata changes
if ($metadataChanged) {
    file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));
}
