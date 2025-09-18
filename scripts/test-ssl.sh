#!/bin/bash

# SSL Testing Script

DOMAIN="zenamanage.com"

echo "🔒 Testing SSL Certificate"
echo "========================"

# Test SSL certificate
echo "Testing SSL certificate..."
if openssl x509 -in storage/ssl/server.crt -text -noout &> /dev/null; then
    echo "✅ SSL certificate: VALID"
else
    echo "❌ SSL certificate: INVALID"
fi

# Test private key
echo "Testing private key..."
if openssl rsa -in storage/ssl/server.key -check &> /dev/null; then
    echo "✅ Private key: VALID"
else
    echo "❌ Private key: INVALID"
fi

# Test certificate and key match
echo "Testing certificate and key match..."
CERT_MODULUS=$(openssl x509 -noout -modulus -in storage/ssl/server.crt | openssl md5)
KEY_MODULUS=$(openssl rsa -noout -modulus -in storage/ssl/server.key | openssl md5)

if [ "$CERT_MODULUS" = "$KEY_MODULUS" ]; then
    echo "✅ Certificate and key: MATCH"
else
    echo "❌ Certificate and key: DO NOT MATCH"
fi

# Test HTTPS connection
echo "Testing HTTPS connection..."
if curl -s -I https://$DOMAIN &> /dev/null; then
    echo "✅ HTTPS connection: SUCCESS"
else
    echo "❌ HTTPS connection: FAILED"
fi

echo "SSL testing completed!"
