#!/bin/bash
# Marlow FM Photobooth - Photo Sync to Remote
# Watches /photos for new files and rsyncs to photobooth.marlowfm.co.uk
#
# Syncs: photos (JPEGs) and .metadata.json
# Does NOT sync: thumbs/ cache directory

PHOTOS_DIR=/photos
REMOTE_USER=broadcast
REMOTE_HOST=10.10.0.165
REMOTE_DIR=/var/www/photobooth/photos

LOG=/home/marlowfm/marlowfm-photobooth/logs/photo-sync.log

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" >> "$LOG"
}

do_sync() {
    rsync -a --quiet --delete \
        --include='*/' \
        --include='*.jpg' \
        --include='.metadata.json' \
        --exclude='thumbs/' \
        --exclude='*' \
        "$PHOTOS_DIR/" \
        "$REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR/" 2>&1

    if [ $? -eq 0 ]; then
        log "Sync OK"
    else
        log "Sync FAILED"
    fi
}

log "Photo sync started, watching $PHOTOS_DIR"

# Initial sync on startup
do_sync

# Watch for new/modified files and sync after a brief settle period
inotifywait -m -r -e close_write,create,moved_to \
    --exclude '/thumbs/' \
    --format '%w%f' \
    "$PHOTOS_DIR" 2>/dev/null | while read -r changed_file; do

    # Only react to JPEGs and metadata
    if [[ "$changed_file" == *.jpg ]] || [[ "$changed_file" == *metadata.json ]]; then
        sleep 2   # brief settle to let any burst of writes complete
        do_sync
    fi
done
