#!/bin/bash
# Marlow FM Photobooth - Kiosk Browser Startup Script
# Starts Chromium in kiosk mode pointing to the photobooth app

# Wait for network to be ready
sleep 5

# Wait for X to be ready
while ! pgrep -x "xfce4-session" > /dev/null; do
    sleep 1
done

# ── WiFi / network check ──────────────────────────────────────────────
# Wait up to 15 seconds for NetworkManager to auto-connect to a saved network
for i in $(seq 1 15); do
    nmcli -t -f STATE general 2>/dev/null | grep -q "^connected$" && break
    sleep 1
done

if ! nmcli -t -f STATE general 2>/dev/null | grep -q "^connected$"; then
    # Write helper script to a temp file to avoid quoting issues with -e / --command
    cat > /tmp/photobooth-wifi-setup.sh << 'WIFIEOF'
#!/bin/bash
clear
echo "======================================="
echo "  Marlow FM Photobooth - WiFi Setup"
echo "======================================="
echo ""
echo "  No network connection detected."
echo ""
echo "  Steps:"
echo "    1. Select 'Activate a connection'"
echo "    2. Choose your WiFi network"
echo "    3. Enter the password when prompted"
echo "    4. Select Back, then Quit when done"
echo ""
echo "======================================="
sleep 2
nmtui
WIFIEOF
    chmod +x /tmp/photobooth-wifi-setup.sh

    # Open terminal for WiFi setup; closes automatically when nmtui quits
    xfce4-terminal \
        --title="Marlow FM Photobooth - Connect to WiFi" \
        --geometry="60x24" \
        --command="/tmp/photobooth-wifi-setup.sh"

    # Wait up to 20 seconds for the connection to fully establish after nmtui closes
    for i in $(seq 1 20); do
        nmcli -t -f STATE general 2>/dev/null | grep -q "^connected$" && break
        sleep 1
    done
fi
# ── End WiFi check ────────────────────────────────────────────────────

# Kill any existing browser instances
pkill -9 chromium 2>/dev/null
pkill -9 chrome 2>/dev/null

# Wait a moment for browser to fully close
sleep 2

# Set display settings for better colors
xrandr --output LVDS --gamma 0.78:0.80:0.72 2>/dev/null

# Disable X11 screen blanking
xset s off
xset -dpms

# Start Chromium in kiosk mode
# Note: --kiosk alone is sufficient; --app= causes conflicts when combined with --kiosk
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
    http://localhost/photobooth/ &

# Store PID for monitoring
echo $! > /tmp/photobooth-browser.pid
