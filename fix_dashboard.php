<?php

echo "🔧 Fixing Dashboard Issues...\n\n";

// 1. Clear all caches
echo "1. Clearing caches...\n";
exec('php artisan config:clear');
exec('php artisan route:clear');
exec('php artisan view:clear');
exec('php artisan cache:clear');

// 2. Rebuild assets
echo "2. Rebuilding assets...\n";
exec('npm run build');

// 3. Create missing directories
echo "3. Creating directories...\n";
if (!is_dir('app/Http/Controllers/Api')) {
    mkdir('app/Http/Controllers/Api', 0755, true);
}

// 4. Set permissions
echo "4. Setting permissions...\n";
exec('chmod -R 755 storage');
exec('chmod -R 755 bootstrap/cache');

echo "✅ Dashboard fixes applied!\n";
echo "🌐 Visit: http://localhost:8000/app/dashboard\n";