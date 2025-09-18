#!/bin/bash

# Domain Testing Script

DOMAIN="zenamanage.com"
IP="192.168.1.100"

echo "🌐 Testing Domain Configuration"
echo "==============================="

# Test DNS resolution
echo "Testing DNS resolution..."
if nslookup $DOMAIN &> /dev/null; then
    echo "✅ DNS resolution: SUCCESS"
else
    echo "❌ DNS resolution: FAILED"
fi

# Test HTTP connection
echo "Testing HTTP connection..."
if curl -s -I http://$DOMAIN &> /dev/null; then
    echo "✅ HTTP connection: SUCCESS"
else
    echo "❌ HTTP connection: FAILED"
fi

# Test Laravel application
echo "Testing Laravel application..."
if curl -s http://$DOMAIN | grep -q "ZenaManage"; then
    echo "✅ Laravel application: SUCCESS"
else
    echo "❌ Laravel application: FAILED"
fi

echo "Domain testing completed!"
