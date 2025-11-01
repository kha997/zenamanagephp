<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Client;

/**
 * LoadTestSuite
 * 
 * Comprehensive load testing framework for ZenaManage system
 * Tests system performance under various load conditions
 * 
 * Features:
 * - API load performance testing
 * - Database load performance testing
 * - Memory usage monitoring
 * - Concurrent user simulation
 * - Performance regression detection
 * - Load capacity analysis
 */
class LoadTestSuite extends TestCase
{
    use RefreshDatabase, WithFaker;
    
    private array $performanceMetrics = [];
    private int $concurrentUsers = 10;
    private int $requestsPerUser = 100;
    private float $maxResponseTime = 500; // milliseconds
    private float $maxMemoryUsage = 128; // MB
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test data
        $this->setUpTestData();
        
        // Clear performance metrics
        $this->performanceMetrics = [];
    }
    
    /**
     * Test API load performance
     */
    public function testApiLoadPerformance(): void
    {
        $this->markTestSkipped('Load test - run manually with: php artisan test --filter=testApiLoadPerformance');
        
        $startTime = microtime(true);
        $totalRequests = 0;
        $successfulRequests = 0;
        $failedRequests = 0;
        $responseTimes = [];
        
        Log::info('Starting API load performance test', [
            'concurrent_users' => $this->concurrentUsers,
            'requests_per_user' => $this->requestsPerUser,
            'max_response_time' => $this->maxResponseTime
        ]);
        
        // Simulate concurrent users
        for ($user = 1; $user <= $this->concurrentUsers; $user++) {
            $userMetrics = $this->simulateUserLoad($user);
            
            $totalRequests += $userMetrics['total_requests'];
            $successfulRequests += $userMetrics['successful_requests'];
            $failedRequests += $userMetrics['failed_requests'];
            $responseTimes = array_merge($responseTimes, $userMetrics['response_times']);
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        // Calculate metrics
        $averageResponseTime = array_sum($responseTimes) / count($responseTimes);
        $p95ResponseTime = $this->calculatePercentile($responseTimes, 95);
        $p99ResponseTime = $this->calculatePercentile($responseTimes, 99);
        $requestsPerSecond = ($totalRequests / $totalTime) * 1000;
        $successRate = ($successfulRequests / $totalRequests) * 100;
        
        $this->performanceMetrics['api_load'] = [
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            'success_rate' => round($successRate, 2),
            'total_time_ms' => round($totalTime, 2),
            'requests_per_second' => round($requestsPerSecond, 2),
            'average_response_time_ms' => round($averageResponseTime, 2),
            'p95_response_time_ms' => round($p95ResponseTime, 2),
            'p99_response_time_ms' => round($p99ResponseTime, 2),
            'max_response_time_ms' => round(max($responseTimes), 2),
            'min_response_time_ms' => round(min($responseTimes), 2)
        ];
        
        // Assertions
        $this->assertGreaterThan(95, $successRate, 'Success rate should be above 95%');
        $this->assertLessThan($this->maxResponseTime, $averageResponseTime, 'Average response time should be below threshold');
        $this->assertLessThan($this->maxResponseTime * 2, $p95ResponseTime, 'P95 response time should be below 2x threshold');
        
        Log::info('API load performance test completed', $this->performanceMetrics['api_load']);
    }
    
    /**
     * Test database load performance
     */
    public function testDatabaseLoadPerformance(): void
    {
        $this->markTestSkipped('Load test - run manually with: php artisan test --filter=testDatabaseLoadPerformance');
        
        $startTime = microtime(true);
        $queryTimes = [];
        $queryCounts = [
            'select' => 0,
            'insert' => 0,
            'update' => 0,
            'delete' => 0
        ];
        
        Log::info('Starting database load performance test');
        
        // Test various database operations
        for ($i = 0; $i < 1000; $i++) {
            $operation = $this->faker->randomElement(['select', 'insert', 'update', 'delete']);
            
            $queryStart = microtime(true);
            
            switch ($operation) {
                case 'select':
                    $this->performSelectOperation();
                    break;
                case 'insert':
                    $this->performInsertOperation();
                    break;
                case 'update':
                    $this->performUpdateOperation();
                    break;
                case 'delete':
                    $this->performDeleteOperation();
                    break;
            }
            
            $queryTime = (microtime(true) - $queryStart) * 1000;
            $queryTimes[] = $queryTime;
            $queryCounts[$operation]++;
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        
        // Calculate metrics
        $averageQueryTime = array_sum($queryTimes) / count($queryTimes);
        $p95QueryTime = $this->calculatePercentile($queryTimes, 95);
        $queriesPerSecond = (count($queryTimes) / $totalTime) * 1000;
        
        $this->performanceMetrics['database_load'] = [
            'total_queries' => count($queryTimes),
            'query_counts' => $queryCounts,
            'total_time_ms' => round($totalTime, 2),
            'queries_per_second' => round($queriesPerSecond, 2),
            'average_query_time_ms' => round($averageQueryTime, 2),
            'p95_query_time_ms' => round($p95QueryTime, 2),
            'max_query_time_ms' => round(max($queryTimes), 2),
            'min_query_time_ms' => round(min($queryTimes), 2)
        ];
        
        // Assertions
        $this->assertLessThan(100, $averageQueryTime, 'Average query time should be below 100ms');
        $this->assertLessThan(200, $p95QueryTime, 'P95 query time should be below 200ms');
        
        Log::info('Database load performance test completed', $this->performanceMetrics['database_load']);
    }
    
    /**
     * Test memory usage under load
     */
    public function testMemoryUsageUnderLoad(): void
    {
        $this->markTestSkipped('Load test - run manually with: php artisan test --filter=testMemoryUsageUnderLoad');
        
        $memoryUsage = [];
        $peakMemory = 0;
        
        Log::info('Starting memory usage test');
        
        // Monitor memory usage during various operations
        for ($i = 0; $i < 100; $i++) {
            $memoryBefore = memory_get_usage(true);
            
            // Perform memory-intensive operations
            $this->performMemoryIntensiveOperation();
            
            $memoryAfter = memory_get_usage(true);
            $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB
            
            $memoryUsage[] = $memoryUsed;
            $peakMemory = max($peakMemory, $memoryAfter / 1024 / 1024);
            
            // Force garbage collection
            if ($i % 10 === 0) {
                gc_collect_cycles();
            }
        }
        
        $averageMemoryUsage = array_sum($memoryUsage) / count($memoryUsage);
        $currentMemoryUsage = memory_get_usage(true) / 1024 / 1024;
        
        $this->performanceMetrics['memory_usage'] = [
            'average_memory_usage_mb' => round($averageMemoryUsage, 2),
            'peak_memory_usage_mb' => round($peakMemory, 2),
            'current_memory_usage_mb' => round($currentMemoryUsage, 2),
            'memory_limit_mb' => $this->convertToMB(ini_get('memory_limit')),
            'memory_efficiency' => round(($currentMemoryUsage / $this->convertToMB(ini_get('memory_limit'))) * 100, 2)
        ];
        
        // Assertions
        $this->assertLessThan($this->maxMemoryUsage, $averageMemoryUsage, 'Average memory usage should be below threshold');
        $this->assertLessThan(80, $this->performanceMetrics['memory_usage']['memory_efficiency'], 'Memory efficiency should be below 80%');
        
        Log::info('Memory usage test completed', $this->performanceMetrics['memory_usage']);
    }
    
    /**
     * Test concurrent user simulation
     */
    public function testConcurrentUserSimulation(): void
    {
        $this->markTestSkipped('Load test - run manually with: php artisan test --filter=testConcurrentUserSimulation');
        
        $concurrentUsers = [5, 10, 20, 50];
        $results = [];
        
        foreach ($concurrentUsers as $userCount) {
            Log::info('Testing concurrent users', ['user_count' => $userCount]);
            
            $startTime = microtime(true);
            $responseTimes = [];
            $errors = 0;
            
            // Simulate concurrent users
            for ($i = 0; $i < $userCount; $i++) {
                $userStart = microtime(true);
                
                try {
                    $this->simulateUserSession();
                    $userTime = (microtime(true) - $userStart) * 1000;
                    $responseTimes[] = $userTime;
                } catch (\Exception $e) {
                    $errors++;
                    Log::warning('User simulation error', [
                        'user_id' => $i,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $totalTime = (microtime(true) - $startTime) * 1000;
            $averageResponseTime = array_sum($responseTimes) / count($responseTimes);
            $p95ResponseTime = $this->calculatePercentile($responseTimes, 95);
            $errorRate = ($errors / $userCount) * 100;
            
            $results[$userCount] = [
                'user_count' => $userCount,
                'total_time_ms' => round($totalTime, 2),
                'average_response_time_ms' => round($averageResponseTime, 2),
                'p95_response_time_ms' => round($p95ResponseTime, 2),
                'error_rate' => round($errorRate, 2),
                'throughput' => round(($userCount / $totalTime) * 1000, 2)
            ];
        }
        
        $this->performanceMetrics['concurrent_users'] = $results;
        
        // Assertions
        foreach ($results as $result) {
            $this->assertLessThan(5, $result['error_rate'], 'Error rate should be below 5%');
            $this->assertLessThan($this->maxResponseTime, $result['average_response_time_ms'], 'Average response time should be below threshold');
        }
        
        Log::info('Concurrent user simulation completed', $results);
    }
    
    /**
     * Test system capacity limits
     */
    public function testSystemCapacityLimits(): void
    {
        $this->markTestSkipped('Load test - run manually with: php artisan test --filter=testSystemCapacityLimits');
        
        $capacityTests = [
            'max_projects' => 1000,
            'max_tasks_per_project' => 100,
            'max_users_per_tenant' => 500,
            'max_concurrent_sessions' => 100
        ];
        
        $results = [];
        
        foreach ($capacityTests as $test => $limit) {
            Log::info('Testing system capacity', ['test' => $test, 'limit' => $limit]);
            
            $startTime = microtime(true);
            $success = true;
            
            try {
                switch ($test) {
                    case 'max_projects':
                        $this->createProjects($limit);
                        break;
                    case 'max_tasks_per_project':
                        $this->createTasksForProject($limit);
                        break;
                    case 'max_users_per_tenant':
                        $this->createUsers($limit);
                        break;
                    case 'max_concurrent_sessions':
                        $this->simulateConcurrentSessions($limit);
                        break;
                }
            } catch (\Exception $e) {
                $success = false;
                Log::error('Capacity test failed', [
                    'test' => $test,
                    'limit' => $limit,
                    'error' => $e->getMessage()
                ]);
            }
            
            $endTime = microtime(true);
            $totalTime = ($endTime - $startTime) * 1000;
            
            $results[$test] = [
                'limit' => $limit,
                'success' => $success,
                'time_ms' => round($totalTime, 2),
                'status' => $success ? 'passed' : 'failed'
            ];
        }
        
        $this->performanceMetrics['capacity_limits'] = $results;
        
        // Assertions
        foreach ($results as $test => $result) {
            $this->assertTrue($result['success'], "Capacity test {$test} should pass");
        }
        
        Log::info('System capacity limits test completed', $results);
    }
    
    /**
     * Simulate user load
     */
    private function simulateUserLoad(int $userId): array
    {
        $totalRequests = 0;
        $successfulRequests = 0;
        $failedRequests = 0;
        $responseTimes = [];
        
        for ($i = 0; $i < $this->requestsPerUser; $i++) {
            $startTime = microtime(true);
            
            try {
                // Simulate various API calls
                $endpoint = $this->faker->randomElement([
                    '/api/projects',
                    '/api/tasks',
                    '/api/clients',
                    '/api/dashboard'
                ]);
                
                $response = $this->get($endpoint);
                
                if ($response->status() === 200) {
                    $successfulRequests++;
                } else {
                    $failedRequests++;
                }
                
                $totalRequests++;
                
            } catch (\Exception $e) {
                $failedRequests++;
                $totalRequests++;
            }
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            $responseTimes[] = $responseTime;
            
            // Small delay to simulate real user behavior
            usleep(rand(10000, 50000)); // 10-50ms
        }
        
        return [
            'user_id' => $userId,
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            'response_times' => $responseTimes
        ];
    }
    
    /**
     * Simulate user session
     */
    private function simulateUserSession(): void
    {
        // Simulate a complete user session
        $this->get('/api/dashboard');
        $this->get('/api/projects');
        $this->get('/api/tasks');
        $this->get('/api/clients');
        
        // Simulate some user interactions
        if (rand(1, 10) <= 3) { // 30% chance
            $this->post('/api/projects', [
                'name' => $this->faker->sentence(3),
                'description' => $this->faker->paragraph(),
                'budget' => $this->faker->numberBetween(1000, 100000)
            ]);
        }
    }
    
    /**
     * Perform select operation
     */
    private function performSelectOperation(): void
    {
        Project::with(['tasks', 'client'])->limit(10)->get();
    }
    
    /**
     * Perform insert operation
     */
    private function performInsertOperation(): void
    {
        Project::create([
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'budget_total' => $this->faker->numberBetween(1000, 100000),
            'user_id' => 1,
            'tenant_id' => 1
        ]);
    }
    
    /**
     * Perform update operation
     */
    private function performUpdateOperation(): void
    {
        $project = Project::first();
        if ($project) {
            $project->update([
                'name' => $this->faker->sentence(3)
            ]);
        }
    }
    
    /**
     * Perform delete operation
     */
    private function performDeleteOperation(): void
    {
        $project = Project::where('id', '>', 1)->first();
        if ($project) {
            $project->delete();
        }
    }
    
    /**
     * Perform memory-intensive operation
     */
    private function performMemoryIntensiveOperation(): void
    {
        // Create large data structures
        $largeArray = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeArray[] = $this->faker->paragraph(5);
        }
        
        // Process the data
        $processedData = array_map('strtoupper', $largeArray);
        
        // Simulate some processing
        $result = array_sum(array_map('strlen', $processedData));
        
        // Clean up
        unset($largeArray, $processedData);
    }
    
    /**
     * Create projects for capacity testing
     */
    private function createProjects(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Project::create([
                'name' => "Test Project {$i}",
                'description' => "Test project description {$i}",
                'budget_total' => 10000,
                'user_id' => 1,
                'tenant_id' => 1
            ]);
        }
    }
    
    /**
     * Create tasks for project
     */
    private function createTasksForProject(int $count): void
    {
        $project = Project::first();
        if (!$project) {
            $project = Project::create([
                'name' => 'Test Project',
                'description' => 'Test project for tasks',
                'budget_total' => 10000,
                'user_id' => 1,
                'tenant_id' => 1
            ]);
        }
        
        for ($i = 0; $i < $count; $i++) {
            Task::create([
                'name' => "Test Task {$i}",
                'description' => "Test task description {$i}",
                'project_id' => $project->id,
                'user_id' => 1,
                'tenant_id' => 1
            ]);
        }
    }
    
    /**
     * Create users for capacity testing
     */
    private function createUsers(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            User::create([
                'name' => "Test User {$i}",
                'email' => "testuser{$i}@example.com",
                'password' => bcrypt('password'),
                'tenant_id' => 1,
                'role' => 'member'
            ]);
        }
    }
    
    /**
     * Simulate concurrent sessions
     */
    private function simulateConcurrentSessions(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->simulateUserSession();
        }
    }
    
    /**
     * Calculate percentile
     */
    private function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        
        if (floor($index) == $index) {
            return $values[$index];
        }
        
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;
        
        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }
    
    /**
     * Convert memory limit to MB
     */
    private function convertToMB(string $memoryLimit): float
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $memoryLimit = (int) $memoryLimit;
        
        switch ($last) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
        }
        
        return $memoryLimit / 1024 / 1024;
    }
    
    /**
     * Set up test data
     */
    private function setUpTestData(): void
    {
        // Create test user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => 1,
            'role' => 'admin'
        ]);
        
        // Create test client
        $client = Client::create([
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'phone' => '1234567890',
            'tenant_id' => 1
        ]);
        
        // Create test project
        $project = Project::create([
            'name' => 'Test Project',
            'description' => 'Test project description',
            'budget_total' => 10000,
            'user_id' => $user->id,
            'client_id' => $client->id,
            'tenant_id' => 1
        ]);
        
        // Create test tasks
        for ($i = 0; $i < 10; $i++) {
            Task::create([
                'name' => "Test Task {$i}",
                'description' => "Test task description {$i}",
                'project_id' => $project->id,
                'user_id' => $user->id,
                'tenant_id' => 1
            ]);
        }
    }
    
    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }
}
