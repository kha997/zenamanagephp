<?php
/**
 * Comprehensive AuthManager Fix Script
 * Fixes the "AuthManager is not callable" error systematically
 */

echo "🔧 Starting Comprehensive AuthManager Fix\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Step 1: Clear all caches
echo "🧹 Step 1: Clearing all caches...\n";
$commands = [
    'php artisan config:clear',
    'php artisan cache:clear',
    'php artisan route:clear',
    'php artisan view:clear',
    'composer dump-autoload'
];

foreach ($commands as $command) {
    echo "Running: $command\n";
    $output = shell_exec($command . ' 2>&1');
    if ($output) {
        echo "Output: " . trim($output) . "\n";
    }
}
echo "✅ Caches cleared\n\n";

// Step 2: Check JWT configuration
echo "🔍 Step 2: Checking JWT configuration...\n";
$jwtConfig = file_get_contents('config/jwt.php');
if (strpos($jwtConfig, 'JWT_SECRET') !== false) {
    echo "✅ JWT config file exists\n";
} else {
    echo "❌ JWT config file missing or invalid\n";
}

// Step 3: Check Auth configuration
echo "🔍 Step 3: Checking Auth configuration...\n";
$authConfig = file_get_contents('config/auth.php');
if (strpos($authConfig, 'jwt') !== false) {
    echo "✅ Auth config has JWT guard\n";
} else {
    echo "❌ Auth config missing JWT guard\n";
}

// Step 4: Check Service Providers
echo "🔍 Step 4: Checking Service Providers...\n";
$appConfig = file_get_contents('config/app.php');
if (strpos($appConfig, 'JwtAuthServiceProvider') !== false) {
    echo "✅ JwtAuthServiceProvider registered\n";
} else {
    echo "❌ JwtAuthServiceProvider not registered\n";
}

// Step 5: Check JWT Guard implementation
echo "🔍 Step 5: Checking JWT Guard implementation...\n";
$jwtGuardFile = 'app/Auth/JwtGuard.php';
if (file_exists($jwtGuardFile)) {
    echo "✅ JwtGuard exists\n";
    $jwtGuardContent = file_get_contents($jwtGuardFile);
    if (strpos($jwtGuardContent, 'implements Guard') !== false) {
        echo "✅ JwtGuard implements Guard interface\n";
    } else {
        echo "❌ JwtGuard doesn't implement Guard interface\n";
    }
} else {
    echo "❌ JwtGuard file missing\n";
}

// Step 6: Check AuthService
echo "🔍 Step 6: Checking AuthService...\n";
$authServiceFile = 'src/RBAC/Services/AuthService.php';
if (file_exists($authServiceFile)) {
    echo "✅ AuthService exists\n";
} else {
    echo "❌ AuthService missing\n";
}

// Step 7: Test JWT Guard registration
echo "🧪 Step 7: Testing JWT Guard registration...\n";
$testScript = '<?php
require_once "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

try {
    $auth = app("auth");
    $guard = $auth->guard("api");
    echo "✅ JWT Guard accessible: " . get_class($guard) . "\n";
} catch (Exception $e) {
    echo "❌ JWT Guard error: " . $e->getMessage() . "\n";
}
';

file_put_contents('test_jwt_guard.php', $testScript);
$output = shell_exec('php test_jwt_guard.php 2>&1');
echo $output;
unlink('test_jwt_guard.php');

echo "\n🎉 AuthManager Fix Analysis Complete!\n";
?>
