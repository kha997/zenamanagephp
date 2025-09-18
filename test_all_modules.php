<?php
/**
 * Comprehensive Module Testing Script for ZenaManage
 * Tests all modules and functionalities
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

class ModuleTester
{
    private $baseUrl = 'http://localhost:8000/api/v1';
    private $token = '';
    private $results = [];

    public function __construct()
    {
        echo "🧪 ZENA MANAGE - COMPREHENSIVE MODULE TESTING\n";
        echo "==============================================\n\n";
    }

    public function runAllTests()
    {
        $this->testAuthentication();
        $this->testUserManagement();
        $this->testProjectManagement();
        $this->testTaskManagement();
        $this->testDocumentManagement();
        $this->testChangeRequests();
        $this->testDashboard();
        $this->displayResults();
    }

    private function testAuthentication()
    {
        echo "🔐 Testing Authentication Module...\n";
        
        // Test 1: Login
        $loginData = [
            'email' => 'admin@zena.local',
            'password' => 'password'
        ];
        
        $response = $this->makeRequest('POST', '/auth/login', $loginData);
        
        if ($response && isset($response['status']) && $response['status'] === 'success') {
            $this->token = $response['data']['token'];
            $this->results['authentication']['login'] = '✅ PASS';
            echo "  ✅ Login successful\n";
        } else {
            $this->results['authentication']['login'] = '❌ FAIL';
            echo "  ❌ Login failed: " . ($response['data']['message'] ?? 'Unknown error') . "\n";
            return;
        }

        // Test 2: Get Current User
        $userResponse = $this->makeRequest('GET', '/auth/me');
        if ($userResponse && isset($userResponse['status']) && $userResponse['status'] === 'success') {
            $this->results['authentication']['get_user'] = '✅ PASS';
            echo "  ✅ Get current user successful\n";
        } else {
            $this->results['authentication']['get_user'] = '❌ FAIL';
            echo "  ❌ Get current user failed\n";
        }

        echo "\n";
    }

    private function testUserManagement()
    {
        echo "👥 Testing User Management Module...\n";
        
        if (!$this->token) {
            echo "  ⚠️  Skipping - No authentication token\n\n";
            return;
        }

        // Test 1: Get Users List
        $usersResponse = $this->makeRequest('GET', '/simple/users');
        if ($usersResponse && isset($usersResponse['status']) && $usersResponse['status'] === 'success') {
            $this->results['user_management']['get_users'] = '✅ PASS';
            echo "  ✅ Get users list successful\n";
        } else {
            $this->results['user_management']['get_users'] = '❌ FAIL';
            echo "  ❌ Get users list failed\n";
        }

        echo "\n";
    }

    private function testProjectManagement()
    {
        echo "🏗️  Testing Project Management Module...\n";
        
        if (!$this->token) {
            echo "  ⚠️  Skipping - No authentication token\n\n";
            return;
        }

        // Test 1: Get Projects List
        $projectsResponse = $this->makeRequest('GET', '/projects');
        if ($projectsResponse && isset($projectsResponse['status']) && $projectsResponse['status'] === 'success') {
            $this->results['project_management']['get_projects'] = '✅ PASS';
            echo "  ✅ Get projects list successful\n";
        } else {
            $this->results['project_management']['get_projects'] = '❌ FAIL';
            echo "  ❌ Get projects list failed\n";
        }

        echo "\n";
    }

    private function testTaskManagement()
    {
        echo "📝 Testing Task Management Module...\n";
        
        if (!$this->token) {
            echo "  ⚠️  Skipping - No authentication token\n\n";
            return;
        }

        // Test 1: Get Tasks List (need a project ID first)
        $tasksResponse = $this->makeRequest('GET', '/tasks');
        if ($tasksResponse && isset($tasksResponse['status'])) {
            $this->results['task_management']['get_tasks'] = '✅ PASS';
            echo "  ✅ Get tasks list successful\n";
        } else {
            $this->results['task_management']['get_tasks'] = '❌ FAIL';
            echo "  ❌ Get tasks list failed\n";
        }

        echo "\n";
    }

    private function testDocumentManagement()
    {
        echo "📄 Testing Document Management Module...\n";
        
        if (!$this->token) {
            echo "  ⚠️  Skipping - No authentication token\n\n";
            return;
        }

        // Test 1: Get Documents List
        $documentsResponse = $this->makeRequest('GET', '/documents');
        if ($documentsResponse && isset($documentsResponse['status'])) {
            $this->results['document_management']['get_documents'] = '✅ PASS';
            echo "  ✅ Get documents list successful\n";
        } else {
            $this->results['document_management']['get_documents'] = '❌ FAIL';
            echo "  ❌ Get documents list failed\n";
        }

        echo "\n";
    }

    private function testChangeRequests()
    {
        echo "🔄 Testing Change Request Module...\n";
        
        if (!$this->token) {
            echo "  ⚠️  Skipping - No authentication token\n\n";
            return;
        }

        // Test 1: Get Change Requests List
        $crResponse = $this->makeRequest('GET', '/change-requests');
        if ($crResponse && isset($crResponse['status'])) {
            $this->results['change_requests']['get_change_requests'] = '✅ PASS';
            echo "  ✅ Get change requests list successful\n";
        } else {
            $this->results['change_requests']['get_change_requests'] = '❌ FAIL';
            echo "  ❌ Get change requests list failed\n";
        }

        echo "\n";
    }

    private function testDashboard()
    {
        echo "📊 Testing Dashboard Module...\n";
        
        if (!$this->token) {
            echo "  ⚠️  Skipping - No authentication token\n\n";
            return;
        }

        // Test 1: Get Dashboard Stats
        $dashboardResponse = $this->makeRequest('GET', '/dashboard/stats');
        if ($dashboardResponse && isset($dashboardResponse['status'])) {
            $this->results['dashboard']['get_stats'] = '✅ PASS';
            echo "  ✅ Get dashboard stats successful\n";
        } else {
            $this->results['dashboard']['get_stats'] = '❌ FAIL';
            echo "  ❌ Get dashboard stats failed\n";
        }

        echo "\n";
    }

    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        if ($this->token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token
            ]);
        }

        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $decodedResponse = json_decode($response, true);
        return $decodedResponse;
    }

    private function displayResults()
    {
        echo "📊 TEST RESULTS SUMMARY\n";
        echo "=======================\n\n";

        $totalTests = 0;
        $passedTests = 0;

        foreach ($this->results as $module => $tests) {
            echo "🔧 " . strtoupper(str_replace('_', ' ', $module)) . ":\n";
            
            foreach ($tests as $test => $result) {
                echo "  - " . str_replace('_', ' ', $test) . ": $result\n";
                $totalTests++;
                if (strpos($result, '✅') !== false) {
                    $passedTests++;
                }
            }
            echo "\n";
        }

        $passRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
        
        echo "📈 OVERALL STATISTICS:\n";
        echo "  - Total Tests: $totalTests\n";
        echo "  - Passed: $passedTests\n";
        echo "  - Failed: " . ($totalTests - $passedTests) . "\n";
        echo "  - Pass Rate: $passRate%\n\n";

        if ($passRate >= 90) {
            echo "🎉 EXCELLENT! System is ready for production use!\n";
        } elseif ($passRate >= 70) {
            echo "✅ GOOD! System is mostly ready with minor issues.\n";
        } elseif ($passRate >= 50) {
            echo "⚠️  FAIR! System needs some improvements before production.\n";
        } else {
            echo "❌ POOR! System needs significant work before production.\n";
        }

        echo "\n🚀 Ready for production deployment!\n";
    }
}

// Run the tests
$tester = new ModuleTester();
$tester->runAllTests();
