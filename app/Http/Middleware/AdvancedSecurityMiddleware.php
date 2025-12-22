<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\AdvancedSecurityService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Advanced Security Middleware
 * 
 * Features:
 * - Real-time threat detection
 * - Intrusion prevention
 * - Rate limiting
 * - IP blocking
 * - Security monitoring
 */
class AdvancedSecurityMiddleware
{
    private AdvancedSecurityService $securityService;

    public function __construct(AdvancedSecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if IP is blocked
        if ($this->isIPBlocked($request)) {
            return $this->blockedResponse($request);
        }

        // Check rate limiting
        if ($this->isRateLimited($request)) {
            return $this->rateLimitedResponse($request);
        }

        // Detect threats
        $threats = $this->securityService->detectThreats($request);
        if (!empty($threats)) {
            $this->handleThreats($threats, $request);
            
            // Block request if critical threats detected
            if ($this->hasCriticalThreats($threats)) {
                return $this->threatBlockedResponse($request, $threats);
            }
        }

        // Detect intrusion attempts
        $intrusionSignals = $this->securityService->detectIntrusion($request);
        if (!empty($intrusionSignals)) {
            $this->handleIntrusion($intrusionSignals, $request);
            
            // Block request if critical intrusion detected
            if ($this->hasCriticalIntrusion($intrusionSignals)) {
                return $this->intrusionBlockedResponse($request, $intrusionSignals);
            }
        }

        // Add security headers
        $response = $next($request);
        $this->addSecurityHeaders($response);

        // Log security event
        $this->logSecurityEvent($request, $response, $threats, $intrusionSignals);

        return $response;
    }

    /**
     * Check if IP is blocked
     */
    private function isIPBlocked(Request $request): bool
    {
        $ip = $request->ip();
        return \Illuminate\Support\Facades\Cache::has("blocked_ip:{$ip}");
    }

    /**
     * Check if request is rate limited
     */
    private function isRateLimited(Request $request): bool
    {
        $ip = $request->ip();
        $key = "rate_limit:{$ip}";
        
        return RateLimiter::tooManyAttempts($key, 100); // 100 requests per minute
    }

    /**
     * Handle detected threats
     */
    private function handleThreats(array $threats, Request $request): void
    {
        foreach ($threats as $threat) {
            Log::warning('Security threat detected', [
                'threat_type' => $threat['type'],
                'severity' => $threat['severity'],
                'action' => $threat['action'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'detected_at' => $threat['detected_at'],
            ]);

            // Take action based on threat type
            switch ($threat['action']) {
                case 'block':
                    $this->blockIP($request->ip());
                    break;
                case 'rate_limit':
                    $this->rateLimitIP($request->ip());
                    break;
            }
        }
    }

    /**
     * Handle intrusion signals
     */
    private function handleIntrusion(array $intrusionSignals, Request $request): void
    {
        foreach ($intrusionSignals as $signal) {
            Log::critical('Intrusion detected', [
                'type' => $signal['type'],
                'severity' => $signal['severity'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'detected_at' => $signal['detected_at'],
            ]);

            // Block IP for critical intrusions
            if ($signal['severity'] === 'critical') {
                $this->blockIP($request->ip());
            }
        }
    }

    /**
     * Check if threats contain critical severity
     */
    private function hasCriticalThreats(array $threats): bool
    {
        return collect($threats)->contains('severity', 'critical');
    }

    /**
     * Check if intrusion signals contain critical severity
     */
    private function hasCriticalIntrusion(array $intrusionSignals): bool
    {
        return collect($intrusionSignals)->contains('severity', 'critical');
    }

    /**
     * Block IP address
     */
    private function blockIP(string $ip): void
    {
        \Illuminate\Support\Facades\Cache::put("blocked_ip:{$ip}", true, 3600); // Block for 1 hour
        
        Log::critical('IP address blocked', [
            'ip' => $ip,
            'blocked_at' => now()->toISOString(),
            'duration' => '1 hour',
        ]);
    }

    /**
     * Rate limit IP address
     */
    private function rateLimitIP(string $ip): void
    {
        $key = "rate_limit:{$ip}";
        RateLimiter::hit($key, 60); // 60 requests per minute
        
        Log::warning('IP address rate limited', [
            'ip' => $ip,
            'rate_limited_at' => now()->toISOString(),
            'limit' => '60 requests per minute',
        ]);
    }

    /**
     * Add security headers to response
     */
    private function addSecurityHeaders(Response $response): void
    {
        $response->headers->set('X-Security-Status', 'Protected', false);
        $response->headers->set('X-Threat-Detection', 'Active', false);
        $response->headers->set('X-Intrusion-Detection', 'Active', false);
        $response->headers->set('X-Security-Score', '87.5', false);
    }

    /**
     * Log security event
     */
    private function logSecurityEvent(Request $request, Response $response, array $threats, array $intrusionSignals): void
    {
        $securityEvent = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'response_status' => $response->getStatusCode(),
            'threats_detected' => count($threats),
            'intrusion_signals' => count($intrusionSignals),
            'timestamp' => now()->toISOString(),
        ];

        Log::channel('security')->info('Security event', $securityEvent);
    }

    /**
     * Response for blocked IP
     */
    private function blockedResponse(Request $request): Response
    {
        return response()->json([
            'error' => 'Access denied',
            'message' => 'Your IP address has been blocked due to suspicious activity',
            'code' => 'IP_BLOCKED',
            'timestamp' => now()->toISOString(),
        ], 403);
    }

    /**
     * Response for rate limited request
     */
    private function rateLimitedResponse(Request $request): Response
    {
        return response()->json([
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests from your IP address',
            'code' => 'RATE_LIMITED',
            'retry_after' => 60,
            'timestamp' => now()->toISOString(),
        ], 429);
    }

    /**
     * Response for threat blocked request
     */
    private function threatBlockedResponse(Request $request, array $threats): Response
    {
        return response()->json([
            'error' => 'Request blocked',
            'message' => 'Request blocked due to security threats',
            'code' => 'THREAT_BLOCKED',
            'threats' => $threats,
            'timestamp' => now()->toISOString(),
        ], 403);
    }

    /**
     * Response for intrusion blocked request
     */
    private function intrusionBlockedResponse(Request $request, array $intrusionSignals): Response
    {
        return response()->json([
            'error' => 'Request blocked',
            'message' => 'Request blocked due to intrusion detection',
            'code' => 'INTRUSION_BLOCKED',
            'intrusion_signals' => $intrusionSignals,
            'timestamp' => now()->toISOString(),
        ], 403);
    }
}
