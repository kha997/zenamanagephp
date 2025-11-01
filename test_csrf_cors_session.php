<?php
/**
 * Test CSRF/CORS/Session for Auth Flow
 * 
 * This script tests Sanctum cookie settings, CSRF endpoint, and CORS configuration
 */

require_once __DIR__ . '/vendor/autoload.php';

// Mock Laravel environment
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Testing CSRF/CORS/Session for Auth Flow\n";
echo "==========================================\n\n";

// Test 1: CORS Configuration
echo "Test 1: CORS Configuration\n";
echo "--------------------------\n";

try {
    $corsConfig = config('cors');
    
    echo "CORS Paths: " . implode(', ', $corsConfig['paths']) . "\n";
    echo "Allowed Methods: " . implode(', ', $corsConfig['allowed_methods']) . "\n";
    echo "Allowed Origins: " . implode(', ', $corsConfig['allowed_origins']) . "\n";
    echo "Supports Credentials: " . ($corsConfig['supports_credentials'] ? 'YES' : 'NO') . "\n";
    
    // Check if CSRF cookie endpoint is included
    $hasCsrfPath = in_array('sanctum/csrf-cookie', $corsConfig['paths']);
    echo "CSRF Cookie Path Included: " . ($hasCsrfPath ? 'YES' : 'NO') . "\n";
    
    // Check if supports credentials is enabled
    $supportsCredentials = $corsConfig['supports_credentials'];
    echo "Supports Credentials: " . ($supportsCredentials ? 'YES' : 'NO') . "\n";
    
    if ($hasCsrfPath && $supportsCredentials) {
        echo "✅ GOOD: CORS configuration is correct for Sanctum\n\n";
    } else {
        echo "❌ ERROR: CORS configuration needs fixing\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 2: Sanctum Configuration
echo "Test 2: Sanctum Configuration\n";
echo "-----------------------------\n";

try {
    $sanctumConfig = config('sanctum');
    
    echo "Stateful Domains: " . implode(', ', $sanctumConfig['stateful']) . "\n";
    echo "Guard: " . implode(', ', $sanctumConfig['guard']) . "\n";
    echo "Expiration: " . ($sanctumConfig['expiration'] ?? 'Never') . "\n";
    echo "Token Prefix: " . ($sanctumConfig['token_prefix'] ?: 'None') . "\n";
    
    // Check if localhost domains are included
    $hasLocalhost = false;
    foreach ($sanctumConfig['stateful'] as $domain) {
        if (strpos($domain, 'localhost') !== false || strpos($domain, '127.0.0.1') !== false) {
            $hasLocalhost = true;
            break;
        }
    }
    
    echo "Localhost Domains Included: " . ($hasLocalhost ? 'YES' : 'NO') . "\n";
    
    if ($hasLocalhost) {
        echo "✅ GOOD: Sanctum stateful domains include localhost\n\n";
    } else {
        echo "❌ ERROR: Sanctum stateful domains missing localhost\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 3: Session Configuration
echo "Test 3: Session Configuration\n";
echo "-----------------------------\n";

try {
    $sessionConfig = config('session');
    
    echo "Driver: " . $sessionConfig['driver'] . "\n";
    echo "Lifetime: " . $sessionConfig['lifetime'] . " minutes\n";
    echo "Secure: " . ($sessionConfig['secure'] ? 'YES' : 'NO') . "\n";
    echo "HTTP Only: " . ($sessionConfig['http_only'] ? 'YES' : 'NO') . "\n";
    echo "Same Site: " . $sessionConfig['same_site'] . "\n";
    
    // Check if session settings are secure
    $isSecure = $sessionConfig['http_only'] && 
                in_array($sessionConfig['same_site'], ['lax', 'strict']);
    
    echo "Session Security: " . ($isSecure ? 'SECURE' : 'INSECURE') . "\n";
    
    if ($isSecure) {
        echo "✅ GOOD: Session configuration is secure\n\n";
    } else {
        echo "❌ ERROR: Session configuration needs security improvements\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 4: CSRF Token Endpoint
echo "Test 4: CSRF Token Endpoint\n";
echo "---------------------------\n";

try {
    // Test if CSRF cookie endpoint exists
    $csrfRoute = route('sanctum.csrf-cookie');
    echo "CSRF Cookie Route: " . $csrfRoute . "\n";
    
    // Test if route is accessible
    $response = \Illuminate\Support\Facades\Http::get($csrfRoute);
    $statusCode = $response->status();
    
    echo "CSRF Endpoint Status: " . $statusCode . "\n";
    
    if ($statusCode === 204) {
        echo "✅ GOOD: CSRF cookie endpoint is working\n\n";
    } else {
        echo "❌ ERROR: CSRF cookie endpoint returned status " . $statusCode . "\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 5: API Authentication Flow
echo "Test 5: API Authentication Flow\n";
echo "--------------------------------\n";

try {
    // Test if API routes require authentication
    $apiRoutes = \Illuminate\Support\Facades\Route::getRoutes();
    $apiRoutesWithAuth = 0;
    $totalApiRoutes = 0;
    
    foreach ($apiRoutes as $route) {
        if (strpos($route->uri(), 'api/v1/') === 0) {
            $totalApiRoutes++;
            $middleware = $route->gatherMiddleware();
            if (in_array('auth:sanctum', $middleware)) {
                $apiRoutesWithAuth++;
            }
        }
    }
    
    echo "Total API v1 Routes: " . $totalApiRoutes . "\n";
    echo "Routes with auth:sanctum: " . $apiRoutesWithAuth . "\n";
    
    $authPercentage = $totalApiRoutes > 0 ? ($apiRoutesWithAuth / $totalApiRoutes) * 100 : 0;
    echo "Authentication Coverage: " . number_format($authPercentage, 1) . "%\n";
    
    if ($authPercentage >= 80) {
        echo "✅ GOOD: Most API routes are protected\n\n";
    } else {
        echo "❌ ERROR: Many API routes are not protected\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

echo "🏁 CSRF/CORS/Session Testing Complete\n";
echo "=====================================\n";
echo "Summary:\n";
echo "- CORS should include 'sanctum/csrf-cookie' path\n";
echo "- CORS should have 'supports_credentials' = true\n";
echo "- Sanctum should include localhost domains\n";
echo "- Session should be secure (http_only, same_site)\n";
echo "- CSRF cookie endpoint should return 204\n";
echo "- API routes should be protected with auth:sanctum\n";
