<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ComprehensiveRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $group
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $group = 'public')
    {
        $config = config("rate-limiting.limits.{$group}");
        
        if (!$config) {
            return $next($request);
        }

        $key = $this->generateKey($request, $group);
        
        // Check if IP/user is exempt
        if ($this->isExempt($request, $group)) {
            return $next($request);
        }

        // Check burst limit first
        if ($this->isBurstExceeded($key, $config)) {
            return $this->handleBurstExceeded($request, $key, $config);
        }

        // Apply standard rate limiting
        $response = $this->applyRateLimit($request, $next, $key, $config);
        
        // Log monitoring data
        $this->logMonitoringData($request, $group, $key);
        
        return $response;
    }

    /**
     * Generate rate limiting key based on group configuration
     */
    protected function generateKey(Request $request, string $group): string
    {
        $keyGenerator = config("rate-limiting.key_generators.{$group}", 'ip');
        
        switch ($keyGenerator) {
            case 'user':
                $userId = Auth::id();
                return "rate_limit:{$group}:user:{$userId}";
                
            case 'ip':
            default:
                $ip = $request->ip();
                return "rate_limit:{$group}:ip:{$ip}";
        }
    }

    /**
     * Check if request is exempt from rate limiting
     */
    protected function isExempt(Request $request, string $group): bool
    {
        $exemptions = config('rate-limiting.exemptions', []);
        
        // Check IP exemptions
        $clientIp = $request->ip();
        if (in_array($clientIp, $exemptions['ips'] ?? [])) {
            return true;
        }
        
        // Check user ID exemptions
        if (Auth::check()) {
            $userId = Auth::id();
            if (in_array($userId, $exemptions['user_ids'] ?? [])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if burst limit is exceeded
     */
    protected function isBurstExceeded(string $key, array $config): bool
    {
        $burstKey = "{$key}:burst";
        $burstCount = Cache::get($burstKey, 0);
        
        return $burstCount >= $config['burst_limit'];
    }

    /**
     * Handle burst limit exceeded
     */
    protected function handleBurstExceeded(Request $request, string $key, array $config): Response
    {
        $banKey = "{$key}:banned";
        $banDuration = $config['ban_duration'];
        
        // Set ban
        Cache::put($banKey, true, $banDuration);
        
        // Log violation
        if (config('rate-limiting.monitoring.log_violations', true)) {
            Log::warning('Rate limit burst exceeded', [
                'ip' => $request->ip(),
                'user_id' => Auth::id(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'burst_limit' => $config['burst_limit'],
                'ban_duration' => $banDuration,
                'timestamp' => now()->toISOString(),
            ]);
        }
        
        return response()->json([
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $banDuration,
            'type' => 'burst_exceeded'
        ], 429)->header('Retry-After', $banDuration);
    }

    /**
     * Apply standard rate limiting
     */
    protected function applyRateLimit(Request $request, Closure $next, string $key, array $config)
    {
        $maxAttempts = $config['requests_per_minute'];
        $decayMinutes = 1;
        
        // Check if currently banned
        $banKey = "{$key}:banned";
        if (Cache::has($banKey)) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'type' => 'banned'
            ], 429);
        }
        
        // Apply Laravel's built-in rate limiting
        $response = RateLimiter::attempt(
            $key,
            $maxAttempts,
            function () use ($request, $next) {
                return $next($request);
            },
            $decayMinutes * 60
        );
        
        if (!$response) {
            // Rate limit exceeded
            $retryAfter = RateLimiter::availableIn($key);
            
            // Log violation
            if (config('rate-limiting.monitoring.log_violations', true)) {
                Log::warning('Rate limit exceeded', [
                    'ip' => $request->ip(),
                    'user_id' => Auth::id(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl(),
                    'max_attempts' => $maxAttempts,
                    'retry_after' => $retryAfter,
                    'timestamp' => now()->toISOString(),
                ]);
            }
            
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
                'type' => 'rate_exceeded'
            ], 429)->header('Retry-After', $retryAfter);
        }
        
        return $response;
    }

    /**
     * Log monitoring data
     */
    protected function logMonitoringData(Request $request, string $group, string $key): void
    {
        if (!config('rate-limiting.monitoring.enabled', true)) {
            return;
        }
        
        $config = config("rate-limiting.limits.{$group}");
        $currentCount = RateLimiter::attempts($key);
        $threshold = $config['requests_per_minute'] * config('rate-limiting.monitoring.alert_threshold', 0.8);
        
        // Log if approaching threshold
        if ($currentCount >= $threshold) {
            Log::info('Rate limit approaching threshold', [
                'group' => $group,
                'ip' => $request->ip(),
                'user_id' => Auth::id(),
                'current_count' => $currentCount,
                'threshold' => $threshold,
                'max_limit' => $config['requests_per_minute'],
                'timestamp' => now()->toISOString(),
            ]);
        }
        
        // Log successful requests if enabled
        if (config('rate-limiting.monitoring.log_successful_requests', false)) {
            Log::debug('Rate limit request successful', [
                'group' => $group,
                'ip' => $request->ip(),
                'user_id' => Auth::id(),
                'url' => $request->fullUrl(),
                'current_count' => $currentCount,
                'timestamp' => now()->toISOString(),
            ]);
        }
    }
}
