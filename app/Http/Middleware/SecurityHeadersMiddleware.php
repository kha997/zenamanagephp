<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Security Headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // HSTS (only in production with HTTPS)
        if (app()->environment('production') && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        
        // Content Security Policy - allow Vite dev server and fonts in local env
        if (app()->environment('local', 'development', 'testing')) {
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:3000 http://127.0.0.1:3000 http://localhost:3001 http://127.0.0.1:3001 https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
                   "style-src 'self' 'unsafe-inline' http://localhost:3000 http://127.0.0.1:3000 http://localhost:3001 http://127.0.0.1:3001 https://fonts.googleapis.com https://fonts.bunny.net https://cdnjs.cloudflare.com; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net https://cdnjs.cloudflare.com; " .
                   "connect-src 'self' http://localhost:3000 http://127.0.0.1:3000 http://localhost:3001 http://127.0.0.1:3001 ws://localhost:3000 ws://127.0.0.1:3000 ws://localhost:3001 ws://127.0.0.1:3001 ws: wss:; " .
                   "media-src 'self'; " .
                   "object-src 'none'; " .
                   "frame-ancestors 'none'; " .
                   "form-action 'self'; " .
                   "base-uri 'self';";
        } else {
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
                   "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
                   "connect-src 'self' ws: wss:; " .
                   "media-src 'self'; " .
                   "object-src 'none'; " .
                   "frame-ancestors 'none'; " .
                   "form-action 'self'; " .
                   "base-uri 'self';";
        }
        
        $response->headers->set('Content-Security-Policy', $csp);
        
        // Permissions Policy (remove unsupported 'speaker')
        $permissionsPolicy = "camera=(), microphone=(), geolocation=(), " .
                            "payment=(), usb=(), magnetometer=(), " .
                            "accelerometer=(), gyroscope=()";
        $response->headers->set('Permissions-Policy', $permissionsPolicy);
        
        // Remove server information
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        
        return $response;
    }
}