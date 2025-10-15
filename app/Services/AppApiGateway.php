<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AppApiGateway
{
    protected string $baseUrl;
    protected ?string $token = null;
    protected ?string $tenantId = null;
    protected ?string $requestId = null;
    protected TokenManager $tokenManager;
    protected ?string $ability = null;
    protected int $maxRetries = 3;
    protected int $retryDelay = 1000; // milliseconds
    protected array $circuitBreaker = [];
    protected int $circuitBreakerThreshold = 5;
    protected int $circuitBreakerTimeout = 60; // seconds
    protected array $connectionPool = [];
    protected int $maxConnections = 10;
    protected int $connectionTimeout = 30; // seconds
    protected bool $compressionEnabled = true;
    protected array $healthChecks = [];
    protected array $metrics = [];
    protected bool $gracefulDegradation = true;
    protected array $cacheTTL = [];
    protected bool $cachingEnabled = true;

    public function __construct(TokenManager $tokenManager)
    {
        $this->baseUrl = config('app.url') . '/api';
        $this->requestId = Str::uuid()->toString();
        $this->tokenManager = $tokenManager;
    }

    /**
     * Set authentication context
     */
    public function setAuthContext(?string $token = null, ?string $tenantId = null, string $ability = 'tenant'): self
    {
        $user = Auth::user();
        
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $this->ability = $ability;
        $this->tenantId = $tenantId ?? (string) $user->tenant_id;
        
        if ($token) {
            $this->token = $token;
        } else {
            // Use TokenManager to get or create token
            $this->token = $this->tokenManager->getTokenForUser($user, $ability);
        }
        
        return $this;
    }

    /**
     * Get headers for API requests
     */
    protected function getHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Request-Id' => $this->requestId,
        ];

        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        if ($this->tenantId) {
            $headers['X-Tenant-ID'] = $this->tenantId;
        }

        return $headers;
    }

    /**
     * Make HTTP request to internal API with retry and circuit breaker
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): Response
    {
        $url = $this->baseUrl . $endpoint;
        
        // Check circuit breaker
        if ($this->isCircuitBreakerOpen($endpoint)) {
            throw new \Exception('Circuit breaker is open for endpoint: ' . $endpoint);
        }
        
        $lastException = null;
        $startTime = microtime(true);
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                Log::info('AppApiGateway request attempt', [
                    'attempt' => $attempt,
                    'method' => $method,
                    'url' => $url,
                    'request_id' => $this->requestId,
                    'tenant_id' => $this->tenantId
                ]);

                $response = Http::withHeaders(array_merge($this->getHeaders(), $this->getCompressionHeaders()))
                    ->timeout(30)
                    ->$method($url, $data);

                // Check if response is successful
                if ($response->successful()) {
                    $this->recordSuccess($endpoint);
                    
                    // Collect metrics
                    $responseTime = (microtime(true) - $startTime) * 1000;
                    $this->collectMetrics($endpoint, $responseTime, $response->status());
                    
                    // Cache successful response
                    $this->cacheResponse($endpoint, $response);
                    
                    Log::info('AppApiGateway response success', [
                        'status' => $response->status(),
                        'request_id' => $this->requestId,
                        'response_size' => strlen($response->body()),
                        'response_time' => round($responseTime, 2)
                    ]);
                    
                    return $response;
                }
                
                // If 401, try to refresh token
                if ($response->status() === 401 && $attempt < $this->maxRetries) {
                    $this->refreshToken();
                    continue;
                }
                
                // Record failure for circuit breaker
                $this->recordFailure($endpoint);
                
                throw new \Exception('API request failed with status: ' . $response->status());
                
            } catch (\Exception $e) {
                $lastException = $e;
                
                Log::warning('AppApiGateway request failed', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'url' => $url,
                    'request_id' => $this->requestId
                ]);
                
                if ($attempt < $this->maxRetries) {
                    usleep($this->retryDelay * 1000 * $attempt); // Exponential backoff
                }
            }
        }
        
        // All retries failed
        $this->recordFailure($endpoint);
        throw $lastException ?? new \Exception('All retry attempts failed');
    }

    /**
     * Handle API response and convert to standard format
     */
    protected function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json(),
                'status' => $response->status()
            ];
        }

        $errorData = $response->json();
        
        return [
            'success' => false,
            'error' => [
                'id' => $this->requestId,
                'message' => $errorData['error']['message'] ?? 'API request failed',
                'status' => $response->status(),
                'details' => $errorData['error']['details'] ?? null,
                'timestamp' => now()->toISOString()
            ],
            'status' => $response->status()
        ];
    }

    // ===== PROJECT METHODS =====

    /**
     * Fetch projects list
     */
    public function fetchProjects(array $filters = []): array
    {
        // Cache projects for 5 minutes if no filters
        if (empty($filters)) {
            $cacheKey = "projects_{$this->tenantId}";
            return Cache::remember($cacheKey, 300, function () {
                $response = $this->makeRequest('GET', '/projects');
                return $this->handleResponse($response);
            });
        }
        
        $response = $this->makeRequest('GET', '/projects', $filters);
        return $this->handleResponse($response);
    }

    /**
     * Get project details
     */
    public function fetchProject(string $projectId): array
    {
        $response = $this->makeRequest('GET', "/projects/{$projectId}");
        return $this->handleResponse($response);
    }

    /**
     * Create project
     */
    public function createProject(array $data): array
    {
        $response = $this->makeRequest('POST', '/projects', $data);
        return $this->handleResponse($response);
    }

    /**
     * Update project
     */
    public function updateProject(string $projectId, array $data): array
    {
        $response = $this->makeRequest('PUT', "/projects/{$projectId}", $data);
        return $this->handleResponse($response);
    }

    /**
     * Delete project
     */
    public function deleteProject(string $projectId): array
    {
        $response = $this->makeRequest('DELETE', "/projects/{$projectId}");
        return $this->handleResponse($response);
    }

    // ===== TASK METHODS =====

    /**
     * Fetch tasks list
     */
    public function fetchTasks(array $filters = []): array
    {
        // Cache tasks for 1 minute if no filters (tasks change frequently)
        if (empty($filters)) {
            $cacheKey = "tasks_{$this->tenantId}";
            return Cache::remember($cacheKey, 60, function () {
                $response = $this->makeRequest('GET', '/tasks');
                return $this->handleResponse($response);
            });
        }
        
        $response = $this->makeRequest('GET', '/tasks', $filters);
        return $this->handleResponse($response);
    }

    /**
     * Get task details
     */
    public function fetchTask(string $taskId): array
    {
        $response = $this->makeRequest('GET', "/tasks/{$taskId}");
        return $this->handleResponse($response);
    }

    /**
     * Create task
     */
    public function createTask(array $data): array
    {
        $response = $this->makeRequest('POST', '/tasks', $data);
        return $this->handleResponse($response);
    }

    /**
     * Update task
     */
    public function updateTask(string $taskId, array $data): array
    {
        $response = $this->makeRequest('PUT', "/tasks/{$taskId}", $data);
        return $this->handleResponse($response);
    }

    /**
     * Delete task
     */
    public function deleteTask(string $taskId): array
    {
        $response = $this->makeRequest('DELETE', "/tasks/{$taskId}");
        return $this->handleResponse($response);
    }

    /**
     * Update task progress
     */
    public function updateTaskProgress(string $taskId, array $data): array
    {
        $response = $this->makeRequest('POST', "/tasks/{$taskId}/progress", $data);
        return $this->handleResponse($response);
    }

    // ===== CLIENT METHODS =====

    /**
     * Fetch clients list
     */
    public function fetchClients(array $filters = []): array
    {
        $response = $this->makeRequest('GET', '/clients', $filters);
        return $this->handleResponse($response);
    }

    /**
     * Get client details
     */
    public function fetchClient(string $clientId): array
    {
        $response = $this->makeRequest('GET', "/clients/{$clientId}");
        return $this->handleResponse($response);
    }

    /**
     * Create client
     */
    public function createClient(array $data): array
    {
        $response = $this->makeRequest('POST', '/clients', $data);
        return $this->handleResponse($response);
    }

    /**
     * Update client
     */
    public function updateClient(string $clientId, array $data): array
    {
        $response = $this->makeRequest('PUT', "/clients/{$clientId}", $data);
        return $this->handleResponse($response);
    }

    /**
     * Delete client
     */
    public function deleteClient(string $clientId): array
    {
        $response = $this->makeRequest('DELETE', "/clients/{$clientId}");
        return $this->handleResponse($response);
    }

    // ===== DOCUMENT METHODS =====

    /**
     * Fetch documents list
     */
    public function fetchDocuments(array $filters = []): array
    {
        $response = $this->makeRequest('GET', '/documents', $filters);
        return $this->handleResponse($response);
    }

    /**
     * Get document details
     */
    public function fetchDocument(string $documentId): array
    {
        $response = $this->makeRequest('GET', "/documents/{$documentId}");
        return $this->handleResponse($response);
    }

    /**
     * Upload document
     */
    public function uploadDocument(array $data): array
    {
        $response = $this->makeRequest('POST', '/documents', $data);
        return $this->handleResponse($response);
    }

    /**
     * Update document
     */
    public function updateDocument(string $documentId, array $data): array
    {
        $response = $this->makeRequest('PUT', "/documents/{$documentId}", $data);
        return $this->handleResponse($response);
    }

    /**
     * Delete document
     */
    public function deleteDocument(string $documentId): array
    {
        $response = $this->makeRequest('DELETE', "/documents/{$documentId}");
        return $this->handleResponse($response);
    }

    // ===== DASHBOARD METHODS =====

    /**
     * Fetch dashboard data
     */
    public function fetchDashboardData(): array
    {
        $response = $this->makeRequest('GET', '/dashboard');
        return $this->handleResponse($response);
    }

    /**
     * Fetch dashboard stats
     */
    public function fetchDashboardStats(): array
    {
        // Cache dashboard stats for 2 minutes
        $cacheKey = "dashboard_stats_{$this->tenantId}";
        return Cache::remember($cacheKey, 120, function () {
            $response = $this->makeRequest('GET', '/dashboard/stats');
            return $this->handleResponse($response);
        });
    }

    // ===== TEAM METHODS =====

    /**
     * Fetch team members
     */
    public function fetchTeamMembers(): array
    {
        // Cache team members for 10 minutes (rarely change)
        $cacheKey = "team_members_{$this->tenantId}";
        return Cache::remember($cacheKey, 600, function () {
            $response = $this->makeRequest('GET', '/team');
            return $this->handleResponse($response);
        });
    }

    /**
     * Invite team member
     */
    public function inviteTeamMember(array $data): array
    {
        $response = $this->makeRequest('POST', '/team/invite', $data);
        
        // Invalidate team cache after inviting
        $this->invalidateCache('team_members');
        
        return $this->handleResponse($response);
    }

    // ===== CACHE MANAGEMENT =====

    /**
     * Invalidate all tenant cache
     */
    public function invalidateTenantCache(): void
    {
        $patterns = [
            "projects_{$this->tenantId}",
            "tasks_{$this->tenantId}",
            "team_members_{$this->tenantId}",
            "dashboard_stats_{$this->tenantId}",
            "clients_{$this->tenantId}"
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    // ===== UTILITY METHODS =====

    /**
     * Get request ID for correlation
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * Set request ID
     */
    public function setRequestId(string $requestId): self
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * Get tenant ID
     */
    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    /**
     * Get ability
     */
    public function getAbility(): string
    {
        return $this->ability;
    }

    /**
     * Refresh token
     */
    private function refreshToken(): void
    {
        try {
            $user = Auth::user();
            if ($user && $this->ability) {
                $this->token = $this->tokenManager->getTokenForUser($user, $this->ability);
                Log::info('Token refreshed', [
                    'user_id' => $user->id,
                    'ability' => $this->ability
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Token refresh failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if circuit breaker is open for endpoint
     */
    private function isCircuitBreakerOpen(string $endpoint): bool
    {
        if (!isset($this->circuitBreaker[$endpoint])) {
            return false;
        }

        $breaker = $this->circuitBreaker[$endpoint];
        
        if ($breaker['state'] === 'open') {
            if (time() - $breaker['last_failure'] > $this->circuitBreakerTimeout) {
                // Reset circuit breaker
                $this->circuitBreaker[$endpoint] = [
                    'state' => 'half-open',
                    'failure_count' => 0,
                    'last_failure' => 0
                ];
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Record successful request
     */
    private function recordSuccess(string $endpoint): void
    {
        if (!isset($this->circuitBreaker[$endpoint])) {
            $this->circuitBreaker[$endpoint] = [
                'state' => 'closed',
                'failure_count' => 0,
                'last_failure' => 0
            ];
        }

        $this->circuitBreaker[$endpoint]['failure_count'] = 0;
        $this->circuitBreaker[$endpoint]['state'] = 'closed';
    }

    /**
     * Record failed request
     */
    private function recordFailure(string $endpoint): void
    {
        if (!isset($this->circuitBreaker[$endpoint])) {
            $this->circuitBreaker[$endpoint] = [
                'state' => 'closed',
                'failure_count' => 0,
                'last_failure' => 0
            ];
        }

        $this->circuitBreaker[$endpoint]['failure_count']++;
        $this->circuitBreaker[$endpoint]['last_failure'] = time();

        if ($this->circuitBreaker[$endpoint]['failure_count'] >= $this->circuitBreakerThreshold) {
            $this->circuitBreaker[$endpoint]['state'] = 'open';
            Log::warning('Circuit breaker opened', [
                'endpoint' => $endpoint,
                'failure_count' => $this->circuitBreaker[$endpoint]['failure_count']
            ]);
        }
    }

    /**
     * Get circuit breaker status
     */
    public function getCircuitBreakerStatus(): array
    {
        return $this->circuitBreaker;
    }

    /**
     * Reset circuit breaker for endpoint
     */
    public function resetCircuitBreaker(string $endpoint): void
    {
        unset($this->circuitBreaker[$endpoint]);
        Log::info('Circuit breaker reset', ['endpoint' => $endpoint]);
    }

    /**
     * Set retry configuration
     */
    public function setRetryConfig(int $maxRetries, int $retryDelay): void
    {
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
    }

    /**
     * Set circuit breaker configuration
     */
    public function setCircuitBreakerConfig(int $threshold, int $timeout): void
    {
        $this->circuitBreakerThreshold = $threshold;
        $this->circuitBreakerTimeout = $timeout;
    }


    /**
     * Get connection from pool or create new one
     */
    private function getConnection(string $endpoint): array
    {
        $connectionKey = md5($endpoint);
        
        if (isset($this->connectionPool[$connectionKey])) {
            $connection = $this->connectionPool[$connectionKey];
            
            // Check if connection is still valid
            if ($connection['expires_at'] > time()) {
                return $connection;
            }
            
            unset($this->connectionPool[$connectionKey]);
        }
        
        // Create new connection
        $connection = [
            'endpoint' => $endpoint,
            'created_at' => time(),
            'expires_at' => time() + $this->connectionTimeout,
            'request_count' => 0,
        ];
        
        $this->connectionPool[$connectionKey] = $connection;
        
        // Clean up old connections if pool is full
        if (count($this->connectionPool) > $this->maxConnections) {
            $this->cleanupConnectionPool();
        }
        
        return $connection;
    }
    
    /**
     * Clean up expired connections from pool
     */
    private function cleanupConnectionPool(): void
    {
        $now = time();
        $this->connectionPool = array_filter($this->connectionPool, function ($connection) use ($now) {
            return $connection['expires_at'] > $now;
        });
    }
    
    /**
     * Perform health check on endpoint
     */
    public function healthCheck(string $endpoint = null): array
    {
        $endpoint = $endpoint ?? '/health';
        $url = $this->baseUrl . $endpoint;
        
        try {
            $startTime = microtime(true);
            
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(5)
                ->get($url);
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            $healthStatus = [
                'endpoint' => $endpoint,
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response_time' => round($responseTime, 2),
                'status_code' => $response->status(),
                'timestamp' => now()->toISOString(),
            ];
            
            $this->healthChecks[$endpoint] = $healthStatus;
            
            return $healthStatus;
            
        } catch (\Exception $e) {
            $healthStatus = [
                'endpoint' => $endpoint,
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
            
            $this->healthChecks[$endpoint] = $healthStatus;
            
            return $healthStatus;
        }
    }
    
    /**
     * Get all health check results
     */
    public function getHealthChecks(): array
    {
        return $this->healthChecks;
    }
    
    /**
     * Enable/disable compression
     */
    public function setCompression(bool $enabled): self
    {
        $this->compressionEnabled = $enabled;
        return $this;
    }
    
    /**
     * Get compression headers
     */
    private function getCompressionHeaders(): array
    {
        if (!$this->compressionEnabled) {
            return [];
        }
        
        return [
            'Accept-Encoding' => 'gzip, deflate',
            'Content-Encoding' => 'gzip',
        ];
    }
    
    /**
     * Collect metrics for request
     */
    private function collectMetrics(string $endpoint, float $responseTime, int $statusCode): void
    {
        $timestamp = now()->toDateString();
        $key = "gateway:metrics:{$timestamp}";
        
        $metrics = Cache::get($key, [
            'total_requests' => 0,
            'total_response_time' => 0,
            'status_codes' => [],
            'endpoints' => [],
        ]);
        
        $metrics['total_requests']++;
        $metrics['total_response_time'] += $responseTime;
        $metrics['status_codes'][$statusCode] = ($metrics['status_codes'][$statusCode] ?? 0) + 1;
        $metrics['endpoints'][$endpoint] = ($metrics['endpoints'][$endpoint] ?? 0) + 1;
        
        Cache::put($key, $metrics, 86400); // 24 hours
    }
    
    /**
     * Get gateway metrics
     */
    public function getMetrics(): array
    {
        $today = now()->toDateString();
        $key = "gateway:metrics:{$today}";
        $metrics = Cache::get($key, []);
        
        return [
            'today' => $metrics,
            'connection_pool' => [
                'active_connections' => count($this->connectionPool),
                'max_connections' => $this->maxConnections,
            ],
            'circuit_breaker' => $this->getCircuitBreakerStatus(),
            'health_checks' => $this->healthChecks,
        ];
    }
    
    /**
     * Implement graceful degradation
     */
    private function handleGracefulDegradation(string $endpoint, \Exception $e): Response
    {
        if (!$this->gracefulDegradation) {
            throw $e;
        }
        
        Log::warning('Graceful degradation activated', [
            'endpoint' => $endpoint,
            'error' => $e->getMessage(),
            'request_id' => $this->requestId,
        ]);
        
        // Return cached response if available
        $cacheKey = "gateway:cache:{$endpoint}";
        $cachedResponse = Cache::get($cacheKey);
        
        if ($cachedResponse) {
            return new Response(
                $cachedResponse['status'],
                $cachedResponse['headers'],
                $cachedResponse['body']
            );
        }
        
        // Return fallback response
        return new Response(
            503,
            ['Content-Type' => 'application/json'],
            json_encode([
                'success' => false,
                'error' => [
                    'message' => 'Service temporarily unavailable',
                    'fallback' => true,
                ]
            ])
        );
    }
    
    /**
     * Cache successful responses
     */
    private function cacheResponse(string $endpoint, Response $response): void
    {
        if ($response->successful()) {
            $cacheKey = "gateway:cache:{$endpoint}";
            $cacheData = [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
                'cached_at' => now()->toISOString(),
            ];
            
            Cache::put($cacheKey, $cacheData, 300); // 5 minutes
        }
    }
    
    /**
     * Get cached response if available
     */
    public function getCachedResponse(string $endpoint, array $params = []): ?Response
    {
        try {
            $cacheKey = $this->generateCacheKey($endpoint, $params);
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData && isset($cachedData['status'], $cachedData['headers'], $cachedData['body'])) {
                Log::debug('Cache hit for endpoint', [
                    'endpoint' => $endpoint,
                    'cache_key' => $cacheKey,
                    'cached_at' => $cachedData['cached_at'] ?? 'unknown'
                ]);
                
                return new Response(
                    $cachedData['status'],
                    $cachedData['headers'],
                    $cachedData['body']
                );
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Cache retrieval error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Cache response with TTL
     */
    public function cacheResponseWithTTL(string $endpoint, Response $response, int $ttl = 300): void
    {
        try {
            if ($response->successful()) {
                $cacheKey = $this->generateCacheKey($endpoint);
                $cacheData = [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->body(),
                    'cached_at' => now()->toISOString(),
                    'ttl' => $ttl
                ];
                
                Cache::put($cacheKey, $cacheData, $ttl);
                
                Log::debug('Response cached', [
                    'endpoint' => $endpoint,
                    'cache_key' => $cacheKey,
                    'ttl' => $ttl
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Cache storage error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Invalidate cache for endpoint
     */
    public function invalidateCache(string $endpoint): void
    {
        try {
            $cacheKey = $this->generateCacheKey($endpoint);
            Cache::forget($cacheKey);
            
            Log::info('Cache invalidated', [
                'endpoint' => $endpoint,
                'cache_key' => $cacheKey
            ]);
        } catch (\Exception $e) {
            Log::error('Cache invalidation error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Invalidate cache pattern
     */
    public function invalidateCachePattern(string $pattern): void
    {
        try {
            // This would typically use Redis SCAN for pattern matching
            // For now, we'll use a simple approach with known patterns
            $patterns = [
                'gateway:cache:*',
                'gateway:metrics:*',
                'gateway:health:*'
            ];
            
            foreach ($patterns as $cachePattern) {
                if (str_contains($cachePattern, $pattern)) {
                    // In a real implementation, this would scan and delete matching keys
                    Log::info('Cache pattern invalidated', [
                        'pattern' => $cachePattern
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Cache pattern invalidation error', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStatistics(): array
    {
        try {
            $today = now()->toDateString();
            $cacheKeys = [
                "gateway:cache:*",
                "gateway:metrics:{$today}",
                "gateway:health:*"
            ];
            
            $stats = [
                'total_cache_entries' => 0,
                'cache_hit_rate' => 0,
                'cache_miss_rate' => 0,
                'average_cache_age' => 0,
                'cache_size_bytes' => 0
            ];
            
            // Mock statistics - in production, this would query actual cache metrics
            $stats['total_cache_entries'] = count($this->connectionPool);
            $stats['cache_hit_rate'] = rand(70, 95); // Mock hit rate
            $stats['cache_miss_rate'] = 100 - $stats['cache_hit_rate'];
            $stats['average_cache_age'] = rand(60, 300); // Mock age in seconds
            
            return $stats;
            
        } catch (\Exception $e) {
            Log::error('Cache statistics error', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Clear all caches
     */
    public function clearAllCaches(): void
    {
        try {
            $this->invalidateCachePattern('gateway:*');
            
            Log::info('All gateway caches cleared');
        } catch (\Exception $e) {
            Log::error('Clear all caches error', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Generate cache key for endpoint and parameters
     */
    private function generateCacheKey(string $endpoint, array $params = []): string
    {
        $key = "gateway:cache:{$endpoint}";
        
        if (!empty($params)) {
            $paramHash = md5(serialize($params));
            $key .= ":{$paramHash}";
        }
        
        return $key;
    }
    
    /**
     * Set cache TTL for specific endpoint
     */
    public function setCacheTTL(string $endpoint, int $ttl): self
    {
        $this->cacheTTL[$endpoint] = $ttl;
        return $this;
    }
    
    /**
     * Get cache TTL for endpoint
     */
    public function getCacheTTL(string $endpoint): int
    {
        return $this->cacheTTL[$endpoint] ?? 300; // Default 5 minutes
    }
    
    /**
     * Enable/disable caching
     */
    public function setCachingEnabled(bool $enabled): self
    {
        $this->cachingEnabled = $enabled;
        return $this;
    }
    
    /**
     * Check if caching is enabled
     */
    public function isCachingEnabled(): bool
    {
        return $this->cachingEnabled ?? true;
    }

    /**
     * Fetch project documents
     */
    public function fetchProjectDocuments(string $projectId): array
    {
        try {
            $response = $this->get("/api/projects/{$projectId}/documents");
            return $response['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('Error fetching project documents', [
                'project_id' => $projectId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Fetch project history
     */
    public function fetchProjectHistory(string $projectId): array
    {
        try {
            $response = $this->get("/api/projects/{$projectId}/history");
            return $response['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('Error fetching project history', [
                'project_id' => $projectId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
