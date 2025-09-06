<?php declare(strict_types=1);

/**
 * Script test API endpoints của Z.E.N.A backend
 * Kiểm tra kết nối và các endpoint cơ bản
 */

// Cấu hình API base URL
$baseUrl = 'http://localhost:8000/api/v1';

/**
 * Hàm helper để gửi HTTP request
 */
function makeRequest(string $method, string $url, array $data = [], array $headers = []): array {
    $ch = curl_init();
    
    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $allHeaders,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'success' => empty($error),
        'http_code' => $httpCode,
        'response' => $response ? json_decode($response, true) : null,
        'error' => $error
    ];
}

/**
 * Test 1: Kiểm tra API Health Check
 */
echo "\n=== TEST 1: API Health Check ===\n";
$result = makeRequest('GET', $baseUrl . '/test');

if ($result['success']) {
    echo "✅ Kết nối API thành công\n";
    echo "HTTP Code: {$result['http_code']}\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ Lỗi kết nối API: {$result['error']}\n";
    exit(1);
}

/**
 * Test 2: Kiểm tra API Info
 */
echo "\n=== TEST 2: API Info ===\n";
$result = makeRequest('GET', $baseUrl . '/info');

if ($result['success'] && $result['http_code'] === 200) {
    echo "✅ API Info endpoint hoạt động\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ API Info endpoint lỗi\n";
    echo "HTTP Code: {$result['http_code']}\n";
}

/**
 * Test 3: Test Authentication - Register
 */
echo "\n=== TEST 3: User Registration ===\n";
$testUser = [
    'name' => 'Test User API',
    'email' => 'testapi@example.com',
    'password' => 'password123',
    'password_confirmation' => 'password123'
];

$result = makeRequest('POST', $baseUrl . '/auth/register', $testUser);

if ($result['success']) {
    echo "✅ Registration endpoint hoạt động\n";
    echo "HTTP Code: {$result['http_code']}\n";
    
    if ($result['http_code'] === 201 && isset($result['response']['data']['access_token'])) {
        echo "✅ Registration thành công, nhận được JWT token\n";
        $accessToken = $result['response']['data']['access_token'];
        echo "Token: " . substr($accessToken, 0, 50) . "...\n";
    } else {
        echo "⚠️ Registration response không như mong đợi\n";
        echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ Registration endpoint lỗi: {$result['error']}\n";
}

/**
 * Test 4: Test Authentication - Login
 */
echo "\n=== TEST 4: User Login ===\n";
$loginData = [
    'email' => 'admin@example.com', // User từ seeder
    'password' => 'password'
];

$result = makeRequest('POST', $baseUrl . '/auth/login', $loginData);

if ($result['success']) {
    echo "✅ Login endpoint hoạt động\n";
    echo "HTTP Code: {$result['http_code']}\n";
    
    if ($result['http_code'] === 200 && isset($result['response']['data']['access_token'])) {
        echo "✅ Login thành công, nhận được JWT token\n";
        $accessToken = $result['response']['data']['access_token'];
        echo "Token: " . substr($accessToken, 0, 50) . "...\n";
        
        // Lưu token để test các endpoint được bảo vệ
        $authToken = $accessToken;
    } else {
        echo "⚠️ Login response không như mong đợi\n";
        echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ Login endpoint lỗi: {$result['error']}\n";
}

/**
 * Test 5: Test Protected Endpoint - Get User Profile
 */
if (isset($authToken)) {
    echo "\n=== TEST 5: Protected Endpoint - User Profile ===\n";
    $result = makeRequest('GET', $baseUrl . '/auth/me', [], [
        'Authorization: Bearer ' . $authToken
    ]);
    
    if ($result['success'] && $result['http_code'] === 200) {
        echo "✅ Protected endpoint hoạt động với JWT token\n";
        echo "User: " . json_encode($result['response']['data'], JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "❌ Protected endpoint lỗi\n";
        echo "HTTP Code: {$result['http_code']}\n";
        echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
    }
    
    /**
     * Test 6: Test JWT Validation
     */
    echo "\n=== TEST 6: JWT Validation ===\n";
    $result = makeRequest('GET', $baseUrl . '/auth/jwt-test', [], [
        'Authorization: Bearer ' . $authToken
    ]);
    
    if ($result['success'] && $result['http_code'] === 200) {
        echo "✅ JWT validation hoạt động\n";
        echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "❌ JWT validation lỗi\n";
        echo "HTTP Code: {$result['http_code']}\n";
    }
}

echo "\n=== KẾT THÚC TEST API ===\n";