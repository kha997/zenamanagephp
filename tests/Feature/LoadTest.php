<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use Src\RBAC\Services\AuthService;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

/**
 * Test tải với concurrent users
 */
class LoadTest extends TestCase
{
    use RefreshDatabase;

    private $baseUrl;
    private $httpClient;
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->authService = app(AuthService::class);
        $this->baseUrl = config('app.url');
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'http_errors' => false
        ]);
        
        // Seed test data
        $this->seedTestData();
    }

    private function seedTestData()
    {
        // Tạo multiple tenants và users
        $tenants = Tenant::factory(5)->create();
        
        foreach ($tenants as $tenant) {
            // Tạo 10 users per tenant
            User::factory(10)->create(['tenant_id' => $tenant->id]);
            
            // Tạo 20 projects per tenant
            $projects = Project::factory(20)->create(['tenant_id' => $tenant->id]);
            
            // Tạo tasks cho projects
            foreach ($projects->take(10) as $project) {
                Task::factory(15)->create(['project_id' => $project->id]);
            }
        }
    }

    /**
     * Test concurrent login requests
     */
    public function test_concurrent_login_requests()
    {
        $users = User::limit(20)->get();
        $concurrentRequests = 20;
        
        $promises = [];
        $startTime = microtime(true);
        
        // Tạo concurrent login requests
        foreach ($users as $index => $user) {
            $promises[] = $this->httpClient->postAsync('/api/v1/auth/login', [
                'json' => [
                    'email' => $user->email,
                    'password' => 'password' // Default factory password
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);
        }
        
        // Wait for all requests to complete
        $responses = Promise\settle($promises)->wait();
        $totalTime = microtime(true) - $startTime;
        
        $successCount = 0;
        $errorCount = 0;
        $responseTimes = [];
        
        foreach ($responses as $response) {
            if ($response['state'] === 'fulfilled') {
                $statusCode = $response['value']->getStatusCode();
                if ($statusCode === 200) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } else {
                $errorCount++;
            }
        }
        
        // Assert performance requirements
        $this->assertGreaterThan(15, $successCount, 'At least 75% of requests should succeed');
        $this->assertLessThan(10, $totalTime, 'Total time should be less than 10 seconds');
        
        echo "\nConcurrent Login Test:\n";
        echo "Total requests: {$concurrentRequests}\n";
        echo "Successful: {$successCount}\n";
        echo "Errors: {$errorCount}\n";
        echo "Total time: " . round($totalTime, 2) . "s\n";
        echo "Average time per request: " . round($totalTime / $concurrentRequests, 3) . "s\n";
    }

    /**
     * Test concurrent API requests
     */
    public function test_concurrent_api_requests()
    {
        // Tạo authenticated users
        $users = User::limit(10)->get();
        $tokens = [];
        
        foreach ($users as $user) {
            $tokens[] = $this->authService->createTokenForUser($user);
        }
        
        $concurrentRequests = 50;
        $promises = [];
        $startTime = microtime(true);
        
        // Tạo concurrent API requests
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $token = $tokens[$i % count($tokens)];
            
            $promises[] = $this->httpClient->getAsync('/api/v1/projects', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ]
            ]);
        }
        
        // Wait for all requests
        $responses = Promise\settle($promises)->wait();
        $totalTime = microtime(true) - $startTime;
        
        $successCount = 0;
        $errorCount = 0;
        $responseTimes = [];
        
        foreach ($responses as $response) {
            if ($response['state'] === 'fulfilled') {
                $statusCode = $response['value']->getStatusCode();
                if ($statusCode === 200) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } else {
                $errorCount++;
            }
        }
        
        // Performance assertions
        $this->assertGreaterThan(40, $successCount, 'At least 80% of requests should succeed');
        $this->assertLessThan(15, $totalTime, 'Total time should be less than 15 seconds');
        
        echo "\nConcurrent API Test:\n";
        echo "Total requests: {$concurrentRequests}\n";
        echo "Successful: {$successCount}\n";
        echo "Errors: {$errorCount}\n";
        echo "Total time: " . round($totalTime, 2) . "s\n";
        echo "Requests per second: " . round($concurrentRequests / $totalTime, 2) . "\n";
    }

    /**
     * Test database connection pool under load
     */
    public function test_database_connection_pool_load()
    {
        $concurrentQueries = 30;
        $promises = [];
        $startTime = microtime(true);
        
        // Get token for authentication
        $user = User::first();
        $token = $this->authService->createTokenForUser($user);
        
        // Simulate concurrent database operations
        for ($i = 0; $i < $concurrentQueries; $i++) {
            $promises[] = $this->httpClient->postAsync('/api/v1/projects', [
                'json' => [
                    'name' => 'Load Test Project ' . $i,
                    'description' => 'Created during load test',
                    'start_date' => '2024-01-01',
                    'end_date' => '2024-12-31'
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);
        }
        
        $responses = Promise\settle($promises)->wait();
        $totalTime = microtime(true) - $startTime;
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($responses as $response) {
            if ($response['state'] === 'fulfilled') {
                $statusCode = $response['value']->getStatusCode();
                if ($statusCode === 201) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } else {
                $errorCount++;
            }
        }
        
        // Check database connections
        $activeConnections = DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value;
        
        $this->assertGreaterThan(20, $successCount, 'Most database operations should succeed');
        $this->assertLessThan(100, $activeConnections, 'Should not exhaust connection pool');
        
        echo "\nDatabase Load Test:\n";
        echo "Concurrent operations: {$concurrentQueries}\n";
        echo "Successful: {$successCount}\n";
        echo "Errors: {$errorCount}\n";
        echo "Total time: " . round($totalTime, 2) . "s\n";
        echo "Active DB connections: {$activeConnections}\n";
    }

    /**
     * Test memory usage under concurrent load
     */
    public function test_memory_usage_under_load()
    {
        $initialMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        // Simulate high memory usage scenario
        $users = User::limit(5)->get();
        $tokens = [];
        
        foreach ($users as $user) {
            $tokens[] = $this->authService->createTokenForUser($user);
        }
        
        $promises = [];
        
        // Create requests that load large datasets
        for ($i = 0; $i < 20; $i++) {
            $token = $tokens[$i % count($tokens)];
            
            $promises[] = $this->httpClient->getAsync('/api/v1/projects?per_page=100', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ]
            ]);
        }
        
        $responses = Promise\settle($promises)->wait();
        
        $finalMemory = memory_get_usage(true);
        $finalPeakMemory = memory_get_peak_usage(true);
        
        $memoryIncrease = $finalMemory - $initialMemory;
        $peakIncrease = $finalPeakMemory - $peakMemory;
        
        // Memory should not increase dramatically
        $this->assertLessThan(100 * 1024 * 1024, $memoryIncrease, 'Memory increase should be less than 100MB');
        
        echo "\nMemory Load Test:\n";
        echo "Initial memory: " . round($initialMemory / 1024 / 1024, 2) . "MB\n";
        echo "Final memory: " . round($finalMemory / 1024 / 1024, 2) . "MB\n";
        echo "Memory increase: " . round($memoryIncrease / 1024 / 1024, 2) . "MB\n";
        echo "Peak memory increase: " . round($peakIncrease / 1024 / 1024, 2) . "MB\n";
    }
}