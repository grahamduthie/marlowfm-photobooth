#!/bin/bash
# Marlow FM Photobooth - Kiosk Diagnostic Script
# Run this after reboot to diagnose white screen issues

LOG_FILE=/home/marlowfm/marlowfm-photobooth/logs/kiosk-diagnostic.log

echo "=== Kiosk Diagnostic Report ===" | tee "$LOG_FILE"
echo "Date: $(date)" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

echo "1. X Session Status:" | tee -a "$LOG_FILE"
pgrep -a xfce4-session | tee -a "$LOG_FILE"
pgrep -a xfwm4 | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

echo "2. Display Configuration:" | tee -a "$LOG_FILE"
xrandr 2>&1 | head -10 | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

echo "3. Current Chromium Processes:" | tee -a "$LOG_FILE"
ps aux | grep -i chrom | grep -v grep | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

echo "4. Chromium Lock Files:" | tee -a "$LOG_FILE"
ls -la /tmp/.org.chromium.Chromium.* 2>/dev/null || echo "No lock files found" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

echo "5. User Data Directory:" | tee -a "$LOG_FILE"
ls -la /home/marlowfm/.chromium-photobooth/ 2>/dev/null | head -10 | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

echo "6. X11 Blanking Status:" | tee -a "$LOG_FILE"
xset q | grep -E "timeout|DPMS" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

echo "7. Apache Status:" | tee -a "$LOG_FILE"
systemctl status apache2 2>&1 | grep -E "Active|Loaded" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

echo "8. Test Local URL:" | tee -a "$LOG_FILE"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost/photobooth/ | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

echo "9. Recent X Session Errors:" | tee -a "$LOG_FILE"
tail -20 ~/.xsession-errors 2>/dev/null | grep -i chrom | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

echo "=== Diagnostic Complete ===" | tee -a "$LOG_FILE"
