#!/bin/bash

# Script monitoring system cho production
# Sử dụng: ./monitor-system.sh [environment]

set -e

# Cấu hình
ENVIRONMENT=${1:-production}
APP_DIR="/var/www/zenamanage"
LOG_FILE="/var/log/zenamanage-monitor.log"
ALERT_WEBHOOK="${MONITOR_ALERT_WEBHOOK}"

# Load environment variables
if [ -f "${APP_DIR}/.env.${ENVIRONMENT}" ]; then
    export $(cat ${APP_DIR}/.env.${ENVIRONMENT} | grep -v '^#' | xargs)
fi

# Thresholds
CPU_THRESHOLD=80
MEMORY_THRESHOLD=85
DISK_THRESHOLD=90
LOAD_THRESHOLD=5.0

# Function để gửi alert
send_alert() {
    local message="$1"
    local severity="$2"
    local metric="$3"
    local value="$4"
    
    echo "[$(date)] ALERT: ${message}" | tee -a "${LOG_FILE}"
    
    if [ ! -z "${ALERT_WEBHOOK}" ]; then
        curl -X POST "${ALERT_WEBHOOK}" \
            -H "Content-Type: application/json" \
            -d "{
                \"text\": \"🚨 ${message}\",
                \"environment\": \"${ENVIRONMENT}\",
                \"severity\": \"${severity}\",
                \"metric\": \"${metric}\",
                \"value\": \"${value}\",
                \"threshold\": \"${5}\",
                \"timestamp\": \"$(date -Iseconds)\",
                \"hostname\": \"$(hostname)\"
            }" 2>/dev/null || true
    fi
}

# Function để log metrics
log_metric() {
    local metric="$1"
    local value="$2"
    local unit="$3"
    
    echo "[$(date)] METRIC: ${metric}=${value}${unit}" >> "${LOG_FILE}"
}

echo "[$(date)] Starting system monitoring for ${ENVIRONMENT} environment..." | tee -a "${LOG_FILE}"

# 1. Kiểm tra CPU usage
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
CPU_USAGE_INT=$(echo "${CPU_USAGE}" | cut -d'.' -f1)

log_metric "cpu_usage" "${CPU_USAGE}" "%"

if [ "${CPU_USAGE_INT}" -gt "${CPU_THRESHOLD}" ]; then
    send_alert "High CPU usage detected" "warning" "cpu_usage" "${CPU_USAGE}%" "${CPU_THRESHOLD}%"
fi

# 2. Kiểm tra Memory usage
MEMORY_INFO=$(free | grep Mem)
MEMORY_TOTAL=$(echo ${MEMORY_INFO} | awk '{print $2}')
MEMORY_USED=$(echo ${MEMORY_INFO} | awk '{print $3}')
MEMORY_USAGE=$(echo "scale=2; ${MEMORY_USED} * 100 / ${MEMORY_TOTAL}" | bc)
MEMORY_USAGE_INT=$(echo "${MEMORY_USAGE}" | cut -d'.' -f1)

log_metric "memory_usage" "${MEMORY_USAGE}" "%"

if [ "${MEMORY_USAGE_INT}" -gt "${MEMORY_THRESHOLD}" ]; then
    send_alert "High memory usage detected" "warning" "memory_usage" "${MEMORY_USAGE}%" "${MEMORY_THRESHOLD}%"
fi

# 3. Kiểm tra Disk usage
DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}' | cut -d'%' -f1)

log_metric "disk_usage" "${DISK_USAGE}" "%"

if [ "${DISK_USAGE}" -gt "${DISK_THRESHOLD}" ]; then
    send_alert "High disk usage detected" "critical" "disk_usage" "${DISK_USAGE}%" "${DISK_THRESHOLD}%"
fi

# 4. Kiểm tra Load average
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | cut -d',' -f1)
LOAD_CHECK=$(echo "${LOAD_AVG} > ${LOAD_THRESHOLD}" | bc -l)

log_metric "load_average" "${LOAD_AVG}" ""

if [ "${LOAD_CHECK}" -eq 1 ]; then
    send_alert "High load average detected" "warning" "load_average" "${LOAD_AVG}" "${LOAD_THRESHOLD}"
fi

# 5. Kiểm tra services
SERVICES=("nginx" "php8.0-fpm" "mysql" "redis-server")

for service in "${SERVICES[@]}"; do
    if systemctl is-active --quiet "${service}"; then
        log_metric "service_${service}" "running" ""
    else
        send_alert "Service ${service} is not running" "critical" "service_status" "stopped" "running"
    fi
done

# 6. Kiểm tra application health
if [ ! -z "${APP_URL}" ]; then
    HEALTH_URL="${APP_URL}/health"
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "${HEALTH_URL}" || echo "000")
    
    log_metric "app_health_status" "${HTTP_STATUS}" ""
    
    if [ "${HTTP_STATUS}" != "200" ]; then
        send_alert "Application health check failed" "critical" "health_check" "HTTP ${HTTP_STATUS}" "HTTP 200"
    fi
fi

# 7. Kiểm tra database connections
if [ ! -z "${DB_HOST}" ]; then
    DB_CONNECTIONS=$(mysql -h"${DB_HOST}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" -e "SHOW STATUS LIKE 'Threads_connected';" | tail -1 | awk '{print $2}' 2>/dev/null || echo "0")
    
    log_metric "db_connections" "${DB_CONNECTIONS}" ""
    
    if [ "${DB_CONNECTIONS}" -gt 100 ]; then
        send_alert "High database connections" "warning" "db_connections" "${DB_CONNECTIONS}" "100"
    fi
fi

# 8. Kiểm tra log errors
ERROR_COUNT=$(tail -1000 "${APP_DIR}/storage/logs/laravel.log" 2>/dev/null | grep -c "ERROR" || echo "0")

log_metric "error_count_last_1000_lines" "${ERROR_COUNT}" ""

if [ "${ERROR_COUNT}" -gt 10 ]; then
    send_alert "High error count in application logs" "warning" "error_count" "${ERROR_COUNT}" "10"
fi

# 9. Kiểm tra WebSocket server
if [ ! -z "${WEBSOCKET_PORT}" ]; then
    WS_CHECK=$(netstat -tuln | grep ":${WEBSOCKET_PORT}" | wc -l)
    
    log_metric "websocket_status" "${WS_CHECK}" ""
    
    if [ "${WS_CHECK}" -eq 0 ]; then
        send_alert "WebSocket server is not running" "critical" "websocket_status" "stopped" "running"
    fi
fi

echo "[$(date)] System monitoring completed" | tee -a "${LOG_FILE}"