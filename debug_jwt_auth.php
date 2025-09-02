<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "=== JWT AUTHENTICATION DEBUG ===\n\n";

try {
    // Test 1: Kiểm tra config auth
    echo "1. Checking auth config...\n";
    $defaultGuard = config('auth.defaults.guard');
    $apiDriver = config('auth.guards.api.driver');
    echo "   Default guard: {$defaultGuard}\n";
    echo "   API guard driver: {$apiDriver}\n\n";
    
    // Test 2: Kiểm tra JWT Guard có được đăng ký không
    echo "2. Testing JWT Guard registration...\n";
    $authManager = app('auth');
    echo "   Auth manager class: " . get_class($authManager) . "\n";
    
    try {
        $apiGuard = $authManager->guard('api');
        echo "   API guard class: " . get_class($apiGuard) . "\n";
        echo "   ✅ JWT Guard registered successfully\n\n";
    } catch (Exception $e) {
        echo "   ❌ JWT Guard registration failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 3: Test JWT token validation
    echo "3. Testing JWT token validation...\n";
    $authService = app(Src\RBAC\Services\AuthService::class);
    echo "   AuthService class: " . get_class($authService) . "\n";
    
    // Tạo token test
    $user = App\Models\User::first();
    if ($user) {
        echo "   Test user: {$user->email}\n";
        $token = $authService->createTokenForUser($user);
        echo "   Generated token: " . substr($token, 0, 50) . "...\n";
        
        $payload = $authService->validateToken($token);
        if ($payload) {
            echo "   ✅ Token validation successful\n";
            echo "   User ID from token: " . ($payload['user_id'] ?? 'N/A') . "\n\n";
        } else {
            echo "   ❌ Token validation failed\n\n";
        }
    } else {
        echo "   ❌ No users found in database\n\n";
    }
    
    // Test 4: Test middleware
    echo "4. Testing auth:api middleware...\n";
    $request = Illuminate\Http\Request::create('/v1/auth/me', 'GET');
    $request->headers->set('Authorization', 'Bearer ' . ($token ?? 'invalid-token'));
    
    try {
        $response = $kernel->handle($request);
        echo "   Response status: " . $response->getStatusCode() . "\n";
        echo "   Response content: " . substr($response->getContent(), 0, 200) . "...\n";
    } catch (Exception $e) {
        echo "   ❌ Middleware test failed: " . $e->getMessage() . "\n";
        echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== DEBUG COMPLETED ===\n";