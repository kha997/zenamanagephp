<?php declare(strict_types=1);

namespace App\Services;

use App\Models\QueryLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

/**
 * Query Logging Service
 * 
 * Logs and monitors database query performance
 */
class QueryLoggingService
{
    private bool $enabled = true;
    private float $slowQueryThreshold = 1000; // milliseconds
    private array $queryLog = [];
    private int $maxLogEntries = 1000;

    public function __construct()
    {
        $this->enabled = config('database.query_logging.enabled', true);
        $this->slowQueryThreshold = config('database.query_logging.slow_threshold', 1000);
        $this->maxLogEntries = config('database.query_logging.max_entries', 1000);
    }

    /**
     * Start query logging
     */
    public function startLogging(): void
    {
        if (!$this->enabled) {
            return;
        }

        DB::enableQueryLog();
        $this->queryLog = [];
    }

    /**
     * Stop query logging and save logs
     */
    public function stopLogging(Request $request = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $queries = DB::getQueryLog();
        $this->processQueries($queries, $request);
        
        DB::flushQueryLog();
    }

    /**
     * Log a single query
     */
    public function logQuery(string $sql, array $bindings = [], float $executionTime = 0, string $connection = 'mysql'): void
    {
        if (!$this->enabled) {
            return;
        }

        $queryData = [
            'sql' => $sql,
            'bindings' => $bindings,
            'execution_time' => $executionTime,
            'connection' => $connection,
            'query_hash' => $this->generateQueryHash($sql, $bindings),
            'query_type' => $this->getQueryType($sql),
            'is_slow' => $executionTime > $this->slowQueryThreshold,
            'executed_at' => now(),
            'user_id' => Auth::id(),
            'memory_usage' => memory_get_usage(true),
        ];

        $this->queryLog[] = $queryData;

        // Log slow queries immediately
        if ($queryData['is_slow']) {
            $this->logSlowQuery($queryData);
        }

        // Save to database if we have enough entries
        if (count($this->queryLog) >= $this->maxLogEntries) {
            $this->saveQueryLogs();
        }
    }

    /**
     * Process queries from Laravel query log
     */
    private function processQueries(array $queries, Request $request = null): void
    {
        foreach ($queries as $query) {
            $queryData = [
                'sql' => $query['query'],
                'bindings' => $query['bindings'],
                'execution_time' => $query['time'],
                'connection' => 'mysql',
                'query_hash' => $this->generateQueryHash($query['query'], $query['bindings']),
                'query_type' => $this->getQueryType($query['query']),
                'is_slow' => $query['time'] > $this->slowQueryThreshold,
                'executed_at' => now(),
                'user_id' => Auth::id(),
                'memory_usage' => memory_get_usage(true),
            ];

            if ($request) {
                $queryData['ip_address'] = $request->ip();
                $queryData['user_agent'] = $request->userAgent();
                $queryData['url'] = $request->fullUrl();
                $queryData['method'] = $request->method();
            }

            $this->queryLog[] = $queryData;

            // Log slow queries immediately
            if ($queryData['is_slow']) {
                $this->logSlowQuery($queryData);
            }
        }

        // Save all queries to database
        $this->saveQueryLogs();
    }

    /**
     * Save query logs to database
     */
    private function saveQueryLogs(): void
    {
        if (empty($this->queryLog)) {
            return;
        }

        try {
            // Batch insert for better performance
            $chunks = array_chunk($this->queryLog, 100);
            
            foreach ($chunks as $chunk) {
                QueryLog::insert($chunk);
            }

            $this->queryLog = [];

        } catch (\Exception $e) {
            Log::error('Failed to save query logs', [
                'error' => $e->getMessage(),
                'count' => count($this->queryLog)
            ]);
        }
    }

    /**
     * Log slow query to application log
     */
    private function logSlowQuery(array $queryData): void
    {
        Log::warning('Slow query detected', [
            'sql' => $queryData['sql'],
            'execution_time' => $queryData['execution_time'],
            'user_id' => $queryData['user_id'],
            'query_type' => $queryData['query_type'],
            'memory_usage' => $queryData['memory_usage']
        ]);
    }

    /**
     * Generate hash for query
     */
    private function generateQueryHash(string $sql, array $bindings): string
    {
        // Normalize SQL by removing variable values
        $normalizedSql = preg_replace('/\?/', '?', $sql);
        $normalizedSql = preg_replace('/\b\d+\b/', '?', $normalizedSql);
        
        return hash('sha256', $normalizedSql . serialize($bindings));
    }

    /**
     * Get query type from SQL
     */
    private function getQueryType(string $sql): string
    {
        $sql = trim(strtoupper($sql));
        
        if (str_starts_with($sql, 'SELECT')) {
            return 'SELECT';
        } elseif (str_starts_with($sql, 'INSERT')) {
            return 'INSERT';
        } elseif (str_starts_with($sql, 'UPDATE')) {
            return 'UPDATE';
        } elseif (str_starts_with($sql, 'DELETE')) {
            return 'DELETE';
        }
        
        return 'OTHER';
    }

    /**
     * Get query performance statistics
     */
    public function getPerformanceStats($startDate = null, $endDate = null): array
    {
        return QueryLog::getPerformanceStats($startDate, $endDate);
    }

    /**
     * Get top slow queries
     */
    public function getTopSlowQueries(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return QueryLog::getTopSlowQueries($limit);
    }

    /**
     * Get query frequency analysis
     */
    public function getQueryFrequencyAnalysis(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return QueryLog::getQueryFrequencyAnalysis($limit);
    }

    /**
     * Clean up old query logs
     */
    public function cleanupOldLogs(int $days = 30): int
    {
        return QueryLog::cleanupOldLogs($days);
    }

    /**
     * Get database performance metrics
     */
    public function getDatabaseMetrics(): array
    {
        try {
            $metrics = [];
            
            // Get MySQL status variables
            $statusVars = [
                'Threads_connected',
                'Threads_running',
                'Queries',
                'Slow_queries',
                'Connections',
                'Aborted_connects',
                'Innodb_buffer_pool_hit_rate',
                'Qcache_hits',
                'Qcache_inserts',
                'Created_tmp_tables',
                'Created_tmp_disk_tables'
            ];

            foreach ($statusVars as $var) {
                $result = DB::select("SHOW STATUS LIKE '{$var}'");
                if (!empty($result)) {
                    $metrics[$var] = $result[0]->Value;
                }
            }

            // Calculate hit rates
            if (isset($metrics['Qcache_hits']) && isset($metrics['Qcache_inserts'])) {
                $total = (int)$metrics['Qcache_hits'] + (int)$metrics['Qcache_inserts'];
                $metrics['query_cache_hit_rate'] = $total > 0 ? round(($metrics['Qcache_hits'] / $total) * 100, 2) : 0;
            }

            return $metrics;

        } catch (\Exception $e) {
            Log::error('Failed to get database metrics', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Enable/disable query logging
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Set slow query threshold
     */
    public function setSlowQueryThreshold(float $threshold): void
    {
        $this->slowQueryThreshold = $threshold;
    }

    /**
     * Get current configuration
     */
    public function getConfiguration(): array
    {
        return [
            'enabled' => $this->enabled,
            'slow_query_threshold' => $this->slowQueryThreshold,
            'max_log_entries' => $this->maxLogEntries,
            'current_log_count' => count($this->queryLog)
        ];
    }
}
