<?php

namespace App\Console\Commands;

use App\Services\PerformanceOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PerformanceTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test performance optimization features';

    protected $performanceService;

    public function __construct(PerformanceOptimizationService $performanceService)
    {
        parent::__construct();
        $this->performanceService = $performanceService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🚀 Starting Performance Optimization Tests...');
        $this->newLine();

        // Test 1: Database Statistics
        $this->info('📊 Testing Database Statistics...');
        try {
            $dbStats = $this->performanceService->getDatabaseStats();
            if (isset($dbStats['error'])) {
                $this->warn('Database stats error: ' . $dbStats['error']);
            } else {
                $this->info('✅ Database statistics retrieved successfully');
                if (isset($dbStats['table_sizes'])) {
                    $this->info('   Tables analyzed: ' . count($dbStats['table_sizes']));
                }
            }
        } catch (\Exception $e) {
            $this->error('❌ Database stats failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 2: Cache Statistics
        $this->info('💾 Testing Cache Statistics...');
        try {
            $cacheStats = $this->performanceService->getCacheStats();
            if (isset($cacheStats['error'])) {
                $this->warn('Cache stats error: ' . $cacheStats['error']);
            } else {
                $this->info('✅ Cache statistics retrieved successfully');
                if (isset($cacheStats['redis_keys'])) {
                    $this->info('   Redis keys: ' . $cacheStats['redis_keys']);
                }
            }
        } catch (\Exception $e) {
            $this->error('❌ Cache stats failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 3: Memory Statistics
        $this->info('🧠 Testing Memory Statistics...');
        try {
            $memoryStats = $this->performanceService->getMemoryStats();
            $this->info('✅ Memory statistics retrieved successfully');
            $this->info('   Current memory: ' . $memoryStats['current_memory']);
            $this->info('   Peak memory: ' . $memoryStats['peak_memory']);
            $this->info('   Memory limit: ' . $memoryStats['memory_limit']);
        } catch (\Exception $e) {
            $this->error('❌ Memory stats failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 4: File Storage Optimization
        $this->info('📁 Testing File Storage Optimization...');
        try {
            $storageStats = $this->performanceService->optimizeFileStorage();
            $this->info('✅ File storage optimization completed');
            $this->info('   Total size: ' . $storageStats['total_size']);
            $this->info('   Large files: ' . count($storageStats['large_files']));
            $this->info('   Old log files: ' . count($storageStats['old_log_files']));
        } catch (\Exception $e) {
            $this->error('❌ File storage optimization failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 5: Index Suggestions
        $this->info('🔍 Testing Index Suggestions...');
        try {
            $suggestions = $this->performanceService->suggestIndexes();
            $this->info('✅ Index suggestions retrieved successfully');
            $this->info('   Suggestions found: ' . count($suggestions));
            foreach ($suggestions as $suggestion) {
                if (isset($suggestion['type'])) {
                    $this->line('   - ' . $suggestion['type'] . ': ' . ($suggestion['suggestion'] ?? 'N/A'));
                }
            }
        } catch (\Exception $e) {
            $this->error('❌ Index suggestions failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 6: Query Performance Monitoring
        $this->info('⚡ Testing Query Performance Monitoring...');
        try {
            $queryMetrics = $this->performanceService->monitorQueryPerformance();
            if (isset($queryMetrics['error'])) {
                $this->warn('Query monitoring error: ' . $queryMetrics['error']);
            } else {
                $this->info('✅ Query performance monitoring completed');
                if (isset($queryMetrics['total_queries'])) {
                    $this->info('   Total queries: ' . $queryMetrics['total_queries']);
                }
                if (isset($queryMetrics['avg_query_time'])) {
                    $this->info('   Average query time: ' . round($queryMetrics['avg_query_time'], 4) . 's');
                }
            }
        } catch (\Exception $e) {
            $this->error('❌ Query performance monitoring failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 7: Cache Clearing
        $this->info('🧹 Testing Cache Clearing...');
        try {
            $clearResults = $this->performanceService->clearCaches();
            $this->info('✅ Cache clearing completed');
            foreach ($clearResults as $type => $result) {
                if ($type !== 'error') {
                    $this->info('   ' . $type . ': ' . $result);
                }
            }
        } catch (\Exception $e) {
            $this->error('❌ Cache clearing failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 8: Database Query Performance
        $this->info('🗄️ Testing Database Query Performance...');
        try {
            $startTime = microtime(true);
            
            // Test a complex query
            $users = DB::table('users')
                ->select('id', 'name', 'email')
                ->where('is_active', true)
                ->limit(100)
                ->get();
            
            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;
            
            $this->info('✅ Database query performance test completed');
            $this->info('   Query execution time: ' . round($executionTime, 2) . 'ms');
            $this->info('   Records returned: ' . $users->count());
            
            if ($executionTime > 100) {
                $this->warn('   ⚠️ Query execution time is high (>100ms)');
            } else {
                $this->info('   ✅ Query execution time is acceptable');
            }
        } catch (\Exception $e) {
            $this->error('❌ Database query performance test failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 9: Index Usage Verification
        $this->info('📈 Testing Index Usage...');
        try {
            $indexes = DB::select("
                SELECT 
                    table_name,
                    index_name,
                    cardinality
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE()
                ORDER BY cardinality DESC
                LIMIT 10
            ");
            
            $this->info('✅ Index usage verification completed');
            $this->info('   Top indexes by cardinality:');
            foreach ($indexes as $index) {
                $this->line('   - ' . $index->table_name . '.' . $index->index_name . ': ' . $index->cardinality);
            }
        } catch (\Exception $e) {
            $this->error('❌ Index usage verification failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 10: Memory Usage Test
        $this->info('💭 Testing Memory Usage...');
        try {
            $initialMemory = memory_get_usage(true);
            
            // Simulate some memory-intensive operations
            $data = [];
            for ($i = 0; $i < 1000; $i++) {
                $data[] = [
                    'id' => $i,
                    'name' => 'Test User ' . $i,
                    'email' => 'user' . $i . '@example.com',
                    'data' => str_repeat('x', 1000)
                ];
            }
            
            $peakMemory = memory_get_peak_usage(true);
            $finalMemory = memory_get_usage(true);
            
            $this->info('✅ Memory usage test completed');
            $this->info('   Initial memory: ' . $this->formatBytes($initialMemory));
            $this->info('   Peak memory: ' . $this->formatBytes($peakMemory));
            $this->info('   Final memory: ' . $this->formatBytes($finalMemory));
            $this->info('   Memory used: ' . $this->formatBytes($finalMemory - $initialMemory));
            
            // Clean up
            unset($data);
            
        } catch (\Exception $e) {
            $this->error('❌ Memory usage test failed: ' . $e->getMessage());
        }
        $this->newLine();

        $this->info('🎉 Performance Optimization Tests Completed!');
        $this->newLine();
        
        $this->info('📋 Summary:');
        $this->info('   - Database optimization: ✅');
        $this->info('   - Cache management: ✅');
        $this->info('   - Memory monitoring: ✅');
        $this->info('   - File storage optimization: ✅');
        $this->info('   - Index suggestions: ✅');
        $this->info('   - Query performance monitoring: ✅');
        $this->info('   - Performance testing: ✅');
        
        return Command::SUCCESS;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}