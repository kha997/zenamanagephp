#!/bin/bash

# Automated Backup System
# This script provides comprehensive backup functionality for ZenaManage

set -e

# Configuration
BACKUP_DIR=${BACKUP_DIR:-"/var/backups/zenamanage"}
DB_HOST=${DB_HOST:-"localhost"}
DB_PORT=${DB_PORT:-"3306"}
DB_NAME=${DB_NAME:-"zenamanage"}
DB_USER=${DB_USER:-"root"}
DB_PASSWORD=${DB_PASSWORD:-"password"}
APP_DIR=${APP_DIR:-"/var/www/html"}
RETENTION_DAYS=${RETENTION_DAYS:-"30"}
COMPRESSION=${COMPRESSION:-"gzip"}
ENCRYPTION=${ENCRYPTION:-"false"}
ENCRYPTION_KEY=${ENCRYPTION_KEY:-""}

# Backup types
BACKUP_TYPES=("database" "files" "config" "logs" "full")

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging
LOG_FILE="/var/log/backup.log"
log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] INFO:${NC} $1" | tee -a "$LOG_FILE"
}

# Function to create backup directory
create_backup_dir() {
    local backup_type=$1
    local timestamp=$(date '+%Y%m%d_%H%M%S')
    local backup_path="$BACKUP_DIR/$backup_type/$timestamp"
    
    mkdir -p "$backup_path"
    echo "$backup_path"
}

# Function to compress backup
compress_backup() {
    local backup_path=$1
    local backup_name=$(basename "$backup_path")
    local backup_dir=$(dirname "$backup_path")
    
    case $COMPRESSION in
        "gzip")
            tar -czf "$backup_dir/${backup_name}.tar.gz" -C "$backup_dir" "$backup_name"
            rm -rf "$backup_path"
            echo "$backup_dir/${backup_name}.tar.gz"
            ;;
        "bzip2")
            tar -cjf "$backup_dir/${backup_name}.tar.bz2" -C "$backup_dir" "$backup_name"
            rm -rf "$backup_path"
            echo "$backup_dir/${backup_name}.tar.bz2"
            ;;
        "xz")
            tar -cJf "$backup_dir/${backup_name}.tar.xz" -C "$backup_dir" "$backup_name"
            rm -rf "$backup_path"
            echo "$backup_dir/${backup_name}.tar.xz"
            ;;
        "none")
            echo "$backup_path"
            ;;
        *)
            warning "Unknown compression type: $COMPRESSION. Using gzip."
            tar -czf "$backup_dir/${backup_name}.tar.gz" -C "$backup_dir" "$backup_name"
            rm -rf "$backup_path"
            echo "$backup_dir/${backup_name}.tar.gz"
            ;;
    esac
}

# Function to encrypt backup
encrypt_backup() {
    local backup_file=$1
    
    if [ "$ENCRYPTION" = "true" ] && [ -n "$ENCRYPTION_KEY" ]; then
        local encrypted_file="${backup_file}.enc"
        openssl enc -aes-256-cbc -salt -in "$backup_file" -out "$encrypted_file" -pass pass:"$ENCRYPTION_KEY"
        rm "$backup_file"
        echo "$encrypted_file"
    else
        echo "$backup_file"
    fi
}

# Function to backup database
backup_database() {
    log "Starting database backup..."
    
    local backup_path=$(create_backup_dir "database")
    local backup_file="$backup_path/database.sql"
    
    # Create database dump
    mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --hex-blob \
        --add-drop-database \
        --databases "$DB_NAME" > "$backup_file"
    
    if [ $? -eq 0 ]; then
        log "Database backup completed: $backup_file"
        
        # Compress backup
        local compressed_file=$(compress_backup "$backup_path")
        
        # Encrypt backup
        local final_file=$(encrypt_backup "$compressed_file")
        
        log "Database backup ready: $final_file"
        echo "$final_file"
    else
        error "Database backup failed"
        return 1
    fi
}

# Function to backup application files
backup_files() {
    log "Starting application files backup..."
    
    local backup_path=$(create_backup_dir "files")
    
    # Backup application directory (excluding unnecessary files)
    tar --exclude='node_modules' \
        --exclude='vendor' \
        --exclude='storage/logs' \
        --exclude='storage/framework/cache' \
        --exclude='storage/framework/sessions' \
        --exclude='storage/framework/views' \
        --exclude='.git' \
        --exclude='*.log' \
        --exclude='*.tmp' \
        -cf "$backup_path/app.tar" -C "$APP_DIR" .
    
    if [ $? -eq 0 ]; then
        log "Application files backup completed: $backup_path/app.tar"
        
        # Compress backup
        local compressed_file=$(compress_backup "$backup_path")
        
        # Encrypt backup
        local final_file=$(encrypt_backup "$compressed_file")
        
        log "Application files backup ready: $final_file"
        echo "$final_file"
    else
        error "Application files backup failed"
        return 1
    fi
}

# Function to backup configuration files
backup_config() {
    log "Starting configuration backup..."
    
    local backup_path=$(create_backup_dir "config")
    
    # Backup configuration files
    cp "$APP_DIR/.env" "$backup_path/" 2>/dev/null || warning ".env file not found"
    cp "$APP_DIR/config/*.php" "$backup_path/" 2>/dev/null || warning "Config files not found"
    cp "$APP_DIR/docker-compose*.yml" "$backup_path/" 2>/dev/null || warning "Docker compose files not found"
    cp "$APP_DIR/Dockerfile*" "$backup_path/" 2>/dev/null || warning "Dockerfile not found"
    
    # Backup nginx configuration
    cp /etc/nginx/sites-available/* "$backup_path/" 2>/dev/null || warning "Nginx config not found"
    
    # Backup cron jobs
    crontab -l > "$backup_path/crontab" 2>/dev/null || warning "No crontab found"
    
    if [ $? -eq 0 ]; then
        log "Configuration backup completed: $backup_path"
        
        # Compress backup
        local compressed_file=$(compress_backup "$backup_path")
        
        # Encrypt backup
        local final_file=$(encrypt_backup "$compressed_file")
        
        log "Configuration backup ready: $final_file"
        echo "$final_file"
    else
        error "Configuration backup failed"
        return 1
    fi
}

# Function to backup logs
backup_logs() {
    log "Starting logs backup..."
    
    local backup_path=$(create_backup_dir "logs")
    
    # Backup application logs
    cp -r "$APP_DIR/storage/logs" "$backup_path/" 2>/dev/null || warning "Application logs not found"
    
    # Backup system logs
    cp /var/log/nginx/*.log "$backup_path/" 2>/dev/null || warning "Nginx logs not found"
    cp /var/log/mysql/*.log "$backup_path/" 2>/dev/null || warning "MySQL logs not found"
    
    # Backup backup logs
    cp "$LOG_FILE" "$backup_path/backup.log" 2>/dev/null || warning "Backup log not found"
    
    if [ $? -eq 0 ]; then
        log "Logs backup completed: $backup_path"
        
        # Compress backup
        local compressed_file=$(compress_backup "$backup_path")
        
        # Encrypt backup
        local final_file=$(encrypt_backup "$compressed_file")
        
        log "Logs backup ready: $final_file"
        echo "$final_file"
    else
        error "Logs backup failed"
        return 1
    fi
}

# Function to perform full backup
backup_full() {
    log "Starting full backup..."
    
    local backup_path=$(create_backup_dir "full")
    local backup_files=()
    
    # Backup database
    local db_backup=$(backup_database)
    if [ $? -eq 0 ]; then
        cp "$db_backup" "$backup_path/"
        backup_files+=("$(basename "$db_backup")")
    fi
    
    # Backup files
    local files_backup=$(backup_files)
    if [ $? -eq 0 ]; then
        cp "$files_backup" "$backup_path/"
        backup_files+=("$(basename "$files_backup")")
    fi
    
    # Backup config
    local config_backup=$(backup_config)
    if [ $? -eq 0 ]; then
        cp "$config_backup" "$backup_path/"
        backup_files+=("$(basename "$config_backup")")
    fi
    
    # Backup logs
    local logs_backup=$(backup_logs)
    if [ $? -eq 0 ]; then
        cp "$logs_backup" "$backup_path/"
        backup_files+=("$(basename "$logs_backup")")
    fi
    
    # Create backup manifest
    cat > "$backup_path/manifest.json" <<EOF
{
    "backup_date": "$(date -Iseconds)",
    "backup_type": "full",
    "backup_files": $(printf '%s\n' "${backup_files[@]}" | jq -R . | jq -s .),
    "compression": "$COMPRESSION",
    "encryption": "$ENCRYPTION",
    "retention_days": $RETENTION_DAYS
}
EOF
    
    log "Full backup completed: $backup_path"
    echo "$backup_path"
}

# Function to cleanup old backups
cleanup_old_backups() {
    log "Cleaning up old backups..."
    
    local deleted_count=0
    
    for backup_type in "${BACKUP_TYPES[@]}"; do
        local backup_dir="$BACKUP_DIR/$backup_type"
        
        if [ -d "$backup_dir" ]; then
            # Find backups older than retention period
            local old_backups=$(find "$backup_dir" -type f -name "*.tar.gz" -o -name "*.tar.bz2" -o -name "*.tar.xz" -o -name "*.enc" -mtime +$RETENTION_DAYS)
            
            if [ -n "$old_backups" ]; then
                echo "$old_backups" | while read -r backup; do
                    rm -f "$backup"
                    deleted_count=$((deleted_count + 1))
                    log "Deleted old backup: $backup"
                done
            fi
        fi
    done
    
    log "Cleanup completed. Deleted $deleted_count old backups."
}

# Function to verify backup
verify_backup() {
    local backup_file=$1
    
    log "Verifying backup: $backup_file"
    
    # Check if file exists and has content
    if [ ! -f "$backup_file" ]; then
        error "Backup file does not exist: $backup_file"
        return 1
    fi
    
    local file_size=$(stat -c%s "$backup_file")
    if [ "$file_size" -eq 0 ]; then
        error "Backup file is empty: $backup_file"
        return 1
    fi
    
    # Test compression
    case "$backup_file" in
        *.tar.gz)
            tar -tzf "$backup_file" >/dev/null 2>&1
            ;;
        *.tar.bz2)
            tar -tjf "$backup_file" >/dev/null 2>&1
            ;;
        *.tar.xz)
            tar -tJf "$backup_file" >/dev/null 2>&1
            ;;
        *.enc)
            # For encrypted files, just check if they exist and have content
            ;;
        *)
            warning "Unknown backup file format: $backup_file"
            ;;
    esac
    
    if [ $? -eq 0 ]; then
        log "Backup verification successful: $backup_file"
        return 0
    else
        error "Backup verification failed: $backup_file"
        return 1
    fi
}

# Function to send backup notification
send_notification() {
    local backup_type=$1
    local status=$2
    local backup_file=$3
    
    # This is a placeholder for notification logic
    # You can integrate with email, Slack, Discord, etc.
    
    if [ "$status" = "success" ]; then
        log "Backup completed successfully: $backup_type -> $backup_file"
    else
        error "Backup failed: $backup_type"
    fi
}

# Function to show backup status
show_status() {
    log "Backup system status:"
    
    for backup_type in "${BACKUP_TYPES[@]}"; do
        local backup_dir="$BACKUP_DIR/$backup_type"
        
        if [ -d "$backup_dir" ]; then
            local count=$(find "$backup_dir" -type f | wc -l)
            local size=$(du -sh "$backup_dir" | cut -f1)
            log "  $backup_type: $count backups ($size)"
        else
            log "  $backup_type: No backups"
        fi
    done
}

# Function to restore backup
restore_backup() {
    local backup_file=$1
    local restore_type=$2
    
    log "Starting restore from: $backup_file"
    
    case $restore_type in
        "database")
            restore_database "$backup_file"
            ;;
        "files")
            restore_files "$backup_file"
            ;;
        "config")
            restore_config "$backup_file"
            ;;
        "full")
            restore_full "$backup_file"
            ;;
        *)
            error "Unknown restore type: $restore_type"
            return 1
            ;;
    esac
}

# Function to restore database
restore_database() {
    local backup_file=$1
    
    log "Restoring database from: $backup_file"
    
    # Decrypt if needed
    local temp_file="$backup_file"
    if [[ "$backup_file" == *.enc ]]; then
        temp_file="${backup_file%.enc}"
        openssl enc -aes-256-cbc -d -in "$backup_file" -out "$temp_file" -pass pass:"$ENCRYPTION_KEY"
    fi
    
    # Extract if compressed
    if [[ "$temp_file" == *.tar.gz ]]; then
        tar -xzf "$temp_file" -C /tmp/
        temp_file="/tmp/$(basename "$temp_file" .tar.gz)/database.sql"
    fi
    
    # Restore database
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" < "$temp_file"
    
    if [ $? -eq 0 ]; then
        log "Database restore completed successfully"
    else
        error "Database restore failed"
        return 1
    fi
}

# Main execution
main() {
    local backup_type=${1:-"full"}
    
    log "Starting backup process: $backup_type"
    
    # Create backup directory
    mkdir -p "$BACKUP_DIR"
    
    # Perform backup based on type
    case $backup_type in
        "database")
            backup_file=$(backup_database)
            ;;
        "files")
            backup_file=$(backup_files)
            ;;
        "config")
            backup_file=$(backup_config)
            ;;
        "logs")
            backup_file=$(backup_logs)
            ;;
        "full")
            backup_file=$(backup_full)
            ;;
        "cleanup")
            cleanup_old_backups
            exit 0
            ;;
        "status")
            show_status
            exit 0
            ;;
        "restore")
            restore_backup "$2" "$3"
            exit 0
            ;;
        *)
            error "Unknown backup type: $backup_type"
            echo "Usage: $0 [database|files|config|logs|full|cleanup|status|restore]"
            exit 1
            ;;
    esac
    
    # Verify backup
    if [ -n "$backup_file" ]; then
        verify_backup "$backup_file"
        
        if [ $? -eq 0 ]; then
            send_notification "$backup_type" "success" "$backup_file"
        else
            send_notification "$backup_type" "failed" "$backup_file"
            exit 1
        fi
    fi
    
    # Cleanup old backups
    cleanup_old_backups
    
    log "Backup process completed successfully"
}

# Run main function
main "$@"
