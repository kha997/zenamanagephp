<?php
/**
 * Test Auth Middleware
 * Tests the auth:api middleware directly
 */

echo "🔐 Testing Auth Middleware\n";
echo "=" . str_repeat("=", 40) . "\n\n";

// Test 1: Test JWT Guard directly
echo "🧪 Test 1: Testing JWT Guard directly...\n";
$testScript = '<?php
require_once "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

try {
    $auth = app("auth");
    $guard = $auth->guard("api");
    echo "✅ JWT Guard accessible: " . get_class($guard) . "\n";
    
    // Test user() method
    $user = $guard->user();
    echo "✅ user() method works: " . ($user ? "User found" : "No user") . "\n";
    
} catch (Exception $e) {
    echo "❌ JWT Guard error: " . $e->getMessage() . "\n";
}
';

file_put_contents('test_jwt_guard_direct.php', $testScript);
$output = shell_exec('php test_jwt_guard_direct.php 2>&1');
echo $output;
unlink('test_jwt_guard_direct.php');

// Test 2: Test auth() helper
echo "\n🧪 Test 2: Testing auth() helper...\n";
$testScript2 = '<?php
require_once "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

try {
    $user = auth()->user();
    echo "✅ auth() helper works: " . ($user ? "User found" : "No user") . "\n";
    
    $guard = auth("api");
    echo "✅ auth("api") works: " . get_class($guard) . "\n";
    
} catch (Exception $e) {
    echo "❌ auth() helper error: " . $e->getMessage() . "\n";
}
';

file_put_contents('test_auth_helper.php', $testScript2);
$output2 = shell_exec('php test_auth_helper.php 2>&1');
echo $output2;
unlink('test_auth_helper.php');

// Test 3: Test middleware resolution
echo "\n🧪 Test 3: Testing middleware resolution...\n";
$testScript3 = '<?php
require_once "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

try {
    $middleware = app("Illuminate\Contracts\Http\Kernel");
    echo "✅ Kernel accessible: " . get_class($middleware) . "\n";
    
    // Test if auth:api middleware exists
    $router = app("router");
    $middlewareGroups = $router->getMiddlewareGroups();
    if (isset($middlewareGroups["api"])) {
        echo "✅ API middleware group exists\n";
        $apiMiddleware = $middlewareGroups["api"];
        if (in_array("auth:api", $apiMiddleware)) {
            echo "✅ auth:api middleware in API group\n";
        } else {
            echo "❌ auth:api middleware NOT in API group\n";
        }
    } else {
        echo "❌ API middleware group not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Middleware error: " . $e->getMessage() . "\n";
}
';

file_put_contents('test_middleware.php', $testScript3);
$output3 = shell_exec('php test_middleware.php 2>&1');
echo $output3;
unlink('test_middleware.php');

echo "\n🎉 Auth Middleware Testing Complete!\n";
?>
