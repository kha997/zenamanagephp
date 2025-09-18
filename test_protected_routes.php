<?php
/**
 * Test Protected Routes with JWT Authentication
 * Tests routes protected by auth:api middleware
 */

$baseUrl = 'http://localhost:8000/api/v1';
$testResults = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Test data
$testUser = [
    'name' => 'Test User Protected',
    'email' => 'testuser@protected.com',
    'password' => 'TestPassword123!',
    'password_confirmation' => 'TestPassword123!',
    'company_name' => 'Test Company Protected',
    'company_domain' => 'testcompany-protected.com',
    'company_phone' => '+1234567890',
    'company_address' => '123 Test Street, Test City'
];

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

function runTest($testName, $url, $method = 'GET', $data = null, $headers = [], $expectedStatus = 200) {
    global $totalTests, $passedTests, $failedTests, $testResults;
    
    $totalTests++;
    echo "🧪 Testing: $testName\n";
    
    $result = makeRequest($url, $method, $data, $headers);
    
    $testResult = [
        'name' => $testName,
        'url' => $url,
        'method' => $method,
        'expected_status' => $expectedStatus,
        'actual_status' => $result['http_code'],
        'success' => false,
        'response' => $result['data'] ?? null,
        'error' => $result['error'] ?? null
    ];
    
    if ($result['http_code'] === $expectedStatus) {
        $passedTests++;
        $testResult['success'] = true;
        echo "✅ PASSED: $testName (Status: {$result['http_code']})\n";
    } else {
        $failedTests++;
        echo "❌ FAILED: $testName (Expected: $expectedStatus, Got: {$result['http_code']})\n";
        if ($result['error']) {
            echo "   Error: {$result['error']}\n";
        }
        if ($result['data'] && isset($result['data']['message'])) {
            echo "   Message: {$result['data']['message']}\n";
        }
    }
    
    $testResults[] = $testResult;
    echo "\n";
    
    return $testResult;
}

// Start testing
echo "🔐 Testing Protected Routes with JWT Authentication\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// 1. User Registration
echo "📝 Testing User Registration...\n";
$registerResult = runTest('User Registration', "$baseUrl/auth/register", 'POST', $testUser, [], 201);
$userId = null;
if ($registerResult['success'] && isset($registerResult['response']['data']['user']['id'])) {
    $userId = $registerResult['response']['data']['user']['id'];
    echo "✅ User created with ID: $userId\n\n";
}

// 2. User Login
echo "🔐 Testing User Login...\n";
$loginData = [
    'email' => $testUser['email'],
    'password' => $testUser['password']
];
$loginResult = runTest('User Login', "$baseUrl/auth/login", 'POST', $loginData, [], 200);
$token = null;
if ($loginResult['success'] && isset($loginResult['response']['data']['token'])) {
    $token = $loginResult['response']['data']['token'];
    echo "✅ Login successful, token obtained\n\n";
} else {
    echo "❌ Login failed, cannot continue with protected route tests\n\n";
    exit(1);
}

$authHeaders = ['Authorization: Bearer ' . $token];

// 3. Test Protected User Routes
echo "👥 Testing Protected User Routes...\n";
runTest('Get User Profile', "$baseUrl/users/profile", 'GET', null, $authHeaders, 200);
runTest('List Users (Protected)', "$baseUrl/users", 'GET', null, $authHeaders, 200);
if ($userId) {
    runTest('Get User by ID (Protected)', "$baseUrl/users/$userId", 'GET', null, $authHeaders, 200);
}

// 4. Test Protected Project Routes
echo "🏗️ Testing Protected Project Routes...\n";
$testProject = [
    'name' => 'Test Project Protected',
    'description' => 'Test project for protected API testing',
    'start_date' => '2025-01-01',
    'end_date' => '2025-12-31',
    'status' => 'planning'
];

$projectResult = runTest('Create Project (Protected)', "$baseUrl/projects", 'POST', $testProject, $authHeaders, 201);
$projectId = null;
if ($projectResult['success'] && isset($projectResult['response']['data']['project']['id'])) {
    $projectId = $projectResult['response']['data']['project']['id'];
    echo "✅ Project created with ID: $projectId\n";
}

if ($projectId) {
    runTest('List Projects (Protected)', "$baseUrl/projects", 'GET', null, $authHeaders, 200);
    runTest('Get Project by ID (Protected)', "$baseUrl/projects/$projectId", 'GET', null, $authHeaders, 200);
    
    // Update project
    $updateProject = array_merge($testProject, ['name' => 'Updated Test Project Protected']);
    runTest('Update Project (Protected)', "$baseUrl/projects/$projectId", 'PUT', $updateProject, $authHeaders, 200);
}

// 5. Test Protected Component Routes
echo "🧩 Testing Protected Component Routes...\n";
if ($projectId) {
    $testComponent = [
        'name' => 'Test Component Protected',
        'planned_cost' => 10000.00,
        'actual_cost' => 0.00,
        'progress_percent' => 0.0,
        'project_id' => $projectId
    ];
    
    $componentResult = runTest('Create Component (Protected)', "$baseUrl/components", 'POST', $testComponent, $authHeaders, 201);
    $componentId = null;
    if ($componentResult['success'] && isset($componentResult['response']['data']['component']['id'])) {
        $componentId = $componentResult['response']['data']['component']['id'];
        echo "✅ Component created with ID: $componentId\n";
    }
    
    if ($componentId) {
        runTest('List Components (Protected)', "$baseUrl/components", 'GET', null, $authHeaders, 200);
        runTest('Get Component by ID (Protected)', "$baseUrl/components/$componentId", 'GET', null, $authHeaders, 200);
    }
}

// 6. Test Protected Task Routes
echo "📋 Testing Protected Task Routes...\n";
if ($projectId) {
    $testTask = [
        'name' => 'Test Task Protected',
        'description' => 'Test task for protected API testing',
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-31',
        'status' => 'pending',
        'priority' => 'medium',
        'estimated_hours' => 40.0,
        'project_id' => $projectId
    ];
    
    if (isset($componentId)) {
        $testTask['component_id'] = $componentId;
    }
    
    $taskResult = runTest('Create Task (Protected)', "$baseUrl/tasks", 'POST', $testTask, $authHeaders, 201);
    $taskId = null;
    if ($taskResult['success'] && isset($taskResult['response']['data']['task']['id'])) {
        $taskId = $taskResult['response']['data']['task']['id'];
        echo "✅ Task created with ID: $taskId\n";
    }
    
    if ($taskId) {
        runTest('List Tasks (Protected)', "$baseUrl/tasks", 'GET', null, $authHeaders, 200);
        runTest('Get Task by ID (Protected)', "$baseUrl/tasks/$taskId", 'GET', null, $authHeaders, 200);
    }
}

// 7. Test Authentication Errors
echo "🚨 Testing Authentication Errors...\n";
runTest('Access Protected Route without Token', "$baseUrl/users/profile", 'GET', null, [], 401);
runTest('Access Protected Route with Invalid Token', "$baseUrl/users/profile", 'GET', null, ['Authorization: Bearer invalid-token'], 401);

// Test Summary
echo "=" . str_repeat("=", 60) . "\n";
echo "📊 TEST SUMMARY\n";
echo "=" . str_repeat("=", 60) . "\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests ✅\n";
echo "Failed: $failedTests ❌\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";

if ($failedTests > 0) {
    echo "❌ FAILED TESTS:\n";
    echo "-" . str_repeat("-", 40) . "\n";
    foreach ($testResults as $result) {
        if (!$result['success']) {
            echo "• {$result['name']} (Status: {$result['actual_status']})\n";
        }
    }
    echo "\n";
}

// Detailed Results
echo "📋 DETAILED RESULTS:\n";
echo "-" . str_repeat("-", 40) . "\n";
foreach ($testResults as $result) {
    $status = $result['success'] ? '✅' : '❌';
    echo "$status {$result['name']} - {$result['method']} {$result['url']} (Status: {$result['actual_status']})\n";
}

echo "\n🎉 Protected Routes Testing Complete!\n";
?>
