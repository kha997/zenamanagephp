<?php
/**
 * Simple End-to-End Integration Test
 */

echo "🧪 SIMPLE E2E INTEGRATION TEST\n";
echo "===================================================\n\n";

$frontendUrl = 'http://localhost:3001';
$backendUrl = 'http://localhost:8000/api/v1';
$totalTests = 0;
$passedTests = 0;

function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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

function runTest($name, $url, $expectedStatus = 200) {
    global $totalTests, $passedTests;
    
    $totalTests++;
    echo "🧪 Testing: $name\n";
    
    $response = makeRequest($url);
    
    if ($response['status'] === $expectedStatus) {
        echo "✅ PASSED: $name (Status: {$response['status']})\n";
        $passedTests++;
    } else {
        echo "❌ FAILED: $name (Expected: $expectedStatus, Got: {$response['status']})\n";
        if ($response['error']) {
            echo "   Error: {$response['error']}\n";
        }
    }
    echo "\n";
}

// Test 1: Frontend Homepage
runTest('Frontend Homepage', $frontendUrl);

// Test 2: Backend Health Check
runTest('Backend Health Check', "$backendUrl/health");

// Test 3: User Login
$loginData = [
    'email' => 'testuser@api.com',
    'password' => 'TestPassword123!'
];

echo "🧪 Testing: User Login\n";
$loginResponse = makeRequest("$backendUrl/auth/login", 'POST', $loginData);
if ($loginResponse['status'] === 200) {
    echo "✅ PASSED: User Login (Status: {$loginResponse['status']})\n";
    $totalTests++;
    $passedTests++;
    
    // Test 4: Get User Profile with token
    $data = json_decode($loginResponse['body'], true);
    $token = $data['data']['token'] ?? null;
    
    if ($token) {
        echo "\n🧪 Testing: Get User Profile\n";
        $profileResponse = makeRequest("$backendUrl/users/profile", 'GET', null, ["Authorization: Bearer $token"]);
        if ($profileResponse['status'] === 200) {
            echo "✅ PASSED: Get User Profile (Status: {$profileResponse['status']})\n";
            $totalTests++;
            $passedTests++;
        } else {
            echo "❌ FAILED: Get User Profile (Status: {$profileResponse['status']})\n";
            $totalTests++;
        }
    }
} else {
    echo "❌ FAILED: User Login (Status: {$loginResponse['status']})\n";
    $totalTests++;
}
echo "\n";

// Test 5: List Users (Simple)
runTest('List Users (Simple)', "$backendUrl/simple/users");

// Test 6: Frontend-Backend Integration
runTest('Frontend-Backend Integration', "$frontendUrl/api/v1/health");

echo "===================================================\n";
echo "📊 E2E TEST SUMMARY\n";
echo "===================================================\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests ✅\n";
echo "Failed: " . ($totalTests - $passedTests) . " ❌\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";

if ($passedTests === $totalTests) {
    echo "🎉 ALL TESTS PASSED! System is working correctly!\n";
} else {
    echo "⚠️ Some tests failed. Check the details above.\n";
}

echo "\n🎉 End-to-End Integration Testing Complete!\n";
?>
