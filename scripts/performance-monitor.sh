#!/bin/bash

# Performance Monitoring Script
# Giám sát hiệu suất ứng dụng và hệ thống

set -e

# Configuration
APP_URL="http://localhost"
LOG_FILE="/var/log/zenamanage/performance.log"
METRICS_FILE="/var/log/zenamanage/metrics.json"
DATE=$(date '+%Y-%m-%d %H:%M:%S')
TIMESTAMP=$(date +%s)

# Logging function
log() {
    echo "[$DATE] $1" | tee -a "$LOG_FILE"
}

# Function to get response time
get_response_time() {
    local url=$1
    curl -o /dev/null -s -w "%{time_total}" "$url"
}

# Function to check database performance
check_database_performance() {
    local query_time=$(mysql -h"${DB_HOST:-localhost}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" -e "SELECT BENCHMARK(1000, MD5('test'));" 2>/dev/null | tail -1 | awk '{print $2}')
    echo "$query_time"
}

log "Starting performance monitoring..."

# 1. Check application response times
log "Checking application response times..."
HOME_RESPONSE=$(get_response_time "$APP_URL")
API_HEALTH_RESPONSE=$(get_response_time "$APP_URL/api/v1/health")
API_READY_RESPONSE=$(get_response_time "$APP_URL/api/v1/ready")

# 2. Check database performance
log "Checking database performance..."
DB_RESPONSE=$(check_database_performance)

# 3. Get system metrics
log "Collecting system metrics..."
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
MEMORY_USAGE=$(free | grep Mem | awk '{printf "%.2f", $3/$2 * 100.0}')
DISK_USAGE=$(df -h / | awk 'NR==2{print $5}' | cut -d'%' -f1)
LOAD_AVERAGE=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | cut -d',' -f1)

# 4. Check service status
log "Checking service status..."
NGINX_STATUS=$(systemctl is-active nginx)
PHP_FPM_STATUS=$(systemctl is-active php8.0-fpm)
MYSQL_STATUS=$(systemctl is-active mysql)
REDIS_STATUS=$(systemctl is-active redis-server)

# 5. Check WebSocket server
WEBSOCKET_STATUS="down"
if pgrep -f "websocket_server.php" > /dev/null; then
    WEBSOCKET_STATUS="active"
fi

# 6. Check queue workers
QUEUE_WORKERS=$(pgrep -f "queue:work" | wc -l)

# 7. Generate metrics JSON
cat > "$METRICS_FILE" << EOF
{
    "timestamp": $TIMESTAMP,
    "date": "$DATE",
    "response_times": {
        "home": $HOME_RESPONSE,
        "api_health": $API_HEALTH_RESPONSE,
        "api_ready": $API_READY_RESPONSE,
        "database": "$DB_RESPONSE"
    },
    "system_metrics": {
        "cpu_usage": $CPU_USAGE,
        "memory_usage": $MEMORY_USAGE,
        "disk_usage": $DISK_USAGE,
        "load_average": $LOAD_AVERAGE
    },
    "services": {
        "nginx": "$NGINX_STATUS",
        "php_fpm": "$PHP_FPM_STATUS",
        "mysql": "$MYSQL_STATUS",
        "redis": "$REDIS_STATUS",
        "websocket": "$WEBSOCKET_STATUS",
        "queue_workers": $QUEUE_WORKERS
    }
}
EOF

# 8. Performance alerts
log "Checking performance thresholds..."

# Alert if response time > 2 seconds
if (( $(echo "$HOME_RESPONSE > 2.0" | bc -l) )); then
    log "WARNING: Home page response time is high: ${HOME_RESPONSE}s"
fi

# Alert if CPU usage > 80%
if (( $(echo "$CPU_USAGE > 80" | bc -l) )); then
    log "WARNING: High CPU usage: ${CPU_USAGE}%"
fi

# Alert if memory usage > 85%
if (( $(echo "$MEMORY_USAGE > 85" | bc -l) )); then
    log "WARNING: High memory usage: ${MEMORY_USAGE}%"
fi

# Alert if disk usage > 90%
if [ "$DISK_USAGE" -gt 90 ]; then
    log "CRITICAL: High disk usage: ${DISK_USAGE}%"
fi

# Alert if any critical service is down
if [ "$NGINX_STATUS" != "active" ] || [ "$PHP_FPM_STATUS" != "active" ] || [ "$MYSQL_STATUS" != "active" ]; then
    log "CRITICAL: One or more critical services are down!"
fi

# Alert if no queue workers are running
if [ "$QUEUE_WORKERS" -eq 0 ]; then
    log "WARNING: No queue workers are running"
fi

log "Performance monitoring completed."
log "Metrics saved to: $METRICS_FILE"