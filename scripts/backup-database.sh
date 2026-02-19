#!/bin/bash

# Z.E.N.A Project Management - Database Backup Script
# Author: Development Team
# Version: 1.0

set -e

# Configuration
BACKUP_DIR="/var/backups/zena/database"
DATE=$(date +"%Y%m%d_%H%M%S")
DB_CONTAINER="zena_mysql"
DB_NAME="zena_db"
DB_USER="zena_user"
: "${DB_PASSWORD:?Set DB_PASSWORD (do NOT hardcode in repo)}"
RETENTION_DAYS=30

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_info "Starting database backup..."

# Create backup directory
mkdir -p $BACKUP_DIR

# Check if container is running
if ! docker ps | grep -q $DB_CONTAINER; then
    log_error "Database container $DB_CONTAINER is not running."
    exit 1
fi

# Create backup
log_info "Creating database backup..."
BACKUP_FILE="$BACKUP_DIR/zena_db_backup_$DATE.sql"

docker exec $DB_CONTAINER mysqldump -u$DB_USER -p$DB_PASSWORD $DB_NAME > $BACKUP_FILE

if [ $? -eq 0 ]; then
    # Compress backup
    gzip $BACKUP_FILE
    log_info "✅ Database backup created: ${BACKUP_FILE}.gz"
    
    # Cleanup old backups
    log_info "Cleaning up old backups (older than $RETENTION_DAYS days)..."
    find $BACKUP_DIR -name "zena_db_backup_*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete
    
    log_info "🎉 Database backup completed successfully!"
else
    log_error "❌ Database backup failed!"
    exit 1
fi