#!/bin/bash
# Marlow FM Photobooth - Idle Watcher
# Monitors user activity and triggers screensaver after 1 hour

SCREENSAVER_TIMEOUT=3600  # 1 hour in seconds
PID_FILE=/tmp/screensaver.pid

screensaver_running() {
    [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null
}

start_screensaver() {
    echo "Starting screensaver..."
    /home/marlowfm/marlowfm-screensaver.sh &
}

stop_screensaver() {
    echo "Stopping screensaver..."
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        kill "$PID" 2>/dev/null
        sleep 0.5
        kill -9 "$PID" 2>/dev/null
        rm -f "$PID_FILE"
    fi
}

check_activity() {
    if command -v xprintidle &>/dev/null; then
        IDLE_MS=$(xprintidle)
        IDLE_SEC=$((IDLE_MS / 1000))

        if [ $IDLE_SEC -ge $SCREENSAVER_TIMEOUT ] && ! screensaver_running; then
            start_screensaver
        elif [ $IDLE_SEC -lt 60 ] && screensaver_running; then
            stop_screensaver
        fi
    fi
}

while true; do
    check_activity
    sleep 5
done
