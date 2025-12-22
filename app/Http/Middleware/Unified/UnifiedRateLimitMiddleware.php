<?php declare(strict_types=1);

namespace App\Http\Middleware\Unified;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Support\ApiResponse;

/**
 * Unified Rate Limit Middleware
 * 
 * Consolidates all rate limiting functionality into a single middleware
 * Replaces: AdvancedRateLimitMiddleware, EnhancedRateLimitMiddleware, 
 *           ComprehensiveRateLimitMiddleware, APIRateLimitMiddleware, etc.
 */
class UnifiedRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $strategy = 'sliding', int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $key = $this->generateKey($request);
        $config = $this->getRateLimitConfig($request, $strategy, $maxAttempts, $decayMinutes);
        
        // Check rate limit
        $rateLimitResult = $this->checkRateLimit($key, $config);
        
        if (!$rateLimitResult['allowed']) {
            return $this->handleRateLimitExceeded($request, $rateLimitResult);
        }
        
        // Update rate limit counters
        $this->updateRateLimitCounters($key, $config);
        
        // Log rate limit activity
        $this->logRateLimitActivity($request, $rateLimitResult, $config);
        
        // Add rate limit headers to response
        $response = $next($request);
        $this->addRateLimitHeaders($response, $rateLimitResult, $config);
        
        return $response;
    }

    /**
     * Generate rate limit key based on request context
     */
    protected function generateKey(Request $request): string
    {
        $user = Auth::user();
        $ip = $request->ip();
        $route = $request->route()?->getName() ?? $request->path();
        
        if ($user) {
            return "rate_limit:user:{$user->id}:{$route}";
        }
        
        return "rate_limit:ip:{$ip}:{$route}";
    }

    /**
     * Get rate limit configuration based on context
     */
    protected function getRateLimitConfig(Request $request, string $strategy, int $maxAttempts, int $decayMinutes): array
    {
        $user = Auth::user();
        $route = $request->route()?->getName() ?? $request->path();
        
        // Base configuration
        $config = [
            'strategy' => $strategy,
            'max_attempts' => $maxAttempts,
            'decay_minutes' => $decayMinutes,
            'penalty_multiplier' => 1.0,
            'success_reduction' => 0.1,
        ];
        
        // Adjust based on user role
        if ($user) {
            switch ($user->role) {
                case 'admin':
                    $config['max_attempts'] = (int) ($maxAttempts * 2);
                    $config['penalty_multiplier'] = 0.5;
                    break;
                case 'member':
                    $config['max_attempts'] = (int) ($maxAttempts * 1.5);
                    $config['penalty_multiplier'] = 0.8;
                    break;
                case 'client':
                    $config['max_attempts'] = (int) ($maxAttempts * 0.8);
                    $config['penalty_multiplier'] = 1.2;
                    break;
            }
        }
        
        // Adjust based on route sensitivity
        if (str_contains($route, 'auth') || str_contains($route, 'login')) {
            $config['max_attempts'] = (int) ($maxAttempts * 0.5);
            $config['penalty_multiplier'] = 2.0;
        } elseif (str_contains($route, 'admin')) {
            $config['max_attempts'] = (int) ($maxAttempts * 1.5);
            $config['penalty_multiplier'] = 0.7;
        }
        
        return $config;
    }

    /**
     * Check rate limit based on strategy
     */
    protected function checkRateLimit(string $key, array $config): array
    {
        $strategy = $config['strategy'];
        $maxAttempts = $config['max_attempts'];
        $decayMinutes = $config['decay_minutes'];
        
        switch ($strategy) {
            case 'sliding':
                return $this->checkSlidingWindow($key, $maxAttempts, $decayMinutes);
            case 'token_bucket':
                return $this->checkTokenBucket($key, $maxAttempts, $decayMinutes);
            case 'fixed':
                return $this->checkFixedWindow($key, $maxAttempts, $decayMinutes);
            default:
                return $this->checkSlidingWindow($key, $maxAttempts, $decayMinutes);
        }
    }

    /**
     * Check sliding window rate limit
     */
    protected function checkSlidingWindow(string $key, int $maxAttempts, int $decayMinutes): array
    {
        $now = now();
        $windowStart = $now->subMinutes($decayMinutes);
        
        // Get current attempts in window
        $attempts = Cache::get("{$key}:attempts", []);
        $attempts = array_filter($attempts, fn($timestamp) => $timestamp > $windowStart->timestamp);
        
        $currentAttempts = count($attempts);
        $remaining = max(0, $maxAttempts - $currentAttempts);
        
        return [
            'allowed' => $currentAttempts < $maxAttempts,
            'current_attempts' => $currentAttempts,
            'max_attempts' => $maxAttempts,
            'remaining' => $remaining,
            'reset_time' => $now->addMinutes($decayMinutes)->timestamp,
            'strategy' => 'sliding'
        ];
    }

    /**
     * Check token bucket rate limit
     */
    protected function checkTokenBucket(string $key, int $maxAttempts, int $decayMinutes): array
    {
        $bucket = Cache::get("{$key}:bucket", [
            'tokens' => $maxAttempts,
            'last_refill' => now()->timestamp
        ]);
        
        $now = now()->timestamp;
        $timePassed = $now - $bucket['last_refill'];
        $tokensToAdd = ($timePassed / 60) * ($maxAttempts / $decayMinutes);
        
        $bucket['tokens'] = min($maxAttempts, $bucket['tokens'] + $tokensToAdd);
        $bucket['last_refill'] = $now;
        
        $allowed = $bucket['tokens'] >= 1;
        
        if ($allowed) {
            $bucket['tokens'] -= 1;
        }
        
        Cache::put("{$key}:bucket", $bucket, now()->addMinutes($decayMinutes * 2));
        
        return [
            'allowed' => $allowed,
            'current_attempts' => $maxAttempts - $bucket['tokens'],
            'max_attempts' => $maxAttempts,
            'remaining' => (int) $bucket['tokens'],
            'reset_time' => $now + ($decayMinutes * 60),
            'strategy' => 'token_bucket'
        ];
    }

    /**
     * Check fixed window rate limit
     */
    protected function checkFixedWindow(string $key, int $maxAttempts, int $decayMinutes): array
    {
        $window = now()->startOfMinute()->timestamp;
        $windowKey = "{$key}:window:{$window}";
        
        $currentAttempts = Cache::get($windowKey, 0);
        $remaining = max(0, $maxAttempts - $currentAttempts);
        
        return [
            'allowed' => $currentAttempts < $maxAttempts,
            'current_attempts' => $currentAttempts,
            'max_attempts' => $maxAttempts,
            'remaining' => $remaining,
            'reset_time' => now()->addMinutes($decayMinutes)->startOfMinute()->timestamp,
            'strategy' => 'fixed'
        ];
    }

    /**
     * Update rate limit counters
     */
    protected function updateRateLimitCounters(string $key, array $config): void
    {
        $strategy = $config['strategy'];
        $decayMinutes = $config['decay_minutes'];
        
        switch ($strategy) {
            case 'sliding':
                $this->updateSlidingWindow($key, $decayMinutes);
                break;
            case 'fixed':
                $this->updateFixedWindow($key, $decayMinutes);
                break;
            // Token bucket is updated in checkTokenBucket
        }
    }

    /**
     * Update sliding window counter
     */
    protected function updateSlidingWindow(string $key, int $decayMinutes): void
    {
        $attempts = Cache::get("{$key}:attempts", []);
        $attempts[] = now()->timestamp;
        
        Cache::put("{$key}:attempts", $attempts, now()->addMinutes($decayMinutes * 2));
    }

    /**
     * Update fixed window counter
     */
    protected function updateFixedWindow(string $key, int $decayMinutes): void
    {
        $window = now()->startOfMinute()->timestamp;
        $windowKey = "{$key}:window:{$window}";
        
        Cache::increment($windowKey);
        Cache::expire($windowKey, now()->addMinutes($decayMinutes * 2));
    }

    /**
     * Handle rate limit exceeded
     */
    protected function handleRateLimitExceeded(Request $request, array $rateLimitResult): Response
    {
        $retryAfter = $rateLimitResult['reset_time'] - now()->timestamp;
        
        Log::warning('Rate limit exceeded', [
            'ip' => $request->ip(),
            'user_id' => Auth::id(),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'current_attempts' => $rateLimitResult['current_attempts'],
            'max_attempts' => $rateLimitResult['max_attempts'],
            'strategy' => $rateLimitResult['strategy'],
            'retry_after' => $retryAfter,
            'request_id' => $request->header('X-Request-Id')
        ]);
        
        return ApiResponse::error(
            'Too many requests',
            429,
            [
                'retry_after' => $retryAfter,
                'current_attempts' => $rateLimitResult['current_attempts'],
                'max_attempts' => $rateLimitResult['max_attempts'],
                'remaining' => $rateLimitResult['remaining']
            ],
            'RATE_LIMIT_EXCEEDED'
        )->header('Retry-After', $retryAfter);
    }

    /**
     * Log rate limit activity
     */
    protected function logRateLimitActivity(Request $request, array $rateLimitResult, array $config): void
    {
        Log::info('Rate limit activity', [
            'ip' => $request->ip(),
            'user_id' => Auth::id(),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'current_attempts' => $rateLimitResult['current_attempts'],
            'max_attempts' => $rateLimitResult['max_attempts'],
            'remaining' => $rateLimitResult['remaining'],
            'strategy' => $rateLimitResult['strategy'],
            'penalty_multiplier' => $config['penalty_multiplier'],
            'request_id' => $request->header('X-Request-Id')
        ]);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(Response $response, array $rateLimitResult, array $config): void
    {
        $response->headers->set('X-RateLimit-Limit', (string) $rateLimitResult['max_attempts']);
        $response->headers->set('X-RateLimit-Remaining', (string) $rateLimitResult['remaining']);
        $response->headers->set('X-RateLimit-Reset', (string) $rateLimitResult['reset_time']);
        $response->headers->set('X-RateLimit-Strategy', (string) $rateLimitResult['strategy']);
    }
}
