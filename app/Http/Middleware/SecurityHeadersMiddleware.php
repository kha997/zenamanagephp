<?php declare(strict_types=1);

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

        // Content Security Policy (CSP)
        $response->headers->set('Content-Security-Policy', 
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; " .
            "img-src 'self' data: https:; " .
            "connect-src 'self' https:; " .
            "frame-src 'none'; " .
            "object-src 'none'; " .
            "base-uri 'self'; " .
            "form-action 'self'; " .
            "upgrade-insecure-requests"
        );

        // X-Content-Type-Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-Frame-Options
        $response->headers->set('X-Frame-Options', 'DENY');

        // X-XSS-Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy
        $response->headers->set('Permissions-Policy', 
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()'
        );

        // Strict Transport Security (HSTS) - only for HTTPS
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Cross-Origin Embedder Policy
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');

        // Cross-Origin Opener Policy
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Cross-Origin Resource Policy
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // Cache Control for sensitive pages
        if ($this->isSensitivePage($request)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    /**
     * Check if the current page is sensitive and should not be cached
     */
    private function isSensitivePage(Request $request): bool
    {
        $sensitivePaths = [
            '/admin',
            '/app/profile',
            '/app/settings',
            '/app/team',
            '/login',
            '/register',
            '/password',
        ];

        foreach ($sensitivePaths as $path) {
            if (str_starts_with($request->getPathInfo(), $path)) {
                return true;
            }
        }

        return false;
    }
}