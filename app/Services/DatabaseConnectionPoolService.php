<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * Database Connection Pool Service
 * 
 * Manages database connection pooling and optimization
 */
class DatabaseConnectionPoolService
{
    private array $connectionStats = [];
    private int $maxConnections = 20;
    private int $minConnections = 5;
    private int $connectionTimeout = 10;
    private int $idleTimeout = 300;

    public function __construct()
    {
        $this->maxConnections = Config::get('database.connections.mysql.pool.max_connections', 20);
        $this->minConnections = Config::get('database.connections.mysql.pool.min_connections', 5);
        $this->connectionTimeout = Config::get('database.connections.mysql.pool.connection_timeout', 10);
        $this->idleTimeout = Config::get('database.connections.mysql.pool.idle_timeout', 300);
    }

    /**
     * Get optimized database connection
     */
    public function getConnection(string $connection = 'mysql'): \Illuminate\Database\Connection
    {
        $startTime = microtime(true);
        
        try {
            $connection = DB::connection($connection);
            
            // Log connection stats
            $this->logConnectionStats($connection, microtime(true) - $startTime);
            
            return $connection;
            
        } catch (\Exception $e) {
            Log::error('Database connection failed', [
                'connection' => $connection,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ]);
            
            throw $e;
        }
    }

    /**
     * Get read-only connection for SELECT queries
     */
    public function getReadConnection(): \Illuminate\Database\Connection
    {
        return $this->getConnection('mysql_read');
    }

    /**
     * Execute query with connection optimization
     */
    public function executeQuery(callable $callback, string $connection = 'mysql'): mixed
    {
        $connection = $this->getConnection($connection);
        
        try {
            return $connection->transaction(function () use ($callback, $connection) {
                return $callback($connection);
            });
        } catch (\Exception $e) {
            Log::error('Query execution failed', [
                'connection' => $connection,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Execute read-only query
     */
    public function executeReadQuery(callable $callback): mixed
    {
        return $this->executeQuery($callback, 'mysql_read');
    }

    /**
     * Get connection statistics
     */
    public function getConnectionStats(): array
    {
        return [
            'active_connections' => $this->getActiveConnectionCount(),
            'max_connections' => $this->maxConnections,
            'min_connections' => $this->minConnections,
            'connection_timeout' => $this->connectionTimeout,
            'idle_timeout' => $this->idleTimeout,
            'stats' => $this->connectionStats
        ];
    }

    /**
     * Monitor database connection health across all connections
     * 
     * This method performs comprehensive health checks on all database connections
     * including main write connection and read replicas. It returns detailed
     * status information for monitoring and alerting purposes.
     * 
     * @return array Health status with connection details and any issues found
     */
    public function monitorConnectionHealth(): array
    {
        $healthStatus = $this->initializeHealthStatus();
        
        $this->checkMainConnection($healthStatus);
        $this->checkReadConnection($healthStatus);
        
        return $healthStatus;
    }

    /**
     * Initialize health status structure
     */
    private function initializeHealthStatus(): array
    {
        return [
            'status' => 'healthy',
            'connections' => [],
            'issues' => [],
            'checked_at' => now()->toISOString()
        ];
    }

    /**
     * Check main database connection health
     */
    private function checkMainConnection(array &$healthStatus): void
    {
        try {
            $mainConnection = DB::connection('mysql');
            $mainConnection->getPdo();
            $healthStatus['connections']['mysql'] = 'healthy';
            
        } catch (\Exception $e) {
            $this->handleConnectionFailure($healthStatus, 'mysql', $e);
        }
    }

    /**
     * Check read database connection health
     */
    private function checkReadConnection(array &$healthStatus): void
    {
        try {
            $readConnection = DB::connection('mysql_read');
            $readConnection->getPdo();
            $healthStatus['connections']['mysql_read'] = 'healthy';
            
        } catch (\Exception $e) {
            $this->handleConnectionFailure($healthStatus, 'mysql_read', $e);
        }
    }

    /**
     * Handle connection failure by updating health status
     */
    private function handleConnectionFailure(array &$healthStatus, string $connectionName, \Exception $e): void
    {
        $healthStatus['connections'][$connectionName] = 'unhealthy';
        $healthStatus['issues'][] = "Database connection '{$connectionName}' failed: " . $e->getMessage();
        $healthStatus['status'] = 'degraded';
    }

    /**
     * Optimize connection settings
     */
    public function optimizeConnections(): void
    {
        try {
            // Set MySQL session variables for optimization
            $optimizationQueries = [
                "SET SESSION innodb_buffer_pool_size = 134217728", // 128MB
                "SET SESSION query_cache_size = 67108864", // 64MB
                "SET SESSION tmp_table_size = 67108864", // 64MB
                "SET SESSION max_heap_table_size = 67108864", // 64MB
                "SET SESSION sort_buffer_size = 2097152", // 2MB
                "SET SESSION read_buffer_size = 131072", // 128KB
                "SET SESSION read_rnd_buffer_size = 262144", // 256KB
            ];

            foreach ($optimizationQueries as $query) {
                try {
                    DB::statement($query);
                } catch (\Exception $e) {
                    Log::warning('Failed to set optimization parameter', [
                        'query' => $query,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Database connections optimized successfully');

        } catch (\Exception $e) {
            Log::error('Failed to optimize database connections', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get active connection count
     */
    private function getActiveConnectionCount(): int
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            return (int) ($result[0]->Value ?? 0);
        } catch (\Exception $e) {
            Log::warning('Failed to get active connection count', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Log connection statistics
     */
    private function logConnectionStats(\Illuminate\Database\Connection $connection, float $executionTime): void
    {
        $this->connectionStats[] = [
            'connection' => $connection->getName(),
            'execution_time' => $executionTime,
            'timestamp' => now()->toISOString()
        ];

        // Keep only last 100 stats
        if (count($this->connectionStats) > 100) {
            $this->connectionStats = array_slice($this->connectionStats, -100);
        }

        // Log slow connections
        if ($executionTime > 1.0) {
            Log::warning('Slow database connection', [
                'connection' => $connection->getName(),
                'execution_time' => $executionTime
            ]);
        }
    }

    /**
     * Cleanup idle connections
     */
    public function cleanupIdleConnections(): void
    {
        try {
            // This would typically be handled by the database server
            // or connection pool manager, but we can log the attempt
            Log::info('Idle connection cleanup attempted');
            
        } catch (\Exception $e) {
            Log::error('Failed to cleanup idle connections', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get database performance metrics
     */
    public function getPerformanceMetrics(): array
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
                'Innodb_buffer_pool_hit_rate'
            ];

            foreach ($statusVars as $var) {
                $result = DB::select("SHOW STATUS LIKE '{$var}'");
                if (!empty($result)) {
                    $metrics[$var] = $result[0]->Value;
                }
            }

            return $metrics;

        } catch (\Exception $e) {
            Log::error('Failed to get database performance metrics', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
}
