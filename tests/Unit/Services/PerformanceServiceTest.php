<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PerformanceMonitoringService;
use App\Services\MemoryMonitoringService;
use App\Services\NetworkMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PerformanceMonitoringService $performanceService;
    protected MemoryMonitoringService $memoryService;
    protected NetworkMonitoringService $networkService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->performanceService = app(PerformanceMonitoringService::class);
        $this->memoryService = app(MemoryMonitoringService::class);
        $this->networkService = app(NetworkMonitoringService::class);
    }

    public function test_performance_monitoring_service_can_record_page_load_time()
    {
        $this->performanceService->recordPageLoadTime('/test-route', 250.5);
        
        $stats = $this->performanceService->getPerformanceStats();
        
        $this->assertArrayHasKey('page_load_time', $stats);
        $this->assertEquals(250.5, $stats['page_load_time']['avg']);
        $this->assertEquals(250.5, $stats['page_load_time']['min']);
        $this->assertEquals(250.5, $stats['page_load_time']['max']);
    }

    public function test_performance_monitoring_service_can_record_api_response_time()
    {
        $this->performanceService->recordApiResponseTime('/api/test', 150.3);
        
        $stats = $this->performanceService->getPerformanceStats();
        
        $this->assertArrayHasKey('api_response_time', $stats);
        $this->assertEquals(150.3, $stats['api_response_time']['avg']);
        $this->assertEquals(150.3, $stats['api_response_time']['min']);
        $this->assertEquals(150.3, $stats['api_response_time']['max']);
    }

    public function test_performance_monitoring_service_can_record_memory_usage()
    {
        $this->performanceService->recordMemoryUsage(1024 * 1024); // 1MB
        
        $stats = $this->performanceService->getPerformanceStats();
        
        $this->assertArrayHasKey('memory_usage', $stats);
        $this->assertArrayHasKey('current', $stats['memory_usage']);
        $this->assertArrayHasKey('peak', $stats['memory_usage']);
    }

    public function test_performance_monitoring_service_can_record_database_query_time()
    {
        $this->performanceService->recordDatabaseQueryTime('SELECT * FROM users', 50.2);
        
        $stats = $this->performanceService->getPerformanceStats();
        
        $this->assertArrayHasKey('database_query_time', $stats);
        $this->assertEquals(50.2, $stats['database_query_time']['avg']);
    }

    public function test_performance_monitoring_service_can_record_cache_hit_ratio()
    {
        $this->performanceService->recordCacheHitRatio('test-key', true);
        $this->performanceService->recordCacheHitRatio('test-key', false);
        
        $stats = $this->performanceService->getPerformanceStats();
        
        $this->assertArrayHasKey('cache_hit_ratio', $stats);
        $this->assertEquals(50.0, $stats['cache_hit_ratio']['hit_ratio']);
    }

    public function test_performance_monitoring_service_can_record_error()
    {
        $this->performanceService->recordError('Test error', 'test-context');
        
        $stats = $this->performanceService->getPerformanceStats();
        
        $this->assertArrayHasKey('error_rate', $stats);
        $this->assertGreaterThan(0, $stats['error_rate']['error_count']);
    }

    public function test_performance_monitoring_service_can_record_throughput()
    {
        $this->performanceService->recordThroughput('test-operation', 100);
        
        $stats = $this->performanceService->getPerformanceStats();
        
        $this->assertArrayHasKey('throughput', $stats);
        $this->assertEquals(100, $stats['throughput']['test-operation']);
    }

    public function test_performance_monitoring_service_can_get_recommendations()
    {
        // Record slow page load time
        $this->performanceService->recordPageLoadTime('/slow-route', 600.0);
        
        $recommendations = $this->performanceService->getPerformanceRecommendations();
        
        $this->assertIsArray($recommendations);
        $this->assertNotEmpty($recommendations);
        
        $pageLoadRecommendation = collect($recommendations)->firstWhere('type', 'page_load_time');
        $this->assertNotNull($pageLoadRecommendation);
        $this->assertEquals('high', $pageLoadRecommendation['priority']);
    }

    public function test_performance_monitoring_service_can_get_thresholds()
    {
        $thresholds = $this->performanceService->getPerformanceThresholds();
        
        $this->assertArrayHasKey('page_load_time', $thresholds);
        $this->assertArrayHasKey('api_response_time', $thresholds);
        $this->assertArrayHasKey('memory_usage', $thresholds);
        $this->assertEquals(500, $thresholds['page_load_time']);
        $this->assertEquals(300, $thresholds['api_response_time']);
    }

    public function test_performance_monitoring_service_can_set_thresholds()
    {
        $newThresholds = [
            'page_load_time' => 1000,
            'api_response_time' => 500,
        ];
        
        $this->performanceService->setPerformanceThresholds($newThresholds);
        
        $thresholds = $this->performanceService->getPerformanceThresholds();
        $this->assertEquals(1000, $thresholds['page_load_time']);
        $this->assertEquals(500, $thresholds['api_response_time']);
    }

    public function test_memory_monitoring_service_can_get_current_memory_usage()
    {
        $memoryUsage = $this->memoryService->getCurrentMemoryUsage();
        
        $this->assertArrayHasKey('current_usage', $memoryUsage);
        $this->assertArrayHasKey('peak_usage', $memoryUsage);
        $this->assertArrayHasKey('memory_limit', $memoryUsage);
        $this->assertArrayHasKey('usage_percentage', $memoryUsage);
        $this->assertIsNumeric($memoryUsage['current_usage']);
        $this->assertIsNumeric($memoryUsage['peak_usage']);
    }

    public function test_memory_monitoring_service_can_record_memory_usage()
    {
        $this->memoryService->recordMemoryUsage();
        
        $stats = $this->memoryService->getMemoryStats();
        
        $this->assertIsArray($stats);
    }

    public function test_memory_monitoring_service_can_get_recommendations()
    {
        $recommendations = $this->memoryService->getMemoryRecommendations();
        
        $this->assertIsArray($recommendations);
    }

    public function test_memory_monitoring_service_can_get_thresholds()
    {
        $thresholds = $this->memoryService->getMemoryThresholds();
        
        $this->assertArrayHasKey('warning', $thresholds);
        $this->assertArrayHasKey('critical', $thresholds);
        $this->assertEquals(70, $thresholds['warning']);
        $this->assertEquals(85, $thresholds['critical']);
    }

    public function test_memory_monitoring_service_can_set_thresholds()
    {
        $newThresholds = [
            'warning' => 60,
            'critical' => 80,
        ];
        
        $this->memoryService->setMemoryThresholds($newThresholds);
        
        $thresholds = $this->memoryService->getMemoryThresholds();
        $this->assertEquals(60, $thresholds['warning']);
        $this->assertEquals(80, $thresholds['critical']);
    }

    public function test_memory_monitoring_service_can_force_garbage_collection()
    {
        $result = $this->memoryService->forceGarbageCollection();
        
        $this->assertArrayHasKey('before_usage', $result);
        $this->assertArrayHasKey('after_usage', $result);
        $this->assertArrayHasKey('freed_memory', $result);
        $this->assertIsNumeric($result['before_usage']);
        $this->assertIsNumeric($result['after_usage']);
    }

    public function test_network_monitoring_service_can_monitor_api_endpoint()
    {
        // Mock a successful response
        $result = $this->networkService->monitorApiEndpoint('https://httpbin.org/get');
        
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('response_time', $result);
        $this->assertArrayHasKey('success', $result);
        $this->assertEquals('https://httpbin.org/get', $result['url']);
    }

    public function test_network_monitoring_service_can_record_response_time()
    {
        $this->networkService->recordResponseTime('https://test.com', 200.5);
        
        $stats = $this->networkService->getNetworkStats();
        
        $this->assertArrayHasKey('response_time', $stats);
        $this->assertEquals(200.5, $stats['response_time']['avg']);
    }

    public function test_network_monitoring_service_can_record_error()
    {
        $this->networkService->recordError('https://test.com', 'Connection timeout');
        
        $stats = $this->networkService->getNetworkStats();
        
        $this->assertArrayHasKey('error_rate', $stats);
        $this->assertGreaterThan(0, $stats['error_rate']['error_count']);
    }

    public function test_network_monitoring_service_can_record_timeout()
    {
        $this->networkService->recordTimeout('https://test.com', 30.0);
        
        $stats = $this->networkService->getNetworkStats();
        
        $this->assertArrayHasKey('timeouts', $stats);
        $this->assertEquals(1, $stats['timeouts']['count']);
    }

    public function test_network_monitoring_service_can_record_throughput()
    {
        $this->networkService->recordThroughput('https://test.com', 50);
        
        $stats = $this->networkService->getNetworkStats();
        
        $this->assertArrayHasKey('throughput', $stats);
        $this->assertEquals(50, $stats['throughput']['avg']);
    }

    public function test_network_monitoring_service_can_get_recommendations()
    {
        // Record slow response time
        $this->networkService->recordResponseTime('https://slow.com', 500.0);
        
        $recommendations = $this->networkService->getNetworkRecommendations();
        
        $this->assertIsArray($recommendations);
        $this->assertNotEmpty($recommendations);
        
        $responseTimeRecommendation = collect($recommendations)->firstWhere('type', 'high_response_time');
        $this->assertNotNull($responseTimeRecommendation);
        $this->assertEquals('high', $responseTimeRecommendation['priority']);
    }

    public function test_network_monitoring_service_can_get_thresholds()
    {
        $thresholds = $this->networkService->getNetworkThresholds();
        
        $this->assertArrayHasKey('response_time', $thresholds);
        $this->assertArrayHasKey('timeout', $thresholds);
        $this->assertArrayHasKey('error_rate', $thresholds);
        $this->assertEquals(300, $thresholds['response_time']);
        $this->assertEquals(30, $thresholds['timeout']);
    }

    public function test_network_monitoring_service_can_set_thresholds()
    {
        $newThresholds = [
            'response_time' => 500,
            'timeout' => 60,
        ];
        
        $this->networkService->setNetworkThresholds($newThresholds);
        
        $thresholds = $this->networkService->getNetworkThresholds();
        $this->assertEquals(500, $thresholds['response_time']);
        $this->assertEquals(60, $thresholds['timeout']);
    }

    public function test_network_monitoring_service_can_test_connectivity()
    {
        $result = $this->networkService->testConnectivity('https://httpbin.org/get');
        
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('response_time', $result);
        $this->assertEquals('https://httpbin.org/get', $result['url']);
    }

    public function test_network_monitoring_service_can_get_health_status()
    {
        $status = $this->networkService->getNetworkHealthStatus();
        
        $this->assertArrayHasKey('health_score', $status);
        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('recommendations_count', $status);
        $this->assertIsNumeric($status['health_score']);
        $this->assertContains($status['status'], ['healthy', 'warning', 'critical']);
    }

    public function test_performance_monitoring_service_can_clear_metrics()
    {
        // Record some metrics
        $this->performanceService->recordPageLoadTime('/test', 100.0);
        $this->performanceService->recordApiResponseTime('/api/test', 50.0);
        
        // Clear metrics
        $this->performanceService->clearMetrics();
        
        $stats = $this->performanceService->getPerformanceStats();
        
        $this->assertEmpty($stats);
    }

    public function test_memory_monitoring_service_can_clear_history()
    {
        // Record some memory usage
        $this->memoryService->recordMemoryUsage();
        
        // Clear history
        $this->memoryService->clearHistory();
        
        $stats = $this->memoryService->getMemoryStats();
        
        $this->assertEmpty($stats);
    }

    public function test_network_monitoring_service_can_clear_history()
    {
        // Record some network data
        $this->networkService->recordResponseTime('https://test.com', 100.0);
        
        // Clear history
        $this->networkService->clearHistory();
        
        $stats = $this->networkService->getNetworkStats();
        
        $this->assertEmpty($stats);
    }

    public function test_performance_monitoring_service_can_export_data()
    {
        // Record some metrics
        $this->performanceService->recordPageLoadTime('/test', 100.0);
        
        $data = $this->performanceService->exportPerformanceData();
        
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('metrics', $data);
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('recommendations', $data);
        $this->assertArrayHasKey('thresholds', $data);
    }

    public function test_memory_monitoring_service_can_export_data()
    {
        $data = $this->memoryService->exportMemoryData();
        
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('current_usage', $data);
        $this->assertArrayHasKey('history', $data);
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('recommendations', $data);
        $this->assertArrayHasKey('thresholds', $data);
    }

    public function test_network_monitoring_service_can_export_data()
    {
        $data = $this->networkService->exportNetworkData();
        
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('history', $data);
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('recommendations', $data);
        $this->assertArrayHasKey('thresholds', $data);
    }
}
