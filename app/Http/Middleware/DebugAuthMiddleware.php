<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Debug Auth Middleware
 * 
 * Temporary middleware to debug authentication issues in E2E tests.
 * Only enabled in testing/local environments.
 */
class DebugAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only log in testing environment and for specific paths
        if (!app()->environment('testing')) {
            return $next($request);
        }

        $path = $request->path();
        $fullUrl = $request->fullUrl();
        $method = $request->method();
        
        // Round 158: Log for /login (POST /api/auth/login) and /api/v1/me
        // Also log any request that contains 'login' or 'v1/me' in path or URL
        $shouldLog = in_array($path, [
            'login',
            'api/login',
            'api/auth/login',
            'api/v1/me',
        ]) || str_contains($path, 'v1/me') || str_contains($fullUrl, '/api/v1/me') 
        || str_contains($path, 'login') || str_contains($fullUrl, '/api/auth/login');
        
        // Round 158: Always log middleware call in testing to verify it's being executed
        if (str_contains($path, 'login') || str_contains($path, 'v1/me') || str_contains($fullUrl, '/api/v1/me')) {
            Log::info('[E2E_AUTH_DEBUG_MIDDLEWARE] Middleware called', [
                'path' => $path,
                'method' => $method,
                'full_url' => $fullUrl,
                'should_log' => $shouldLog,
                'environment' => app()->environment(),
            ]);
        }
        
        if ($shouldLog) {
            $sessionCookieName = config('session.cookie', 'laravel_session');
            $sanctumStateful = config('sanctum.stateful', []);
            
            // Round 158: Use Sanctum's actual stateful check via StatefulGuard
            $isStatefulBySanctum = false;
            try {
                // Check if Sanctum's EnsureFrontendRequestsAreStateful has already processed this request
                $hasSanctumAttribute = $request->attributes->has('sanctum');
                
                // Try to use Sanctum's actual validation if available
                // Sanctum checks Origin/Referer against stateful domains
                $referer = $request->header('Referer');
                $origin = $request->header('Origin');
                $domain = $referer ?: $origin;
                
                if ($domain) {
                    // Extract host from domain (remove protocol and path)
                    $parsedUrl = parse_url($domain);
                    $requestHost = $parsedUrl['host'] ?? null;
                    
                    if ($requestHost) {
                        // Check if request host matches any stateful domain (with or without port)
                        foreach ($sanctumStateful as $statefulUri) {
                            $statefulHost = trim($statefulUri);
                            // Remove port from stateful host for comparison
                            $statefulHostNoPort = preg_replace('/:\d+$/', '', $statefulHost);
                            
                            // Match if hostnames match (ignoring port)
                            if ($requestHost === $statefulHost || $requestHost === $statefulHostNoPort) {
                                $isStatefulBySanctum = true;
                                break;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // If Sanctum check fails, log but continue
                Log::warning('[E2E_AUTH_DEBUG] Sanctum stateful check failed', [
                    'error' => $e->getMessage(),
                ]);
            }
            
            Log::info('[E2E_AUTH_DEBUG]', [
                'url' => $request->method() . ' ' . $request->fullUrl(),
                'path' => $path,
                'host' => $request->getHost(),
                'port' => $request->getPort(),
                'sanctum_stateful' => $sanctumStateful,
                'is_stateful_by_sanctum' => $isStatefulBySanctum,
                'has_sanctum_attribute' => $hasSanctumAttribute ?? false,
                'session_cookie_name' => $sessionCookieName,
                'has_session_cookie' => $request->cookies->has($sessionCookieName),
                'session_id' => session()->getId(),
                'session_driver' => config('session.driver'),
                'session_started' => session()->isStarted(),
                'guard_web_id' => optional(auth('web')->user())->id,
                'guard_sanctum_id' => optional(auth('sanctum')->user())->id,
                'auth_default_id' => optional(auth()->user())->id,
                'referer' => $referer ?? null,
                'origin' => $origin ?? null,
                'domain_checked' => $domain ?? null,
                'cookies_all' => array_keys($request->cookies->all()),
                'headers_relevant' => [
                    'X-XSRF-TOKEN' => $request->header('X-XSRF-TOKEN') ? 'present' : 'missing',
                    'Authorization' => $request->header('Authorization') ? 'present' : 'missing',
                    'X-Requested-With' => $request->header('X-Requested-With'),
                    'X-Web-Login' => $request->header('X-Web-Login'),
                ],
            ]);
        }

        return $next($request);
    }
}

