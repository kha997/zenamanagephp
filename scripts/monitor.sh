#!/bin/bash

# Monitoring Script
# Dashboard System - Production Monitoring

set -e

# Configuration
PROJECT_DIR="/var/www/zenamanage"
LOG_FILE="/var/log/zenamanage/monitor.log"
ALERT_EMAIL="admin@zenamanage.com"
SLACK_WEBHOOK_URL="${SLACK_WEBHOOK_URL:-}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

# Check Docker services
check_docker_services() {
    log "Checking Docker services..."
    
    cd "$PROJECT_DIR"
    
    # Get service status
    services_status=$(docker-compose -f docker-compose.prod.yml ps --format "table {{.Name}}\t{{.Status}}")
    
    # Check if all services are running
    if docker-compose -f docker-compose.prod.yml ps | grep -q "Exit"; then
        error "Some Docker services are not running"
        echo "$services_status"
        return 1
    fi
    
    success "All Docker services are running"
    return 0
}

# Check application health
check_application_health() {
    log "Checking application health..."
    
    # Check main application
    if ! curl -f -s http://localhost/health > /dev/null; then
        error "Main application health check failed"
        return 1
    fi
    
    # Check API health
    if ! curl -f -s http://localhost/api/health > /dev/null; then
        error "API health check failed"
        return 1
    fi
    
    success "Application health checks passed"
    return 0
}

# Check database connection
check_database() {
    log "Checking database connection..."
    
    cd "$PROJECT_DIR"
    
    # Test database connection
    if ! php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connected successfully';" > /dev/null 2>&1; then
        error "Database connection failed"
        return 1
    fi
    
    # Check database performance
    query_time=$(php artisan tinker --execute="
        \$start = microtime(true);
        DB::select('SELECT 1');
        echo (microtime(true) - \$start) * 1000;
    " 2>/dev/null)
    
    if (( $(echo "$query_time > 1000" | bc -l) )); then
        warning "Database query time is slow: ${query_time}ms"
    fi
    
    success "Database connection is healthy"
    return 0
}

# Check Redis connection
check_redis() {
    log "Checking Redis connection..."
    
    cd "$PROJECT_DIR"
    
    # Test Redis connection
    if ! php artisan tinker --execute="Redis::ping(); echo 'Redis connected successfully';" > /dev/null 2>&1; then
        error "Redis connection failed"
        return 1
    fi
    
    # Check Redis memory usage
    redis_memory=$(docker exec zenamanage-dashboard-redis redis-cli info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
    log "Redis memory usage: $redis_memory"
    
    success "Redis connection is healthy"
    return 0
}

# Check disk space
check_disk_space() {
    log "Checking disk space..."
    
    # Check root partition
    root_usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    if [ "$root_usage" -gt 90 ]; then
        error "Root partition is ${root_usage}% full"
        return 1
    elif [ "$root_usage" -gt 80 ]; then
        warning "Root partition is ${root_usage}% full"
    fi
    
    # Check project directory
    project_usage=$(df "$PROJECT_DIR" | awk 'NR==2 {print $5}' | sed 's/%//')
    if [ "$project_usage" -gt 90 ]; then
        error "Project partition is ${project_usage}% full"
        return 1
    elif [ "$project_usage" -gt 80 ]; then
        warning "Project partition is ${project_usage}% full"
    fi
    
    success "Disk space is adequate"
    return 0
}

# Check memory usage
check_memory() {
    log "Checking memory usage..."
    
    # Get memory usage
    memory_usage=$(free | awk 'NR==2{printf "%.2f", $3*100/$2}')
    
    if (( $(echo "$memory_usage > 90" | bc -l) )); then
        error "Memory usage is ${memory_usage}%"
        return 1
    elif (( $(echo "$memory_usage > 80" | bc -l) )); then
        warning "Memory usage is ${memory_usage}%"
    fi
    
    success "Memory usage is normal: ${memory_usage}%"
    return 0
}

# Check CPU usage
check_cpu() {
    log "Checking CPU usage..."
    
    # Get CPU usage (average over 5 seconds)
    cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | awk -F'%' '{print $1}')
    
    if (( $(echo "$cpu_usage > 90" | bc -l) )); then
        error "CPU usage is ${cpu_usage}%"
        return 1
    elif (( $(echo "$cpu_usage > 80" | bc -l) )); then
        warning "CPU usage is ${cpu_usage}%"
    fi
    
    success "CPU usage is normal: ${cpu_usage}%"
    return 0
}

# Check log files
check_logs() {
    log "Checking log files..."
    
    # Check for errors in Laravel logs
    if [ -f "$PROJECT_DIR/storage/logs/laravel.log" ]; then
        error_count=$(tail -n 100 "$PROJECT_DIR/storage/logs/laravel.log" | grep -c "ERROR" || true)
        if [ "$error_count" -gt 10 ]; then
            warning "High number of errors in Laravel logs: $error_count"
        fi
    fi
    
    # Check Nginx error logs
    if [ -f "/var/log/nginx/error.log" ]; then
        nginx_errors=$(tail -n 100 /var/log/nginx/error.log | grep -c "error" || true)
        if [ "$nginx_errors" -gt 5 ]; then
            warning "Nginx error log has $nginx_errors errors"
        fi
    fi
    
    success "Log files checked"
    return 0
}

# Check SSL certificates
check_ssl() {
    log "Checking SSL certificates..."
    
    # Check certificate expiration
    if [ -f "/etc/ssl/certs/zenamanage.crt" ]; then
        expiry_date=$(openssl x509 -in /etc/ssl/certs/zenamanage.crt -noout -enddate | cut -d= -f2)
        expiry_timestamp=$(date -d "$expiry_date" +%s)
        current_timestamp=$(date +%s)
        days_until_expiry=$(( (expiry_timestamp - current_timestamp) / 86400 ))
        
        if [ "$days_until_expiry" -lt 30 ]; then
            warning "SSL certificate expires in $days_until_expiry days"
        fi
        
        success "SSL certificate is valid for $days_until_expiry days"
    else
        warning "SSL certificate not found"
    fi
    
    return 0
}

# Check backup status
check_backups() {
    log "Checking backup status..."
    
    backup_dir="/var/backups/zenamanage"
    
    if [ -d "$backup_dir" ]; then
        # Check if backup was created today
        latest_backup=$(find "$backup_dir" -name "backup_*.tar.gz" -type f -mtime -1 | head -1)
        
        if [ -z "$latest_backup" ]; then
            warning "No backup found for today"
        else
            success "Latest backup: $(basename "$latest_backup")"
        fi
    else
        warning "Backup directory not found"
    fi
    
    return 0
}

# Send alert
send_alert() {
    local message="$1"
    local severity="${2:-ERROR}"
    
    log "Sending alert: $message"
    
    # Send email alert
    if command -v mail > /dev/null 2>&1; then
        echo "Alert: $message" | mail -s "ZenaManage Dashboard - $severity Alert" "$ALERT_EMAIL"
    fi
    
    # Send Slack alert
    if [ ! -z "$SLACK_WEBHOOK_URL" ]; then
        emoji="🚨"
        if [ "$severity" = "WARNING" ]; then
            emoji="⚠️"
        fi
        
        curl -X POST -H 'Content-type: application/json' \
        --data "{\"text\":\"$emoji ZenaManage Dashboard - $severity: $message\"}" \
        "$SLACK_WEBHOOK_URL" 2>/dev/null || true
    fi
}

# Generate monitoring report
generate_report() {
    log "Generating monitoring report..."
    
    report_file="/var/log/zenamanage/monitoring-report-$(date +%Y%m%d).txt"
    
    cat > "$report_file" << EOF
ZenaManage Dashboard - Monitoring Report
Generated: $(date)
========================================

System Status:
- Docker Services: $(docker-compose -f docker-compose.prod.yml ps --format "table {{.Name}}\t{{.Status}}" | tail -n +2 | wc -l) services running
- Application Health: $(curl -f -s http://localhost/health > /dev/null && echo "OK" || echo "FAILED")
- Database: $(php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';" 2>/dev/null || echo "FAILED")
- Redis: $(php artisan tinker --execute="Redis::ping(); echo 'OK';" 2>/dev/null || echo "FAILED")

Resource Usage:
- CPU: $(top -bn1 | grep "Cpu(s)" | awk '{print $2}')
- Memory: $(free | awk 'NR==2{printf "%.2f%%", $3*100/$2}')
- Disk Usage: $(df / | awk 'NR==2 {print $5}')

SSL Certificate:
- Status: $(openssl x509 -in /etc/ssl/certs/zenamanage.crt -noout -enddate 2>/dev/null | cut -d= -f2 || echo "Not found")

Recent Errors:
$(tail -n 20 "$PROJECT_DIR/storage/logs/laravel.log" | grep "ERROR" | tail -n 5 || echo "No recent errors")

EOF

    success "Monitoring report generated: $report_file"
}

# Main monitoring function
main() {
    log "Starting monitoring check..."
    
    local errors=0
    local warnings=0
    
    # Run all checks
    check_docker_services || ((errors++))
    check_application_health || ((errors++))
    check_database || ((errors++))
    check_redis || ((errors++))
    check_disk_space || ((errors++))
    check_memory || ((errors++))
    check_cpu || ((errors++))
    check_logs || ((warnings++))
    check_ssl || ((warnings++))
    check_backups || ((warnings++))
    
    # Generate report
    generate_report
    
    # Send alerts if needed
    if [ "$errors" -gt 0 ]; then
        send_alert "System has $errors critical errors" "ERROR"
    elif [ "$warnings" -gt 0 ]; then
        send_alert "System has $warnings warnings" "WARNING"
    else
        success "All monitoring checks passed"
    fi
    
    log "Monitoring check completed. Errors: $errors, Warnings: $warnings"
}

# Handle script arguments
case "${1:-}" in
    "report")
        generate_report
        ;;
    "health")
        check_application_health
        ;;
    "services")
        check_docker_services
        ;;
    *)
        main
        ;;
esac
