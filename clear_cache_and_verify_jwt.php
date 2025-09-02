<?php declare(strict_types=1);

echo "=== CLEARING CACHE AND VERIFYING JWT CONFIG ===\n\n";

// 1. Xóa cache config tận gốc
echo "1. Clearing config cache...\n";
$commands = [
    'cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan config:clear',
    'cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan optimize:clear',
    'cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && composer dump-autoload -o'
];

foreach ($commands as $cmd) {
    echo "   Running: $cmd\n";
    exec($cmd . ' 2>&1', $output, $return);
    if ($return !== 0) {
        echo "   ❌ Error: " . implode("\n", $output) . "\n";
    } else {
        echo "   ✅ Success\n";
    }
    $output = [];
}

echo "\n2. Verifying runtime JWT config...\n";

// 2. Kiểm tra runtime config
require_once '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/vendor/autoload.php';

$app = require_once '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$defaultGuard = config('auth.defaults.guard');
$apiDriver = config('auth.guards.api.driver');

echo "   defaults.guard: '$defaultGuard' (should be 'api')\n";
echo "   guards.api.driver: '$apiDriver' (should be 'jwt')\n";

if ($defaultGuard === 'api' && $apiDriver === 'jwt') {
    echo "   ✅ JWT config is correct!\n";
} else {
    echo "   ❌ JWT config is incorrect!\n";
}

echo "\n3. Testing JWT endpoints...\n";
echo "   Please run: php test_api\n";
echo "   Expected: /me, /jwt-test, /user-profile should return 401 (not 500) when no token\n";
echo "   Expected: Same endpoints should return 200 with valid JWT token\n";

echo "\n=== COMPLETED ===\n";
?>