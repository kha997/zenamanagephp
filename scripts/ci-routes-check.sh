#!/bin/bash

# CI Routes Check - Lightweight but thorough
# This script runs before the full test suite to catch route issues early

set -e

echo "🔍 Running Routes CI Check..."

# Step 1: Clear and cache routes
echo "📦 Caching routes..."
php artisan route:clear
php artisan route:cache

# Step 2: Run route-specific tests (only new route tests)
echo "🧪 Running route tests..."
php artisan test --testsuite=Feature --filter="Routes|UniqueRoutes|RouteConventions|RouteSnapshot|LegacyRedirects"

# Step 3: Verify route list is accessible
echo "📋 Verifying route list..."
php artisan route:list --json > /dev/null

# Step 4: Check essential app routes exist
echo "✅ Checking essential app routes..."
php artisan route:list | grep -E "app\.(projects|tasks|clients|quotes)" > /dev/null

echo "🎉 Routes CI Check passed!"
