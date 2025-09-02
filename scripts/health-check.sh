#!/bin/bash

# Script health check và auto-restart services
# Sử dụng: ./health-check.sh [environment]

set -e

ENVIRONMENT=${1:-production}
APP_DIR="/var/www/zenamanage"
LOG_FILE="/var/log/zenamanage-health.log"

# Load environment variables
if [ -f "${APP_DIR}/.env.${ENVIRONMENT}" ]; then
    export $(cat ${APP_DIR}/.env.${ENVIRONMENT} | grep -v '^#' | xargs)
fi

# Function để restart service
restart_service() {
    local service="$1"
    echo "[$(date)] Restarting ${service}..." | tee -a "${LOG_FILE}"
    
    systemctl restart "${service}"
    
    if [ $? -eq 0 ]; then
        echo "[$(date)] ${service} restarted successfully" | tee -a "${LOG_FILE}"
        
        # Gửi notification
        if [ ! -z "${HEALTH_ALERT_WEBHOOK}" ]; then
            curl -X POST "${HEALTH_ALERT_WEBHOOK}" \
                -H "Content-Type: application/json" \
                -d "{
                    \"text\": \"🔄 Service ${service} was restarted automatically\",
                    \"environment\": \"${ENVIRONMENT}\",
                    \"timestamp\": \"$(date -Iseconds)\",
                    \"hostname\": \"$(hostname)\"
                }" 2>/dev/null || true
        fi
    else
        echo "[$(date)] Failed to restart ${service}" | tee -a "${LOG_FILE}"
    fi
}

# Function để check và restart nếu cần
check_and_restart() {
    local service="$1"
    local max_retries="${2:-3}"
    local retry_count=0
    
    while [ $retry_count -lt $max_retries ]; do
        if systemctl is-active --quiet "${service}"; then
            echo "[$(date)] ${service} is running" >> "${LOG_FILE}"
            return 0
        else
            echo "[$(date)] ${service} is not running, attempting restart (attempt $((retry_count + 1))/${max_retries})" | tee -a "${LOG_FILE}"
            restart_service "${service}"
            sleep 10
            retry_count=$((retry_count + 1))
        fi
    done
    
    echo "[$(date)] Failed to restart ${service} after ${max_retries} attempts" | tee -a "${LOG_FILE}"
    return 1
}

echo "[$(date)] Starting health check for ${ENVIRONMENT} environment..." >> "${LOG_FILE}"

# Kiểm tra các services quan trọng
SERVICES=("nginx" "php8.0-fpm" "mysql" "redis-server")

for service in "${SERVICES[@]}"; do
    check_and_restart "${service}"
done

# Kiểm tra WebSocket server
if [ ! -z "${WEBSOCKET_PORT}" ]; then
    WS_RUNNING=$(netstat -tuln | grep ":${WEBSOCKET_PORT}" | wc -l)
    
    if [ "${WS_RUNNING}" -eq 0 ]; then
        echo "[$(date)] WebSocket server is not running, starting..." | tee -a "${LOG_FILE}"
        
        cd "${APP_DIR}"
        nohup node websocket_server.js > /var/log/websocket.log 2>&1 &
        
        sleep 5
        
        WS_RUNNING=$(netstat -tuln | grep ":${WEBSOCKET_PORT}" | wc -l)
        if [ "${WS_RUNNING}" -gt 0 ]; then
            echo "[$(date)] WebSocket server started successfully" | tee -a "${LOG_FILE}"
        else
            echo "[$(date)] Failed to start WebSocket server" | tee -a "${LOG_FILE}"
        fi
    fi
fi

# Kiểm tra application health endpoint
if [ ! -z "${APP_URL}" ]; then
    HEALTH_URL="${APP_URL}/health"
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "${HEALTH_URL}" --max-time 10 || echo "000")
    
    if [ "${HTTP_STATUS}" != "200" ]; then
        echo "[$(date)] Application health check failed (HTTP ${HTTP_STATUS}), restarting PHP-FPM..." | tee -a "${LOG_FILE}"
        restart_service "php8.0-fpm"
        
        # Wait and check again
        sleep 10
        HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "${HEALTH_URL}" --max-time 10 || echo "000")
        
        if [ "${HTTP_STATUS}" == "200" ]; then
            echo "[$(date)] Application health restored" | tee -a "${LOG_FILE}"
        else
            echo "[$(date)] Application health still failing after restart" | tee -a "${LOG_FILE}"
        fi
    else
        echo "[$(date)] Application health check passed" >> "${LOG_FILE}"
    fi
fi

echo "[$(date)] Health check completed" >> "${LOG_FILE}"