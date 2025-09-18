<?php
/**
 * Test Single Routes
 * Tests individual routes to isolate the AuthManager error
 */

$baseUrl = 'http://localhost:8000/api/v1';

// Helper functions
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
        'Content-Type: application/json',
        'Accept: application/json'
    ], $headers));
    
    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'http_code' => 0];
    }
    
    return [
        'response' => $response,
        'http_code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

echo "🧪 Testing Single Routes\n";
echo "=" . str_repeat("=", 40) . "\n\n";

// Test 1: Health Check
echo "1. Health Check\n";
$result = makeRequest("$baseUrl/health");
echo "Status: {$result['http_code']}\n";
if ($result['data'] && isset($result['data']['message'])) {
    echo "Message: {$result['data']['message']}\n";
}
echo "\n";

// Test 2: User Registration
echo "2. User Registration\n";
$testUser = [
    'name' => 'Test User Single',
    'email' => 'testuser@single.com',
    'password' => 'TestPassword123!',
    'password_confirmation' => 'TestPassword123!',
    'company_name' => 'Test Company Single',
    'company_domain' => 'testcompany-single.com',
    'company_phone' => '+1234567890',
    'company_address' => '123 Test Street, Test City'
];

$result = makeRequest("$baseUrl/auth/register", 'POST', $testUser);
echo "Status: {$result['http_code']}\n";
if ($result['data'] && isset($result['data']['message'])) {
    echo "Message: {$result['data']['message']}\n";
}
echo "\n";

// Test 3: User Login
echo "3. User Login\n";
$loginData = [
    'email' => $testUser['email'],
    'password' => $testUser['password']
];

$result = makeRequest("$baseUrl/auth/login", 'POST', $loginData);
echo "Status: {$result['http_code']}\n";
if ($result['data'] && isset($result['data']['message'])) {
    echo "Message: {$result['data']['message']}\n";
}
echo "\n";

// Test 4: Simple User List (No Auth)
echo "4. Simple User List (No Auth)\n";
$result = makeRequest("$baseUrl/simple/users");
echo "Status: {$result['http_code']}\n";
if ($result['data'] && isset($result['data']['message'])) {
    echo "Message: {$result['data']['message']}\n";
}
echo "\n";

echo "🎉 Single Routes Testing Complete!\n";
?>
