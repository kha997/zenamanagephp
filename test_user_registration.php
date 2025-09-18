<?php
/**
 * Test User Registration Directly
 * Tests user registration without going through API
 */

echo "🧪 Testing User Registration Directly\n";
echo "=" . str_repeat("=", 40) . "\n\n";

require_once "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

try {
    // Test 1: Create tenant
    echo "🏢 Test 1: Creating tenant...\n";
    $tenant = \App\Models\Tenant::create([
        'name' => 'Test Tenant Direct',
        'domain' => 'testtenant-direct-' . time() . '.com',
        'phone' => '+1234567890',
        'address' => '123 Test Street, Test City'
    ]);
    echo "✅ Tenant created with ID: {$tenant->id}\n\n";

    // Test 2: Create user
    echo "👤 Test 2: Creating user...\n";
    $user = \App\Models\User::create([
        'name' => 'Test User Direct',
        'email' => 'testuser@direct.com',
        'password' => \Illuminate\Support\Facades\Hash::make('TestPassword123!'),
        'tenant_id' => $tenant->id,
        'is_active' => true
    ]);
    echo "✅ User created with ID: {$user->id}\n\n";

    // Test 3: Test systemRoles relationship
    echo "🔗 Test 3: Testing systemRoles relationship...\n";
    try {
        $roles = $user->systemRoles()->pluck('name')->toArray();
        echo "✅ systemRoles relationship works: " . json_encode($roles) . "\n";
    } catch (Exception $e) {
        echo "❌ systemRoles relationship error: " . $e->getMessage() . "\n";
    }

    // Test 4: Test AuthService
    echo "🔐 Test 4: Testing AuthService...\n";
    try {
        $authService = app(\Src\RBAC\Services\AuthService::class);
        $token = $authService->createTokenForUser($user);
        echo "✅ AuthService works, token generated\n";
        
        // Test token validation
        $payload = $authService->validateToken($token);
        if ($payload) {
            echo "✅ Token validation works\n";
        } else {
            echo "❌ Token validation failed\n";
        }
    } catch (Exception $e) {
        echo "❌ AuthService error: " . $e->getMessage() . "\n";
    }

    // Test 5: Test JWT Guard
    echo "🛡️ Test 5: Testing JWT Guard...\n";
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

    echo "\n🎉 Direct User Registration Testing Complete!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
