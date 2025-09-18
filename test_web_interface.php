<?php
/**
 * Test Web Interface cho User Management
 * Demo cách sử dụng web interface
 */

echo "=== WEB INTERFACE TEST ===\n\n";

echo "1. Mở web interface:\n";
echo "   URL: http://localhost:8000/user-management-test.html\n\n";

echo "2. Test tạo user mới:\n";
echo "   - Name: user3\n";
echo "   - Email: user3@zena.com\n";
echo "   - Password: Renzopi1123\n";
echo "   - Confirm Password: Renzopi1123\n";
echo "   - Tenant ID: 01k4vjtwfzsg7ypbp4pme22vep\n";
echo "   - Status: Active\n\n";

echo "3. Test API trực tiếp:\n";
$testData = [
    'name' => 'user3',
    'email' => 'user3@zena.com',
    'password' => 'Renzopi1123',
    'password_confirmation' => 'Renzopi1123',
    'tenant_id' => '01k4vjtwfzsg7ypbp4pme22vep'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/v1/simple/users');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
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
    echo "✅ User created successfully!\n";
    echo "   - ID: {$data['data']['user']['id']}\n";
    echo "   - Name: {$data['data']['user']['name']}\n";
    echo "   - Email: {$data['data']['user']['email']}\n";
    echo "   - Tenant: {$data['data']['user']['tenant']['name']}\n\n";
} else {
    echo "❌ User creation failed! HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
}

echo "4. Test lấy danh sách users:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/v1/simple/users');
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
    echo "✅ Found " . count($users) . " users:\n";
    foreach ($users as $user) {
        echo "   - {$user['name']} ({$user['email']}) - {$user['tenant']['name']}\n";
    }
} else {
    echo "❌ Get users failed! HTTP Code: $httpCode\n";
}

echo "\n=== WEB INTERFACE TEST COMPLETED ===\n";
echo "Bạn có thể sử dụng web interface tại: http://localhost:8000/user-management-test.html\n";
echo "Web interface đã được cập nhật để hiển thị lỗi validation chi tiết!\n";
