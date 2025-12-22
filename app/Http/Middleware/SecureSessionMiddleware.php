<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SecureSessionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Regenerate session ID periodically for security
        $this->regenerateSessionId($request);

        // Validate session integrity
        $this->validateSessionIntegrity($request);

        // Set secure session flags
        $this->setSecureSessionFlags($request);

        $response = $next($request);

        // Add session security headers
        $this->addSessionSecurityHeaders($response);

        return $response;
    }

    /**
     * Regenerate session ID periodically for security
     */
    private function regenerateSessionId(Request $request): void
    {
        $lastRegeneration = Session::get('last_session_regeneration', 0);
        $currentTime = time();
        
        // Regenerate every 15 minutes for security
        if ($currentTime - $lastRegeneration > 900) {
            Session::regenerate();
            Session::put('last_session_regeneration', $currentTime);
        }
    }

    /**
     * Validate session integrity
     */
    private function validateSessionIntegrity(Request $request): void
    {
        // Check for session hijacking indicators
        $userAgent = $request->userAgent();
        $ipAddress = $request->ip();
        
        $storedUserAgent = Session::get('user_agent');
        $storedIpAddress = Session::get('ip_address');
        
        // If session exists but user agent or IP changed, invalidate session
        if ($storedUserAgent && $storedUserAgent !== $userAgent) {
            Session::invalidate();
            return;
        }
        
        if ($storedIpAddress && $storedIpAddress !== $ipAddress) {
            // Allow IP changes for mobile users (less strict)
            if (!$this->isMobileUser($request)) {
                Session::invalidate();
                return;
            }
        }
        
        // Store current user agent and IP
        Session::put('user_agent', $userAgent);
        Session::put('ip_address', $ipAddress);
    }

    /**
     * Set secure session flags
     */
    private function setSecureSessionFlags(Request $request): void
    {
        // Set session timeout warning
        $sessionTimeout = config('session.lifetime', 120) * 60; // Convert to seconds
        $sessionStart = Session::get('session_start_time', time());
        $timeRemaining = $sessionTimeout - (time() - $sessionStart);
        
        if ($timeRemaining < 300) { // 5 minutes warning
            Session::put('session_warning', true);
        }
        
        // Track session activity
        Session::put('last_activity', time());
    }

    /**
     * Add session security headers
     */
    private function addSessionSecurityHeaders(Response $response): void
    {
        // Set secure cookie flags
        $response->headers->set('Set-Cookie', 
            'laravel_session=' . Session::getId() . '; HttpOnly; Secure; SameSite=Strict; Path=/'
        );
    }

    /**
     * Check if user is on mobile (more lenient IP validation)
     */
    private function isMobileUser(Request $request): bool
    {
        $userAgent = $request->userAgent();
        
        $mobileKeywords = [
            'Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 
            'BlackBerry', 'Windows Phone', 'Opera Mini'
        ];
        
        foreach ($mobileKeywords as $keyword) {
            if (str_contains($userAgent, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
}
