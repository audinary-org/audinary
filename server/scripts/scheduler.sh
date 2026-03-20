#!/bin/sh
# Lightweight task scheduler for Docker containers
# Runs periodic cleanup tasks without requiring crond

SCRIPT_DIR="/var/www/html/scripts"
PHP_BIN="$(which php)"
CYCLE_COUNT=0
# Daily backup runs every 96 cycles (96 x 15min = 24h)
BACKUP_INTERVAL=96

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] SCHEDULER: $1"
}

log "Starting task scheduler"

while true; do
    CYCLE_COUNT=$((CYCLE_COUNT + 1))

    # --- Every 15 minutes: cleanup tasks ---
    log "Running cleanup tasks (cycle $CYCLE_COUNT)..."

    $PHP_BIN "$SCRIPT_DIR/cleanup_rate_limits.php" 2>&1 | while read -r line; do log "$line"; done
    $PHP_BIN "$SCRIPT_DIR/cleanup_password_reset_tokens.php" 2>&1 | while read -r line; do log "$line"; done
    $PHP_BIN "$SCRIPT_DIR/cleanup_status.php" 2>&1 | while read -r line; do log "$line"; done
    $PHP_BIN "$SCRIPT_DIR/cleanup_backups.php" 2>&1 | while read -r line; do log "$line"; done

    # --- Daily: create backup ---
    if [ "$CYCLE_COUNT" -ge "$BACKUP_INTERVAL" ]; then
        log "Running daily backup..."
        $PHP_BIN "$SCRIPT_DIR/create_backup.php" 2>&1 | while read -r line; do log "$line"; done
        CYCLE_COUNT=0
    fi

    log "Tasks completed. Sleeping 15 minutes..."
    sleep 900
done
