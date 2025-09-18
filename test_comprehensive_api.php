<?php
/**
 * Comprehensive API Testing Script for Z.E.N.A System
 * Tests all authentication, RBAC, and dashboard endpoints
 */

require_once __DIR__ . '/vendor/autoload.php';

class ZenaApiTester {
    private $baseUrl = 'http://localhost:8000';
    private $token = null;
    private $testResults = [];
    
    public function __construct() {
        echo "🚀 Starting Comprehensive Z.E.N.A API Testing\n";
        echo "============================================\n\n";
    }
    
    /**
     * Make HTTP request
     */
    private function makeRequest($method, $endpoint, $data = null, $headers = []) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
        ], $headers));
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
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
            throw new Exception("cURL Error: $error");
        }
        
        return [
            'status' => $httpCode,
            'body' => $response,
            'data' => json_decode($response, true)
        ];
    }
    
    /**
     * Test authentication endpoints
     */
    public function testAuthentication() {
        echo "🔐 Testing Authentication Endpoints\n";
        echo "-----------------------------------\n";
        
        // Test login with PM user
        try {
            $response = $this->makeRequest('POST', '/api/zena/login', [
                'email' => 'pm@zena.com',
                'password' => 'password123'
            ]);
            
            if ($response['status'] === 200 && isset($response['data']['token'])) {
                $this->token = $response['data']['token'];
                $this->testResults['auth']['login'] = '✅ PASS';
                echo "✅ Login successful - Token obtained\n";
            } else {
                $this->testResults['auth']['login'] = '❌ FAIL';
                echo "❌ Login failed: " . $response['body'] . "\n";
            }
        } catch (Exception $e) {
            $this->testResults['auth']['login'] = '❌ ERROR';
            echo "❌ Login error: " . $e->getMessage() . "\n";
        }
        
        // Test profile endpoint
        if ($this->token) {
            try {
                $response = $this->makeRequest('GET', '/api/zena/me', null, [
                    'Authorization: Bearer ' . $this->token
                ]);
                
                if ($response['status'] === 200) {
                    $this->testResults['auth']['profile'] = '✅ PASS';
                    echo "✅ Profile endpoint working\n";
                    echo "   User: " . $response['data']['data']['name'] . "\n";
                    echo "   Roles: " . implode(', ', array_column($response['data']['data']['roles'], 'name')) . "\n";
                } else {
                    $this->testResults['auth']['profile'] = '❌ FAIL';
                    echo "❌ Profile endpoint failed: " . $response['body'] . "\n";
                }
            } catch (Exception $e) {
                $this->testResults['auth']['profile'] = '❌ ERROR';
                echo "❌ Profile error: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test role-based dashboard endpoints
     */
    public function testRoleBasedDashboards() {
        echo "📊 Testing Role-based Dashboard Endpoints\n";
        echo "----------------------------------------\n";
        
        if (!$this->token) {
            echo "❌ No token available, skipping dashboard tests\n\n";
            return;
        }
        
        $headers = ['Authorization: Bearer ' . $this->token];
        
        // Test general dashboard
        $this->testEndpoint('GET', '/api/zena/dashboard', null, $headers, 'General Dashboard');
        
        // Test PM-specific endpoints
        $this->testEndpoint('GET', '/api/zena/pm/dashboard', null, $headers, 'PM Dashboard');
        $this->testEndpoint('GET', '/api/zena/pm/progress', null, $headers, 'PM Progress');
        $this->testEndpoint('GET', '/api/zena/pm/risks', null, $headers, 'PM Risks');
        $this->testEndpoint('GET', '/api/zena/pm/weekly-report', null, $headers, 'PM Weekly Report');
        
        // Test Designer-specific endpoints
        $this->testEndpoint('GET', '/api/zena/designer/dashboard', null, $headers, 'Designer Dashboard');
        $this->testEndpoint('GET', '/api/zena/designer/tasks', null, $headers, 'Designer Tasks');
        $this->testEndpoint('GET', '/api/zena/designer/drawings', null, $headers, 'Designer Drawings');
        
        // Test Site Engineer-specific endpoints
        $this->testEndpoint('GET', '/api/zena/site-engineer/dashboard', null, $headers, 'Site Engineer Dashboard');
        $this->testEndpoint('GET', '/api/zena/site-engineer/tasks', null, $headers, 'Site Engineer Tasks');
        $this->testEndpoint('GET', '/api/zena/site-engineer/material-requests', null, $headers, 'Site Engineer Material Requests');
        
        echo "\n";
    }
    
    /**
     * Test individual endpoint
     */
    private function testEndpoint($method, $endpoint, $data, $headers, $name) {
        try {
            $response = $this->makeRequest($method, $endpoint, $data, $headers);
            
            if ($response['status'] === 200) {
                echo "✅ $name: OK\n";
                $this->testResults['endpoints'][$endpoint] = '✅ PASS';
            } elseif ($response['status'] === 401) {
                echo "🔒 $name: Unauthorized (expected for some roles)\n";
                $this->testResults['endpoints'][$endpoint] = '🔒 UNAUTHORIZED';
            } elseif ($response['status'] === 403) {
                echo "🚫 $name: Forbidden (role restriction)\n";
                $this->testResults['endpoints'][$endpoint] = '🚫 FORBIDDEN';
            } else {
                echo "❌ $name: Failed (HTTP {$response['status']})\n";
                $this->testResults['endpoints'][$endpoint] = '❌ FAIL';
            }
        } catch (Exception $e) {
            echo "❌ $name: Error - " . $e->getMessage() . "\n";
            $this->testResults['endpoints'][$endpoint] = '❌ ERROR';
        }
    }
    
    /**
     * Test RBAC middleware
     */
    public function testRbacMiddleware() {
        echo "🛡️ Testing RBAC Middleware\n";
        echo "-------------------------\n";
        
        if (!$this->token) {
            echo "❌ No token available, skipping RBAC tests\n\n";
            return;
        }
        
        $headers = ['Authorization: Bearer ' . $this->token];
        
        // Test protected routes
        $this->testEndpoint('GET', '/api/zena/test', null, $headers, 'Protected Test Route');
        $this->testEndpoint('GET', '/api/zena/simple-test', null, [], 'Public Test Route');
        $this->testEndpoint('GET', '/api/zena/auth-test', null, $headers, 'Auth Test Route');
        
        echo "\n";
    }
    
    /**
     * Test different user roles
     */
    public function testDifferentRoles() {
        echo "👥 Testing Different User Roles\n";
        echo "-------------------------------\n";
        
        $testUsers = [
            ['email' => 'admin@zena.com', 'password' => 'password123', 'role' => 'Admin'],
            ['email' => 'designer@zena.com', 'password' => 'password123', 'role' => 'Designer'],
            ['email' => 'siteengineer@zena.com', 'password' => 'password123', 'role' => 'SiteEngineer'],
            ['email' => 'qc@zena.com', 'password' => 'password123', 'role' => 'QC'],
            ['email' => 'client@zena.com', 'password' => 'password123', 'role' => 'Client'],
        ];
        
        foreach ($testUsers as $user) {
            echo "Testing {$user['role']} user...\n";
            
            try {
                $response = $this->makeRequest('POST', '/api/zena/login', [
                    'email' => $user['email'],
                    'password' => $user['password']
                ]);
                
                if ($response['status'] === 200 && isset($response['data']['token'])) {
                    $userToken = $response['data']['token'];
                    $headers = ['Authorization: Bearer ' . $userToken];
                    
                    // Test their specific dashboard
                    $dashboardEndpoint = '/api/zena/' . strtolower($user['role']) . '/dashboard';
                    $this->testEndpoint('GET', $dashboardEndpoint, null, $headers, "{$user['role']} Dashboard");
                    
                    echo "✅ {$user['role']} authentication successful\n";
                } else {
                    echo "❌ {$user['role']} authentication failed\n";
                }
            } catch (Exception $e) {
                echo "❌ {$user['role']} test error: " . $e->getMessage() . "\n";
            }
            
            echo "\n";
        }
    }
    
    /**
     * Test logout
     */
    public function testLogout() {
        echo "🚪 Testing Logout\n";
        echo "----------------\n";
        
        if (!$this->token) {
            echo "❌ No token available, skipping logout test\n\n";
            return;
        }
        
        try {
            $response = $this->makeRequest('POST', '/api/zena/logout', null, [
                'Authorization: Bearer ' . $this->token
            ]);
            
            if ($response['status'] === 200) {
                $this->testResults['auth']['logout'] = '✅ PASS';
                echo "✅ Logout successful\n";
            } else {
                $this->testResults['auth']['logout'] = '❌ FAIL';
                echo "❌ Logout failed: " . $response['body'] . "\n";
            }
        } catch (Exception $e) {
            $this->testResults['auth']['logout'] = '❌ ERROR';
            echo "❌ Logout error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Generate test report
     */
    public function generateReport() {
        echo "📋 Test Results Summary\n";
        echo "=======================\n";
        
        $totalTests = 0;
        $passedTests = 0;
        
        foreach ($this->testResults as $category => $tests) {
            echo "\n$category:\n";
            foreach ($tests as $test => $result) {
                echo "  $test: $result\n";
                $totalTests++;
                if (strpos($result, '✅') === 0) {
                    $passedTests++;
                }
            }
        }
        
        echo "\n";
        echo "Total Tests: $totalTests\n";
        echo "Passed: $passedTests\n";
        echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n";
        
        if ($passedTests === $totalTests) {
            echo "\n🎉 All tests passed! Z.E.N.A system is ready for production.\n";
        } else {
            echo "\n⚠️ Some tests failed. Please review the issues above.\n";
        }
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        $this->testAuthentication();
        $this->testRoleBasedDashboards();
        $this->testRbacMiddleware();
        $this->testDifferentRoles();
        $this->testLogout();
        $this->generateReport();
    }
}

// Run the tests
$tester = new ZenaApiTester();
$tester->runAllTests();
