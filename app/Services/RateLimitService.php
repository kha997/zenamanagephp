<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Rate Limiting Service
 * 
 * Provides advanced rate limiting capabilities:
 * - Dynamic rate limit adjustments based on system load
 * - User-specific rate limits based on subscription tiers
 * - Endpoint-specific rate limiting
 * - Rate limit analytics and monitoring
 */
class RateLimitService
{
    private array $userTierLimits = [
        'free' => [
            'requests_per_minute' => 60,
            'burst_limit' => 100,
            'daily_limit' => 1000,
        ],
        'premium' => [
            'requests_per_minute' => 300,
            'burst_limit' => 500,
            'daily_limit' => 10000,
        ],
        'enterprise' => [
            'requests_per_minute' => 1000,
            'burst_limit' => 2000,
            'daily_limit' => 100000,
        ],
        'admin' => [
            'requests_per_minute' => 5000,
            'burst_limit' => 10000,
            'daily_limit' => 1000000,
        ],
    ];

    private array $endpointLimits = [
        'auth/login' => ['requests_per_minute' => 5, 'burst_limit' => 10],
        'auth/register' => ['requests_per_minute' => 3, 'burst_limit' => 5],
        'upload' => ['requests_per_minute' => 10, 'burst_limit' => 20],
        'export' => ['requests_per_minute' => 2, 'burst_limit' => 5],
        'bulk' => ['requests_per_minute' => 5, 'burst_limit' => 10],
    ];

    /**
     * Get rate limit configuration for user and endpoint
     */
    public function getRateLimitConfig(Request $request, string $endpoint = null): array
    {
        $user = $request->user();
        $userTier = $user ? $this->getUserTier($user) : 'free';
        $baseConfig = $this->userTierLimits[$userTier];
        
        // Apply endpoint-specific limits if available
        if ($endpoint && isset($this->endpointLimits[$endpoint])) {
            $endpointConfig = $this->endpointLimits[$endpoint];
            $baseConfig = array_merge($baseConfig, $endpointConfig);
        }
        
        // Adjust based on system load
        $baseConfig = $this->adjustForSystemLoad($baseConfig);
        
        return $baseConfig;
    }

    /**
     * Get static configuration for rate limit types (used by tests)
     */
    public function getConfig(string $type = 'default'): array
    {
        $configs = [
            'default' => [
                'requests_per_minute' => 60,
                'burst_limit' => 100,
                'window_size' => 60,
            ],
            'auth' => [
                'requests_per_minute' => 5,
                'burst_limit' => 10,
                'window_size' => 60,
            ],
            'api' => [
                'requests_per_minute' => 10,
                'burst_limit' => 20,
                'window_size' => 60,
            ],
        ];

        return $configs[$type] ?? $configs['default'];
    }

    /**
     * Check if request is allowed based on multiple criteria
     */
    public function isRequestAllowed(Request $request, string $endpoint = null): array
    {
        $config = $this->getRateLimitConfig($request, $endpoint);
        $identifier = $this->getIdentifier($request);
        
        // Check minute-based rate limit
        $minuteResult = $this->checkMinuteRateLimit($identifier, $config);
        
        // Check daily rate limit
        $dailyResult = $this->checkDailyRateLimit($identifier, $config);
        
        // Check burst limit
        $burstResult = $this->checkBurstLimit($identifier, $config);
        
        $allowed = $minuteResult['allowed'] && $dailyResult['allowed'] && $burstResult['allowed'];
        
        return [
            'allowed' => $allowed,
            'minute_limit' => $minuteResult,
            'daily_limit' => $dailyResult,
            'burst_limit' => $burstResult,
            'config' => $config,
        ];
    }

    /**
     * Get user tier based on user properties
     */
    private function getUserTier($user): string
    {
        // Check if user is admin
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return 'admin';
        }
        
        // Check subscription tier (you might have a subscription system)
        if (method_exists($user, 'subscription') && $user->subscription) {
            return $user->subscription->tier ?? 'free';
        }
        
        // Default to free tier
        return 'free';
    }

    /**
     * Adjust rate limits based on system load
     */
    private function adjustForSystemLoad(array $config): array
    {
        // Get system load metrics
        $systemLoad = $this->getSystemLoad();
        
        // Reduce limits if system is under high load
        if ($systemLoad > 0.8) {
            $config['requests_per_minute'] = (int)($config['requests_per_minute'] * 0.5);
            $config['burst_limit'] = (int)($config['burst_limit'] * 0.5);
        } elseif ($systemLoad > 0.6) {
            $config['requests_per_minute'] = (int)($config['requests_per_minute'] * 0.8);
            $config['burst_limit'] = (int)($config['burst_limit'] * 0.8);
        }
        
        return $config;
    }

    /**
     * Get system load (simplified implementation)
     */
    private function getSystemLoad(): float
    {
        // In a real implementation, you might check:
        // - CPU usage
        // - Memory usage
        // - Database connection count
        // - Queue length
        
        // For now, return a mock value
        return 0.3; // 30% load
    }

    /**
     * Check minute-based rate limit
     */
    private function checkMinuteRateLimit(string $identifier, array $config): array
    {
        $now = time();
        $windowSize = 60; // 1 minute
        $maxRequests = $config['requests_per_minute'];
        
        $windowKey = "rate_limit:minute:{$identifier}";
        $currentWindow = Cache::get($windowKey, []);
        
        // Clean old entries
        $currentWindow = array_filter($currentWindow, function($timestamp) use ($now, $windowSize) {
            return ($now - $timestamp) < $windowSize;
        });
        
        $currentRequests = count($currentWindow);
        $allowed = $currentRequests < $maxRequests;
        
        if ($allowed) {
            $currentWindow[] = $now;
            Cache::put($windowKey, $currentWindow, $windowSize + 10);
        }
        
        return [
            'allowed' => $allowed,
            'current_requests' => $currentRequests,
            'max_requests' => $maxRequests,
            'remaining' => max(0, $maxRequests - $currentRequests),
            'reset_time' => $now + $windowSize,
        ];
    }

    /**
     * Check daily rate limit
     */
    private function checkDailyRateLimit(string $identifier, array $config): array
    {
        $today = date('Y-m-d');
        $maxRequests = $config['daily_limit'];
        
        $dailyKey = "rate_limit:daily:{$identifier}:{$today}";
        $currentRequests = Cache::get($dailyKey, 0);
        
        $allowed = $currentRequests < $maxRequests;
        
        if ($allowed) {
            Cache::increment($dailyKey);
            Cache::expire($dailyKey, 86400); // 24 hours
        }
        
        return [
            'allowed' => $allowed,
            'current_requests' => $currentRequests,
            'max_requests' => $maxRequests,
            'remaining' => max(0, $maxRequests - $currentRequests),
            'reset_time' => strtotime('tomorrow'),
        ];
    }

    /**
     * Check burst limit (short-term spike allowance)
     */
    private function checkBurstLimit(string $identifier, array $config): array
    {
        $now = time();
        $windowSize = 10; // 10 seconds
        $maxRequests = $config['burst_limit'];
        
        $burstKey = "rate_limit:burst:{$identifier}";
        $currentWindow = Cache::get($burstKey, []);
        
        // Clean old entries
        $currentWindow = array_filter($currentWindow, function($timestamp) use ($now, $windowSize) {
            return ($now - $timestamp) < $windowSize;
        });
        
        $currentRequests = count($currentWindow);
        $allowed = $currentRequests < $maxRequests;
        
        if ($allowed) {
            $currentWindow[] = $now;
            Cache::put($burstKey, $currentWindow, $windowSize + 5);
        }
        
        return [
            'allowed' => $allowed,
            'current_requests' => $currentRequests,
            'max_requests' => $maxRequests,
            'remaining' => max(0, $maxRequests - $currentRequests),
            'reset_time' => $now + $windowSize,
        ];
    }

    /**
     * Get unique identifier for rate limiting
     */
    public function getIdentifier(Request $request): string
    {
        if ($request->user()) {
            return 'user:' . $request->user()->id;
        }
        
        return 'ip:' . $request->ip();
    }

    /**
     * Get rate limit statistics for monitoring
     */
    public function getRateLimitStats(string $identifier): array
    {
        $now = time();
        
        return [
            'minute' => $this->getCurrentWindowStats("rate_limit:minute:{$identifier}"),
            'daily' => $this->getDailyStats($identifier),
            'burst' => $this->getCurrentWindowStats("rate_limit:burst:{$identifier}"),
        ];
    }

    /**
     * Get current window statistics
     */
    private function getCurrentWindowStats(string $key): array
    {
        $window = Cache::get($key, []);
        $now = time();
        
        // Clean old entries
        $window = array_filter($window, function($timestamp) use ($now) {
            return ($now - $timestamp) < 60;
        });
        
        return [
            'current_requests' => count($window),
            'window_size' => 60,
        ];
    }

    /**
     * Get daily statistics
     */
    private function getDailyStats(string $identifier): array
    {
        $today = date('Y-m-d');
        $dailyKey = "rate_limit:daily:{$identifier}:{$today}";
        
        return [
            'current_requests' => Cache::get($dailyKey, 0),
            'date' => $today,
        ];
    }
}
