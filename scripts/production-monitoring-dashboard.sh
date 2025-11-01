#!/bin/bash

# ZenaManage Production Monitoring Dashboard Script

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
PROJECT_PATH=$(pwd)
LOG_FILE="$PROJECT_PATH/storage/logs/monitoring-dashboard-$(date +%Y%m%d_%H%M%S).log"

# --- Functions ---
log() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

success() {
    log "✅ $1"
}

error() {
    log "❌ $1"
    exit 1
}

warning() {
    log "⚠️  $1"
}

# --- Main Script ---
log "📊 ZenaManage Production Monitoring Dashboard"
log "============================================="

# 1. System Health Check
log "🔍 System Health Check"
log "====================="

# Check PHP version
PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2)
log "PHP Version: $PHP_VERSION"

# Check Laravel version
LARAVEL_VERSION=$(php artisan --version | cut -d ' ' -f 3)
log "Laravel Version: $LARAVEL_VERSION"

# Check disk space
DISK_USAGE=$(df -h . | tail -1 | awk '{print $5}' | sed 's/%//')
DISK_FREE=$(df -h . | tail -1 | awk '{print $4}')
log "Disk Usage: ${DISK_USAGE}% (${DISK_FREE} free)"

# Check memory usage
MEMORY_USAGE=$(ps -o pid,ppid,cmd,%mem,%cpu --sort=-%mem | head -n 2 | tail -n 1 | awk '{print $4}')
log "Memory Usage: ${MEMORY_USAGE}%"

# 2. Database Health Check
log ""
log "🗄️  Database Health Check"
log "========================"

# Check database connection
if php artisan migrate:status &> /dev/null; then
    success "Database connection: OK"
else
    error "Database connection: FAILED"
fi

# Check database size
DB_SIZE=$(php artisan tinker --execute="echo DB::select('SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS \"DB Size in MB\" FROM information_schema.tables WHERE table_schema = DATABASE()')[0]->{'DB Size in MB'};" 2>/dev/null || echo "Unknown")
log "Database Size: ${DB_SIZE} MB"

# Check table counts
USER_COUNT=$(php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null || echo "Unknown")
INVITATION_COUNT=$(php artisan tinker --execute="echo App\Models\Invitation::count();" 2>/dev/null || echo "Unknown")
ORGANIZATION_COUNT=$(php artisan tinker --execute="echo App\Models\Organization::count();" 2>/dev/null || echo "Unknown")

log "Users: $USER_COUNT"
log "Invitations: $INVITATION_COUNT"
log "Organizations: $ORGANIZATION_COUNT"

# 3. Email System Health Check
log ""
log "📧 Email System Health Check"
log "============================"

# Check email configuration
if php artisan email:test test@example.com --type=simple --sync &> /dev/null; then
    success "Email configuration: OK"
else
    warning "Email configuration: ISSUES DETECTED"
fi

# Check email statistics
EMAIL_STATS=$(php artisan email:monitor --send-alerts=false 2>/dev/null | grep -E "(Total Sent|Total Delivered|Total Failed)" || echo "Unknown")
log "Email Statistics:"
echo "$EMAIL_STATS" | while read line; do
    log "  $line"
done

# 4. Queue System Health Check
log ""
log "⚡ Queue System Health Check"
log "============================="

# Check queue workers
WORKER_COUNT=$(pgrep -f "artisan queue:work" | wc -l)
log "Active Queue Workers: $WORKER_COUNT"

# Check queue status
QUEUE_STATUS=$(php artisan queue:work --once --timeout=1 2>&1 | head -n 1 || echo "No jobs")
log "Queue Status: $QUEUE_STATUS"

# Check Redis connection
if php artisan redis:ping &> /dev/null; then
    success "Redis connection: OK"
else
    warning "Redis connection: ISSUES DETECTED"
fi

# 5. Application Health Check
log ""
log "🚀 Application Health Check"
log "==========================="

# Check application key
if php artisan key:generate --show &> /dev/null; then
    success "Application key: OK"
else
    error "Application key: MISSING"
fi

# Check storage permissions
if [ -w "storage/logs" ] && [ -w "storage/app" ]; then
    success "Storage permissions: OK"
else
    warning "Storage permissions: ISSUES DETECTED"
fi

# Check cache
if php artisan cache:clear &> /dev/null; then
    success "Cache system: OK"
else
    warning "Cache system: ISSUES DETECTED"
fi

# 6. Security Health Check
log ""
log "🔒 Security Health Check"
log "========================"

# Check .env file
if [ -f ".env" ]; then
    success ".env file: EXISTS"
else
    error ".env file: MISSING"
fi

# Check debug mode
DEBUG_MODE=$(grep "APP_DEBUG" .env | cut -d '=' -f 2)
if [ "$DEBUG_MODE" = "false" ]; then
    success "Debug mode: DISABLED (Production)"
else
    warning "Debug mode: ENABLED (Development)"
fi

# Check HTTPS
if grep -q "APP_URL=https" .env; then
    success "HTTPS: ENABLED"
else
    warning "HTTPS: NOT CONFIGURED"
fi

# 7. Performance Metrics
log ""
log "📈 Performance Metrics"
log "======================"

# Check response time
RESPONSE_TIME=$(curl -s -o /dev/null -w "%{time_total}" http://localhost:8000 2>/dev/null || echo "Unknown")
log "Response Time: ${RESPONSE_TIME}s"

# Check memory usage
MEMORY_USAGE_MB=$(php -r "echo round(memory_get_usage() / 1024 / 1024, 2);")
log "Memory Usage: ${MEMORY_USAGE_MB} MB"

# Check CPU usage
CPU_USAGE=$(top -l 1 | grep "CPU usage" | awk '{print $3}' | sed 's/%//' || echo "Unknown")
log "CPU Usage: ${CPU_USAGE}%"

# 8. Monitoring Alerts
log ""
log "🚨 Monitoring Alerts"
log "====================="

# Check if monitoring is enabled
if grep -q "MONITORING_ALERT_EMAIL" .env; then
    ALERT_EMAIL=$(grep "MONITORING_ALERT_EMAIL" .env | cut -d '=' -f 2)
    success "Monitoring alerts: ENABLED ($ALERT_EMAIL)"
else
    warning "Monitoring alerts: NOT CONFIGURED"
fi

# Check cron jobs
CRON_JOBS=$(crontab -l 2>/dev/null | grep "zenamanage" | wc -l)
log "Cron Jobs: $CRON_JOBS configured"

# 9. Backup Status
log ""
log "💾 Backup Status"
log "================"

# Check backup directory
if [ -d "storage/backups" ]; then
    BACKUP_COUNT=$(ls storage/backups/ 2>/dev/null | wc -l)
    success "Backup directory: EXISTS ($BACKUP_COUNT backups)"
else
    warning "Backup directory: NOT FOUND"
fi

# 10. Summary Report
log ""
log "📋 Production Monitoring Summary"
log "================================="

# Overall health score
HEALTH_SCORE=0
TOTAL_CHECKS=10

# Count successful checks
if php artisan migrate:status &> /dev/null; then HEALTH_SCORE=$((HEALTH_SCORE + 1)); fi
if php artisan email:test test@example.com --type=simple --sync &> /dev/null; then HEALTH_SCORE=$((HEALTH_SCORE + 1)); fi
if php artisan redis:ping &> /dev/null; then HEALTH_SCORE=$((HEALTH_SCORE + 1)); fi
if php artisan key:generate --show &> /dev/null; then HEALTH_SCORE=$((HEALTH_SCORE + 1)); fi
if [ -w "storage/logs" ] && [ -w "storage/app" ]; then HEALTH_SCORE=$((HEALTH_SCORE + 1)); fi
if php artisan cache:clear &> /dev/null; then HEALTH_SCORE=$((HEALTH_SCORE + 1)); fi
if [ -f ".env" ]; then HEALTH_SCORE=$((HEALTH_SCORE + 1)); fi
if [ "$DEBUG_MODE" = "false" ]; then HEALTH_SCORE=$((HEALTH_SCORE + 1)); fi
if grep -q "MONITORING_ALERT_EMAIL" .env; then HEALTH_SCORE=$((HEALTH_SCORE + 1)); fi
if [ -d "storage/backups" ]; then HEALTH_SCORE=$((HEALTH_SCORE + 1)); fi

HEALTH_PERCENTAGE=$((HEALTH_SCORE * 100 / TOTAL_CHECKS))

log "Overall Health Score: $HEALTH_SCORE/$TOTAL_CHECKS ($HEALTH_PERCENTAGE%)"

if [ $HEALTH_PERCENTAGE -ge 90 ]; then
    success "System Status: EXCELLENT"
elif [ $HEALTH_PERCENTAGE -ge 80 ]; then
    success "System Status: GOOD"
elif [ $HEALTH_PERCENTAGE -ge 70 ]; then
    warning "System Status: FAIR"
else
    error "System Status: POOR"
fi

log ""
log "🎯 Recommendations:"
log "==================="

if [ $HEALTH_PERCENTAGE -lt 90 ]; then
    log "1. Review failed health checks above"
    log "2. Check error logs: tail -f storage/logs/laravel.log"
    log "3. Monitor system resources regularly"
    log "4. Set up automated backups"
    log "5. Configure monitoring alerts"
fi

log ""
log "📊 Monitoring Dashboard completed at: $(date)"
log "Log file: $LOG_FILE"