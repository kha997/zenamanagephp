#!/bin/bash

# Test login script
echo "Testing login..."

# Get CSRF token
TOKEN=$(curl -s http://localhost:8000/login | grep -o 'name="_token" value="[^"]*"' | cut -d'"' -f4)
echo "CSRF Token: $TOKEN"

# Login with CSRF token
curl -c cookies.txt -X POST http://localhost:8000/login \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "X-CSRF-TOKEN: $TOKEN" \
  -d "email=test@example.com&password=password123&_token=$TOKEN" \
  -L

echo ""
echo "--- Testing dashboard ---"
curl -b cookies.txt http://localhost:8000/app/dashboard
