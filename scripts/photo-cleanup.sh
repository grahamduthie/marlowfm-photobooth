#!/bin/bash
# Marlow FM Photobooth - Daily Photo Cleanup
# Deletes photos and metadata older than RETENTION_DAYS (set in config.php: 365 days)
# Run via cron; logs to photo-sync.log

LOG=/home/marlowfm/marlowfm-photobooth/logs/photo-sync.log

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] CLEANUP: $*" >> "$LOG"
}

RESULT=$(php -r "
    require_once '/home/marlowfm/photobooth-config/config.php';
    \$cutoff = strtotime('-' . RETENTION_DAYS . ' days');
    \$metaFile = PHOTO_BASE_DIR . '/.metadata.json';
    if (!file_exists(\$metaFile)) { echo '0 deleted (no metadata)'; exit; }
    \$all = json_decode(file_get_contents(\$metaFile), true) ?? [];
    \$deleted = 0;
    foreach (\$all as \$token => \$data) {
        if (strtotime(\$data['created']) < \$cutoff) {
            \$dir = PHOTO_BASE_DIR . '/' . date('Y/m/d', strtotime(\$data['created']));
            @unlink(\$dir . '/' . \$data['filename_clean']);
            @unlink(\$dir . '/' . \$data['filename_branded']);
            unset(\$all[\$token]);
            \$deleted++;
        }
    }
    file_put_contents(\$metaFile, json_encode(\$all, JSON_PRETTY_PRINT));
    echo \$deleted . ' deleted';
" 2>&1)

log "$RESULT"
