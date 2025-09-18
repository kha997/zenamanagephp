#!/bin/bash

# Log Cleanup Script
# Quản lý và xoá các file log cũ

set -e

# Configuration
APP_DIR="/var/www/html"
LOG_DIR="$APP_DIR/storage/logs"
NGINX_LOG_DIR="/var/log/nginx"
SYSTEM_LOG_DIR="/var/log/zenamanage"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

# Logging function
log() {
    echo "[$DATE] $1"
}

log "Starting log cleanup process..."

# 1. Compress and archive Laravel logs older than 7 days
log "Archiving Laravel logs..."
find "$LOG_DIR" -name "*.log" -type f -mtime +7 -exec gzip {} \;

# 2. Remove compressed logs older than 30 days
log "Removing old compressed Laravel logs..."
find "$LOG_DIR" -name "*.log.gz" -type f -mtime +30 -delete

# 3. Rotate Nginx access logs
log "Rotating Nginx access logs..."
if [ -f "$NGINX_LOG_DIR/access.log" ]; then
    mv "$NGINX_LOG_DIR/access.log" "$NGINX_LOG_DIR/access.log.$(date +%Y%m%d)"
    touch "$NGINX_LOG_DIR/access.log"
    chown www-data:adm "$NGINX_LOG_DIR/access.log"
    chmod 640 "$NGINX_LOG_DIR/access.log"
fi

# 4. Rotate Nginx error logs
log "Rotating Nginx error logs..."
if [ -f "$NGINX_LOG_DIR/error.log" ]; then
    mv "$NGINX_LOG_DIR/error.log" "$NGINX_LOG_DIR/error.log.$(date +%Y%m%d)"
    touch "$NGINX_LOG_DIR/error.log"
    chown www-data:adm "$NGINX_LOG_DIR/error.log"
    chmod 640 "$NGINX_LOG_DIR/error.log"
fi

# 5. Compress old Nginx logs
log "Compressing old Nginx logs..."
find "$NGINX_LOG_DIR" -name "*.log.*" -type f ! -name "*.gz" -mtime +1 -exec gzip {} \;

# 6. Remove old Nginx logs
log "Removing old Nginx logs..."
find "$NGINX_LOG_DIR" -name "*.log.*.gz" -type f -mtime +30 -delete

# 7. Clean up system logs
log "Cleaning up system logs..."
if [ -d "$SYSTEM_LOG_DIR" ]; then
    find "$SYSTEM_LOG_DIR" -name "*.log" -type f -mtime +30 -delete
fi

# 8. Clean up PHP-FPM logs
log "Cleaning up PHP-FPM logs..."
find /var/log -name "php*fpm*.log*" -type f -mtime +30 -delete 2>/dev/null || true

# 9. Reload Nginx to recognize new log files
log "Reloading Nginx..."
sudo systemctl reload nginx

# 10. Clear Laravel log cache
log "Clearing Laravel log cache..."
cd "$APP_DIR"
php artisan cache:clear

log "Log cleanup completed successfully."