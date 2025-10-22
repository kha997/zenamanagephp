#!/bin/bash

# ZenaManage Performance Monitoring Script
# Version: 1.0.0
# Date: 2025-10-15

# --- Configuration ---
APP_NAME="ZenaManage"
METRICS_URL="http://127.0.0.1:8000/api/metrics"
HEALTH_URL="http://127.0.0.1:8000/api/metrics/health"
LOG_FILE="storage/logs/performance-monitoring.log"
ALERT_THRESHOLDS=(
    "error_rate:5"
    "response_time:500"
    "cpu_usage:80"
    "memory_usage:85"
)

# --- Functions ---
print_status() {
    echo -e "\n[INFO] $1"
}

print_success() {
    echo -e "\n[SUCCESS] $1"
}

print_warning() {
    echo -e "\n[WARNING] $1"
}

print_error() {
    echo -e "\n[ERROR] $1"
}

# --- Main Monitoring Function ---
monitor_performance() {
    print_status "🔍 Starting $APP_NAME Performance Monitoring..."
    
    # Create log directory if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Get current timestamp
    TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
    
    # Collect metrics
    print_status "📊 Collecting application metrics..."
    
    if ! METRICS_RESPONSE=$(curl -s "$METRICS_URL" 2>/dev/null); then
        print_error "Failed to collect metrics from $METRICS_URL"
        echo "[$TIMESTAMP] ERROR: Failed to collect metrics" >> "$LOG_FILE"
        return 1
    fi
    
    # Parse metrics
    ERROR_RATE=$(echo "$METRICS_RESPONSE" | jq -r '.data.application.error_rate // 0')
    RESPONSE_TIME=$(echo "$METRICS_RESPONSE" | jq -r '.data.application.response_time_avg // 0')
    CPU_USAGE=$(echo "$METRICS_RESPONSE" | jq -r '.data.system.cpu_usage // 0')
    MEMORY_USAGE=$(echo "$METRICS_RESPONSE" | jq -r '.data.system.memory_usage.percentage // 0')
    ACTIVE_USERS=$(echo "$METRICS_RESPONSE" | jq -r '.data.application.active_users // 0')
    REQUESTS_PER_MINUTE=$(echo "$METRICS_RESPONSE" | jq -r '.data.application.requests_per_minute // 0')
    
    # Log metrics
    echo "[$TIMESTAMP] METRICS: error_rate=$ERROR_RATE, response_time=${RESPONSE_TIME}ms, cpu=$CPU_USAGE%, memory=$MEMORY_USAGE%, users=$ACTIVE_USERS, rpm=$REQUESTS_PER_MINUTE" >> "$LOG_FILE"
    
    # Check thresholds and generate alerts
    print_status "🚨 Checking alert thresholds..."
    
    ALERTS=()
    
    # Check error rate
    if (( $(echo "$ERROR_RATE > 5" | bc -l) )); then
        ALERTS+=("HIGH_ERROR_RATE: $ERROR_RATE%")
        print_warning "High error rate detected: $ERROR_RATE%"
    fi
    
    # Check response time
    if (( $(echo "$RESPONSE_TIME > 500" | bc -l) )); then
        ALERTS+=("HIGH_RESPONSE_TIME: ${RESPONSE_TIME}ms")
        print_warning "High response time detected: ${RESPONSE_TIME}ms"
    fi
    
    # Check CPU usage
    if (( $(echo "$CPU_USAGE > 80" | bc -l) )); then
        ALERTS+=("HIGH_CPU_USAGE: $CPU_USAGE%")
        print_warning "High CPU usage detected: $CPU_USAGE%"
    fi
    
    # Check memory usage
    if (( $(echo "$MEMORY_USAGE > 85" | bc -l) )); then
        ALERTS+=("HIGH_MEMORY_USAGE: $MEMORY_USAGE%")
        print_warning "High memory usage detected: $MEMORY_USAGE%"
    fi
    
    # Log alerts
    if [ ${#ALERTS[@]} -gt 0 ]; then
        for alert in "${ALERTS[@]}"; do
            echo "[$TIMESTAMP] ALERT: $alert" >> "$LOG_FILE"
        done
        print_warning "⚠️  ${#ALERTS[@]} alert(s) triggered"
    else
        print_success "✅ All metrics within normal ranges"
    fi
    
    # Health check
    print_status "🏥 Performing health check..."
    
    if ! HEALTH_RESPONSE=$(curl -s "$HEALTH_URL" 2>/dev/null); then
        print_error "Failed to perform health check"
        echo "[$TIMESTAMP] ERROR: Health check failed" >> "$LOG_FILE"
        return 1
    fi
    
    HEALTH_STATUS=$(echo "$HEALTH_RESPONSE" | jq -r '.status // "unknown"')
    echo "[$TIMESTAMP] HEALTH: $HEALTH_STATUS" >> "$LOG_FILE"
    
    if [ "$HEALTH_STATUS" = "healthy" ]; then
        print_success "✅ System health: $HEALTH_STATUS"
    elif [ "$HEALTH_STATUS" = "degraded" ]; then
        print_warning "⚠️  System health: $HEALTH_STATUS"
    else
        print_error "❌ System health: $HEALTH_STATUS"
    fi
    
    # Performance summary
    print_status "📈 Performance Summary"
    echo "=========================================="
    echo "Active Users: $ACTIVE_USERS"
    echo "Requests/min: $REQUESTS_PER_MINUTE"
    echo "Error Rate: $ERROR_RATE%"
    echo "Response Time: ${RESPONSE_TIME}ms"
    echo "CPU Usage: $CPU_USAGE%"
    echo "Memory Usage: $MEMORY_USAGE%"
    echo "Health Status: $HEALTH_STATUS"
    echo "=========================================="
    
    print_success "Performance monitoring completed successfully! 🎉"
}

# --- Alert Notification Function ---
send_alert() {
    local alert_type="$1"
    local alert_value="$2"
    local timestamp="$3"
    
    # Email notification (if configured)
    if [ -n "$ALERT_EMAIL" ]; then
        echo "Alert: $alert_type = $alert_value at $timestamp" | mail -s "ZenaManage Alert" "$ALERT_EMAIL"
    fi
    
    # Slack notification (if configured)
    if [ -n "$SLACK_WEBHOOK" ]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"🚨 ZenaManage Alert: $alert_type = $alert_value at $timestamp\"}" \
            "$SLACK_WEBHOOK"
    fi
    
    # Log alert
    echo "[$timestamp] NOTIFICATION: $alert_type = $alert_value" >> "$LOG_FILE"
}

# --- Cleanup old logs ---
cleanup_logs() {
    print_status "🧹 Cleaning up old log files..."
    
    # Keep only last 7 days of logs
    find "$(dirname "$LOG_FILE")" -name "performance-monitoring.log*" -mtime +7 -delete 2>/dev/null
    
    print_success "Log cleanup completed"
}

# --- Main execution ---
case "${1:-monitor}" in
    "monitor")
        monitor_performance
        ;;
    "cleanup")
        cleanup_logs
        ;;
    "test")
        print_status "🧪 Testing monitoring endpoints..."
        curl -s "$METRICS_URL" | jq '.status'
        curl -s "$HEALTH_URL" | jq '.status'
        ;;
    *)
        echo "Usage: $0 {monitor|cleanup|test}"
        echo "  monitor  - Run performance monitoring (default)"
        echo "  cleanup  - Clean up old log files"
        echo "  test     - Test monitoring endpoints"
        exit 1
        ;;
esac
