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
use App\Services\PerformanceMonitoringService;
use App\Services\PerformanceAlertingService;

/**
 * PerformanceTestSuite
 * 
 * Comprehensive performance testing suite for ZenaManage system
 * Tests system performance across various scenarios and conditions
 * 
 * Features:
 * - API response time testing
 * - Database query performance testing
 * - Cache performance testing
 * - Memory usage monitoring
 * - Performance regression testing
 * - Performance benchmarking
 */
class PerformanceTestSuite extends TestCase
{
    use RefreshDatabase, WithFaker;
    
    private PerformanceMonitoringService $performanceService;
    private PerformanceAlertingService $alertingService;
    private array $performanceBaselines = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->performanceService = app(PerformanceMonitoringService::class);
        $this->alertingService = app(PerformanceAlertingService::class);
        
        // Set up test data
        $this->setUpTestData();
        
        // Load performance baselines
        $this->loadPerformanceBaselines();
    }
    
    /**
     * Test API response time
     */
    public function testApiResponseTime(): void
    {
        $endpoints = [
            '/api/dashboard',
            '/api/projects',
            '/api/tasks',
            '/api/clients',
            '/api/performance/metrics'
        ];
        
        $responseTimes = [];
        
        foreach ($endpoints as $endpoint) {
            $times = [];
            
            // Test each endpoint multiple times
            for ($i = 0; $i < 10; $i++) {
                $startTime = microtime(true);
                
                $response = $this->get($endpoint);
                
                $endTime = microtime(true);
                $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
                
                $times[] = $responseTime;
                
                // Assert response is successful
                $this->assertTrue($response->status() === 200 || $response->status() === 401, 
                    "Endpoint {$endpoint} should return 200 or 401");
            }
            
            $averageTime = array_sum($times) / count($times);
            $p95Time = $this->calculatePercentile($times, 95);
            $maxTime = max($times);
            
            $responseTimes[$endpoint] = [
                'average_ms' => round($averageTime, 2),
                'p95_ms' => round($p95Time, 2),
                'max_ms' => round($maxTime, 2),
                'min_ms' => round(min($times), 2)
            ];
            
            // Assert performance thresholds
            $this->assertLessThan(300, $averageTime, "Average response time for {$endpoint} should be below 300ms");
            $this->assertLessThan(500, $p95Time, "P95 response time for {$endpoint} should be below 500ms");
        }
        
        Log::info('API response time test completed', $responseTimes);
    }
    
    /**
     * Test database query performance
     */
    public function testDatabaseQueryPerformance(): void
    {
        $queries = [
            'simple_select' => function() {
                return Project::all();
            },
            'with_relations' => function() {
                return Project::with(['tasks', 'client', 'user'])->get();
            },
            'complex_query' => function() {
                return Project::whereHas('tasks', function($query) {
                    $query->where('status', 'active');
                })->with(['tasks' => function($query) {
                    $query->where('progress_percent', '>', 50);
                }])->get();
            },
            'aggregate_query' => function() {
                return Project::selectRaw('COUNT(*) as total, AVG(budget_total) as avg_budget')
                    ->groupBy('tenant_id')
                    ->get();
            }
        ];
        
        $queryTimes = [];
        
        foreach ($queries as $queryName => $queryFunction) {
            $times = [];
            
            // Run each query multiple times
            for ($i = 0; $i < 5; $i++) {
                $startTime = microtime(true);
                
                $result = $queryFunction();
                
                $endTime = microtime(true);
                $queryTime = ($endTime - $startTime) * 1000;
                
                $times[] = $queryTime;
                
                // Assert query returns results
                $this->assertNotNull($result, "Query {$queryName} should return results");
            }
            
            $averageTime = array_sum($times) / count($times);
            $p95Time = $this->calculatePercentile($times, 95);
            
            $queryTimes[$queryName] = [
                'average_ms' => round($averageTime, 2),
                'p95_ms' => round($p95Time, 2),
                'max_ms' => round(max($times), 2),
                'min_ms' => round(min($times), 2)
            ];
            
            // Assert performance thresholds
            $this->assertLessThan(100, $averageTime, "Average query time for {$queryName} should be below 100ms");
            $this->assertLessThan(200, $p95Time, "P95 query time for {$queryName} should be below 200ms");
        }
        
        Log::info('Database query performance test completed', $queryTimes);
    }
    
    /**
     * Test cache performance
     */
    public function testCachePerformance(): void
    {
        $cacheTests = [
            'simple_cache' => function() {
                $key = 'test_simple_' . uniqid();
                $value = $this->faker->paragraph();
                
                $start = microtime(true);
                Cache::put($key, $value, 60);
                $putTime = (microtime(true) - $start) * 1000;
                
                $start = microtime(true);
                $retrieved = Cache::get($key);
                $getTime = (microtime(true) - $start) * 1000;
                
                Cache::forget($key);
                
                return [
                    'put_time_ms' => round($putTime, 2),
                    'get_time_ms' => round($getTime, 2),
                    'success' => $retrieved === $value
                ];
            },
            'cache_hit_rate' => function() {
                $key = 'test_hit_rate_' . uniqid();
                $value = $this->faker->paragraph();
                
                // Put value in cache
                Cache::put($key, $value, 60);
                
                $hitTimes = [];
                $missTimes = [];
                
                // Test cache hits
                for ($i = 0; $i < 10; $i++) {
                    $start = microtime(true);
                    $retrieved = Cache::get($key);
                    $time = (microtime(true) - $start) * 1000;
                    
                    if ($retrieved === $value) {
                        $hitTimes[] = $time;
                    } else {
                        $missTimes[] = $time;
                    }
                }
                
                Cache::forget($key);
                
                return [
                    'hit_times_ms' => array_map(function($t) { return round($t, 2); }, $hitTimes),
                    'miss_times_ms' => array_map(function($t) { return round($t, 2); }, $missTimes),
                    'hit_rate' => count($hitTimes) / (count($hitTimes) + count($missTimes)) * 100
                ];
            }
        ];
        
        $cacheResults = [];
        
        foreach ($cacheTests as $testName => $testFunction) {
            $result = $testFunction();
            $cacheResults[$testName] = $result;
            
            // Assert cache performance
            if (isset($result['put_time_ms'])) {
                $this->assertLessThan(10, $result['put_time_ms'], "Cache put time should be below 10ms");
                $this->assertLessThan(5, $result['get_time_ms'], "Cache get time should be below 5ms");
                $this->assertTrue($result['success'], "Cache should store and retrieve values correctly");
            }
            
            if (isset($result['hit_rate'])) {
                $this->assertGreaterThan(90, $result['hit_rate'], "Cache hit rate should be above 90%");
            }
        }
        
        Log::info('Cache performance test completed', $cacheResults);
    }
    
    /**
     * Test memory usage during operations
     */
    public function testMemoryUsage(): void
    {
        $memoryTests = [
            'baseline' => function() {
                return memory_get_usage(true) / 1024 / 1024; // MB
            },
            'after_data_creation' => function() {
                // Create some test data
                $projects = [];
                for ($i = 0; $i < 100; $i++) {
                    $projects[] = [
                        'name' => $this->faker->sentence(3),
                        'description' => $this->faker->paragraph(),
                        'budget' => $this->faker->numberBetween(1000, 100000),
                        'data' => $this->faker->paragraphs(5)
                    ];
                }
                
                $memory = memory_get_usage(true) / 1024 / 1024;
                unset($projects);
                return $memory;
            },
            'after_database_operations' => function() {
                // Perform database operations
                $projects = Project::with(['tasks', 'client'])->get();
                $tasks = Task::with(['project', 'user'])->get();
                
                $memory = memory_get_usage(true) / 1024 / 1024;
                unset($projects, $tasks);
                return $memory;
            }
        ];
        
        $memoryResults = [];
        
        foreach ($memoryTests as $testName => $testFunction) {
            $memory = $testFunction();
            $memoryResults[$testName] = round($memory, 2);
        }
        
        // Calculate memory efficiency
        $baseline = $memoryResults['baseline'];
        $peak = max($memoryResults);
        $efficiency = ($baseline / $peak) * 100;
        
        $memoryResults['efficiency'] = round($efficiency, 2);
        $memoryResults['peak_memory_mb'] = $peak;
        $memoryResults['baseline_memory_mb'] = $baseline;
        
        // Assert memory efficiency
        $this->assertGreaterThan(50, $efficiency, "Memory efficiency should be above 50%");
        $this->assertLessThan(200, $peak, "Peak memory usage should be below 200MB");
        
        Log::info('Memory usage test completed', $memoryResults);
    }
    
    /**
     * Test performance regression
     */
    public function testPerformanceRegression(): void
    {
        $currentMetrics = $this->performanceService->getAllMetrics();
        $regressions = $this->alertingService->detectPerformanceRegressions();
        
        // Check for regressions
        foreach ($regressions as $regression) {
            $this->assertLessThan(50, $regression['regression_percentage'], 
                "Performance regression for {$regression['metric']} should be below 50%");
        }
        
        // Check current metrics against baselines
        $baselineChecks = [
            'api_response_time' => 300,
            'database_query_time' => 100,
            'memory_usage' => 80,
            'cpu_usage' => 75
        ];
        
        foreach ($baselineChecks as $metric => $threshold) {
            if (isset($currentMetrics[$metric])) {
                $this->assertLessThan($threshold, $currentMetrics[$metric], 
                    "Current {$metric} should be below threshold {$threshold}");
            }
        }
        
        Log::info('Performance regression test completed', [
            'regressions' => $regressions,
            'current_metrics' => $currentMetrics
        ]);
    }
    
    /**
     * Test performance benchmarking
     */
    public function testPerformanceBenchmarking(): void
    {
        $benchmarks = [
            'api_response_time' => [
                'excellent' => 100,
                'good' => 200,
                'acceptable' => 300,
                'poor' => 500
            ],
            'database_query_time' => [
                'excellent' => 10,
                'good' => 25,
                'acceptable' => 50,
                'poor' => 100
            ],
            'memory_usage' => [
                'excellent' => 40,
                'good' => 60,
                'acceptable' => 80,
                'poor' => 95
            ]
        ];
        
        $currentMetrics = $this->performanceService->getAllMetrics();
        $benchmarkResults = [];
        
        foreach ($benchmarks as $metric => $thresholds) {
            if (isset($currentMetrics[$metric])) {
                $value = $currentMetrics[$metric];
                $rating = $this->getPerformanceRating($value, $thresholds);
                
                $benchmarkResults[$metric] = [
                    'value' => $value,
                    'rating' => $rating,
                    'thresholds' => $thresholds
                ];
                
                // Assert minimum acceptable performance
                $this->assertLessThan($thresholds['poor'], $value, 
                    "{$metric} should be below poor threshold");
            }
        }
        
        Log::info('Performance benchmarking test completed', $benchmarkResults);
    }
    
    /**
     * Test performance monitoring service
     */
    public function testPerformanceMonitoringService(): void
    {
        $metrics = $this->performanceService->getAllMetrics();
        
        // Assert all required metrics are present
        $requiredMetrics = [
            'request_count',
            'average_response_time',
            'error_count',
            'memory_usage',
            'cpu_usage',
            'uptime'
        ];
        
        foreach ($requiredMetrics as $metric) {
            $this->assertArrayHasKey($metric, $metrics, "Metric {$metric} should be present");
            $this->assertIsNumeric($metrics[$metric], "Metric {$metric} should be numeric");
        }
        
        // Test dashboard data
        $dashboardData = $this->performanceService->getDashboardData();
        $this->assertIsArray($dashboardData, "Dashboard data should be an array");
        $this->assertArrayHasKey('metrics', $dashboardData, "Dashboard data should have metrics");
        
        Log::info('Performance monitoring service test completed', $metrics);
    }
    
    /**
     * Test performance alerting service
     */
    public function testPerformanceAlertingService(): void
    {
        // Test threshold checking
        $this->alertingService->checkPerformanceThresholds();
        
        // Test recommendations
        $recommendations = $this->alertingService->getPerformanceRecommendations();
        $this->assertIsArray($recommendations, "Recommendations should be an array");
        
        // Test regression detection
        $regressions = $this->alertingService->detectPerformanceRegressions();
        $this->assertIsArray($regressions, "Regressions should be an array");
        
        // Test alerts
        $alerts = $this->alertingService->getPerformanceAlerts();
        $this->assertIsArray($alerts, "Alerts should be an array");
        
        Log::info('Performance alerting service test completed', [
            'recommendations_count' => count($recommendations),
            'regressions_count' => count($regressions),
            'alerts_count' => count($alerts)
        ]);
    }
    
    /**
     * Get performance rating based on thresholds
     */
    private function getPerformanceRating(float $value, array $thresholds): string
    {
        if ($value <= $thresholds['excellent']) {
            return 'excellent';
        } elseif ($value <= $thresholds['good']) {
            return 'good';
        } elseif ($value <= $thresholds['acceptable']) {
            return 'acceptable';
        } else {
            return 'poor';
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
     * Load performance baselines
     */
    private function loadPerformanceBaselines(): void
    {
        $this->performanceBaselines = [
            'api_response_time' => 200,
            'database_query_time' => 50,
            'memory_usage' => 60,
            'cpu_usage' => 40,
            'error_rate' => 2
        ];
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
        
        // Create test projects
        for ($i = 0; $i < 5; $i++) {
            $project = Project::create([
                'name' => "Test Project {$i}",
                'description' => "Test project description {$i}",
                'budget_total' => $this->faker->numberBetween(1000, 100000),
                'user_id' => $user->id,
                'client_id' => $client->id,
                'tenant_id' => 1
            ]);
            
            // Create tasks for each project
            for ($j = 0; $j < 10; $j++) {
                Task::create([
                    'name' => "Test Task {$i}-{$j}",
                    'description' => "Test task description {$i}-{$j}",
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                    'tenant_id' => 1
                ]);
            }
        }
    }
}
