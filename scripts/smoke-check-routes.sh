#!/bin/bash

# Smoke Check Routes - Post-deployment verification
# Run this after every deployment to ensure routes are working

set -e

APP_URL=${APP_URL:-"http://localhost:8000"}

echo "🚀 Running Routes Smoke Check..."
echo "App URL: $APP_URL"

# Step 1: Route verification
echo "📦 1. Route verification..."
composer route:verify

# Step 2: Check essential app routes exist
echo "✅ 2. Checking essential app routes..."
php artisan route:list | grep -E 'app\.(projects|tasks|clients|quotes)' || {
    echo "❌ Missing essential app routes!"
    exit 1
}

# Step 3: Test legacy redirects
echo "🔄 3. Testing legacy redirects..."
curl -s -I "$APP_URL/projects" | grep -q "301 Moved Permanently" || {
    echo "❌ Legacy redirect /projects -> /app/projects failed!"
    exit 1
}

curl -s -I "$APP_URL/tasks" | grep -q "301 Moved Permanently" || {
    echo "❌ Legacy redirect /tasks -> /app/tasks failed!"
    exit 1
}

curl -s -I "$APP_URL/clients" | grep -q "301 Moved Permanently" || {
    echo "❌ Legacy redirect /clients -> /app/clients failed!"
    exit 1
}

curl -s -I "$APP_URL/quotes" | grep -q "301 Moved Permanently" || {
    echo "❌ Legacy redirect /quotes -> /app/quotes failed!"
    exit 1
}

# Step 4: Test app routes (should redirect to login if not authenticated)
echo "🔐 4. Testing app routes (should redirect to login)..."
curl -s -I "$APP_URL/app/dashboard" | grep -q "302\|301" || {
    echo "❌ App route /app/dashboard should redirect to login!"
    exit 1
}

curl -s -I "$APP_URL/app/projects" | grep -q "302\|301" || {
    echo "❌ App route /app/projects should redirect to login!"
    exit 1
}

# Step 5: Run route tests
echo "🧪 5. Running route tests..."
php artisan test --testsuite=Feature --filter=Routes

echo "🎉 Routes Smoke Check passed!"
echo "✅ All routes are working correctly"
