<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // Temporarily disabled all global middleware for debugging
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\ErrorEnvelopeMiddleware::class,
        ],
    ];

    /**
     * Canonical middleware alias map shared across the kernel and router.
     *
     * @var array<string, class-string|string>
     */
    protected const CANONICAL_MIDDLEWARE_ALIASES = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.api' => \App\Http\Middleware\ApiAuthenticationMiddleware::class,
        'auth.session' => \App\Http\Middleware\SessionManagementMiddleware::class,
        'tenant.isolation' => \App\Http\Middleware\TenantIsolationMiddleware::class,
        'rbac' => \App\Http\Middleware\RoleBasedAccessControlMiddleware::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'rate.limit' => \App\Http\Middleware\EnhancedRateLimitMiddleware::class,
        'api.cache' => \App\Http\Middleware\ApiResponseCacheMiddleware::class,
        'debug.gate' => \App\Http\Middleware\DebugGateMiddleware::class,
        'cors' => \App\Http\Middleware\CorsMiddleware::class,
        'security.headers' => \App\Http\Middleware\SecurityHeadersMiddleware::class,
        'input.sanitization' => \App\Http\Middleware\InputSanitizationMiddleware::class,
        'error.envelope' => \App\Http\Middleware\ErrorEnvelopeMiddleware::class,
        'legacy.route' => \App\Http\Middleware\LegacyRouteMiddleware::class,
        'legacy.redirect' => \App\Http\Middleware\LegacyRedirectMiddleware::class,
        'legacy.gone' => \App\Http\Middleware\LegacyGoneMiddleware::class,
    ];

    /**
     * The application's route middleware aliases (Laravel 10+).
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = self::CANONICAL_MIDDLEWARE_ALIASES;

    /**
     * Backward compatible route middleware map (Laravel 9 and earlier tooling).
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = self::CANONICAL_MIDDLEWARE_ALIASES;

    /**
     * The priority-sorted list of middleware.
     *
     * Ensures the Authenticate/TenantIsolation/RBAC sequence never interleaves
     * other middleware in a way that would return the wrong status code.
     *
     * @var array<int, class-string|string>
     */
    protected $middlewarePriority = [
        \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
        \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \Illuminate\Auth\Middleware\Authorize::class,
        \App\Http\Middleware\Authenticate::class,
        \App\Http\Middleware\TenantIsolationMiddleware::class,
        \App\Http\Middleware\RoleBasedAccessControlMiddleware::class,
    ];

    /**
     * Return middleware aliases for tooling compatibility.
     *
     * @return array<string, class-string|string>
     */
    public function getMiddlewareAliases(): array
    {
        return self::CANONICAL_MIDDLEWARE_ALIASES;
    }
    
    /**
     * Override terminate to handle middleware resolution issues
     */
    public function terminate($request, $response)
    {
        try {
            parent::terminate($request, $response);
        } catch (\ReflectionException $e) {
            // Log the error but don't break the application
            \Log::warning('Middleware resolution error during terminate', [
                'error' => $e->getMessage(),
                'request_uri' => $request->getRequestUri(),
                'method' => $request->getMethod()
            ]);
        } catch (\Illuminate\Contracts\Container\BindingResolutionException $e) {
            // Log the error but don't break the application
            \Log::warning('Middleware binding resolution error during terminate', [
                'error' => $e->getMessage(),
                'request_uri' => $request->getRequestUri(),
                'method' => $request->getMethod()
            ]);
        }
    }
}
