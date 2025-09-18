<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserDashboard;
use App\Models\DashboardWidget;
use App\Models\DashboardMetric;
use App\Models\DashboardAlert;
use App\Models\Project;
use App\Models\Task;
use App\Models\RFI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PerformanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $project;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = \App\Models\Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.com',
            'is_active' => true
        ]);
        
        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => 'project_manager',
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create test project
        $this->project = Project::create([
            'name' => 'Test Project',
            'description' => 'Test project description',
            'status' => 'active',
            'budget' => 100000,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create performance test data
        $this->createPerformanceTestData();
        
        // Authenticate user
        Sanctum::actingAs($this->user);
    }

    protected function createPerformanceTestData(): void
    {
        // Create widgets
        DashboardWidget::create([
            'name' => 'Project Overview',
            'code' => 'project_overview',
            'type' => 'card',
            'category' => 'overview',
            'description' => 'Project overview widget',
            'config' => json_encode(['default_size' => 'large']),
            'permissions' => json_encode(['project_manager']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        // Create metrics
        DashboardMetric::create([
            'name' => 'Project Progress',
            'code' => 'project_progress',
            'description' => 'Overall project progress percentage',
            'unit' => '%',
            'type' => 'gauge',
            'is_active' => true,
            'permissions' => json_encode(['project_manager']),
            'tenant_id' => $this->tenant->id
        ]);

        // Create large dataset for performance testing
        $this->createLargeDataset();
    }

    protected function createLargeDataset(): void
    {
        // Create 5000 tasks
        for ($i = 1; $i <= 5000; $i++) {
            Task::create([
                'title' => "Task {$i}",
                'description' => "Description for task {$i}",
                'status' => ['pending', 'in_progress', 'completed'][array_rand(['pending', 'in_progress', 'completed'])],
                'priority' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                'due_date' => now()->addDays(rand(1, 30)),
                'assigned_to' => $this->user->id,
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Create 2500 RFIs
        for ($i = 1; $i <= 2500; $i++) {
            RFI::create([
                'subject' => "RFI {$i}",
                'description' => "Description for RFI {$i}",
                'status' => ['open', 'answered', 'closed'][array_rand(['open', 'answered', 'closed'])],
                'priority' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                'due_date' => now()->addDays(rand(1, 14)),
                'discipline' => ['construction', 'electrical', 'mechanical'][array_rand(['construction', 'electrical', 'mechanical'])],
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Create 1000 alerts
        for ($i = 1; $i <= 1000; $i++) {
            DashboardAlert::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'message' => "Alert {$i}",
                'type' => ['project', 'budget', 'schedule', 'quality'][array_rand(['project', 'budget', 'schedule', 'quality'])],
                'severity' => ['low', 'medium', 'high', 'critical'][array_rand(['low', 'medium', 'high', 'critical'])],
                'is_read' => rand(0, 1) == 1,
                'triggered_at' => now()->subDays(rand(0, 30)),
                'context' => json_encode(['project_id' => $this->project->id])
            ]);
        }

        // Create metric values
        $metric = DashboardMetric::first();
        for ($i = 1; $i <= 1000; $i++) {
            \App\Models\DashboardMetricValue::create([
                'metric_id' => $metric->id,
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'value' => rand(1, 100),
                'timestamp' => now()->subDays(rand(0, 30)),
                'context' => json_encode(['test' => true])
            ]);
        }
    }

    /** @test */
    public function it_can_handle_high_load_dashboard_requests()
    {
        $concurrentRequests = 50;
        $startTime = microtime(true);
        
        $responses = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $responses[] = $this->getJson('/api/v1/dashboard/role-based');
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $averageTime = $totalTime / $concurrentRequests;
        
        // Verify all requests succeeded
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // Performance assertions
        $this->assertLessThan(5000, $totalTime, '50 concurrent requests should complete in less than 5000ms');
        $this->assertLessThan(100, $averageTime, 'Average response time should be less than 100ms');
        
        echo "\nHigh Load Test Results:\n";
        echo "Total Time: {$totalTime}ms\n";
        echo "Average Time: {$averageTime}ms\n";
        echo "Requests per Second: " . round(1000 / $averageTime, 2) . "\n";
    }

    /** @test */
    public function it_can_handle_widget_data_performance()
    {
        $widget = DashboardWidget::first();
        
        $concurrentRequests = 20;
        $startTime = microtime(true);
        
        $responses = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $responses[] = $this->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $averageTime = $totalTime / $concurrentRequests;
        
        // Verify all requests succeeded
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // Performance assertions
        $this->assertLessThan(2000, $totalTime, '20 widget data requests should complete in less than 2000ms');
        $this->assertLessThan(100, $averageTime, 'Average widget data response time should be less than 100ms');
        
        echo "\nWidget Data Performance Test Results:\n";
        echo "Total Time: {$totalTime}ms\n";
        echo "Average Time: {$averageTime}ms\n";
    }

    /** @test */
    public function it_can_handle_metrics_performance()
    {
        $concurrentRequests = 15;
        $startTime = microtime(true);
        
        $responses = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $responses[] = $this->getJson('/api/v1/dashboard/metrics');
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $averageTime = $totalTime / $concurrentRequests;
        
        // Verify all requests succeeded
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // Performance assertions
        $this->assertLessThan(1500, $totalTime, '15 metrics requests should complete in less than 1500ms');
        $this->assertLessThan(100, $averageTime, 'Average metrics response time should be less than 100ms');
        
        echo "\nMetrics Performance Test Results:\n";
        echo "Total Time: {$totalTime}ms\n";
        echo "Average Time: {$averageTime}ms\n";
    }

    /** @test */
    public function it_can_handle_alerts_performance()
    {
        $concurrentRequests = 10;
        $startTime = microtime(true);
        
        $responses = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $responses[] = $this->getJson('/api/v1/dashboard/alerts');
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $averageTime = $totalTime / $concurrentRequests;
        
        // Verify all requests succeeded
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // Performance assertions
        $this->assertLessThan(1000, $totalTime, '10 alerts requests should complete in less than 1000ms');
        $this->assertLessThan(100, $averageTime, 'Average alerts response time should be less than 100ms');
        
        echo "\nAlerts Performance Test Results:\n";
        echo "Total Time: {$totalTime}ms\n";
        echo "Average Time: {$averageTime}ms\n";
    }

    /** @test */
    public function it_can_handle_database_query_optimization()
    {
        // Test query count for dashboard loading
        DB::enableQueryLog();
        
        $response = $this->getJson('/api/v1/dashboard/role-based');
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        $response->assertStatus(200);
        $this->assertLessThan(25, $queryCount, 'Dashboard should use less than 25 database queries');
        
        echo "\nDatabase Query Optimization Results:\n";
        echo "Total Queries: {$queryCount}\n";
        
        // Analyze query types
        $queryTypes = [];
        foreach ($queries as $query) {
            $sql = $query['query'];
            if (strpos($sql, 'SELECT') === 0) {
                $queryTypes['SELECT'] = ($queryTypes['SELECT'] ?? 0) + 1;
            } elseif (strpos($sql, 'INSERT') === 0) {
                $queryTypes['INSERT'] = ($queryTypes['INSERT'] ?? 0) + 1;
            } elseif (strpos($sql, 'UPDATE') === 0) {
                $queryTypes['UPDATE'] = ($queryTypes['UPDATE'] ?? 0) + 1;
            } elseif (strpos($sql, 'DELETE') === 0) {
                $queryTypes['DELETE'] = ($queryTypes['DELETE'] ?? 0) + 1;
            }
        }
        
        foreach ($queryTypes as $type => $count) {
            echo "{$type} Queries: {$count}\n";
        }
        
        DB::disableQueryLog();
    }

    /** @test */
    public function it_can_handle_memory_usage_optimization()
    {
        $startMemory = memory_get_usage();
        
        // Perform multiple operations
        for ($i = 0; $i < 20; $i++) {
            $response = $this->getJson('/api/v1/dashboard/role-based');
            $response->assertStatus(200);
            
            $widgetsResponse = $this->getJson('/api/v1/dashboard/widgets');
            $widgetsResponse->assertStatus(200);
            
            $metricsResponse = $this->getJson('/api/v1/dashboard/metrics');
            $metricsResponse->assertStatus(200);
            
            $alertsResponse = $this->getJson('/api/v1/dashboard/alerts');
            $alertsResponse->assertStatus(200);
        }
        
        $endMemory = memory_get_usage();
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB
        
        $this->assertLessThan(200, $memoryUsed, 'Memory usage should be less than 200MB for 20 operations');
        
        echo "\nMemory Usage Optimization Results:\n";
        echo "Memory Used: {$memoryUsed}MB\n";
        echo "Peak Memory: " . (memory_get_peak_usage() / 1024 / 1024) . "MB\n";
    }

    /** @test */
    public function it_can_handle_cache_performance()
    {
        // Clear cache first
        Cache::flush();
        
        // First request (cache miss)
        $startTime = microtime(true);
        $response1 = $this->getJson('/api/v1/dashboard/role-based');
        $endTime = microtime(true);
        $firstRequestTime = ($endTime - $startTime) * 1000;
        
        $response1->assertStatus(200);
        
        // Second request (cache hit)
        $startTime = microtime(true);
        $response2 = $this->getJson('/api/v1/dashboard/role-based');
        $endTime = microtime(true);
        $secondRequestTime = ($endTime - $startTime) * 1000;
        
        $response2->assertStatus(200);
        
        // Cache should improve performance
        $this->assertLessThan($firstRequestTime, $secondRequestTime, 'Cached request should be faster');
        
        echo "\nCache Performance Results:\n";
        echo "First Request (Cache Miss): {$firstRequestTime}ms\n";
        echo "Second Request (Cache Hit): {$secondRequestTime}ms\n";
        echo "Performance Improvement: " . round(($firstRequestTime - $secondRequestTime) / $firstRequestTime * 100, 2) . "%\n";
    }

    /** @test */
    public function it_can_handle_concurrent_widget_operations()
    {
        $widget = DashboardWidget::first();
        $concurrentOperations = 10;
        
        $startTime = microtime(true);
        
        // Add widgets concurrently
        $responses = [];
        for ($i = 0; $i < $concurrentOperations; $i++) {
            $responses[] = $this->postJson('/api/v1/dashboard/widgets', [
                'widget_id' => $widget->id,
                'config' => [
                    'title' => "Concurrent Widget {$i}",
                    'size' => 'medium'
                ]
            ]);
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        
        // Verify all operations succeeded
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        $this->assertLessThan(3000, $totalTime, '10 concurrent widget operations should complete in less than 3000ms');
        
        echo "\nConcurrent Widget Operations Results:\n";
        echo "Total Time: {$totalTime}ms\n";
        echo "Average Time per Operation: " . ($totalTime / $concurrentOperations) . "ms\n";
    }

    /** @test */
    public function it_can_handle_large_dataset_queries()
    {
        // Test with large dataset
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/dashboard/role-based');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(2000, $executionTime, 'Dashboard should load in less than 2000ms with large dataset');
        
        // Test widget data with large dataset
        $widget = DashboardWidget::first();
        
        $startTime = microtime(true);
        
        $dataResponse = $this->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");
        
        $endTime = microtime(true);
        $dataExecutionTime = ($endTime - $startTime) * 1000;
        
        $dataResponse->assertStatus(200);
        $this->assertLessThan(1000, $dataExecutionTime, 'Widget data should load in less than 1000ms with large dataset');
        
        echo "\nLarge Dataset Query Results:\n";
        echo "Dashboard Load Time: {$executionTime}ms\n";
        echo "Widget Data Load Time: {$dataExecutionTime}ms\n";
    }

    /** @test */
    public function it_can_handle_stress_testing()
    {
        $stressTestCycles = 5;
        $operationsPerCycle = 10;
        
        $totalStartTime = microtime(true);
        
        for ($cycle = 0; $cycle < $stressTestCycles; $cycle++) {
            $cycleStartTime = microtime(true);
            
            // Perform multiple operations
            for ($i = 0; $i < $operationsPerCycle; $i++) {
                // Dashboard request
                $dashboardResponse = $this->getJson('/api/v1/dashboard/role-based');
                $dashboardResponse->assertStatus(200);
                
                // Widgets request
                $widgetsResponse = $this->getJson('/api/v1/dashboard/widgets');
                $widgetsResponse->assertStatus(200);
                
                // Metrics request
                $metricsResponse = $this->getJson('/api/v1/dashboard/metrics');
                $metricsResponse->assertStatus(200);
                
                // Alerts request
                $alertsResponse = $this->getJson('/api/v1/dashboard/alerts');
                $alertsResponse->assertStatus(200);
            }
            
            $cycleEndTime = microtime(true);
            $cycleTime = ($cycleEndTime - $cycleStartTime) * 1000;
            
            echo "\nStress Test Cycle " . ($cycle + 1) . " Results:\n";
            echo "Cycle Time: {$cycleTime}ms\n";
            echo "Operations per Second: " . round(($operationsPerCycle * 4) / ($cycleTime / 1000), 2) . "\n";
        }
        
        $totalEndTime = microtime(true);
        $totalTime = ($totalEndTime - $totalStartTime) * 1000;
        
        echo "\nOverall Stress Test Results:\n";
        echo "Total Time: {$totalTime}ms\n";
        echo "Total Operations: " . ($stressTestCycles * $operationsPerCycle * 4) . "\n";
        echo "Overall Operations per Second: " . round(($stressTestCycles * $operationsPerCycle * 4) / ($totalTime / 1000), 2) . "\n";
        
        $this->assertLessThan(10000, $totalTime, 'Stress test should complete in less than 10000ms');
    }

    /** @test */
    public function it_can_handle_memory_leak_prevention()
    {
        $initialMemory = memory_get_usage();
        
        // Perform many operations to test for memory leaks
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/v1/dashboard/role-based');
            $response->assertStatus(200);
            
            // Force garbage collection every 10 iterations
            if ($i % 10 === 0) {
                gc_collect_cycles();
            }
        }
        
        $finalMemory = memory_get_usage();
        $memoryIncrease = ($finalMemory - $initialMemory) / 1024 / 1024; // Convert to MB
        
        // Memory increase should be reasonable (less than 50MB for 100 operations)
        $this->assertLessThan(50, $memoryIncrease, 'Memory increase should be less than 50MB for 100 operations');
        
        echo "\nMemory Leak Prevention Results:\n";
        echo "Initial Memory: " . ($initialMemory / 1024 / 1024) . "MB\n";
        echo "Final Memory: " . ($finalMemory / 1024 / 1024) . "MB\n";
        echo "Memory Increase: {$memoryIncrease}MB\n";
        echo "Peak Memory: " . (memory_get_peak_usage() / 1024 / 1024) . "MB\n";
    }

    /** @test */
    public function it_can_handle_response_time_consistency()
    {
        $responseTimes = [];
        $testCount = 20;
        
        for ($i = 0; $i < $testCount; $i++) {
            $startTime = microtime(true);
            
            $response = $this->getJson('/api/v1/dashboard/role-based');
            
            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000;
            
            $response->assertStatus(200);
            $responseTimes[] = $responseTime;
        }
        
        // Calculate statistics
        $averageTime = array_sum($responseTimes) / count($responseTimes);
        $minTime = min($responseTimes);
        $maxTime = max($responseTimes);
        $standardDeviation = sqrt(array_sum(array_map(function($x) use ($averageTime) {
            return pow($x - $averageTime, 2);
        }, $responseTimes)) / count($responseTimes));
        
        // Response times should be consistent
        $this->assertLessThan(500, $averageTime, 'Average response time should be less than 500ms');
        $this->assertLessThan(200, $standardDeviation, 'Response time standard deviation should be less than 200ms');
        
        echo "\nResponse Time Consistency Results:\n";
        echo "Average Time: {$averageTime}ms\n";
        echo "Min Time: {$minTime}ms\n";
        echo "Max Time: {$maxTime}ms\n";
        echo "Standard Deviation: {$standardDeviation}ms\n";
        echo "Coefficient of Variation: " . round(($standardDeviation / $averageTime) * 100, 2) . "%\n";
    }
}
