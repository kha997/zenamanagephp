#!/bin/bash

# Database Maintenance Script
# Tối ưu hóa và bảo trì cơ sở dữ liệu

set -e

# Load environment variables
source /var/www/html/.env

# Configuration
LOG_FILE="/var/log/zenamanage/maintenance.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')
MYSQL_USER="${DB_USERNAME}"
MYSQL_PASSWORD="${DB_PASSWORD}"
MYSQL_DATABASE="${DB_DATABASE}"
MYSQL_HOST="${DB_HOST:-localhost}"

# Logging function
log() {
    echo "[$DATE] $1" | tee -a "$LOG_FILE"
}

log "Starting database maintenance..."

# 1. Optimize tables
log "Optimizing database tables..."
mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "
    OPTIMIZE TABLE users, projects, tasks, components, documents, interaction_logs, 
    change_requests, notifications, event_logs, baselines, document_versions;
" >> "$LOG_FILE" 2>&1

# 2. Analyze tables for better query performance
log "Analyzing tables..."
mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "
    ANALYZE TABLE users, projects, tasks, components, documents, interaction_logs, 
    change_requests, notifications, event_logs, baselines, document_versions;
" >> "$LOG_FILE" 2>&1

# 3. Clean up old logs (older than 90 days)
log "Cleaning up old interaction logs..."
mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "
    DELETE FROM interaction_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
" >> "$LOG_FILE" 2>&1

# 4. Clean up old event logs (older than 30 days)
log "Cleaning up old event logs..."
mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "
    DELETE FROM event_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
" >> "$LOG_FILE" 2>&1

# 5. Clean up read notifications (older than 30 days)
log "Cleaning up old read notifications..."
mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "
    DELETE FROM notifications WHERE read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
" >> "$LOG_FILE" 2>&1

# 6. Update table statistics
log "Updating table statistics..."
mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "
    FLUSH TABLES;
" >> "$LOG_FILE" 2>&1

# 7. Check for table corruption
log "Checking for table corruption..."
mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "
    CHECK TABLE users, projects, tasks, components, documents, interaction_logs, 
    change_requests, notifications, event_logs, baselines, document_versions;
" >> "$LOG_FILE" 2>&1

log "Database maintenance completed successfully."