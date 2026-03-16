#!/bin/bash
# Marlow FM Photobooth - Kiosk Browser Startup Script
# Starts Chromium in kiosk mode pointing to the photobooth app

# Wait for network to be ready
sleep 5

# Wait for X to be ready
while ! pgrep -x "xfce4-session" > /dev/null; do
    sleep 1
done

# Kill any existing browser instances
pkill -9 chromium 2>/dev/null
pkill -9 chrome 2>/dev/null

# Wait a moment
sleep 2

# Set display settings for better colors
xrandr --output LVDS --gamma 0.78:0.80:0.72 2>/dev/null

# Disable X11 screen blanking (DPMS is already off; this kills the 10-min blank timeout)
xset s off
xset s noblank

# Start Chromium in kiosk mode
chromium \
    --kiosk \
    --app=http://localhost/photobooth/ \
    --disable-restore-session-state \
    --disable-new-tab-first-run \
    --disable-background-networking \
    --disable-checker-imaging-for-32bit-images \
    --disable-component-update \
    --disable-default-apps \
    --disable-extensions \
    --disable-popup-blocking \
    --disable-pinch \
    --disable-sync \
    --no-first-run \
    --password-store=basic \
    --use-fake-ui-for-media-stream \
    --user-data-dir=/home/marlowfm/.chromium-photobooth \
    http://localhost/photobooth/ &

# Store PID for monitoring
echo $! > /tmp/photobooth-browser.pid
