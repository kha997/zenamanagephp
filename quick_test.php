<?php
/**
 * Quick API Test Script
 */

echo "🧪 QUICK API TEST\n";
echo "================\n\n";

// Test 1: Health Check
echo "1. Health Check: ";
$response = file_get_contents('http://localhost:8000/api/v1/health');
if ($response) {
    $data = json_decode($response, true);
    if ($data && $data['status'] === 'success') {
        echo "✅ PASS\n";
    } else {
        echo "❌ FAIL\n";
    }
} else {
    echo "❌ FAIL\n";
}

// Test 2: Login
echo "2. Login: ";
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
        echo "✅ PASS\n";
        $token = $data['data']['token'];
    } else {
        echo "❌ FAIL - " . ($data['data']['message'] ?? 'Unknown error') . "\n";
        $token = null;
    }
} else {
    echo "❌ FAIL\n";
    $token = null;
}

if ($token) {
    // Test 3: Users (no auth required)
    echo "3. Users List: ";
    $response = file_get_contents('http://localhost:8000/api/v1/simple/users');
    if ($response) {
        $data = json_decode($response, true);
        if ($data && $data['status'] === 'success') {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL\n";
        }
    } else {
        echo "❌ FAIL\n";
    }

    // Test 4: Projects (with auth)
    echo "4. Projects List: ";
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Bearer ' . $token
        ]
    ]);
    
    $response = file_get_contents('http://localhost:8000/api/v1/projects', false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['status'])) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL\n";
        }
    } else {
        echo "❌ FAIL\n";
    }

    // Test 5: Tasks
    echo "5. Tasks List: ";
    $response = file_get_contents('http://localhost:8000/api/v1/tasks', false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['status'])) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL\n";
        }
    } else {
        echo "❌ FAIL\n";
    }

    // Test 6: Documents
    echo "6. Documents List: ";
    $response = file_get_contents('http://localhost:8000/api/v1/documents', false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['status'])) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL\n";
        }
    } else {
        echo "❌ FAIL\n";
    }
}

echo "\n🎯 Frontend Test:\n";
echo "Frontend should be running at: http://localhost:5174\n";
echo "Dashboard should be accessible at: http://localhost:8000/dashboard\n\n";

echo "✅ BASIC FUNCTIONALITY TEST COMPLETE!\n";
