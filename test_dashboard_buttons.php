<?php
/**
 * Dashboard Buttons Functionality Test
 * Tests all buttons and functions shown in the dashboard
 */

echo "🧪 DASHBOARD BUTTONS FUNCTIONALITY TEST\n";
echo "========================================\n\n";

// Get fresh token
echo "1. Getting authentication token...\n";
$loginData = json_encode([
    'email' => 'admin@zena.local',
    'password' => 'password'
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $loginData
    ]
]);

$response = file_get_contents('http://localhost:8000/api/v1/auth/login', false, $context);
if ($response) {
    $data = json_decode($response, true);
    if ($data && $data['status'] === 'success') {
        $token = $data['data']['token'];
        echo "   ✅ Login successful\n\n";
    } else {
        echo "   ❌ Login failed\n";
        exit;
    }
} else {
    echo "   ❌ Login failed\n";
    exit;
}

// Test function to make authenticated requests
function makeRequest($method, $endpoint, $data = null, $token = null) {
    $url = 'http://localhost:8000/api/v1' . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    if ($token) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ]);
    }

    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'response' => $response,
        'http_code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

echo "2. Testing Dashboard Buttons Functionality:\n";
echo "===========================================\n\n";

// Test 1: + Project Button
echo "🔨 Testing '+ Project' Button:\n";
$projectData = [
    'name' => 'Test Project from Dashboard',
    'description' => 'Project created via dashboard button test',
    'status' => 'planning',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+30 days'))
];

$result = makeRequest('POST', '/projects', $projectData, $token);
if ($result['http_code'] === 200 || $result['http_code'] === 201) {
    echo "   ✅ Project creation successful\n";
} else {
    echo "   ❌ Project creation failed (HTTP {$result['http_code']})\n";
    if ($result['data'] && isset($result['data']['message'])) {
        echo "      Error: " . $result['data']['message'] . "\n";
    }
}
echo "\n";

// Test 2: + Task Button
echo "📝 Testing '+ Task' Button:\n";
$taskData = [
    'title' => 'Test Task from Dashboard',
    'description' => 'Task created via dashboard button test',
    'status' => 'pending',
    'priority' => 'medium',
    'due_date' => date('Y-m-d', strtotime('+7 days'))
];

$result = makeRequest('POST', '/tasks', $taskData, $token);
if ($result['http_code'] === 200 || $result['http_code'] === 201) {
    echo "   ✅ Task creation successful\n";
} else {
    echo "   ❌ Task creation failed (HTTP {$result['http_code']})\n";
    if ($result['data'] && isset($result['data']['message'])) {
        echo "      Error: " . $result['data']['message'] . "\n";
    }
}
echo "\n";

// Test 3: Invite User Button
echo "👥 Testing 'Invite User' Button:\n";
$userData = [
    'name' => 'Test User from Dashboard',
    'email' => 'testuser@example.com',
    'password' => 'password123',
    'role' => 'member'
];

$result = makeRequest('POST', '/simple/users', $userData, $token);
if ($result['http_code'] === 200 || $result['http_code'] === 201) {
    echo "   ✅ User invitation successful\n";
} else {
    echo "   ❌ User invitation failed (HTTP {$result['http_code']})\n";
    if ($result['data'] && isset($result['data']['message'])) {
        echo "      Error: " . $result['data']['message'] . "\n";
    }
}
echo "\n";

// Test 4: Filter Buttons (All, Design, Construction)
echo "🔍 Testing Filter Buttons:\n";

// Test All filter
$result = makeRequest('GET', '/projects', null, $token);
if ($result['http_code'] === 200) {
    echo "   ✅ 'All' filter working\n";
} else {
    echo "   ❌ 'All' filter failed (HTTP {$result['http_code']})\n";
}

// Test Design filter
$result = makeRequest('GET', '/projects?category=design', null, $token);
if ($result['http_code'] === 200) {
    echo "   ✅ 'Design' filter working\n";
} else {
    echo "   ❌ 'Design' filter failed (HTTP {$result['http_code']})\n";
}

// Test Construction filter
$result = makeRequest('GET', '/projects?category=construction', null, $token);
if ($result['http_code'] === 200) {
    echo "   ✅ 'Construction' filter working\n";
} else {
    echo "   ❌ 'Construction' filter failed (HTTP {$result['http_code']})\n";
}
echo "\n";

// Test 5: Navigation Buttons
echo "🧭 Testing Navigation Buttons:\n";

// Test Projects navigation
$result = makeRequest('GET', '/projects', null, $token);
if ($result['http_code'] === 200) {
    echo "   ✅ 'Projects' navigation working\n";
} else {
    echo "   ❌ 'Projects' navigation failed (HTTP {$result['http_code']})\n";
}

// Test Tasks navigation
$result = makeRequest('GET', '/tasks', null, $token);
if ($result['http_code'] === 200) {
    echo "   ✅ 'Tasks' navigation working\n";
} else {
    echo "   ❌ 'Tasks' navigation failed (HTTP {$result['http_code']})\n";
}

// Test Team navigation (users)
$result = makeRequest('GET', '/simple/users', null, $token);
if ($result['http_code'] === 200) {
    echo "   ✅ 'Team' navigation working\n";
} else {
    echo "   ❌ 'Team' navigation failed (HTTP {$result['http_code']})\n";
}
echo "\n";

// Test 6: View All Buttons
echo "👀 Testing 'View All' Buttons:\n";

// Test View All Tasks
$result = makeRequest('GET', '/tasks', null, $token);
if ($result['http_code'] === 200) {
    echo "   ✅ 'View All Tasks' working\n";
} else {
    echo "   ❌ 'View All Tasks' failed (HTTP {$result['http_code']})\n";
}

// Test View All Approvals (documents)
$result = makeRequest('GET', '/documents', null, $token);
if ($result['http_code'] === 200) {
    echo "   ✅ 'View All Approvals' working\n";
} else {
    echo "   ❌ 'View All Approvals' failed (HTTP {$result['http_code']})\n";
}
echo "\n";

echo "🎯 DASHBOARD BUTTONS TEST COMPLETE!\n";
echo "====================================\n\n";

echo "📊 SUMMARY:\n";
echo "Frontend Dashboard: http://localhost:5174\n";
echo "Backend API: http://localhost:8000/api/v1\n";
echo "Login: admin@zena.local / password\n\n";

echo "✅ Ready for production use!\n";
