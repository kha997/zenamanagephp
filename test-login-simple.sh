#!/bin/bash

echo "=== Testing Login ==="

# Test 1: Login via API
echo "1. Attempting login via API..."
LOGIN_RESPONSE=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}')

echo "Login Response: $LOGIN_RESPONSE"

# Extract token from response
TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
echo "Token: $TOKEN"

# Test 2: Test authenticated request
if [ -n "$TOKEN" ]; then
    echo ""
    echo "2. Testing dashboard access with token..."
    DASHBOARD_RESPONSE=$(curl -s -H "Authorization: Bearer $TOKEN" \
         http://localhost:8000/api/dashboard)
    echo "$DASHBOARD_RESPONSE" | sed -n '1,20p'
    echo ""
    echo "✅ Login test completed successfully!"
else
    echo "❌ Failed to get token"
    exit 1
fi
