#!/bin/bash

# Domain Monitoring Script

DOMAIN="zenamanage.com"
SERVER_IP="192.168.1.100"
LOG_FILE="storage/logs/domain-monitor-$(date +%Y%m%d_%H%M%S).log"

echo "🌐 Monitoring Domain: zenamanage.com"
echo "=============================="
echo "Log file: $LOG_FILE"

# Function to log with timestamp
log() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Monitor DNS resolution
log "Checking DNS resolution..."
RESOLVED_IP=$(nslookup $DOMAIN | grep "Address:" | tail -1 | awk '{print $2}')
if [ "$RESOLVED_IP" = "$SERVER_IP" ]; then
    log "✅ DNS resolution: CORRECT ($RESOLVED_IP)"
else
    log "❌ DNS resolution: INCORRECT (Expected: $SERVER_IP, Got: $RESOLVED_IP)"
fi

# Monitor HTTP connection
log "Checking HTTP connection..."
if curl -s -I http://$DOMAIN &> /dev/null; then
    log "✅ HTTP connection: SUCCESS"
else
    log "❌ HTTP connection: FAILED"
fi

# Monitor HTTPS connection
log "Checking HTTPS connection..."
if curl -s -I https://$DOMAIN &> /dev/null; then
    log "✅ HTTPS connection: SUCCESS"
else
    log "❌ HTTPS connection: FAILED"
fi

# Monitor Laravel application
log "Checking Laravel application..."
if curl -s http://$DOMAIN | grep -q "ZenaManage"; then
    log "✅ Laravel application: SUCCESS"
else
    log "❌ Laravel application: FAILED"
fi

# Monitor web server status
log "Checking web server status..."
if systemctl is-active --quiet apache2; then
    log "✅ Apache: RUNNING"
elif systemctl is-active --quiet nginx; then
    log "✅ Nginx: RUNNING"
else
    log "❌ Web server: NOT RUNNING"
fi

log "Domain monitoring completed!"
