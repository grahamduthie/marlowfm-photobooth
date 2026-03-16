#!/bin/bash
# Marlow FM Photobooth - Screensaver
# Shows a gallery photo slideshow after 1 hour of inactivity.

PID_FILE=/tmp/screensaver.pid

# Don't start a second instance
if [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
    exit 0
fi

chromium \
    --kiosk \
    --app=http://localhost/photobooth/screensaver.html \
    --user-data-dir=/home/marlowfm/.chromium-screensaver \
    --no-first-run \
    --disable-extensions \
    --disable-background-networking \
    --disable-sync &>/dev/null &

echo $! > "$PID_FILE"
