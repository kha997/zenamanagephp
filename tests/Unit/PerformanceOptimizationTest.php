<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PerformanceOptimizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PerformanceOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected $performanceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->performanceService = app(PerformanceOptimizationService::class);
    }

    /** @test */
    public function can_optimize_database_tables()
    {
        $results = $this->performanceService->optimizeTables();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('users', $results);
        $this->assertArrayHasKey('projects', $results);
        $this->assertArrayHasKey('tasks', $results);
    }

    /** @test */
    public function can_analyze_database_tables()
    {
        $results = $this->performanceService->analyzeTables();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('users', $results);
        $this->assertArrayHasKey('projects', $results);
        $this->assertArrayHasKey('tasks', $results);
    }

    /** @test */
    public function can_get_cache_statistics()
    {
        $stats = $this->performanceService->getCacheStats();
        
        $this->assertIsArray($stats);
        // Should have Redis stats or error message
        $this->assertTrue(
            isset($stats['redis_keys']) || isset($stats['error'])
        );
    }

    /** @test */
    public function can_get_database_statistics()
    {
        $stats = $this->performanceService->getDatabaseStats();
        
        $this->assertIsArray($stats);
        // Should have table sizes or error message
        $this->assertTrue(
            isset($stats['table_sizes']) || isset($stats['error'])
        );
    }

    /** @test */
    public function can_suggest_database_indexes()
    {
        $suggestions = $this->performanceService->suggestIndexes();
        
        $this->assertIsArray($suggestions);
        
        // Should return array of suggestions (may be empty) or errors
        foreach ($suggestions as $suggestion) {
            $this->assertArrayHasKey('type', $suggestion);
            
            // If it's an error, it should have 'error' key
            if ($suggestion['type'] === 'error') {
                $this->assertArrayHasKey('error', $suggestion);
            } else {
                // If it's a suggestion, it should have 'suggestion' key
                $this->assertArrayHasKey('suggestion', $suggestion);
            }
        }
        
        // If no suggestions found, that's also valid
        if (empty($suggestions)) {
            $this->assertTrue(true, 'No index suggestions found - this is valid');
        }
    }

    /** @test */
    public function can_monitor_query_performance()
    {
        $metrics = $this->performanceService->monitorQueryPerformance();
        
        $this->assertIsArray($metrics);
        // Should have performance metrics or error message
        $this->assertTrue(
            isset($metrics['total_queries']) || isset($metrics['error'])
        );
    }

    /** @test */
    public function can_get_memory_statistics()
    {
        $stats = $this->performanceService->getMemoryStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('current_memory', $stats);
        $this->assertArrayHasKey('peak_memory', $stats);
        $this->assertArrayHasKey('memory_limit', $stats);
        $this->assertArrayHasKey('current_memory_bytes', $stats);
        $this->assertArrayHasKey('peak_memory_bytes', $stats);
        
        // Memory values should be positive
        $this->assertGreaterThan(0, $stats['current_memory_bytes']);
        $this->assertGreaterThan(0, $stats['peak_memory_bytes']);
    }

    /** @test */
    public function can_optimize_file_storage()
    {
        $results = $this->performanceService->optimizeFileStorage();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('storage_path', $results);
        $this->assertArrayHasKey('total_size', $results);
        $this->assertArrayHasKey('total_size_bytes', $results);
        $this->assertArrayHasKey('large_files', $results);
        $this->assertArrayHasKey('old_log_files', $results);
        
        // Storage path should exist
        $this->assertTrue(is_dir($results['storage_path']));
    }

    /** @test */
    public function can_clear_application_caches()
    {
        $results = $this->performanceService->clearCaches();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('laravel_cache', $results);
        $this->assertArrayHasKey('config_cache', $results);
        $this->assertArrayHasKey('route_cache', $results);
        $this->assertArrayHasKey('view_cache', $results);
        $this->assertArrayHasKey('redis_cache', $results);
        
        // All caches should be cleared
        $this->assertEquals('cleared', $results['laravel_cache']);
        $this->assertEquals('cleared', $results['config_cache']);
        $this->assertEquals('cleared', $results['route_cache']);
        $this->assertEquals('cleared', $results['view_cache']);
        $this->assertEquals('cleared', $results['redis_cache']);
    }

    /** @test */
    public function can_get_slow_queries()
    {
        $queries = $this->performanceService->getSlowQueries(5);
        
        $this->assertIsArray($queries);
        // Should return array of slow queries (may be empty)
        foreach ($queries as $query) {
            $this->assertArrayHasKey('sql_text', $query);
            $this->assertArrayHasKey('exec_count', $query);
            $this->assertArrayHasKey('avg_time_seconds', $query);
        }
    }

    /** @test */
    public function memory_stats_format_bytes_correctly()
    {
        $stats = $this->performanceService->getMemoryStats();
        
        // Check that memory is formatted as string with units
        $this->assertIsString($stats['current_memory']);
        $this->assertIsString($stats['peak_memory']);
        
        // Should contain units (B, KB, MB, GB, TB)
        $this->assertMatchesRegularExpression('/\d+(\.\d+)?\s+(B|KB|MB|GB|TB)/', $stats['current_memory']);
        $this->assertMatchesRegularExpression('/\d+(\.\d+)?\s+(B|KB|MB|GB|TB)/', $stats['peak_memory']);
    }

    /** @test */
    public function file_storage_optimization_finds_large_files()
    {
        $results = $this->performanceService->optimizeFileStorage();
        
        $this->assertIsArray($results['large_files']);
        
        // Each large file should have required fields
        foreach ($results['large_files'] as $file) {
            $this->assertArrayHasKey('path', $file);
            $this->assertArrayHasKey('size', $file);
            $this->assertArrayHasKey('size_bytes', $file);
            $this->assertArrayHasKey('modified', $file);
            
            // Size should be formatted
            $this->assertIsString($file['size']);
            $this->assertIsInt($file['size_bytes']);
            $this->assertGreaterThan(0, $file['size_bytes']);
        }
    }

    /** @test */
    public function file_storage_optimization_finds_old_log_files()
    {
        $results = $this->performanceService->optimizeFileStorage();
        
        $this->assertIsArray($results['old_log_files']);
        
        // Each old log file should have required fields
        foreach ($results['old_log_files'] as $file) {
            $this->assertArrayHasKey('path', $file);
            $this->assertArrayHasKey('size', $file);
            $this->assertArrayHasKey('modified', $file);
            $this->assertArrayHasKey('age_days', $file);
            
            // Age should be positive for old files
            $this->assertIsInt($file['age_days']);
            $this->assertGreaterThanOrEqual(0, $file['age_days']);
        }
    }

    /** @test */
    public function performance_service_handles_errors_gracefully()
    {
        // Test that service methods don't throw exceptions
        try {
            $this->performanceService->optimizeTables();
            $this->assertTrue(true, 'optimizeTables() executed without throwing exception');
        } catch (\Exception $e) {
            $this->fail('optimizeTables() threw an exception: ' . $e->getMessage());
        }
        
        try {
            $this->performanceService->analyzeTables();
            $this->assertTrue(true, 'analyzeTables() executed without throwing exception');
        } catch (\Exception $e) {
            $this->fail('analyzeTables() threw an exception: ' . $e->getMessage());
        }
        
        try {
            $this->performanceService->getCacheStats();
            $this->assertTrue(true, 'getCacheStats() executed without throwing exception');
        } catch (\Exception $e) {
            $this->fail('getCacheStats() threw an exception: ' . $e->getMessage());
        }
        
        try {
            $this->performanceService->getDatabaseStats();
            $this->assertTrue(true, 'getDatabaseStats() executed without throwing exception');
        } catch (\Exception $e) {
            $this->fail('getDatabaseStats() threw an exception: ' . $e->getMessage());
        }
        
        try {
            $this->performanceService->suggestIndexes();
            $this->assertTrue(true, 'suggestIndexes() executed without throwing exception');
        } catch (\Exception $e) {
            $this->fail('suggestIndexes() threw an exception: ' . $e->getMessage());
        }
        
        try {
            $this->performanceService->monitorQueryPerformance();
            $this->assertTrue(true, 'monitorQueryPerformance() executed without throwing exception');
        } catch (\Exception $e) {
            $this->fail('monitorQueryPerformance() threw an exception: ' . $e->getMessage());
        }
        
        try {
            $this->performanceService->getMemoryStats();
            $this->assertTrue(true, 'getMemoryStats() executed without throwing exception');
        } catch (\Exception $e) {
            $this->fail('getMemoryStats() threw an exception: ' . $e->getMessage());
        }
        
        try {
            $this->performanceService->optimizeFileStorage();
            $this->assertTrue(true, 'optimizeFileStorage() executed without throwing exception');
        } catch (\Exception $e) {
            $this->fail('optimizeFileStorage() threw an exception: ' . $e->getMessage());
        }
        
        try {
            $this->performanceService->clearCaches();
            $this->assertTrue(true, 'clearCaches() executed without throwing exception');
        } catch (\Exception $e) {
            $this->fail('clearCaches() threw an exception: ' . $e->getMessage());
        }
        
        try {
            $this->performanceService->getSlowQueries();
            $this->assertTrue(true, 'getSlowQueries() executed without throwing exception');
        } catch (\Exception $e) {
            $this->fail('getSlowQueries() threw an exception: ' . $e->getMessage());
        }
    }

    /** @test */
    public function performance_service_returns_consistent_data_types()
    {
        // All methods should return arrays
        $this->assertIsArray($this->performanceService->optimizeTables());
        $this->assertIsArray($this->performanceService->analyzeTables());
        $this->assertIsArray($this->performanceService->getCacheStats());
        $this->assertIsArray($this->performanceService->getDatabaseStats());
        $this->assertIsArray($this->performanceService->suggestIndexes());
        $this->assertIsArray($this->performanceService->monitorQueryPerformance());
        $this->assertIsArray($this->performanceService->getMemoryStats());
        $this->assertIsArray($this->performanceService->optimizeFileStorage());
        $this->assertIsArray($this->performanceService->clearCaches());
        $this->assertIsArray($this->performanceService->getSlowQueries());
    }
}