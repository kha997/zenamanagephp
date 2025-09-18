#!/bin/bash

# Script setup crontab cho production
# Sử dụng: ./setup-cron.sh [environment]

ENVIRONMENT=${1:-production}
APP_DIR="/var/www/zenamanage"
SCRIPTS_DIR="${APP_DIR}/scripts"

echo "Setting up crontab for ${ENVIRONMENT} environment..."

# Tạo temporary crontab file
TEMP_CRON="/tmp/zenamanage_cron"

# Backup crontab hiện tại
crontab -l > "${TEMP_CRON}" 2>/dev/null || echo "# ZenaManage Crontab" > "${TEMP_CRON}"

# Xóa các entry cũ của ZenaManage
sed -i '/# ZenaManage/d' "${TEMP_CRON}"
sed -i '/zenamanage/d' "${TEMP_CRON}"

# Thêm các cron jobs mới
cat >> "${TEMP_CRON}" << EOF

# ZenaManage Production Cron Jobs
# Database backup - daily at 2:00 AM
0 2 * * * ${SCRIPTS_DIR}/backup-database.sh ${ENVIRONMENT} >> /var/log/zenamanage-backup.log 2>&1

# Files backup - daily at 3:00 AM
0 3 * * * ${SCRIPTS_DIR}/backup-files.sh ${ENVIRONMENT} >> /var/log/zenamanage-backup.log 2>&1

# System monitoring - every 5 minutes
*/5 * * * * ${SCRIPTS_DIR}/monitor-system.sh ${ENVIRONMENT}

# Laravel queue worker restart - every hour
0 * * * * cd ${APP_DIR} && php artisan queue:restart

# Laravel schedule runner - every minute
* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1

# Clear application cache - daily at 4:00 AM
0 4 * * * cd ${APP_DIR} && php artisan cache:clear && php artisan config:clear

# Cleanup old logs - weekly on Sunday at 5:00 AM
0 5 * * 0 find ${APP_DIR}/storage/logs -name "*.log" -mtime +30 -delete

# Health check and restart services if needed - every 10 minutes
*/10 * * * * ${SCRIPTS_DIR}/health-check.sh ${ENVIRONMENT}

EOF

# Apply crontab
crontab "${TEMP_CRON}"

if [ $? -eq 0 ]; then
    echo "Crontab setup completed successfully"
    echo "Current crontab:"
    crontab -l
else
    echo "Error: Failed to setup crontab"
    exit 1
fi

# Cleanup
rm -f "${TEMP_CRON}"

echo "Cron jobs have been configured for ${ENVIRONMENT} environment"