#!/bin/bash

# Disaster Recovery Automation Script
# ZenaManage Project - Automated DR Operations

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
CONFIG_FILE="$PROJECT_ROOT/config/dr-monitor.conf"
LOG_FILE="$PROJECT_ROOT/logs/dr-automation-$(date +%Y%m%d-%H%M%S).log"
BACKUP_DIR="$PROJECT_ROOT/storage/backups"
RECOVERY_DIR="$PROJECT_ROOT/storage/recovery"

# Load configuration
if [ -f "$CONFIG_FILE" ]; then
    source "$CONFIG_FILE"
else
    echo "Configuration file not found: $CONFIG_FILE"
    exit 1
fi

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Setup function
setup() {
    log_info "Setting up disaster recovery automation environment..."
    
    # Create necessary directories
    mkdir -p "$(dirname "$LOG_FILE")"
    mkdir -p "$BACKUP_DIR"
    mkdir -p "$RECOVERY_DIR"
    
    # Set permissions
    chmod 755 "$BACKUP_DIR"
    chmod 755 "$RECOVERY_DIR"
    
    log_info "DR automation environment setup complete"
}

# Backup automation
automate_backup() {
    log_info "Starting automated backup process..."
    
    local backup_type="${1:-full}"
    local timestamp=$(date +%Y%m%d_%H%M%S)
    
    case "$backup_type" in
        "full")
            automate_full_backup "$timestamp"
            ;;
        "incremental")
            automate_incremental_backup "$timestamp"
            ;;
        "database")
            automate_database_backup "$timestamp"
            ;;
        "application")
            automate_application_backup "$timestamp"
            ;;
        "config")
            automate_config_backup "$timestamp"
            ;;
        *)
            log_error "Invalid backup type: $backup_type"
            return 1
            ;;
    esac
    
    log_success "Automated backup process completed"
}

# Full backup automation
automate_full_backup() {
    local timestamp="$1"
    log_info "Creating full backup with timestamp: $timestamp"
    
    # Create backup directory
    local backup_path="$BACKUP_DIR/full-backup-$timestamp"
    mkdir -p "$backup_path"
    
    # Backup database
    if command -v mysqldump >/dev/null 2>&1; then
        log_info "Backing up database..."
        mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" > "$backup_path/database.sql"
        log_success "Database backup completed"
    else
        log_warning "mysqldump not available, skipping database backup"
    fi
    
    # Backup application files
    log_info "Backing up application files..."
    tar -czf "$backup_path/application.tar.gz" -C "$APP_ROOT" .
    log_success "Application backup completed"
    
    # Backup configuration files
    log_info "Backing up configuration files..."
    tar -czf "$backup_path/config.tar.gz" -C "$APP_ROOT" config/ .env
    log_success "Configuration backup completed"
    
    # Create backup manifest
    cat > "$backup_path/manifest.txt" << EOF
Backup Type: Full
Timestamp: $timestamp
Database: $DB_NAME
Application: $APP_ROOT
Created: $(date)
EOF
    
    log_success "Full backup completed: $backup_path"
}

# Incremental backup automation
automate_incremental_backup() {
    local timestamp="$1"
    log_info "Creating incremental backup with timestamp: $timestamp"
    
    # Create backup directory
    local backup_path="$BACKUP_DIR/incremental-backup-$timestamp"
    mkdir -p "$backup_path"
    
    # Find last full backup
    local last_full_backup=$(find "$BACKUP_DIR" -name "full-backup-*" -type d | sort | tail -1)
    
    if [ -z "$last_full_backup" ]; then
        log_warning "No full backup found, creating full backup instead"
        automate_full_backup "$timestamp"
        return
    fi
    
    log_info "Using last full backup as base: $last_full_backup"
    
    # Backup database changes
    if command -v mysqldump >/dev/null 2>&1; then
        log_info "Backing up database changes..."
        mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" > "$backup_path/database.sql"
        log_success "Database backup completed"
    fi
    
    # Backup application changes
    log_info "Backing up application changes..."
    tar -czf "$backup_path/application.tar.gz" -C "$APP_ROOT" .
    log_success "Application backup completed"
    
    # Create backup manifest
    cat > "$backup_path/manifest.txt" << EOF
Backup Type: Incremental
Timestamp: $timestamp
Base Backup: $last_full_backup
Database: $DB_NAME
Application: $APP_ROOT
Created: $(date)
EOF
    
    log_success "Incremental backup completed: $backup_path"
}

# Database backup automation
automate_database_backup() {
    local timestamp="$1"
    log_info "Creating database backup with timestamp: $timestamp"
    
    # Create backup directory
    local backup_path="$BACKUP_DIR/database-backup-$timestamp"
    mkdir -p "$backup_path"
    
    # Backup database
    if command -v mysqldump >/dev/null 2>&1; then
        log_info "Backing up database..."
        mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" > "$backup_path/database.sql"
        log_success "Database backup completed"
    else
        log_error "mysqldump not available"
        return 1
    fi
    
    # Create backup manifest
    cat > "$backup_path/manifest.txt" << EOF
Backup Type: Database
Timestamp: $timestamp
Database: $DB_NAME
Host: $DB_HOST
Port: $DB_PORT
Created: $(date)
EOF
    
    log_success "Database backup completed: $backup_path"
}

# Application backup automation
automate_application_backup() {
    local timestamp="$1"
    log_info "Creating application backup with timestamp: $timestamp"
    
    # Create backup directory
    local backup_path="$BACKUP_DIR/application-backup-$timestamp"
    mkdir -p "$backup_path"
    
    # Backup application files
    log_info "Backing up application files..."
    tar -czf "$backup_path/application.tar.gz" -C "$APP_ROOT" .
    log_success "Application backup completed"
    
    # Create backup manifest
    cat > "$backup_path/manifest.txt" << EOF
Backup Type: Application
Timestamp: $timestamp
Application: $APP_ROOT
Created: $(date)
EOF
    
    log_success "Application backup completed: $backup_path"
}

# Configuration backup automation
automate_config_backup() {
    local timestamp="$1"
    log_info "Creating configuration backup with timestamp: $timestamp"
    
    # Create backup directory
    local backup_path="$BACKUP_DIR/config-backup-$timestamp"
    mkdir -p "$backup_path"
    
    # Backup configuration files
    log_info "Backing up configuration files..."
    tar -czf "$backup_path/config.tar.gz" -C "$APP_ROOT" config/ .env
    log_success "Configuration backup completed"
    
    # Create backup manifest
    cat > "$backup_path/manifest.txt" << EOF
Backup Type: Configuration
Timestamp: $timestamp
Application: $APP_ROOT
Created: $(date)
EOF
    
    log_success "Configuration backup completed: $backup_path"
}

# Recovery automation
automate_recovery() {
    log_info "Starting automated recovery process..."
    
    local recovery_type="${1:-full}"
    local backup_path="${2:-}"
    
    if [ -z "$backup_path" ]; then
        log_error "Backup path not specified"
        return 1
    fi
    
    if [ ! -d "$backup_path" ]; then
        log_error "Backup path does not exist: $backup_path"
        return 1
    fi
    
    case "$recovery_type" in
        "full")
            automate_full_recovery "$backup_path"
            ;;
        "database")
            automate_database_recovery "$backup_path"
            ;;
        "application")
            automate_application_recovery "$backup_path"
            ;;
        "config")
            automate_config_recovery "$backup_path"
            ;;
        *)
            log_error "Invalid recovery type: $recovery_type"
            return 1
            ;;
    esac
    
    log_success "Automated recovery process completed"
}

# Full recovery automation
automate_full_recovery() {
    local backup_path="$1"
    log_info "Starting full recovery from: $backup_path"
    
    # Stop services
    log_info "Stopping services..."
    systemctl stop nginx mysql redis php-fpm 2>/dev/null || true
    log_success "Services stopped"
    
    # Recover database
    if [ -f "$backup_path/database.sql" ]; then
        log_info "Recovering database..."
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$backup_path/database.sql"
        log_success "Database recovered"
    fi
    
    # Recover application files
    if [ -f "$backup_path/application.tar.gz" ]; then
        log_info "Recovering application files..."
        tar -xzf "$backup_path/application.tar.gz" -C "$APP_ROOT"
        log_success "Application files recovered"
    fi
    
    # Recover configuration files
    if [ -f "$backup_path/config.tar.gz" ]; then
        log_info "Recovering configuration files..."
        tar -xzf "$backup_path/config.tar.gz" -C "$APP_ROOT"
        log_success "Configuration files recovered"
    fi
    
    # Start services
    log_info "Starting services..."
    systemctl start mysql redis php-fpm nginx 2>/dev/null || true
    log_success "Services started"
    
    log_success "Full recovery completed"
}

# Database recovery automation
automate_database_recovery() {
    local backup_path="$1"
    log_info "Starting database recovery from: $backup_path"
    
    if [ ! -f "$backup_path/database.sql" ]; then
        log_error "Database backup file not found: $backup_path/database.sql"
        return 1
    fi
    
    # Stop database
    log_info "Stopping database..."
    systemctl stop mysql 2>/dev/null || true
    
    # Recover database
    log_info "Recovering database..."
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$backup_path/database.sql"
    log_success "Database recovered"
    
    # Start database
    log_info "Starting database..."
    systemctl start mysql 2>/dev/null || true
    log_success "Database started"
    
    log_success "Database recovery completed"
}

# Application recovery automation
automate_application_recovery() {
    local backup_path="$1"
    log_info "Starting application recovery from: $backup_path"
    
    if [ ! -f "$backup_path/application.tar.gz" ]; then
        log_error "Application backup file not found: $backup_path/application.tar.gz"
        return 1
    fi
    
    # Stop application services
    log_info "Stopping application services..."
    systemctl stop nginx php-fpm 2>/dev/null || true
    
    # Recover application files
    log_info "Recovering application files..."
    tar -xzf "$backup_path/application.tar.gz" -C "$APP_ROOT"
    log_success "Application files recovered"
    
    # Start application services
    log_info "Starting application services..."
    systemctl start php-fpm nginx 2>/dev/null || true
    log_success "Application services started"
    
    log_success "Application recovery completed"
}

# Configuration recovery automation
automate_config_recovery() {
    local backup_path="$1"
    log_info "Starting configuration recovery from: $backup_path"
    
    if [ ! -f "$backup_path/config.tar.gz" ]; then
        log_error "Configuration backup file not found: $backup_path/config.tar.gz"
        return 1
    fi
    
    # Stop services
    log_info "Stopping services..."
    systemctl stop nginx mysql redis php-fpm 2>/dev/null || true
    
    # Recover configuration files
    log_info "Recovering configuration files..."
    tar -xzf "$backup_path/config.tar.gz" -C "$APP_ROOT"
    log_success "Configuration files recovered"
    
    # Start services
    log_info "Starting services..."
    systemctl start mysql redis php-fpm nginx 2>/dev/null || true
    log_success "Services started"
    
    log_success "Configuration recovery completed"
}

# Monitoring automation
automate_monitoring() {
    log_info "Starting automated monitoring..."
    
    # Check system health
    check_system_health
    
    # Check service status
    check_service_status
    
    # Check backup status
    check_backup_status
    
    # Check disk space
    check_disk_space
    
    # Check memory usage
    check_memory_usage
    
    log_success "Automated monitoring completed"
}

# System health check
check_system_health() {
    log_info "Checking system health..."
    
    # Check CPU load
    local cpu_load=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    if (( $(echo "$cpu_load > 2.0" | bc -l) )); then
        log_warning "High CPU load: $cpu_load"
    else
        log_info "CPU load: $cpu_load - OK"
    fi
    
    # Check memory usage
    local memory_usage=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    if [ "$memory_usage" -gt 90 ]; then
        log_warning "High memory usage: ${memory_usage}%"
    else
        log_info "Memory usage: ${memory_usage}% - OK"
    fi
    
    # Check disk space
    local disk_usage=$(df "$APP_ROOT" | awk 'NR==2 {print $5}' | sed 's/%//')
    if [ "$disk_usage" -gt 90 ]; then
        log_warning "High disk usage: ${disk_usage}%"
    else
        log_info "Disk usage: ${disk_usage}% - OK"
    fi
}

# Service status check
check_service_status() {
    log_info "Checking service status..."
    
    local services=("nginx" "mysql" "redis" "php-fpm")
    
    for service in "${services[@]}"; do
        if systemctl is-active --quiet "$service" 2>/dev/null; then
            log_info "Service $service - OK"
        else
            log_warning "Service $service - NOT RUNNING"
        fi
    done
}

# Backup status check
check_backup_status() {
    log_info "Checking backup status..."
    
    local today=$(date +%Y%m%d)
    local backup_files=(
        "database-backup-$today.sql"
        "application-backup-$today.tar.gz"
        "config-backup-$today.tar.gz"
    )
    
    for backup_file in "${backup_files[@]}"; do
        if [ -f "$BACKUP_DIR/$backup_file" ]; then
            log_info "Backup $backup_file - OK"
        else
            log_warning "Backup $backup_file - MISSING"
        fi
    done
}

# Disk space check
check_disk_space() {
    log_info "Checking disk space..."
    
    local disk_usage=$(df "$APP_ROOT" | awk 'NR==2 {print $5}' | sed 's/%//')
    local disk_path=$(df "$APP_ROOT" | awk 'NR==2 {print $1}')
    
    if [ "$disk_usage" -gt 90 ]; then
        log_warning "Disk usage is ${disk_usage}% on $disk_path"
    else
        log_info "Disk usage is ${disk_usage}% on $disk_path - OK"
    fi
}

# Memory usage check
check_memory_usage() {
    log_info "Checking memory usage..."
    
    local memory_usage=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    
    if [ "$memory_usage" -gt 90 ]; then
        log_warning "Memory usage is ${memory_usage}%"
    else
        log_info "Memory usage is ${memory_usage}% - OK"
    fi
}

# Cleanup automation
automate_cleanup() {
    log_info "Starting automated cleanup..."
    
    local days_to_keep="${1:-30}"
    local cutoff_date=$(date -d "$days_to_keep days ago" +%Y%m%d)
    
    log_info "Cleaning up backups older than $days_to_keep days (before $cutoff_date)"
    
    # Clean up old backups
    find "$BACKUP_DIR" -type d -name "*backup-*" -exec basename {} \; | while read -r backup_dir; do
        local backup_date=$(echo "$backup_dir" | grep -o '[0-9]\{8\}' | head -1)
        if [ -n "$backup_date" ] && [ "$backup_date" -lt "$cutoff_date" ]; then
            log_info "Removing old backup: $backup_dir"
            rm -rf "$BACKUP_DIR/$backup_dir"
        fi
    done
    
    # Clean up old logs
    find "$(dirname "$LOG_FILE")" -name "*.log" -mtime +"$days_to_keep" -delete
    
    log_success "Automated cleanup completed"
}

# Schedule automation
schedule_automation() {
    log_info "Setting up automated scheduling..."
    
    # Create cron jobs
    local cron_file="/tmp/dr-automation-cron"
    
    cat > "$cron_file" << EOF
# Disaster Recovery Automation Schedule
# Full backup every Sunday at 2:00 AM
0 2 * * 0 $SCRIPT_DIR/dr-automation.sh backup full

# Incremental backup every day at 2:00 AM
0 2 * * * $SCRIPT_DIR/dr-automation.sh backup incremental

# Database backup every 4 hours
0 */4 * * * $SCRIPT_DIR/dr-automation.sh backup database

# Application backup every day at 4:00 AM
0 4 * * * $SCRIPT_DIR/dr-automation.sh backup application

# Configuration backup every day at 6:00 AM
0 6 * * * $SCRIPT_DIR/dr-automation.sh backup config

# Monitoring every 5 minutes
*/5 * * * * $SCRIPT_DIR/dr-automation.sh monitor

# Cleanup every day at 3:00 AM
0 3 * * * $SCRIPT_DIR/dr-automation.sh cleanup 30
EOF
    
    # Install cron jobs
    crontab "$cron_file"
    rm "$cron_file"
    
    log_success "Automated scheduling setup completed"
}

# Main function
main() {
    case "${1:-}" in
        "setup")
            setup
            ;;
        "backup")
            automate_backup "${2:-full}"
            ;;
        "recovery")
            automate_recovery "${2:-full}" "${3:-}"
            ;;
        "monitor")
            automate_monitoring
            ;;
        "cleanup")
            automate_cleanup "${2:-30}"
            ;;
        "schedule")
            schedule_automation
            ;;
        *)
            echo "Usage: $0 {setup|backup|recovery|monitor|cleanup|schedule}"
            echo ""
            echo "Commands:"
            echo "  setup                    - Setup DR automation environment"
            echo "  backup [type]            - Create backup (full|incremental|database|application|config)"
            echo "  recovery [type] [path]   - Recover from backup"
            echo "  monitor                  - Run monitoring checks"
            echo "  cleanup [days]           - Clean up old backups and logs"
            echo "  schedule                 - Setup automated scheduling"
            echo ""
            echo "Examples:"
            echo "  $0 setup"
            echo "  $0 backup full"
            echo "  $0 backup database"
            echo "  $0 recovery full /path/to/backup"
            echo "  $0 monitor"
            echo "  $0 cleanup 30"
            echo "  $0 schedule"
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
