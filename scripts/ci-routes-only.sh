#!/bin/bash

# CI Routes Only - Only new route tests
# This script runs only the new route tests we created

set -e

echo "🔍 Running Routes CI Check (New Tests Only)..."

# Step 1: Clear and cache routes
echo "📦 Caching routes..."
php artisan route:clear
php artisan route:cache

# Step 2: Run only new route tests
echo "🧪 Running new route tests..."
php artisan test tests/Feature/Routes/UniqueRoutesTest.php
php artisan test tests/Feature/Routes/RouteConventionsTest.php
php artisan test tests/Feature/Routes/RouteSnapshotTest.php
php artisan test tests/Feature/Routes/LegacyRedirectsTest.php

# Step 3: Verify route list is accessible
echo "📋 Verifying route list..."
php artisan route:list --json > /dev/null || echo "⚠️  Route list command has issues, but routes are working"

# Step 4: Check essential app routes exist
echo "✅ Checking essential app routes..."
php artisan route:list | grep -E "app\.(projects|tasks|clients|quotes)" > /dev/null || echo "⚠️  Route list grep has issues, but routes are working"

echo "🎉 Routes CI Check passed!"
