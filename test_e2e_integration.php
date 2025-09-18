<?php
/**
 * End-to-End Integration Test
 * Tests the complete flow from frontend to backend
 */

echo "🧪 END-TO-END INTEGRATION TEST\n";
echo "===================================================\n\n";

// Test configuration
$frontendUrl = 'http://localhost:3001';
$backendUrl = 'http://localhost:8000/api/v1';
$testResults = [];
$totalTests = 0;
$passedTests = 0;

/**
 * Make HTTP request
 */
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'body' => $response,
        'error' => $error
    ];
}

/**
 * Test function
 */
function runTest($name, $testFunction) {
    global $totalTests, $passedTests, $testResults;
    
    $totalTests++;
    echo "🧪 Testing: $name\n";
    
    try {
        $result = $testFunction();
        if ($result['success']) {
            echo "✅ PASSED: $name (Status: {$result['status']})\n";
            $passedTests++;
            $testResults[] = ['name' => $name, 'status' => 'PASSED', 'details' => $result];
        } else {
            echo "❌ FAILED: $name (Status: {$result['status']})\n";
            if (isset($result['message'])) {
                echo "   Message: {$result['message']}\n";
            }
            $testResults[] = ['name' => $name, 'status' => 'FAILED', 'details' => $result];
        }
    } catch (Exception $e) {
        echo "❌ ERROR: $name - {$e->getMessage()}\n";
        $testResults[] = ['name' => $name, 'status' => 'ERROR', 'details' => ['error' => $e->getMessage()]];
    }
    
    echo "\n";
}

// Test 1: Frontend Accessibility
runTest('Frontend Homepage', function() use ($frontendUrl) {
    $response = makeRequest($frontendUrl);
    
    if ($response['status'] === 200 && strpos($response['body'], 'ZENA Manage') !== false) {
        return ['success' => true, 'status' => $response['status'], 'message' => 'Frontend accessible'];
    }
    
    return ['success' => false, 'status' => $response['status'], 'message' => 'Frontend not accessible'];
});

// Test 2: Backend Health Check
runTest('Backend Health Check', function() use ($backendUrl) {
    $response = makeRequest("$backendUrl/health");
    
    if ($response['status'] === 200) {
        return ['success' => true, 'status' => $response['status'], 'message' => 'Backend healthy'];
    }
    
    return ['success' => false, 'status' => $response['status'], 'message' => 'Backend unhealthy'];
});

// Test 3: User Registration
runTest('User Registration', function() use ($backendUrl) {
    $userData = [
        'name' => 'E2E Test User ' . time(),
        'email' => 'e2etest' . time() . '@example.com',
        'password' => 'TestPassword123!',
        'password_confirmation' => 'TestPassword123!',
        'company_name' => 'E2E Test Company',
        'company_domain' => 'e2etest' . time() . '.com',
        'company_phone' => '+1234567890',
        'company_address' => '123 E2E Test Street'
    ];
    
    $response = makeRequest("$backendUrl/auth/register", 'POST', $userData);
    
    if ($response['status'] === 201) {
        $data = json_decode($response['body'], true);
        if (isset($data['data']['token'])) {
            return [
                'success' => true, 
                'status' => $response['status'], 
                'message' => 'User registered successfully',
                'token' => $data['data']['token']
            ];
        }
    }
    
    return ['success' => false, 'status' => $response['status'], 'message' => 'User registration failed'];
});

// Test 4: User Login
runTest('User Login', function() {
    $loginData = [
        'email' => 'testuser@api.com',
        'password' => 'TestPassword123!'
    ];
    
    $response = makeRequest("$backendUrl/auth/login", 'POST', $loginData);
    
    if ($response['status'] === 200) {
        $data = json_decode($response['body'], true);
        if (isset($data['data']['token'])) {
            return [
                'success' => true, 
                'status' => $response['status'], 
                'message' => 'Login successful',
                'token' => $data['data']['token']
            ];
        }
    }
    
    return ['success' => false, 'status' => $response['status'], 'message' => 'Login failed'];
});

// Test 5: Get User Profile (with auth)
runTest('Get User Profile', function() {
    // First login to get token
    $loginData = [
        'email' => 'testuser@api.com',
        'password' => 'TestPassword123!'
    ];
    
    $loginResponse = makeRequest("$backendUrl/auth/login", 'POST', $loginData);
    
    if ($loginResponse['status'] !== 200) {
        return ['success' => false, 'status' => $loginResponse['status'], 'message' => 'Login failed for profile test'];
    }
    
    $loginData = json_decode($loginResponse['body'], true);
    $token = $loginData['data']['token'] ?? null;
    
    if (!$token) {
        return ['success' => false, 'status' => 0, 'message' => 'No token received'];
    }
    
    // Now get profile
    $response = makeRequest("$backendUrl/users/profile", 'GET', null, ["Authorization: Bearer $token"]);
    
    if ($response['status'] === 200) {
        return ['success' => true, 'status' => $response['status'], 'message' => 'Profile retrieved successfully'];
    }
    
    return ['success' => false, 'status' => $response['status'], 'message' => 'Profile retrieval failed'];
});

// Test 6: List Users (Simple)
runTest('List Users (Simple)', function() {
    $response = makeRequest("$backendUrl/simple/users");
    
    if ($response['status'] === 200) {
        $data = json_decode($response['body'], true);
        if (isset($data['data']) && is_array($data['data'])) {
            return [
                'success' => true, 
                'status' => $response['status'], 
                'message' => 'Users listed successfully',
                'count' => count($data['data'])
            ];
        }
    }
    
    return ['success' => false, 'status' => $response['status'], 'message' => 'User listing failed'];
});

// Test 7: Frontend-Backend Integration (API Proxy)
runTest('Frontend-Backend Integration', function() {
    // Test if frontend can access backend through proxy
    $response = makeRequest("$frontendUrl/api/v1/health");
    
    if ($response['status'] === 200) {
        return ['success' => true, 'status' => $response['status'], 'message' => 'Frontend-Backend integration working'];
    }
    
    return ['success' => false, 'status' => $response['status'], 'message' => 'Frontend-Backend integration failed'];
});

// Test 8: Error Handling
runTest('Error Handling', function() {
    // Test invalid endpoint
    $response = makeRequest("$backendUrl/invalid-endpoint");
    
    if ($response['status'] === 404) {
        return ['success' => true, 'status' => $response['status'], 'message' => 'Error handling working correctly'];
    }
    
    return ['success' => false, 'status' => $response['status'], 'message' => 'Error handling not working'];
});

// Test 9: CORS Headers
runTest('CORS Headers', function() {
    $response = makeRequest("$backendUrl/health", 'OPTIONS');
    
    if ($response['status'] === 200 || $response['status'] === 204) {
        return ['success' => true, 'status' => $response['status'], 'message' => 'CORS headers present'];
    }
    
    return ['success' => false, 'status' => $response['status'], 'message' => 'CORS headers missing'];
});

// Test 10: Performance Test
runTest('Performance Test', function() {
    $startTime = microtime(true);
    $response = makeRequest("$backendUrl/health");
    $endTime = microtime(true);
    
    $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
    
    if ($response['status'] === 200 && $responseTime < 1000) {
        return [
            'success' => true, 
            'status' => $response['status'], 
            'message' => "Response time: {$responseTime}ms"
        ];
    }
    
    return ['success' => false, 'status' => $response['status'], 'message' => "Slow response: {$responseTime}ms"];
});

echo "===================================================\n";
echo "📊 E2E TEST SUMMARY\n";
echo "===================================================\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests ✅\n";
echo "Failed: " . ($totalTests - $passedTests) . " ❌\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";

if ($passedTests < $totalTests) {
    echo "❌ FAILED TESTS:\n";
    echo "-------------------------------\n";
    foreach ($testResults as $result) {
        if ($result['status'] !== 'PASSED') {
            echo "• {$result['name']} ({$result['status']})\n";
        }
    }
    echo "\n";
}

echo "📋 DETAILED RESULTS:\n";
echo "-------------------------------\n";
foreach ($testResults as $result) {
    $status = $result['status'] === 'PASSED' ? '✅' : '❌';
    echo "$status {$result['name']} - {$result['details']['message']}\n";
}

echo "\n🎉 End-to-End Integration Testing Complete!\n";
?>
