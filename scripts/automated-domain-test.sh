#!/bin/bash

# Automated Domain Testing Script

DOMAIN="zenamanage.com"
SERVER_IP="192.168.1.100"
TEST_COUNT=10
INTERVAL=60

echo "🤖 Automated Domain Testing"
echo "==========================="
echo "Domain: $DOMAIN"
echo "Server IP: $SERVER_IP"
echo "Test Count: $TEST_COUNT"
echo "Interval: $INTERVAL seconds"
echo ""

for i in $(seq 1 $TEST_COUNT); do
    echo "Test $i/$TEST_COUNT - $(date)"
    echo "================================"
    
    # Check DNS resolution
    RESOLVED_IP=$(nslookup $DOMAIN | grep "Address:" | tail -1 | awk '{print $2}')
    if [ "$RESOLVED_IP" = "$SERVER_IP" ]; then
        echo "✅ DNS: CORRECT ($RESOLVED_IP)"
    else
        echo "❌ DNS: INCORRECT (Expected: $SERVER_IP, Got: $RESOLVED_IP)"
    fi
    
    # Check HTTP connection
    if curl -s -I http://$DOMAIN &> /dev/null; then
        echo "✅ HTTP: SUCCESS"
    else
        echo "❌ HTTP: FAILED"
    fi
    
    # Check HTTPS connection
    if curl -s -I https://$DOMAIN &> /dev/null; then
        echo "✅ HTTPS: SUCCESS"
    else
        echo "❌ HTTPS: FAILED"
    fi
    
    echo ""
    
    if [ $i -lt $TEST_COUNT ]; then
        echo "Waiting $INTERVAL seconds..."
        sleep $INTERVAL
    fi
done

echo "Automated testing completed!"
