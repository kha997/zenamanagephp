<?php
/**
 * Comprehensive API Endpoints Test Script
 * Tests all major API endpoints for functionality and error handling
 */

// Configuration
$baseUrl = 'http://localhost:8000/api/v1';
$testResults = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Test data
$testUser = [
    'name' => 'Test User API',
    'email' => 'testuser@api.com',
    'password' => 'TestPassword123!',
    'password_confirmation' => 'TestPassword123!',
    'company_name' => 'Test Company API',
    'company_domain' => 'testcompany-api.com',
    'company_phone' => '+1234567890',
    'company_address' => '123 Test Street, Test City'
];

$testProject = [
    'name' => 'Test Project API',
    'description' => 'Test project for API testing',
    'start_date' => '2025-01-01',
    'end_date' => '2025-12-31',
    'status' => 'planning'
];

$testComponent = [
    'name' => 'Test Component API',
    'planned_cost' => 10000.00,
    'actual_cost' => 0.00,
    'progress_percent' => 0.0
];

$testTask = [
    'name' => 'Test Task API',
    'description' => 'Test task for API testing',
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-31',
    'status' => 'pending',
    'priority' => 'medium',
    'estimated_hours' => 40.0
];

$testAssignment = [
    'user_id' => '',
    'split_percent' => 100.0,
    'role' => 'assignee'
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
echo "🚀 Starting Comprehensive API Endpoints Test\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// 1. Health Check
runTest('Health Check', "$baseUrl/health", 'GET', null, [], 200);

// 2. User Registration
echo "📝 Testing User Registration...\n";
$registerResult = runTest('User Registration', "$baseUrl/auth/register", 'POST', $testUser, [], 201);
$userId = null;
if ($registerResult['success'] && isset($registerResult['response']['data']['user']['id'])) {
    $userId = $registerResult['response']['data']['user']['id'];
    echo "✅ User created with ID: $userId\n\n";
}

// 3. User Login
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
    echo "❌ Login failed, cannot continue with authenticated tests\n\n";
    exit(1);
}

$authHeaders = ['Authorization: Bearer ' . $token];

// 4. User Profile
runTest('Get User Profile', "$baseUrl/users/profile", 'GET', null, $authHeaders, 200);

// 5. User Management (Simple)
runTest('List Users (Simple)', "$baseUrl/simple/users", 'GET', null, $authHeaders, 200);
runTest('Get User by ID (Simple)', "$baseUrl/simple/users/$userId", 'GET', null, $authHeaders, 200);

// 6. User Management (V2)
runTest('List Users (V2)', "$baseUrl/users-v2", 'GET', null, $authHeaders, 200);
runTest('Get User by ID (V2)', "$baseUrl/users-v2/$userId", 'GET', null, $authHeaders, 200);

// 7. Project Management
echo "🏗️ Testing Project Management...\n";
$projectResult = runTest('Create Project', "$baseUrl/projects", 'POST', $testProject, $authHeaders, 201);
$projectId = null;
if ($projectResult['success'] && isset($projectResult['response']['data']['project']['id'])) {
    $projectId = $projectResult['response']['data']['project']['id'];
    echo "✅ Project created with ID: $projectId\n";
}

if ($projectId) {
    runTest('List Projects', "$baseUrl/projects", 'GET', null, $authHeaders, 200);
    runTest('Get Project by ID', "$baseUrl/projects/$projectId", 'GET', null, $authHeaders, 200);
    
    // Update project
    $updateProject = array_merge($testProject, ['name' => 'Updated Test Project API']);
    runTest('Update Project', "$baseUrl/projects/$projectId", 'PUT', $updateProject, $authHeaders, 200);
}

// 8. Component Management
echo "🧩 Testing Component Management...\n";
if ($projectId) {
    $testComponent['project_id'] = $projectId;
    $componentResult = runTest('Create Component', "$baseUrl/projects/$projectId/components", 'POST', $testComponent, $authHeaders, 201);
    $componentId = null;
    if ($componentResult['success'] && isset($componentResult['response']['data']['component']['id'])) {
        $componentId = $componentResult['response']['data']['component']['id'];
        echo "✅ Component created with ID: $componentId\n";
    }
    
    if ($componentId) {
        runTest('List Components', "$baseUrl/projects/$projectId/components", 'GET', null, $authHeaders, 200);
        runTest('Get Component by ID', "$baseUrl/components/$componentId", 'GET', null, $authHeaders, 200);
        runTest('Get Component Tree', "$baseUrl/projects/$projectId/components/tree", 'GET', null, $authHeaders, 200);
        
        // Update component
        $updateComponent = array_merge($testComponent, ['name' => 'Updated Test Component API']);
        runTest('Update Component', "$baseUrl/components/$componentId", 'PUT', $updateComponent, $authHeaders, 200);
    }
}

// 9. Task Management
echo "📋 Testing Task Management...\n";
if ($projectId) {
    $testTask['project_id'] = $projectId;
    if (isset($componentId)) {
        $testTask['component_id'] = $componentId;
    }
    
    $taskResult = runTest('Create Task', "$baseUrl/projects/$projectId/tasks", 'POST', $testTask, $authHeaders, 201);
    $taskId = null;
    if ($taskResult['success'] && isset($taskResult['response']['data']['task']['id'])) {
        $taskId = $taskResult['response']['data']['task']['id'];
        echo "✅ Task created with ID: $taskId\n";
    }
    
    if ($taskId) {
        runTest('List Tasks', "$baseUrl/projects/$projectId/tasks", 'GET', null, $authHeaders, 200);
        runTest('Get Task by ID', "$baseUrl/tasks/$taskId", 'GET', null, $authHeaders, 200);
        
        // Update task
        $updateTask = array_merge($testTask, ['name' => 'Updated Test Task API']);
        runTest('Update Task', "$baseUrl/tasks/$taskId", 'PUT', $updateTask, $authHeaders, 200);
        
        // Update task status
        runTest('Update Task Status', "$baseUrl/tasks/$taskId/status", 'PATCH', ['status' => 'in_progress'], $authHeaders, 200);
    }
}

// 10. Task Assignment Management
echo "👥 Testing Task Assignment Management...\n";
if ($taskId && $userId) {
    $testAssignment['user_id'] = $userId;
    
    $assignmentResult = runTest('Create Task Assignment', "$baseUrl/tasks/$taskId/assignments", 'POST', $testAssignment, $authHeaders, 201);
    $assignmentId = null;
    if ($assignmentResult['success'] && isset($assignmentResult['response']['data']['assignment']['id'])) {
        $assignmentId = $assignmentResult['response']['data']['assignment']['id'];
        echo "✅ Task Assignment created with ID: $assignmentId\n";
    }
    
    if ($assignmentId) {
        runTest('List Task Assignments', "$baseUrl/tasks/$taskId/assignments", 'GET', null, $authHeaders, 200);
        runTest('Get User Assignments', "$baseUrl/users/$userId/assignments", 'GET', null, $authHeaders, 200);
        runTest('Get User Assignment Stats', "$baseUrl/users/$userId/assignments/stats", 'GET', null, $authHeaders, 200);
        
        // Update assignment
        $updateAssignment = array_merge($testAssignment, ['split_percent' => 80.0]);
        runTest('Update Task Assignment', "$baseUrl/assignments/$assignmentId", 'PUT', $updateAssignment, $authHeaders, 200);
    }
}

// 11. Error Handling Tests
echo "🚨 Testing Error Handling...\n";
runTest('Get Non-existent Project', "$baseUrl/projects/non-existent-id", 'GET', null, $authHeaders, 404);
runTest('Get Non-existent Task', "$baseUrl/tasks/non-existent-id", 'GET', null, $authHeaders, 404);
runTest('Create Project without Auth', "$baseUrl/projects", 'POST', $testProject, [], 401);

// 12. Cleanup (Optional)
echo "🧹 Testing Cleanup...\n";
if (isset($assignmentId)) {
    runTest('Delete Task Assignment', "$baseUrl/assignments/$assignmentId", 'DELETE', null, $authHeaders, 200);
}
if (isset($taskId)) {
    runTest('Delete Task', "$baseUrl/tasks/$taskId", 'DELETE', null, $authHeaders, 200);
}
if (isset($componentId)) {
    runTest('Delete Component', "$baseUrl/components/$componentId", 'DELETE', null, $authHeaders, 200);
}
if (isset($projectId)) {
    runTest('Delete Project', "$baseUrl/projects/$projectId", 'DELETE', null, $authHeaders, 200);
}

// Test Summary
echo "=" . str_repeat("=", 50) . "\n";
echo "📊 TEST SUMMARY\n";
echo "=" . str_repeat("=", 50) . "\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests ✅\n";
echo "Failed: $failedTests ❌\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";

if ($failedTests > 0) {
    echo "❌ FAILED TESTS:\n";
    echo "-" . str_repeat("-", 30) . "\n";
    foreach ($testResults as $result) {
        if (!$result['success']) {
            echo "• {$result['name']} (Status: {$result['actual_status']})\n";
        }
    }
    echo "\n";
}

// Detailed Results
echo "📋 DETAILED RESULTS:\n";
echo "-" . str_repeat("-", 30) . "\n";
foreach ($testResults as $result) {
    $status = $result['success'] ? '✅' : '❌';
    echo "$status {$result['name']} - {$result['method']} {$result['url']} (Status: {$result['actual_status']})\n";
}

echo "\n🎉 API Testing Complete!\n";
?>
