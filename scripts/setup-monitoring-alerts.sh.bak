#!/bin/bash

# Monitoring Alerts Setup Script
# This script sets up monitoring alerts for production

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_PATH=$(pwd)
CRON_FILE="/tmp/zenamanage_monitoring_cron"

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

log "📊 Setting up Monitoring Alerts for Production"
log "=============================================="

# Check if we're in the right directory
if [[ ! -f "artisan" ]]; then
    error "Not in Laravel project directory. Please run from project root."
fi

# Create monitoring cron jobs
log "Creating monitoring cron jobs..."

cat > "$CRON_FILE" << EOF
# ZenaManage Monitoring Cron Jobs
# Generated on $(date)

# Email system monitoring (every 5 minutes)
*/5 * * * * cd $PROJECT_PATH && php artisan email:monitor --send-alerts >> storage/logs/monitoring.log 2>&1

# Email cache warming (daily at 2 AM)
0 2 * * * cd $PROJECT_PATH && php artisan email:warm-cache >> storage/logs/cache-warming.log 2>&1

# Queue workers restart (daily at 3 AM)
0 3 * * * cd $PROJECT_PATH && php artisan queue:restart >> storage/logs/queue-restart.log 2>&1

# System health check (every 15 minutes)
*/15 * * * * cd $PROJECT_PATH && php artisan system:monitor --duration=60 --interval=15 --log >> storage/logs/system-monitor.log 2>&1

# Worker status check (every 10 minutes)
*/10 * * * * cd $PROJECT_PATH && php artisan workers:status --json >> storage/logs/worker-status.log 2>&1

# Database backup (daily at 1 AM)
0 1 * * * cd $PROJECT_PATH && php artisan db:backup >> storage/logs/db-backup.log 2>&1

# Log rotation (weekly on Sunday at 4 AM)
0 4 * * 0 cd $PROJECT_PATH && find storage/logs -name "*.log" -mtime +7 -delete >> storage/logs/log-rotation.log 2>&1
EOF

success "Monitoring cron jobs created: $CRON_FILE"

# Create log directories
log "Creating log directories..."
mkdir -p storage/logs
success "Log directories created"

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
if php artisan system:monitor --duration=10 --interval=5; then
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
log "Creating monitoring dashboard script..."

cat > scripts/monitoring-dashboard.sh << 'EOF'
#!/bin/bash

# ZenaManage Monitoring Dashboard
# This script displays real-time monitoring information

PROJECT_PATH=$(pwd)

echo "📊 ZenaManage Monitoring Dashboard"
echo "================================="
echo ""

# System status
echo "🖥️  System Status:"
echo "-----------------"
php artisan workers:status --json | jq -r '.queues | to_entries[] | "\(.key): \(.value.active_workers)/\(.value.workers) workers"'
echo ""

# Email statistics
echo "📧 Email Statistics (Last 24 Hours):"
echo "-------------------------------------"
php artisan email:monitor | grep -E "(Total Sent|Total Delivered|Total Failed|Delivery Rate|Failure Rate)"
echo ""

# Queue statistics
echo "🚀 Queue Statistics:"
echo "--------------------"
php artisan email:monitor | grep -E "(Total Jobs|Total Failed|Active Workers)"
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
free -h | grep Mem | awk '{print "Used: " $3 " / " $2 " (" $3/$2*100 "%)"}'
echo ""

echo "Last updated: $(date)"
EOF

chmod +x scripts/monitoring-dashboard.sh
success "Monitoring dashboard script created"

# Create alert test script
log "Creating alert test script..."

cat > scripts/test-alerts.sh << 'EOF'
#!/bin/bash

# Alert Test Script
# This script tests the alert system

PROJECT_PATH=$(pwd)

echo "🚨 Testing Alert System"
echo "======================="

# Test email alerts
echo "Testing email alerts..."
php artisan email:monitor --send-alerts --alert-threshold=1

# Test system monitoring
echo "Testing system monitoring..."
php artisan system:monitor --duration=30 --interval=10

# Test worker status
echo "Testing worker status..."
php artisan workers:status --detailed

echo "Alert system test completed!"
EOF

chmod +x scripts/test-alerts.sh
success "Alert test script created"

log "Monitoring Alerts Setup Summary:"
log "==============================="
log "✅ Monitoring cron jobs created"
log "✅ Log directories created"
log "✅ Monitoring commands tested"
log "✅ Dashboard script created"
log "✅ Alert test script created"
log ""
log "Cron Jobs Created:"
log "=================="
log "• Email monitoring: Every 5 minutes"
log "• System monitoring: Every 15 minutes"
log "• Worker status: Every 10 minutes"
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
log "Dashboard:        ./scripts/monitoring-dashboard.sh"
log "Test alerts:      ./scripts/test-alerts.sh"
log "Email monitor:    php artisan email:monitor --send-alerts"
log "System monitor:   php artisan system:monitor --duration=300"
log "Worker status:    php artisan workers:status --detailed"
log ""
log "Log Files:"
log "=========="
log "Monitoring:       storage/logs/monitoring.log"
log "System Monitor:  storage/logs/system-monitor.log"
log "Worker Status:   storage/logs/worker-status.log"
log "Cache Warming:   storage/logs/cache-warming.log"
log "Queue Restart:   storage/logs/queue-restart.log"
log "DB Backup:       storage/logs/db-backup.log"

success "Monitoring alerts setup completed successfully!"
