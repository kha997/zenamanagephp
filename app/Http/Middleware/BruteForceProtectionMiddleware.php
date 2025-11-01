<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Brute Force Protection Middleware
 * 
 * Implements brute force protection with:
 * - Rate limiting for login attempts
 * - Account lockout after failed attempts
 * - IP-based blocking
 * - Progressive delays
 */
class BruteForceProtectionMiddleware
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes
    private const PROGRESSIVE_DELAY_BASE = 2; // seconds
    
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to login endpoints
        if (!$this->isLoginEndpoint($request)) {
            return $next($request);
        }
        
        $email = $request->input('email');
        $ip = $request->ip();
        
        // Check if account is locked
        if ($this->isAccountLocked($email)) {
            return $this->handleAccountLocked($request, $email);
        }
        
        // Check if IP is blocked
        if ($this->isIpBlocked($ip)) {
            return $this->handleIpBlocked($request, $ip);
        }
        
        // Check attempt count
        $attempts = $this->getAttemptCount($email, $ip);
        
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->lockAccount($email);
            $this->blockIp($ip);
            
            Log::warning('Account locked due to brute force attempts', [
                'email' => $email,
                'ip' => $ip,
                'attempts' => $attempts,
                'request_id' => $request->header('X-Request-Id')
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Account temporarily locked due to multiple failed attempts. Please try again in 15 minutes.',
                'code' => 'ACCOUNT_LOCKED',
                'retry_after' => self::LOCKOUT_DURATION
            ], 423);
        }
        
        // Add progressive delay for failed attempts
        if ($attempts > 0) {
            $delay = self::PROGRESSIVE_DELAY_BASE * pow(2, $attempts - 1);
            sleep(min($delay, 10)); // Max 10 seconds delay
        }
        
        $response = $next($request);
        
        // If login failed, increment attempt count
        if ($response->getStatusCode() === 401) {
            $this->incrementAttemptCount($email, $ip);
            
            Log::info('Failed login attempt recorded', [
                'email' => $email,
                'ip' => $ip,
                'attempts' => $attempts + 1,
                'request_id' => $request->header('X-Request-Id')
            ]);
        } else {
            // Clear attempts on successful login
            $this->clearAttempts($email, $ip);
        }
        
        return $response;
    }
    
    private function isLoginEndpoint(Request $request): bool
    {
        return $request->is('api/auth/login') || 
               $request->is('api/login') ||
               $request->is('login');
    }
    
    private function isAccountLocked(string $email): bool
    {
        return Cache::has("brute_force:account:{$email}");
    }
    
    private function isIpBlocked(string $ip): bool
    {
        return Cache::has("brute_force:ip:{$ip}");
    }
    
    private function getAttemptCount(string $email, string $ip): int
    {
        $emailKey = "brute_force:attempts:email:{$email}";
        $ipKey = "brute_force:attempts:ip:{$ip}";
        
        $emailAttempts = Cache::get($emailKey, 0);
        $ipAttempts = Cache::get($ipKey, 0);
        
        return max($emailAttempts, $ipAttempts);
    }
    
    private function lockAccount(string $email): void
    {
        Cache::put("brute_force:account:{$email}", true, self::LOCKOUT_DURATION);
    }
    
    private function blockIp(string $ip): void
    {
        Cache::put("brute_force:ip:{$ip}", true, self::LOCKOUT_DURATION);
    }
    
    private function incrementAttemptCount(string $email, string $ip): void
    {
        $emailKey = "brute_force:attempts:email:{$email}";
        $ipKey = "brute_force:attempts:ip:{$ip}";
        
        Cache::increment($emailKey, 1);
        Cache::increment($ipKey, 1);
        
        // Set expiration for attempt counters
        Cache::put($emailKey, Cache::get($emailKey), self::LOCKOUT_DURATION);
        Cache::put($ipKey, Cache::get($ipKey), self::LOCKOUT_DURATION);
    }
    
    private function clearAttempts(string $email, string $ip): void
    {
        Cache::forget("brute_force:attempts:email:{$email}");
        Cache::forget("brute_force:attempts:ip:{$ip}");
        Cache::forget("brute_force:account:{$email}");
        Cache::forget("brute_force:ip:{$ip}");
    }
    
    private function handleAccountLocked(Request $request, string $email): Response
    {
        Log::warning('Blocked login attempt to locked account', [
            'email' => $email,
            'ip' => $request->ip(),
            'request_id' => $request->header('X-Request-Id')
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Account temporarily locked due to multiple failed attempts. Please try again in 15 minutes.',
            'code' => 'ACCOUNT_LOCKED',
            'retry_after' => self::LOCKOUT_DURATION
        ], 423);
    }
    
    private function handleIpBlocked(Request $request, string $ip): Response
    {
        Log::warning('Blocked login attempt from blocked IP', [
            'ip' => $ip,
            'request_id' => $request->header('X-Request-Id')
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'IP address temporarily blocked due to suspicious activity. Please try again in 15 minutes.',
            'code' => 'IP_BLOCKED',
            'retry_after' => self::LOCKOUT_DURATION
        ], 423);
    }
}
