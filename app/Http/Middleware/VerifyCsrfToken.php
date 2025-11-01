<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // API routes are excluded (they use token authentication)
        'api/*',
        'app/api/*',
        
        // Webhook endpoints (if any)
        'webhooks/*',
        
        // Test endpoints (only in development)
        'test-login-simple',
        'test-api-*',
    ];

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     */
    protected function inExceptArray($request): bool
    {
        // Only exclude API routes and webhooks in production
        if (app()->environment('production')) {
            $this->except = array_filter($this->except, function ($pattern) {
                return !str_starts_with($pattern, 'test-');
            });
        }

        // Ensure session is started for CSRF verification
        if (!$request->hasSession()) {
            return false; // Don't bypass CSRF if no session
        }

        return parent::inExceptArray($request);
    }

    /**
     * Handle an incoming request.
     */
    public function handle($request, \Closure $next)
    {
        // Ensure session is started
        if (!$request->hasSession()) {
            $request->setLaravelSession(app('session.store'));
        }

        // Force CSRF verification for POST requests
        if ($request->isMethod('POST') && !$this->inExceptArray($request)) {
            $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');
            $sessionToken = $request->session()->token();
            
            if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
                abort(419, 'CSRF token mismatch');
            }
        }

        return parent::handle($request, $next);
    }

    /**
     * Add the CSRF token to the response.
     */
    protected function addCookieToResponse($request, $response)
    {
        return parent::addCookieToResponse($request, $response);
    }
}