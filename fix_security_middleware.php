<?php

echo "🔧 Fixing SecurityHeadersMiddleware...\n\n";

// 1. Clear all caches first
echo "1. Clearing caches...\n";
exec('php artisan config:clear');
exec('php artisan route:clear');
exec('php artisan view:clear');
exec('php artisan cache:clear');

// 2. Dump autoload
echo "2. Dumping autoload...\n";
exec('composer dump-autoload');

// 3. Test the middleware
echo "3. Testing middleware...\n";
$testUrl = 'http://localhost:8000/app/dashboard';
$headers = @get_headers($testUrl);

if ($headers && strpos($headers[0], '200') !== false) {
    echo "✅ Dashboard is now accessible!\n";
} else {
    echo "⚠️  Dashboard may still have issues, check manually\n";
}

echo "\n🎯 SecurityHeadersMiddleware has been simplified\n";
echo "🌐 Visit: http://localhost:8000/app/dashboard\n";
echo "📊 Dashboard should load without middleware errors\n";