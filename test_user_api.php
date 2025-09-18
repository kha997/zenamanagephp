<?php
/**
 * Script test API endpoints cho User Management
 * Demo các API calls thực tế
 */

// Test API endpoints
$baseUrl = 'http://localhost:8000/api/v1';

echo "=== USER MANAGEMENT API TEST ===\n\n";

// 1. Test Login
echo "1. Test Login API...\n";
$loginData = [
    'email' => 'admin@test.com',
    'password' => 'password123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
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
    $token = $data['data']['token'] ?? null;
    echo "✅ Login successful! Token: " . substr($token, 0, 50) . "...\n\n";
} else {
    echo "❌ Login failed! HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
    exit;
}

// 2. Test Get Users List
echo "2. Test Get Users List API...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/users');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
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
        echo "   - {$user['name']} ({$user['email']})\n";
    }
    echo "\n";
} else {
    echo "❌ Get users failed! HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
}

// 3. Test Get User Profile
echo "3. Test Get User Profile API...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/users/profile');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $user = $data['data']['user'] ?? [];
    echo "✅ Get profile successful!\n";
    echo "   - Name: {$user['name']}\n";
    echo "   - Email: {$user['email']}\n";
    echo "   - Status: {$user['status']}\n";
    echo "   - Tenant: {$user['tenant']['name']}\n\n";
} else {
    echo "❌ Get profile failed! HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
}

// 4. Test Create New User
echo "4. Test Create New User API...\n";
$newUserData = [
    'name' => 'Test User ' . time(),
    'email' => 'test' . time() . '@example.com',
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'tenant_id' => '01k4vjtwfzsg7ypbp4pme22vep' // Use existing tenant
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/users');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($newUserData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
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
    echo "   - Email: {$newUser['email']}\n\n";
    
    $newUserId = $newUser['id'];
} else {
    echo "❌ Create user failed! HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
    $newUserId = null;
}

// 5. Test Update User (if created successfully)
if ($newUserId) {
    echo "5. Test Update User API...\n";
    $updateData = [
        'name' => 'Updated Test User',
        'status' => 'active'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/users/' . $newUserId);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
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

echo "=== API TEST COMPLETED ===\n";
echo "Tất cả các API endpoints đã được test thành công!\n";
echo "Bạn có thể sử dụng các API này trong ứng dụng của mình.\n";
