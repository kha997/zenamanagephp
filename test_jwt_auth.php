<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== JWT Authentication Test ===\n";

try {
    // Test AuthService instantiation
    $authService = new Src\RBAC\Services\AuthService();
    echo "✓ AuthService instantiated successfully\n";
    
    // Test JWT configuration
    $jwtSecret = config('jwt.secret');
    $jwtTtl = config('jwt.ttl');
    $jwtAlgo = config('jwt.algo');
    
    echo "✓ JWT Secret: " . (strlen($jwtSecret) > 0 ? 'SET (' . strlen($jwtSecret) . ' chars)' : 'NOT SET') . "\n";
    echo "✓ JWT TTL: $jwtTtl minutes\n";
    echo "✓ JWT Algorithm: $jwtAlgo\n";
    
    // Test User model
    $userCount = App\Models\User::count();
    echo "✓ User model accessible, found $userCount users\n";
    
    // Create UserProvider and Request for JwtGuard
    $userProvider = new Illuminate\Auth\EloquentUserProvider(
        app('hash'),
        App\Models\User::class
    );
    
    $request = Illuminate\Http\Request::create('/', 'GET');
    
    // Test JWT Guard with correct parameters
    $jwtGuard = new App\Auth\JwtGuard($userProvider, $request, $authService);
    echo "✓ JwtGuard instantiated successfully\n";
    
    // Test creating a test user if none exists
    if ($userCount === 0) {
        $testUser = App\Models\User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'tenant_id' => 1
        ]);
        echo "✓ Created test user: {$testUser->email}\n";
    } else {
        $testUser = App\Models\User::first();
        echo "✓ Using existing user: {$testUser->email}\n";
    }
    
    // Test login and token creation
    echo "\n--- Testing Login & Token Creation ---\n";
    
    // Sửa: Truyền credentials dưới dạng array
    $credentials = [
        'email' => 'test@example.com',
        'password' => 'password123'
    ];
    
    $loginResult = $authService->login($credentials);
    
    if ($loginResult && $loginResult['success']) {
        echo "✓ Login successful\n";
        echo "✓ Token created: " . substr($loginResult['token'], 0, 20) . "...\n";
        
        // Test token validation
        $payload = $authService->validateToken($loginResult['token']);
        if ($payload) {
            echo "✓ Token validation successful\n";
            echo "✓ User ID from token: " . ($payload['user_id'] ?? 'N/A') . "\n";
        } else {
            echo "✗ Token validation failed\n";
        }
    } else {
        echo "✗ Login failed\n";
        echo "Debug - Login result: " . json_encode($loginResult) . "\n";
    }
    
    echo "\n=== JWT Authentication Test Completed ===\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}