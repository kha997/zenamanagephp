#!/bin/bash

echo "=== Testing Login ==="

# Test 1: Get login page and extract CSRF token
echo "1. Getting CSRF token..."
TOKEN=$(curl -s -c cookies.txt http://localhost:8000/login | grep -o 'name="_token" value="[^"]*"' | cut -d'"' -f4)
echo "CSRF Token: $TOKEN"

# Test 2: Login with CSRF token
echo "2. Attempting login..."
curl -b cookies.txt -c cookies.txt -X POST http://localhost:8000/login \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "X-CSRF-TOKEN: $TOKEN" \
  -d "email=test@example.com&password=password123&_token=$TOKEN" \
  -L

echo ""
echo "3. Testing dashboard access..."
curl -b cookies.txt http://localhost:8000/app/dashboard | head -20
