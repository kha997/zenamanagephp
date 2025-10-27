#!/bin/bash

# Test login script
echo "Testing login..."

# Login via API
echo "Attempting login via API..."
LOGIN_RESPONSE=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}')

echo "$LOGIN_RESPONSE" | jq .

# Extract token from response
TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -n "$TOKEN" ]; then
    echo ""
    echo "Token: $TOKEN"
    echo ""
    echo "--- Testing dashboard ---"
    curl -s -H "Authorization: Bearer $TOKEN" \
         http://localhost:8000/api/dashboard | jq .
else
    echo "❌ Failed to get token"
    exit 1
fi
