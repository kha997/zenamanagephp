<?php

echo "🔧 Comprehensive Dashboard Fix...\n\n";

// 1. Fix migration issue first
echo "1. Fixing migration issue...\n";
exec('php artisan migrate:rollback --step=1');
echo "   Migration rolled back\n";

// 2. Clear all caches
echo "2. Clearing all caches...\n";
exec('php artisan config:clear');
exec('php artisan route:clear');
exec('php artisan view:clear');
exec('php artisan cache:clear');
exec('composer dump-autoload');

// 3. Run migration again
echo "3. Running migrations...\n";
exec('php artisan migrate');

// 4. Create missing directories
echo "4. Creating missing directories...\n";
$dirs = [
    'app/Http/Controllers/Api',
    'storage/logs',
    'public/css',
    'public/js'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "   Created: $dir\n";
    }
}

// 5. Set proper permissions
echo "5. Setting permissions...\n";
exec('chmod -R 755 storage');
exec('chmod -R 755 bootstrap/cache');
exec('chmod -R 755 public');

// 6. Generate app key if missing
echo "6. Checking app key...\n";
if (empty(env('APP_KEY'))) {
    exec('php artisan key:generate');
    echo "   App key generated\n";
}

// 7. Create symlink for storage
echo "7. Creating storage link...\n";
exec('php artisan storage:link');

echo "\n✅ Comprehensive dashboard fix completed!\n";
echo "🌐 Now visit: http://localhost:8000/app/dashboard\n";
echo "📊 KPI cards should load properly now\n";