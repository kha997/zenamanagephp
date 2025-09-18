#!/bin/bash

# Production Monitoring Setup Script
# This script sets up monitoring alerts for production environment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_PATH=$(pwd)
CRON_FILE="/tmp/zenamanage_production_monitoring_cron"
LOG_DIR="storage/logs"

# Functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
    exit 1
}

log "📊 Setting up Production Monitoring and Alerting"
log "==============================================="

# Check if we're in the right directory
if [[ ! -f "artisan" ]]; then
    error "Not in Laravel project directory. Please run from project root."
fi

# Get monitoring configuration
log "Configure Production Monitoring:"
read -p "Alert Email Address: " ALERT_EMAIL
read -p "Check Interval (seconds, default 300): " CHECK_INTERVAL
read -p "Alert Threshold (default 50): " ALERT_THRESHOLD
read -p "Slack Webhook URL (optional): " SLACK_WEBHOOK

# Set defaults
CHECK_INTERVAL=${CHECK_INTERVAL:-300}
ALERT_THRESHOLD=${ALERT_THRESHOLD:-50}

# Validate inputs
if [[ -z "$ALERT_EMAIL" ]]; then
    error "Alert email address is required"
fi

# Validate email format
if [[ ! "$ALERT_EMAIL" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]]; then
    error "Invalid email address format"
fi

log "Monitoring Configuration:"
log "Alert Email: $ALERT_EMAIL"
log "Check Interval: $CHECK_INTERVAL seconds"
log "Alert Threshold: $ALERT_THRESHOLD"
log "Slack Webhook: ${SLACK_WEBHOOK:-'Not configured'}"

# Update .env file with monitoring settings
log "Updating .env file with monitoring configuration..."

# Function to update or add environment variable
update_env_var() {
    local key=$1
    local value=$2
    
    if grep -q "^$key=" "$PROJECT_PATH/.env"; then
        # Update existing variable
        sed -i.bak "s/^$key=.*/$key=\"$value\"/" "$PROJECT_PATH/.env"
    else
        # Add new variable
        echo "$key=\"$value\"" >> "$PROJECT_PATH/.env"
    fi
}

# Update monitoring settings
update_env_var "MONITORING_ENABLED" "true"
update_env_var "MONITORING_ALERT_EMAIL" "$ALERT_EMAIL"
update_env_var "MONITORING_CHECK_INTERVAL" "$CHECK_INTERVAL"
update_env_var "MONITORING_ALERT_THRESHOLD" "$ALERT_THRESHOLD"

if [[ -n "$SLACK_WEBHOOK" ]]; then
    update_env_var "MONITORING_SLACK_WEBHOOK" "$SLACK_WEBHOOK"
fi

success "Monitoring configuration updated in .env file"

# Create log directory
mkdir -p "$LOG_DIR"

# Create monitoring cron jobs
log "Creating production monitoring cron jobs..."

cat > "$CRON_FILE" << EOF
# ZenaManage Production Monitoring Cron Jobs
# Generated on $(date)

# Email system monitoring (every 5 minutes)
*/5 * * * * cd $PROJECT_PATH && php artisan email:monitor --send-alerts >> $LOG_DIR/monitoring.log 2>&1

# Email cache warming (daily at 2 AM)
0 2 * * * cd $PROJECT_PATH && php artisan email:warm-cache >> $LOG_DIR/cache-warming.log 2>&1

# Queue workers restart (daily at 3 AM)
0 3 * * * cd $PROJECT_PATH && php artisan queue:restart >> $LOG_DIR/queue-restart.log 2>&1

# System health check (every 15 minutes)
*/15 * * * * cd $PROJECT_PATH && php artisan system:monitor --duration=60 --interval=15 --log >> $LOG_DIR/system-monitor.log 2>&1

# Worker status check (every 10 minutes)
*/10 * * * * cd $PROJECT_PATH && php artisan workers:status --json >> $LOG_DIR/worker-status.log 2>&1

# Database backup (daily at 1 AM)
0 1 * * * cd $PROJECT_PATH && php artisan db:backup >> $LOG_DIR/db-backup.log 2>&1

# Log rotation (weekly on Sunday at 4 AM)
0 4 * * 0 cd $PROJECT_PATH && find $LOG_DIR -name "*.log" -mtime +7 -delete >> $LOG_DIR/log-rotation.log 2>&1

# Performance monitoring (every 30 minutes)
*/30 * * * * cd $PROJECT_PATH && php artisan system:monitor --duration=300 --interval=30 --log >> $LOG_DIR/performance-monitor.log 2>&1
EOF

success "Production monitoring cron jobs created: $CRON_FILE"

# Test monitoring commands
log "Testing monitoring commands..."

# Test email monitoring
log "Testing email monitoring..."
if php artisan email:monitor --send-alerts; then
    success "Email monitoring test passed"
else
    warning "Email monitoring test failed (this is expected in development)"
fi

# Test system monitoring
log "Testing system monitoring..."
if timeout 30 php artisan system:monitor --duration=20 --interval=5; then
    success "System monitoring test passed"
else
    warning "System monitoring test failed"
fi

# Test worker status
log "Testing worker status..."
if php artisan workers:status; then
    success "Worker status test passed"
else
    warning "Worker status test failed"
fi

# Test email cache warming
log "Testing email cache warming..."
if php artisan email:warm-cache; then
    success "Email cache warming test passed"
else
    warning "Email cache warming test failed"
fi

# Create monitoring dashboard script
log "Creating production monitoring dashboard script..."

cat > scripts/production-monitoring-dashboard.sh << 'EOF'
#!/bin/bash

# ZenaManage Production Monitoring Dashboard
# This script displays real-time production monitoring information

PROJECT_PATH=$(pwd)

echo "📊 ZenaManage Production Monitoring Dashboard"
echo "==========================================="
echo ""

# System status
echo "🖥️  System Status:"
echo "-----------------"
php artisan workers:status --json | jq -r '.queues | to_entries[] | "\(.key): \(.value.active_workers)/\(.value.workers) workers"' 2>/dev/null || echo "Workers: Not available"
echo ""

# Email statistics
echo "📧 Email Statistics (Last 24 Hours):"
echo "-------------------------------------"
php artisan email:monitor | grep -E "(Total Sent|Total Delivered|Total Failed|Delivery Rate|Failure Rate)" 2>/dev/null || echo "Email stats: Not available"
echo ""

# Queue statistics
echo "🚀 Queue Statistics:"
echo "--------------------"
php artisan email:monitor | grep -E "(Total Jobs|Total Failed|Active Workers)" 2>/dev/null || echo "Queue stats: Not available"
echo ""

# Recent logs
echo "📝 Recent Logs:"
echo "---------------"
echo "Email Monitoring:"
tail -5 storage/logs/monitoring.log 2>/dev/null || echo "No monitoring logs yet"
echo ""
echo "System Monitoring:"
tail -5 storage/logs/system-monitor.log 2>/dev/null || echo "No system monitoring logs yet"
echo ""

# Disk usage
echo "💾 Disk Usage:"
echo "--------------"
df -h / | tail -1 | awk '{print "Used: " $3 " / " $2 " (" $5 ")"}'
echo ""

# Memory usage
echo "🧠 Memory Usage:"
echo "----------------"
free -h | grep Mem | awk '{print "Used: " $3 " / " $2 " (" $3/$2*100 "%)"}' 2>/dev/null || echo "Memory: Not available"
echo ""

# Load average
echo "⚡ Load Average:"
echo "----------------"
uptime | awk -F'load average:' '{print $2}' 2>/dev/null || echo "Load: Not available"
echo ""

echo "Last updated: $(date)"
EOF

chmod +x scripts/production-monitoring-dashboard.sh
success "Production monitoring dashboard script created"

# Create alert test script
log "Creating production alert test script..."

cat > scripts/test-production-alerts.sh << 'EOF'
#!/bin/bash

# Production Alert Test Script
# This script tests the production alert system

PROJECT_PATH=$(pwd)

echo "🚨 Testing Production Alert System"
echo "=================================="

# Test email alerts
echo "Testing email alerts..."
php artisan email:monitor --send-alerts --alert-threshold=1

# Test system monitoring
echo "Testing system monitoring..."
php artisan system:monitor --duration=30 --interval=10

# Test worker status
echo "Testing worker status..."
php artisan workers:status --detailed

echo "Production alert system test completed!"
EOF

chmod +x scripts/test-production-alerts.sh
success "Production alert test script created"

# Create monitoring service script
log "Creating monitoring service script..."

cat > scripts/monitoring-service.sh << 'EOF'
#!/bin/bash

# Monitoring Service Script
# This script manages the monitoring service

ACTION=${1:-status}
PROJECT_PATH=$(pwd)

case $ACTION in
    start)
        echo "Starting monitoring service..."
        # Start monitoring cron jobs
        crontab /tmp/zenamanage_production_monitoring_cron
        echo "Monitoring service started"
        ;;
    stop)
        echo "Stopping monitoring service..."
        # Remove monitoring cron jobs
        crontab -l | grep -v "zenamanage" | crontab -
        echo "Monitoring service stopped"
        ;;
    restart)
        echo "Restarting monitoring service..."
        $0 stop
        sleep 2
        $0 start
        ;;
    status)
        echo "Monitoring Service Status:"
        echo "========================="
        echo "Cron Jobs:"
        crontab -l | grep "zenamanage" | wc -l | xargs echo "Active:"
        echo ""
        echo "Log Files:"
        ls -la storage/logs/monitoring*.log 2>/dev/null || echo "No monitoring logs found"
        ;;
    *)
        echo "Usage: $0 [start|stop|restart|status]"
        exit 1
        ;;
esac
EOF

chmod +x scripts/monitoring-service.sh
success "Monitoring service script created"

log "Production Monitoring Setup Summary:"
log "==================================="
log "✅ Monitoring configuration updated"
log "✅ Cron jobs created"
log "✅ Log directories created"
log "✅ Monitoring commands tested"
log "✅ Dashboard script created"
log "✅ Alert test script created"
log "✅ Monitoring service script created"
log ""
log "Cron Jobs Created:"
log "=================="
log "• Email monitoring: Every 5 minutes"
log "• System monitoring: Every 15 minutes"
log "• Worker status: Every 10 minutes"
log "• Performance monitoring: Every 30 minutes"
log "• Email cache warming: Daily at 2 AM"
log "• Queue restart: Daily at 3 AM"
log "• Database backup: Daily at 1 AM"
log "• Log rotation: Weekly on Sunday at 4 AM"
log ""
log "To install cron jobs:"
log "====================="
log "crontab $CRON_FILE"
log ""
log "Monitoring Commands:"
log "===================="
log "Dashboard:        ./scripts/production-monitoring-dashboard.sh"
log "Test alerts:      ./scripts/test-production-alerts.sh"
log "Service control:  ./scripts/monitoring-service.sh [start|stop|restart|status]"
log "Email monitor:    php artisan email:monitor --send-alerts"
log "System monitor:   php artisan system:monitor --duration=300"
log "Worker status:    php artisan workers:status --detailed"
log ""
log "Log Files:"
log "=========="
log "Monitoring:       $LOG_DIR/monitoring.log"
log "System Monitor:  $LOG_DIR/system-monitor.log"
log "Worker Status:   $LOG_DIR/worker-status.log"
log "Cache Warming:   $LOG_DIR/cache-warming.log"
log "Queue Restart:   $LOG_DIR/queue-restart.log"
log "DB Backup:       $LOG_DIR/db-backup.log"
log "Performance:     $LOG_DIR/performance-monitor.log"

success "Production monitoring setup completed successfully!"
