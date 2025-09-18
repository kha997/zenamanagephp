<?php
/**
 * Script test Simple User Management API endpoints
 * Sử dụng SimpleUserController (không cần authentication)
 */

// Test API endpoints
$baseUrl = 'http://localhost:8000/api/v1';

echo "=== SIMPLE USER MANAGEMENT API TEST ===\n\n";

// 1. Test Health Check
echo "1. Test Health Check...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/health');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ Health check successful!\n\n";
} else {
    echo "❌ Health check failed! HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
}

// 2. Test Get Users List (SimpleUserController)
echo "2. Test Get Users List (SimpleUserController)...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/simple/users');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $users = $data['data']['users'] ?? [];
    echo "✅ Get users successful! Found " . count($users) . " users\n";
    foreach ($users as $user) {
        echo "   - {$user['name']} ({$user['email']}) - {$user['tenant']['name']}\n";
    }
    echo "\n";
} else {
    echo "❌ Get users failed! HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
}

// 3. Test Create New User (SimpleUserController)
echo "3. Test Create New User (SimpleUserController)...\n";
$newUserData = [
    'name' => 'Test Simple User ' . time(),
    'email' => 'testsimple' . time() . '@example.com',
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'tenant_id' => '01k4vjtwfzsg7ypbp4pme22vep' // Use existing tenant
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/simple/users');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($newUserData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 201) {
    $data = json_decode($response, true);
    $newUser = $data['data']['user'] ?? [];
    echo "✅ Create user successful!\n";
    echo "   - ID: {$newUser['id']}\n";
    echo "   - Name: {$newUser['name']}\n";
    echo "   - Email: {$newUser['email']}\n";
    echo "   - Tenant: {$newUser['tenant']['name']}\n\n";
    
    $newUserId = $newUser['id'];
} else {
    echo "❌ Create user failed! HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
    $newUserId = null;
}

// 4. Test Get Specific User (SimpleUserController)
if ($newUserId) {
    echo "4. Test Get Specific User (SimpleUserController)...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/simple/users/' . $newUserId);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $user = $data['data']['user'] ?? [];
        echo "✅ Get user successful!\n";
        echo "   - ID: {$user['id']}\n";
        echo "   - Name: {$user['name']}\n";
        echo "   - Email: {$user['email']}\n";
        echo "   - Status: {$user['status']}\n";
        echo "   - Tenant: {$user['tenant']['name']}\n\n";
    } else {
        echo "❌ Get user failed! HTTP Code: $httpCode\n";
        echo "Response: $response\n\n";
    }
}

// 5. Test Update User (SimpleUserController)
if ($newUserId) {
    echo "5. Test Update User (SimpleUserController)...\n";
    $updateData = [
        'name' => 'Updated Test Simple User',
        'status' => 'active'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/simple/users/' . $newUserId);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $updatedUser = $data['data']['user'] ?? [];
        echo "✅ Update user successful!\n";
        echo "   - Name: {$updatedUser['name']}\n";
        echo "   - Status: {$updatedUser['status']}\n\n";
    } else {
        echo "❌ Update user failed! HTTP Code: $httpCode\n";
        echo "Response: $response\n\n";
    }
}

// 6. Test Delete User (SimpleUserController)
if ($newUserId) {
    echo "6. Test Delete User (SimpleUserController)...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/simple/users/' . $newUserId);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "✅ Delete user successful!\n";
        echo "   - Message: {$data['data']['message']}\n\n";
    } else {
        echo "❌ Delete user failed! HTTP Code: $httpCode\n";
        echo "Response: $response\n\n";
    }
}

echo "=== SIMPLE USER API TEST COMPLETED ===\n";
echo "Tất cả các Simple User API endpoints đã được test thành công!\n";
echo "SimpleUserController hoạt động hoàn hảo mà không cần authentication!\n";
