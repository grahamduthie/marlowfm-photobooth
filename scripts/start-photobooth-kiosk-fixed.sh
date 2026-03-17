#!/bin/bash
# Marlow FM Photobooth - Kiosk Browser Startup Script (FIXED VERSION)
# Starts Chromium in kiosk mode pointing to the photobooth app

# Wait for network to be ready
sleep 5

# Wait for X session to be fully ready (not just xfce4-session process)
# Check for window manager which indicates X is truly ready
while ! pgrep -x "xfwm4" > /dev/null; do
    sleep 1
done

# Additional wait for compositor to initialize
sleep 3

# Kill any existing browser instances
pkill -9 chromium 2>/dev/null
pkill -9 chrome 2>/dev/null

# Wait a moment for browser to fully close
sleep 2

# Set display settings for better colors
# Note: LVDS is the correct output name on this Toshiba C850D-12L
xrandr --output LVDS --gamma 0.78:0.80:0.72 2>/dev/null || echo "Warning: xrandr gamma adjustment failed"

# Disable X11 screen blanking
xset s off
xset -dpms

# Clear Chromium cache and lock files to prevent stale content issues
rm -rf /tmp/.org.chromium.Chromium.* 2>/dev/null
rm -rf /home/marlowfm/.chromium-photobooth/Default/Cache/* 2>/dev/null
rm -rf /home/marlowfm/.chromium-photobooth/Default/Code\ Cache/* 2>/dev/null
rm -rf /home/marlowfm/.chromium-photobooth/Default/GPUCache/* 2>/dev/null

# Start Chromium in kiosk mode with enhanced compatibility flags
# Key changes:
# - Added --disable-gpu to avoid DRM buffer allocation failures on older Radeon hardware
# - The DRM_IOCTL_MODE_CREATE_DUMB errors (27, 13) indicate GPU memory allocation failures
# - Software rendering is more stable on this Toshiba C850D-12L with older ATI graphics
# - Kept --kiosk alone (NOT combined with --app= as they conflict)
chromium \
    --kiosk \
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
    --disable-gpu \
    --disable-software-rasterizer \
    --touch-events=disabled \
    http://localhost/photobooth/ &

# Store PID for monitoring
echo $! > /tmp/photobooth-browser.pid

# Log startup for debugging
echo "[$(date)] Chromium kiosk started with PID $(cat /tmp/photobooth-browser.pid)" >> /home/marlowfm/marlowfm-photobooth/logs/kiosk-startup.log
