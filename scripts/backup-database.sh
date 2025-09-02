#!/bin/bash

# Script backup database cho production
# Sử dụng: ./backup-database.sh [environment]

set -e

# Cấu hình
ENVIRONMENT=${1:-production}
BACKUP_DIR="/var/backups/zenamanage"
DATE=$(date +"%Y%m%d_%H%M%S")
RETENTION_DAYS=30
S3_BUCKET="zenamanage-backups"

# Load environment variables
if [ -f ".env.${ENVIRONMENT}" ]; then
    export $(cat .env.${ENVIRONMENT} | grep -v '^#' | xargs)
else
    echo "Error: .env.${ENVIRONMENT} file not found"
    exit 1
fi

# Tạo thư mục backup nếu chưa tồn tại
mkdir -p "${BACKUP_DIR}"

# Tên file backup
BACKUP_FILE="${BACKUP_DIR}/zenamanage_${ENVIRONMENT}_${DATE}.sql"
COMPRESSED_FILE="${BACKUP_FILE}.gz"

echo "[$(date)] Starting database backup for ${ENVIRONMENT} environment..."

# Backup database
mysqldump \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --user="${DB_USERNAME}" \
    --password="${DB_PASSWORD}" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --add-drop-database \
    --databases "${DB_DATABASE}" > "${BACKUP_FILE}"

if [ $? -eq 0 ]; then
    echo "[$(date)] Database backup completed: ${BACKUP_FILE}"
else
    echo "[$(date)] Error: Database backup failed"
    exit 1
fi

# Nén file backup
gzip "${BACKUP_FILE}"
echo "[$(date)] Backup compressed: ${COMPRESSED_FILE}"

# Upload to S3 (nếu có cấu hình)
if [ ! -z "${AWS_ACCESS_KEY_ID}" ] && [ ! -z "${S3_BUCKET}" ]; then
    echo "[$(date)] Uploading backup to S3..."
    aws s3 cp "${COMPRESSED_FILE}" "s3://${S3_BUCKET}/database/$(basename ${COMPRESSED_FILE})"
    
    if [ $? -eq 0 ]; then
        echo "[$(date)] Backup uploaded to S3 successfully"
    else
        echo "[$(date)] Warning: Failed to upload backup to S3"
    fi
fi

# Xóa backup cũ (local)
echo "[$(date)] Cleaning up old backups (older than ${RETENTION_DAYS} days)..."
find "${BACKUP_DIR}" -name "zenamanage_${ENVIRONMENT}_*.sql.gz" -mtime +${RETENTION_DAYS} -delete

# Xóa backup cũ trên S3
if [ ! -z "${AWS_ACCESS_KEY_ID}" ] && [ ! -z "${S3_BUCKET}" ]; then
    CUTOFF_DATE=$(date -d "${RETENTION_DAYS} days ago" +"%Y-%m-%d")
    aws s3 ls "s3://${S3_BUCKET}/database/" | while read -r line; do
        FILE_DATE=$(echo $line | awk '{print $1}')
        FILE_NAME=$(echo $line | awk '{print $4}')
        
        if [[ "$FILE_DATE" < "$CUTOFF_DATE" ]]; then
            aws s3 rm "s3://${S3_BUCKET}/database/${FILE_NAME}"
            echo "[$(date)] Deleted old S3 backup: ${FILE_NAME}"
        fi
    done
fi

# Ghi log
echo "[$(date)] Database backup process completed" >> "/var/log/zenamanage-backup.log"

# Kiểm tra dung lượng backup
BACKUP_SIZE=$(du -h "${COMPRESSED_FILE}" | cut -f1)
echo "[$(date)] Backup size: ${BACKUP_SIZE}"

# Gửi notification (nếu có lỗi)
if [ $? -ne 0 ]; then
    # Gửi alert qua webhook hoặc email
    curl -X POST "${BACKUP_ALERT_WEBHOOK}" \
        -H "Content-Type: application/json" \
        -d "{
            \"text\": \"❌ Database backup failed for ${ENVIRONMENT} environment\",
            \"environment\": \"${ENVIRONMENT}\",
            \"timestamp\": \"$(date -Iseconds)\",
            \"error\": \"Backup process failed\"
        }" 2>/dev/null || true
else
    # Gửi thông báo thành công
    curl -X POST "${BACKUP_SUCCESS_WEBHOOK}" \
        -H "Content-Type: application/json" \
        -d "{
            \"text\": \"✅ Database backup completed for ${ENVIRONMENT} environment\",
            \"environment\": \"${ENVIRONMENT}\",
            \"timestamp\": \"$(date -Iseconds)\",
            \"size\": \"${BACKUP_SIZE}\",
            \"file\": \"$(basename ${COMPRESSED_FILE})\"
        }" 2>/dev/null || true
fi

echo "[$(date)] Backup script finished"