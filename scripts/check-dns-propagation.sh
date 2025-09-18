#!/bin/bash

# DNS Propagation Checker Script

DOMAIN="zenamanage.com"
SERVER_IP="192.168.1.100"

echo "🌐 Checking DNS Propagation for zenamanage.com"
echo "======================================"

# Check DNS resolution
echo "Checking DNS resolution..."
RESOLVED_IP=$(nslookup $DOMAIN | grep "Address:" | tail -1 | awk '{print $2}')
if [ "$RESOLVED_IP" = "$SERVER_IP" ]; then
    echo "✅ DNS resolution: CORRECT ($RESOLVED_IP)"
else
    echo "❌ DNS resolution: INCORRECT (Expected: $SERVER_IP, Got: $RESOLVED_IP)"
fi

# Check www subdomain
echo "Checking www subdomain..."
WWW_RESOLVED_IP=$(nslookup www.$DOMAIN | grep "Address:" | tail -1 | awk '{print $2}')
if [ "$WWW_RESOLVED_IP" = "$SERVER_IP" ]; then
    echo "✅ WWW subdomain: CORRECT ($WWW_RESOLVED_IP)"
else
    echo "❌ WWW subdomain: INCORRECT (Expected: $SERVER_IP, Got: $WWW_RESOLVED_IP)"
fi

# Check HTTP connection
echo "Checking HTTP connection..."
if curl -s -I http://$DOMAIN &> /dev/null; then
    echo "✅ HTTP connection: SUCCESS"
else
    echo "❌ HTTP connection: FAILED"
fi

# Check HTTPS connection
echo "Checking HTTPS connection..."
if curl -s -I https://$DOMAIN &> /dev/null; then
    echo "✅ HTTPS connection: SUCCESS"
else
    echo "❌ HTTPS connection: FAILED"
fi

# Check Laravel application
echo "Checking Laravel application..."
if curl -s http://$DOMAIN | grep -q "ZenaManage"; then
    echo "✅ Laravel application: SUCCESS"
else
    echo "❌ Laravel application: FAILED"
fi

echo "DNS propagation check completed!"
