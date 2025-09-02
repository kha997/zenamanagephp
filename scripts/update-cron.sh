#!/bin/bash

# Update Cron Jobs Script
# Cập nhật cấu hình crontab với các tác vụ mới

set -e

# Configuration
SCRIPT_DIR="/var/www/html/scripts"
LOG_DIR="/var/log/zenamanage"

echo "Updating crontab with production maintenance tasks..."

# Create comprehensive crontab
(crontab -l 2>/dev/null || echo "") | grep -v "zenamanage" > /tmp/crontab_temp

cat >> /tmp/crontab_temp << EOF

# Z.E.N.A Management System - Production Tasks

# Database backup - Daily at 2:00 AM
0 2 * * * $SCRIPT_DIR/backup-database.sh >> $LOG_DIR/backup.log 2>&1

# File backup - Daily at 3:00 AM
0 3 * * * $SCRIPT_DIR/backup-files.sh >> $LOG_DIR/backup.log 2>&1

# System monitoring - Every 5 minutes
*/5 * * * * $SCRIPT_DIR/monitor-system.sh >> $LOG_DIR/monitoring.log 2>&1

# Performance monitoring - Every 15 minutes
*/15 * * * * $SCRIPT_DIR/performance-monitor.sh >> $LOG_DIR/performance.log 2>&1

# Health check - Every 2 minutes
*/2 * * * * $SCRIPT_DIR/health-check.sh >> $LOG_DIR/health.log 2>&1

# Database maintenance - Weekly on Sunday at 4:00 AM
0 4 * * 0 $SCRIPT_DIR/maintenance-database.sh >> $LOG_DIR/maintenance.log 2>&1

# Log cleanup - Daily at 1:00 AM
0 1 * * * $SCRIPT_DIR/cleanup-logs.sh >> $LOG_DIR/cleanup.log 2>&1

# Laravel queue work restart - Every hour
0 * * * * cd /var/www/html && php artisan queue:restart >> $LOG_DIR/queue.log 2>&1

# Laravel cache clear - Daily at 5:00 AM
0 5 * * * cd /var/www/html && php artisan cache:clear >> $LOG_DIR/cache.log 2>&1

# Laravel schedule runner - Every minute
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1

EOF

# Install the new crontab
crontab /tmp/crontab_temp

# Clean up
rm /tmp/crontab_temp

echo "Crontab updated successfully!"
echo "Current crontab:"
crontab -l