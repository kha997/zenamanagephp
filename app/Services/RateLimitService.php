<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\ComprehensiveLoggingService;

/**
 * Rate Limiting Service
 * 
 * Provides comprehensive rate limiting functionality with:
 * - Multiple rate limit strategies (sliding window, token bucket, fixed window)
 * - Dynamic configuration based on user roles and endpoints
 * - Advanced analytics and monitoring
 * - Automatic scaling and adjustment
 */
class RateLimitService
{
    private ComprehensiveLoggingService $loggingService;
    private RateLimitConfigurationService $configService;
    
    public function __construct(
        ComprehensiveLoggingService $loggingService,
        RateLimitConfigurationService $configService
    ) {
        $this->loggingService = $loggingService;
        $this->configService = $configService;
    }
    
    /**
     * Check if request is allowed based on rate limits
     */
    public function checkRateLimit(Request $request, string $endpoint = 'default'): array
    {
        $identifier = $this->getIdentifier($request);
        $config = $this->getRateLimitConfig($request, $endpoint);
        
        $result = $this->applyRateLimit($identifier, $config, $request, $endpoint);
        
        // Log rate limit check
        $this->logRateLimitCheck($request, $result);
        
        return $result;
    }
    
    /**
     * Get unique identifier for rate limiting
     */
    private function getIdentifier(Request $request): string
    {
        $user = $request->user();
        $ip = $request->ip();
        
        if ($user) {
            return "user:{$user->id}:{$ip}";
        }
        
        return "ip:{$ip}";
    }
    
    /**
     * Get rate limit configuration based on request context
     */
    private function getRateLimitConfig(Request $request, string $endpoint): array
    {
        $user = $request->user();
        $context = [
            'user_role' => $user?->role ?? 'guest',
            'is_authenticated' => $user !== null,
            'system_load' => $this->getSystemLoad(),
        ];
        
        return $this->configService->getConfig($endpoint, $context);
    }
    
    /**
     * Get current system load (simplified implementation)
     */
    private function getSystemLoad(): float
    {
        // In production, you'd want to get actual system metrics
        // For now, return a simulated load based on time
        $hour = (int) date('H');
        
        if ($hour >= 9 && $hour <= 17) {
            return 1.2; // Higher load during business hours
        } elseif ($hour >= 18 && $hour <= 22) {
            return 0.8; // Moderate load in evening
        } else {
            return 0.5; // Lower load at night
        }
    }
    
    /**
     * Apply rate limiting strategy
     */
    private function applyRateLimit(string $identifier, array $config, Request $request, string $endpoint): array
    {
        $strategy = $config['strategy'] ?? 'sliding_window';
        
        switch ($strategy) {
            case 'sliding_window':
                return $this->slidingWindowStrategy($identifier, $config, $request, $endpoint);
            case 'token_bucket':
                return $this->tokenBucketStrategy($identifier, $config, $request, $endpoint);
            case 'fixed_window':
                return $this->fixedWindowStrategy($identifier, $config, $request, $endpoint);
            default:
                return $this->slidingWindowStrategy($identifier, $config, $request, $endpoint);
        }
    }
    
    /**
     * Sliding window rate limiting strategy
     */
    private function slidingWindowStrategy(string $identifier, array $config, Request $request, string $endpoint): array
    {
        $now = time();
        $windowSize = $config['window_size'];
        $maxRequests = $config['requests_per_minute'];
        $burstLimit = $config['burst_limit'];
        
        $windowKey = "rate_limit:sliding:{$identifier}:{$endpoint}";
        $currentWindow = Cache::get($windowKey, []);
        
        // Clean old entries
        $currentWindow = array_filter($currentWindow, function($timestamp) use ($now, $windowSize) {
            return ($now - $timestamp) < $windowSize;
        });
        
        $currentRequests = count($currentWindow);
        $allowed = $currentRequests < $maxRequests;
        $burstAllowed = $currentRequests < $burstLimit;
        
        if ($allowed || $burstAllowed) {
            $currentWindow[] = $now;
            Cache::put($windowKey, $currentWindow, $windowSize + 10);
            
            return [
                'allowed' => true,
                'strategy' => 'sliding_window',
                'current_requests' => $currentRequests + 1,
                'max_requests' => $maxRequests,
                'burst_limit' => $burstLimit,
                'window_size' => $windowSize,
                'reset_time' => $now + $windowSize,
                'remaining' => max(0, $maxRequests - ($currentRequests + 1)),
                'is_burst' => !$allowed && $burstAllowed,
                'retry_after' => null,
            ];
        }
        
        $oldestRequest = min($currentWindow);
        $resetTime = $oldestRequest + $windowSize;
        
        return [
            'allowed' => false,
            'strategy' => 'sliding_window',
            'current_requests' => $currentRequests,
            'max_requests' => $maxRequests,
            'burst_limit' => $burstLimit,
            'window_size' => $windowSize,
            'reset_time' => $resetTime,
            'remaining' => 0,
            'is_burst' => false,
            'retry_after' => $resetTime - $now,
        ];
    }
    
    /**
     * Token bucket rate limiting strategy
     */
    private function tokenBucketStrategy(string $identifier, array $config, Request $request, string $endpoint): array
    {
        $now = time();
        $maxTokens = $config['burst_limit'];
        $refillRate = $config['requests_per_minute'] / 60; // tokens per second
        $windowSize = $config['window_size'];
        
        $bucketKey = "rate_limit:bucket:{$identifier}:{$endpoint}";
        $bucket = Cache::get($bucketKey, [
            'tokens' => $maxTokens,
            'last_refill' => $now,
        ]);
        
        // Calculate tokens to add based on time passed
        $timePassed = $now - $bucket['last_refill'];
        $tokensToAdd = $timePassed * $refillRate;
        $bucket['tokens'] = min($maxTokens, $bucket['tokens'] + $tokensToAdd);
        $bucket['last_refill'] = $now;
        
        $allowed = $bucket['tokens'] >= 1;
        
        if ($allowed) {
            $bucket['tokens'] -= 1;
            Cache::put($bucketKey, $bucket, $windowSize + 10);
            
            return [
                'allowed' => true,
                'strategy' => 'token_bucket',
                'current_requests' => $maxTokens - $bucket['tokens'],
                'max_requests' => $maxTokens,
                'burst_limit' => $maxTokens,
                'window_size' => $windowSize,
                'reset_time' => $now + $windowSize,
                'remaining' => (int) $bucket['tokens'],
                'is_burst' => false,
                'retry_after' => null,
            ];
        }
        
        $timeToNextToken = (1 - $bucket['tokens']) / $refillRate;
        
        return [
            'allowed' => false,
            'strategy' => 'token_bucket',
            'current_requests' => $maxTokens - $bucket['tokens'],
            'max_requests' => $maxTokens,
            'burst_limit' => $maxTokens,
            'window_size' => $windowSize,
            'reset_time' => $now + $timeToNextToken,
            'remaining' => (int) $bucket['tokens'],
            'is_burst' => false,
            'retry_after' => (int) ceil($timeToNextToken),
        ];
    }
    
    /**
     * Fixed window rate limiting strategy
     */
    private function fixedWindowStrategy(string $identifier, array $config, Request $request, string $endpoint): array
    {
        $now = time();
        $windowSize = $config['window_size'];
        $maxRequests = $config['requests_per_minute'];
        
        // Create fixed window boundaries
        $windowStart = floor($now / $windowSize) * $windowSize;
        $windowKey = "rate_limit:fixed:{$identifier}:{$endpoint}:{$windowStart}";
        
        $currentRequests = Cache::get($windowKey, 0);
        $allowed = $currentRequests < $maxRequests;
        
        if ($allowed) {
            Cache::put($windowKey, $currentRequests + 1, $windowSize + 10);
            
            return [
                'allowed' => true,
                'strategy' => 'fixed_window',
                'current_requests' => $currentRequests + 1,
                'max_requests' => $maxRequests,
                'burst_limit' => $maxRequests,
                'window_size' => $windowSize,
                'reset_time' => $windowStart + $windowSize,
                'remaining' => $maxRequests - ($currentRequests + 1),
                'is_burst' => false,
                'retry_after' => null,
            ];
        }
        
        return [
            'allowed' => false,
            'strategy' => 'fixed_window',
            'current_requests' => $currentRequests,
            'max_requests' => $maxRequests,
            'burst_limit' => $maxRequests,
            'window_size' => $windowSize,
            'reset_time' => $windowStart + $windowSize,
            'remaining' => 0,
            'is_burst' => false,
            'retry_after' => $windowStart + $windowSize - $now,
        ];
    }
    
    /**
     * Log rate limit check for monitoring
     */
    private function logRateLimitCheck(Request $request, array $result): void
    {
        $logData = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
            'strategy' => $result['strategy'],
            'allowed' => $result['allowed'],
            'current_requests' => $result['current_requests'],
            'max_requests' => $result['max_requests'],
            'remaining' => $result['remaining'],
            'is_burst' => $result['is_burst'] ?? false,
        ];
        
        if (!$result['allowed']) {
            $this->loggingService->logSecurity('rate_limit_exceeded', $logData);
        } elseif ($result['remaining'] < 10) {
            $this->loggingService->logAudit('rate_limit_warning', 'Rate Limiting', null, $logData);
        }
    }
    
    /**
     * Get rate limit statistics for monitoring
     */
    public function getRateLimitStats(string $identifier = null, string $endpoint = null): array
    {
        $stats = [];
        
        // Get all rate limit keys
        $pattern = $identifier ? "rate_limit:*:{$identifier}*" : "rate_limit:*";
        if ($endpoint) {
            $pattern .= ":{$endpoint}";
        }
        
        // This is a simplified version - in production you'd want to use Redis SCAN
        $keys = Cache::get('rate_limit_keys', []);
        
        foreach ($keys as $key) {
            $data = Cache::get($key);
            if ($data) {
                $stats[$key] = $data;
            }
        }
        
        return $stats;
    }
    
    /**
     * Clear rate limits for specific identifier
     */
    public function clearRateLimit(string $identifier, string $endpoint = null): bool
    {
        $pattern = $endpoint ? "rate_limit:*:{$identifier}:{$endpoint}" : "rate_limit:*:{$identifier}*";
        
        // In production, you'd want to use Redis DEL with pattern matching
        $keys = Cache::get('rate_limit_keys', []);
        $cleared = 0;
        
        foreach ($keys as $key) {
            if (str_contains($key, $identifier)) {
                Cache::forget($key);
                $cleared++;
            }
        }
        
        $this->loggingService->logAudit('rate_limit_cleared', 'Rate Limiting', null, [
            'identifier' => $identifier,
            'endpoint' => $endpoint,
            'keys_cleared' => $cleared,
        ]);
        
        return $cleared > 0;
    }
    
    /**
     * Update rate limit configuration dynamically
     */
    public function updateConfig(string $endpoint, array $config): bool
    {
        $configKey = "rate_limit_config:{$endpoint}";
        Cache::put($configKey, $config, 3600); // Cache for 1 hour
        
        $this->loggingService->logAudit('rate_limit_config_updated', 'Rate Limiting', null, [
            'endpoint' => $endpoint,
            'config' => $config,
        ]);
        
        return true;
    }
}