<?php declare(strict_types=1);

/**
 * Test script để kiểm tra JWT Authentication API endpoints
 * Server phải đang chạy trên http://127.0.0.1:8001
 */

class JwtApiTester
{
    private string $baseUrl = 'http://127.0.0.1:8001/api';
    private ?string $authToken = null;
    
    public function runTests(): void
    {
        echo "=== JWT API ENDPOINTS TEST ===\n\n";
        
        // Test 1: Health check
        $this->testHealthCheck();
        
        // Test 2: Basic API test
        $this->testBasicApi();
        
        // Test 3: Register user
        $this->testRegister();
        
        // Test 4: Login user
        $this->testLogin();
        
        // Test 5: JWT protected endpoints
        if ($this->authToken) {
            $this->testJwtProtectedEndpoints();
        }
        
        echo "\n=== TEST COMPLETED ===\n";
    }
    
    private function testHealthCheck(): void
    {
        echo "1. Testing Health Check...\n";
        $response = $this->makeRequest('GET', '/test');
        
        if ($response && isset($response['status']) && $response['status'] === 'success') {
            echo "   ✅ Health check PASSED\n";
            echo "   📝 Message: {$response['message']}\n";
        } else {
            echo "   ❌ Health check FAILED\n";
        }
        echo "\n";
    }
    
    private function testBasicApi(): void
    {
        echo "2. Testing Basic API Info...\n";
        $response = $this->makeRequest('GET', '/info');
        
        if ($response && isset($response['status']) && $response['status'] === 'success') {
            echo "   ✅ API Info PASSED\n";
            echo "   📝 Service: {$response['data']['service']}\n";
            echo "   📝 Version: {$response['data']['api_version']}\n";
        } else {
            echo "   ❌ API Info FAILED\n";
        }
        echo "\n";
    }
    
    private function testRegister(): void
    {
        echo "3. Testing User Registration...\n";
        
        $userData = [
            'name' => 'Test User JWT',
            'email' => 'testjwt@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            // Thêm trường company_name bắt buộc
            'company_name' => 'Test Company JWT',
            // Các trường tùy chọn
            'company_domain' => 'testjwt.com',
            'company_phone' => '+84123456789',
            'company_address' => '123 Test Street, Ho Chi Minh City'
        ];
        
        $response = $this->makeRequest('POST', '/v1/auth/register', $userData);
        
        if ($response && isset($response['status']) && $response['status'] === 'success') {
            echo "   ✅ Registration PASSED\n";
            echo "   📝 User ID: {$response['data']['user']['id']}\n";
            echo "   📝 Email: {$response['data']['user']['email']}\n";
            echo "   📝 Company: {$response['data']['tenant']['name']}\n";
        } else {
            echo "   ❌ Registration FAILED\n";
            if (isset($response['message'])) {
                echo "   📝 Error: {$response['message']}\n";
            }
            if (isset($response['data'])) {
                echo "   📝 Validation Errors: " . json_encode($response['data']) . "\n";
            }
        }
        echo "\n";
    }
    
    private function testLogin(): void
    {
        echo "4. Testing User Login...\n";
        
        $loginData = [
            'email' => 'testjwt@example.com',
            'password' => 'password123'
        ];
        
        $response = $this->makeRequest('POST', '/v1/auth/login', $loginData);
        
        if ($response && isset($response['status']) && $response['status'] === 'success') {
            echo "   ✅ Login PASSED\n";
            echo "   📝 User: {$response['data']['user']['name']}\n";
            echo "   📝 Token Type: {$response['data']['token_type']}\n";
            echo "   📝 Expires In: {$response['data']['expires_in']} seconds\n";
            
            // Lưu token để test các endpoint được bảo vệ
            $this->authToken = $response['data']['token'];
        } else {
            echo "   ❌ Login FAILED\n";
            if (isset($response['message'])) {
                echo "   📝 Error: {$response['message']}\n";
            }
        }
        echo "\n";
    }
    
    private function testJwtProtectedEndpoints(): void
    {
        echo "5. Testing JWT Protected Endpoints...\n";
        
        // Test /me endpoint
        echo "   5.1 Testing /me endpoint...\n";
        $response = $this->makeRequest('GET', '/v1/auth/me', null, [
            'Authorization: Bearer ' . $this->authToken
        ]);
        
        if ($response && isset($response['status']) && $response['status'] === 'success') {
            echo "      ✅ /me endpoint PASSED\n";
            echo "      📝 User: {$response['data']['name']}\n";
            echo "      📝 Email: {$response['data']['email']}\n";
        } else {
            echo "      ❌ /me endpoint FAILED\n";
        }
        
        // Test JWT test endpoint
        echo "   5.2 Testing /jwt-test endpoint...\n";
        $response = $this->makeRequest('GET', '/v1/jwt-test', null, [
            'Authorization: Bearer ' . $this->authToken
        ]);
        
        if ($response && isset($response['status']) && $response['status'] === 'success') {
            echo "      ✅ /jwt-test endpoint PASSED\n";
            echo "      📝 Message: {$response['message']}\n";
            echo "      📝 User ID: {$response['data']['user_id']}\n";
        } else {
            echo "      ❌ /jwt-test endpoint FAILED\n";
        }
        
        // Test user profile endpoint
        echo "   5.3 Testing /user-profile endpoint...\n";
        $response = $this->makeRequest('GET', '/v1/user-profile', null, [
            'Authorization: Bearer ' . $this->authToken
        ]);
        
        if ($response && isset($response['status']) && $response['status'] === 'success') {
            echo "      ✅ /user-profile endpoint PASSED\n";
            echo "      📝 User: {$response['data']['user']['name']}\n";
            echo "      📝 Tenant: " . ($response['data']['tenant']['name'] ?? 'N/A') . "\n";
        } else {
            echo "      ❌ /user-profile endpoint FAILED\n";
        }
        
        // Test unauthorized access
        echo "   5.4 Testing unauthorized access...\n";
        $response = $this->makeRequest('GET', '/v1/jwt-test');
        
        if ($response && isset($response['message']) && strpos($response['message'], 'Unauthenticated') !== false) {
            echo "      ✅ Unauthorized access properly blocked\n";
        } else {
            echo "      ❌ Unauthorized access not properly blocked\n";
        }
        
        echo "\n";
    }
    
    private function makeRequest(string $method, string $endpoint, ?array $data = null, array $headers = []): ?array
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Default headers
        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "   ❌ CURL Error: $error\n";
            return null;
        }
        
        echo "   📡 HTTP $httpCode: $method $endpoint\n";
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "   ❌ JSON Decode Error: " . json_last_error_msg() . "\n";
            echo "   📝 Raw Response: $response\n";
            return null;
        }
        
        return $decoded;
    }
}

// Chạy test
try {
    $tester = new JwtApiTester();
    $tester->runTests();
} catch (Exception $e) {
    echo "❌ Test Error: " . $e->getMessage() . "\n";
}