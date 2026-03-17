#!/bin/bash
# Marlow FM Photobooth - Screensaver (FIXED VERSION)
# Shows a gallery photo slideshow after 1 hour of inactivity.

PID_FILE=/tmp/screensaver.pid

# Don't start a second instance
if [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
    exit 0
fi

# Clear any stale lock files
rm -rf /tmp/.org.chromium.Chromium.* 2>/dev/null

# Start Chromium in kiosk mode
# FIXED: Removed --app= which conflicts with --kiosk and causes white screen issues
# Using --kiosk alone with URL as the last argument (same as main kiosk script)
# Added --disable-gpu to avoid DRM buffer allocation failures on older Radeon hardware
chromium \
    --kiosk \
    --user-data-dir=/home/marlowfm/.chromium-screensaver \
    --no-first-run \
    --disable-extensions \
    --disable-background-networking \
    --disable-sync \
    --disable-gpu \
    --disable-software-rasterizer \
    --touch-events=disabled \
    http://localhost/photobooth/screensaver.html \
    &>/dev/null &

echo $! > "$PID_FILE"

# Log startup for debugging
echo "[$(date)] Screensaver started with PID $(cat $PID_FILE)" >> /home/marlowfm/marlowfm-photobooth/logs/kiosk-startup.log
