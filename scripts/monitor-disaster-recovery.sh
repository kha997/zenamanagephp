#!/bin/bash

# Disaster Recovery Monitoring Script
# ZenaManage Project - Continuous DR Monitoring

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_FILE="$PROJECT_ROOT/logs/dr-monitor-$(date +%Y%m%d).log"
ALERT_LOG="$PROJECT_ROOT/logs/dr-alerts-$(date +%Y%m%d).log"
CONFIG_FILE="$PROJECT_ROOT/config/dr-monitor.conf"

# Default configuration
BACKUP_DIR="$PROJECT_ROOT/storage/backups"
MONITOR_INTERVAL=300  # 5 minutes
ALERT_THRESHOLD=80    # Alert when disk usage > 80%
EMAIL_ALERTS=true
SLACK_ALERTS=false
WEBHOOK_URL=""
EMAIL_RECIPIENTS=""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Load configuration
load_config() {
    if [ -f "$CONFIG_FILE" ]; then
        source "$CONFIG_FILE"
    fi
}

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

# Alert functions
send_alert() {
    local alert_type="$1"
    local message="$2"
    local severity="$3"
    
    # Log alert
    echo "$(date '+%Y-%m-%d %H:%M:%S') - [$severity] $alert_type: $message" >> "$ALERT_LOG"
    
    # Send email alert
    if [ "$EMAIL_ALERTS" = true ] && [ -n "$EMAIL_RECIPIENTS" ]; then
        send_email_alert "$alert_type" "$message" "$severity"
    fi
    
    # Send Slack alert
    if [ "$SLACK_ALERTS" = true ] && [ -n "$WEBHOOK_URL" ]; then
        send_slack_alert "$alert_type" "$message" "$severity"
    fi
}

send_email_alert() {
    local alert_type="$1"
    local message="$2"
    local severity="$3"
    
    local subject="[DR-ALERT] $alert_type - $severity"
    local body="Disaster Recovery Alert
    
Alert Type: $alert_type
Severity: $severity
Message: $message
Timestamp: $(date)
Server: $(hostname)
    
This is an automated alert from the ZenaManage Disaster Recovery Monitoring System.
    
Please investigate and take appropriate action.
    
Best regards,
DR Monitoring System"
    
    echo "$body" | mail -s "$subject" "$EMAIL_RECIPIENTS" 2>/dev/null || log_error "Failed to send email alert"
}

send_slack_alert() {
    local alert_type="$1"
    local message="$2"
    local severity="$3"
    
    local color="good"
    case "$severity" in
        "CRITICAL") color="danger" ;;
        "WARNING") color="warning" ;;
        "INFO") color="good" ;;
    esac
    
    local payload="{
        \"attachments\": [
            {
                \"color\": \"$color\",
                \"title\": \"DR Alert: $alert_type\",
                \"text\": \"$message\",
                \"fields\": [
                    {
                        \"title\": \"Severity\",
                        \"value\": \"$severity\",
                        \"short\": true
                    },
                    {
                        \"title\": \"Server\",
                        \"value\": \"$(hostname)\",
                        \"short\": true
                    },
                    {
                        \"title\": \"Timestamp\",
                        \"value\": \"$(date)\",
                        \"short\": false
                    }
                ]
            }
        ]
    }"
    
    curl -X POST -H 'Content-type: application/json' \
         --data "$payload" \
         "$WEBHOOK_URL" 2>/dev/null || log_error "Failed to send Slack alert"
}

# Monitor disk space
monitor_disk_space() {
    local disk_usage=$(df "$PROJECT_ROOT" | awk 'NR==2 {print $5}' | sed 's/%//')
    local disk_path=$(df "$PROJECT_ROOT" | awk 'NR==2 {print $1}')
    
    if [ "$disk_usage" -gt "$ALERT_THRESHOLD" ]; then
        send_alert "DISK_SPACE" "Disk usage is ${disk_usage}% on $disk_path" "WARNING"
        log_warning "Disk usage is ${disk_usage}% on $disk_path"
    else
        log_info "Disk usage is ${disk_usage}% on $disk_path - OK"
    fi
}

# Monitor memory usage
monitor_memory() {
    local memory_usage=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    
    if [ "$memory_usage" -gt 90 ]; then
        send_alert "MEMORY" "Memory usage is ${memory_usage}%" "CRITICAL"
        log_error "Memory usage is ${memory_usage}%"
    elif [ "$memory_usage" -gt 80 ]; then
        send_alert "MEMORY" "Memory usage is ${memory_usage}%" "WARNING"
        log_warning "Memory usage is ${memory_usage}%"
    else
        log_info "Memory usage is ${memory_usage}% - OK"
    fi
}

# Monitor CPU load
monitor_cpu_load() {
    local cpu_load=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    
    if (( $(echo "$cpu_load > 3.0" | bc -l) )); then
        send_alert "CPU_LOAD" "CPU load is $cpu_load" "CRITICAL"
        log_error "CPU load is $cpu_load"
    elif (( $(echo "$cpu_load > 2.0" | bc -l) )); then
        send_alert "CPU_LOAD" "CPU load is $cpu_load" "WARNING"
        log_warning "CPU load is $cpu_load"
    else
        log_info "CPU load is $cpu_load - OK"
    fi
}

# Monitor database
monitor_database() {
    if command -v mysql >/dev/null 2>&1; then
        if mysql -e "SELECT 1;" >/dev/null 2>&1; then
            log_info "Database connection - OK"
        else
            send_alert "DATABASE" "Cannot connect to database" "CRITICAL"
            log_error "Cannot connect to database"
        fi
    else
        send_alert "DATABASE" "MySQL client not available" "WARNING"
        log_warning "MySQL client not available"
    fi
}

# Monitor Redis
monitor_redis() {
    if command -v redis-cli >/dev/null 2>&1; then
        if redis-cli ping >/dev/null 2>&1; then
            log_info "Redis connection - OK"
        else
            send_alert "REDIS" "Cannot connect to Redis" "CRITICAL"
            log_error "Cannot connect to Redis"
        fi
    else
        send_alert "REDIS" "Redis client not available" "WARNING"
        log_warning "Redis client not available"
    fi
}

# Monitor web server
monitor_web_server() {
    local web_ports=("80" "443")
    
    for port in "${web_ports[@]}"; do
        if nc -z 127.0.0.1 "$port" 2>/dev/null; then
            log_info "Web server port $port - OK"
        else
            send_alert "WEB_SERVER" "Web server port $port is not accessible" "CRITICAL"
            log_error "Web server port $port is not accessible"
        fi
    done
}

# Monitor backup files
monitor_backups() {
    local today=$(date +%Y%m%d)
    local yesterday=$(date -d "yesterday" +%Y%m%d)
    
    local backup_files=(
        "database-backup-$today.sql"
        "application-backup-$today.tar.gz"
        "config-backup-$today.tar.gz"
    )
    
    local missing_backups=()
    
    for backup_file in "${backup_files[@]}"; do
        if [ ! -f "$BACKUP_DIR/$backup_file" ]; then
            missing_backups+=("$backup_file")
        fi
    done
    
    if [ ${#missing_backups[@]} -gt 0 ]; then
        send_alert "BACKUP" "Missing backup files: ${missing_backups[*]}" "WARNING"
        log_warning "Missing backup files: ${missing_backups[*]}"
    else
        log_info "All backup files present - OK"
    fi
}

# Monitor log files
monitor_log_files() {
    local log_files=(
        "$PROJECT_ROOT/storage/logs/laravel.log"
        "$PROJECT_ROOT/storage/logs/system.log"
        "$PROJECT_ROOT/storage/logs/error.log"
    )
    
    for log_file in "${log_files[@]}"; do
        if [ -f "$log_file" ]; then
            local log_size=$(stat -c%s "$log_file" 2>/dev/null || echo "0")
            local log_size_mb=$((log_size / 1024 / 1024))
            
            if [ "$log_size_mb" -gt 100 ]; then
                send_alert "LOG_SIZE" "Log file $(basename "$log_file") is ${log_size_mb}MB" "WARNING"
                log_warning "Log file $(basename "$log_file") is ${log_size_mb}MB"
            else
                log_info "Log file $(basename "$log_file") size: ${log_size_mb}MB - OK"
            fi
        else
            send_alert "LOG_FILE" "Log file $(basename "$log_file") not found" "WARNING"
            log_warning "Log file $(basename "$log_file") not found"
        fi
    done
}

# Monitor application health
monitor_application_health() {
    if [ -f "$PROJECT_ROOT/artisan" ]; then
        # Check if Laravel application is accessible
        if php "$PROJECT_ROOT/artisan" --version >/dev/null 2>&1; then
            log_info "Laravel application - OK"
        else
            send_alert "APPLICATION" "Laravel application is not functional" "CRITICAL"
            log_error "Laravel application is not functional"
        fi
    else
        send_alert "APPLICATION" "Laravel application not found" "CRITICAL"
        log_error "Laravel application not found"
    fi
}

# Monitor network connectivity
monitor_network() {
    local endpoints=(
        "127.0.0.1:80"
        "127.0.0.1:3306"
        "127.0.0.1:6379"
    )
    
    for endpoint in "${endpoints[@]}"; do
        local host=$(echo "$endpoint" | cut -d: -f1)
        local port=$(echo "$endpoint" | cut -d: -f2)
        
        if nc -z "$host" "$port" 2>/dev/null; then
            log_info "Network endpoint $endpoint - OK"
        else
            send_alert "NETWORK" "Network endpoint $endpoint is not accessible" "CRITICAL"
            log_error "Network endpoint $endpoint is not accessible"
        fi
    done
}

# Monitor services
monitor_services() {
    local services=(
        "nginx"
        "mysql"
        "redis"
        "php-fpm"
    )
    
    for service in "${services[@]}"; do
        if systemctl is-active --quiet "$service" 2>/dev/null; then
            log_info "Service $service - OK"
        else
            send_alert "SERVICE" "Service $service is not running" "CRITICAL"
            log_error "Service $service is not running"
        fi
    done
}

# Monitor security
monitor_security() {
    # Check SSL certificate expiration
    if [ -f "$PROJECT_ROOT/storage/ssl/cert.pem" ]; then
        local cert_expiry=$(openssl x509 -in "$PROJECT_ROOT/storage/ssl/cert.pem" -noout -dates | grep "notAfter" | cut -d= -f2)
        local cert_expiry_epoch=$(date -d "$cert_expiry" +%s)
        local current_epoch=$(date +%s)
        local days_until_expiry=$(( (cert_expiry_epoch - current_epoch) / 86400 ))
        
        if [ "$days_until_expiry" -lt 30 ]; then
            send_alert "SSL_CERT" "SSL certificate expires in $days_until_expiry days" "WARNING"
            log_warning "SSL certificate expires in $days_until_expiry days"
        else
            log_info "SSL certificate expires in $days_until_expiry days - OK"
        fi
    else
        send_alert "SSL_CERT" "SSL certificate not found" "WARNING"
        log_warning "SSL certificate not found"
    fi
    
    # Check file permissions
    local critical_files=(
        "$PROJECT_ROOT/.env"
        "$PROJECT_ROOT/storage"
        "$PROJECT_ROOT/bootstrap/cache"
    )
    
    for file in "${critical_files[@]}"; do
        if [ -e "$file" ]; then
            local perms=$(stat -c "%a" "$file" 2>/dev/null || echo "000")
            if [ "$perms" = "755" ] || [ "$perms" = "644" ]; then
                log_info "File permissions for $(basename "$file"): $perms - OK"
            else
                send_alert "FILE_PERMISSIONS" "Insecure file permissions for $(basename "$file"): $perms" "WARNING"
                log_warning "Insecure file permissions for $(basename "$file"): $perms"
            fi
        fi
    done
}

# Monitor backup integrity
monitor_backup_integrity() {
    local today=$(date +%Y%m%d)
    local db_backup="$BACKUP_DIR/database-backup-$today.sql"
    
    if [ -f "$db_backup" ]; then
        # Check if backup file is not empty
        if [ ! -s "$db_backup" ]; then
            send_alert "BACKUP_INTEGRITY" "Database backup file is empty" "CRITICAL"
            log_error "Database backup file is empty"
        else
            # Check if backup contains expected tables
            local expected_tables=("users" "projects" "tasks" "documents")
            local tables_found=0
            
            for table in "${expected_tables[@]}"; do
                if grep -q "CREATE TABLE.*$table" "$db_backup"; then
                    tables_found=$((tables_found + 1))
                fi
            done
            
            if [ $tables_found -eq ${#expected_tables[@]} ]; then
                log_info "Database backup integrity - OK"
            else
                send_alert "BACKUP_INTEGRITY" "Database backup missing $(( ${#expected_tables[@]} - tables_found )) expected tables" "WARNING"
                log_warning "Database backup missing $(( ${#expected_tables[@]} - tables_found )) expected tables"
            fi
        fi
    else
        send_alert "BACKUP_INTEGRITY" "Database backup file not found" "WARNING"
        log_warning "Database backup file not found"
    fi
}

# Generate monitoring report
generate_report() {
    local report_file="$PROJECT_ROOT/logs/dr-monitor-report-$(date +%Y%m%d-%H%M%S).html"
    
    cat > "$report_file" << EOF
<!DOCTYPE html>
<html>
<head>
    <title>Disaster Recovery Monitoring Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background-color: #f0f0f0; padding: 20px; border-radius: 5px; }
        .summary { background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .alert { background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { background-color: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Disaster Recovery Monitoring Report</h1>
        <p>Generated on: $(date)</p>
        <p>Server: $(hostname)</p>
    </div>
    
    <div class="summary">
        <h2>System Status Summary</h2>
        <p><strong>Disk Usage:</strong> $(df "$PROJECT_ROOT" | awk 'NR==2 {print $5}')</p>
        <p><strong>Memory Usage:</strong> $(free | awk 'NR==2{printf "%.0f", $3*100/$2}')%</p>
        <p><strong>CPU Load:</strong> $(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')</p>
        <p><strong>Uptime:</strong> $(uptime | awk '{print $3,$4}' | sed 's/,//')</p>
    </div>
    
    <div class="info">
        <h2>Recent Monitoring Log</h2>
        <pre>$(tail -20 "$LOG_FILE" 2>/dev/null || echo "No log data available")</pre>
    </div>
    
    <div class="alert">
        <h2>Recent Alerts</h2>
        <pre>$(tail -10 "$ALERT_LOG" 2>/dev/null || echo "No alerts")</pre>
    </div>
    
    <div class="footer">
        <p>This report was generated by the ZenaManage Disaster Recovery Monitoring Script.</p>
        <p>For questions or issues, contact the Technical Team.</p>
    </div>
</body>
</html>
EOF
    
    log_info "Monitoring report generated: $report_file"
}

# Main monitoring loop
monitor_loop() {
    log_info "Starting disaster recovery monitoring loop..."
    log_info "Monitoring interval: $MONITOR_INTERVAL seconds"
    
    while true; do
        log_info "Starting monitoring cycle..."
        
        # Run all monitoring functions
        monitor_disk_space
        monitor_memory
        monitor_cpu_load
        monitor_database
        monitor_redis
        monitor_web_server
        monitor_backups
        monitor_log_files
        monitor_application_health
        monitor_network
        monitor_services
        monitor_security
        monitor_backup_integrity
        
        log_info "Monitoring cycle completed"
        
        # Wait for next cycle
        sleep "$MONITOR_INTERVAL"
    done
}

# Single monitoring run
single_run() {
    log_info "Running single disaster recovery monitoring check..."
    
    monitor_disk_space
    monitor_memory
    monitor_cpu_load
    monitor_database
    monitor_redis
    monitor_web_server
    monitor_backups
    monitor_log_files
    monitor_application_health
    monitor_network
    monitor_services
    monitor_security
    monitor_backup_integrity
    
    generate_report
    
    log_info "Single monitoring check completed"
}

# Setup function
setup() {
    log_info "Setting up disaster recovery monitoring..."
    
    # Create necessary directories
    mkdir -p "$(dirname "$LOG_FILE")"
    mkdir -p "$(dirname "$ALERT_LOG")"
    mkdir -p "$BACKUP_DIR"
    
    # Create default configuration file if it doesn't exist
    if [ ! -f "$CONFIG_FILE" ]; then
        cat > "$CONFIG_FILE" << EOF
# Disaster Recovery Monitoring Configuration

# Monitoring settings
MONITOR_INTERVAL=300
ALERT_THRESHOLD=80

# Alert settings
EMAIL_ALERTS=true
SLACK_ALERTS=false
WEBHOOK_URL=""
EMAIL_RECIPIENTS=""

# Backup settings
BACKUP_DIR="$PROJECT_ROOT/storage/backups"
EOF
        log_info "Default configuration file created: $CONFIG_FILE"
    fi
    
    log_info "Disaster recovery monitoring setup complete"
}

# Main function
main() {
    case "${1:-}" in
        "setup")
            setup
            ;;
        "monitor")
            load_config
            monitor_loop
            ;;
        "check")
            load_config
            single_run
            ;;
        "report")
            load_config
            generate_report
            ;;
        *)
            echo "Usage: $0 {setup|monitor|check|report}"
            echo ""
            echo "Commands:"
            echo "  setup   - Setup monitoring environment"
            echo "  monitor - Start continuous monitoring"
            echo "  check   - Run single monitoring check"
            echo "  report  - Generate monitoring report"
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
