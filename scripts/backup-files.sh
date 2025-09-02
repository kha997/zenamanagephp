#!/bin/bash

# Script backup files cho production
# Sử dụng: ./backup-files.sh [environment]

set -e

# Cấu hình
ENVIRONMENT=${1:-production}
APP_DIR="/var/www/zenamanage"
BACKUP_DIR="/var/backups/zenamanage"
DATE=$(date +"%Y%m%d_%H%M%S")
RETENTION_DAYS=7
S3_BUCKET="zenamanage-backups"

# Load environment variables
if [ -f "${APP_DIR}/.env.${ENVIRONMENT}" ]; then
    export $(cat ${APP_DIR}/.env.${ENVIRONMENT} | grep -v '^#' | xargs)
fi

# Tạo thư mục backup
mkdir -p "${BACKUP_DIR}"

echo "[$(date)] Starting files backup for ${ENVIRONMENT} environment..."

# Danh sách thư mục cần backup
DIRECTORIES_TO_BACKUP=(
    "storage/app/public"
    "storage/app/documents"
    "storage/app/uploads"
    "public/uploads"
    ".env.${ENVIRONMENT}"
)

# Tạo file backup
BACKUP_FILE="${BACKUP_DIR}/zenamanage_files_${ENVIRONMENT}_${DATE}.tar.gz"

cd "${APP_DIR}"

# Tạo danh sách file để backup
FILES_LIST="/tmp/backup_files_${DATE}.txt"
> "${FILES_LIST}"

for dir in "${DIRECTORIES_TO_BACKUP[@]}"; do
    if [ -e "$dir" ]; then
        echo "$dir" >> "${FILES_LIST}"
        echo "[$(date)] Added to backup: $dir"
    else
        echo "[$(date)] Warning: $dir not found, skipping"
    fi
done

# Tạo archive
if [ -s "${FILES_LIST}" ]; then
    tar -czf "${BACKUP_FILE}" -T "${FILES_LIST}" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        echo "[$(date)] Files backup completed: ${BACKUP_FILE}"
    else
        echo "[$(date)] Error: Files backup failed"
        rm -f "${FILES_LIST}"
        exit 1
    fi
else
    echo "[$(date)] Error: No files to backup"
    rm -f "${FILES_LIST}"
    exit 1
fi

# Cleanup
rm -f "${FILES_LIST}"

# Upload to S3
if [ ! -z "${AWS_ACCESS_KEY_ID}" ] && [ ! -z "${S3_BUCKET}" ]; then
    echo "[$(date)] Uploading files backup to S3..."
    aws s3 cp "${BACKUP_FILE}" "s3://${S3_BUCKET}/files/$(basename ${BACKUP_FILE})"
    
    if [ $? -eq 0 ]; then
        echo "[$(date)] Files backup uploaded to S3 successfully"
    else
        echo "[$(date)] Warning: Failed to upload files backup to S3"
    fi
fi

# Xóa backup cũ
echo "[$(date)] Cleaning up old file backups..."
find "${BACKUP_DIR}" -name "zenamanage_files_${ENVIRONMENT}_*.tar.gz" -mtime +${RETENTION_DAYS} -delete

# Kiểm tra dung lượng
BACKUP_SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
echo "[$(date)] Files backup size: ${BACKUP_SIZE}"

echo "[$(date)] Files backup script finished"