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
        \App\Http\Middleware\EnhancedRateLimitMiddleware::class,
        \App\Http\Middleware\ApiResponseCacheMiddleware::class,
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
            \App\Http\Middleware\ResetRequestContextMiddleware::class,
\App\Http\Middleware\CorsMiddleware::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\ApiResponseEnvelopeMiddleware::class,
            \App\Http\Middleware\ErrorEnvelopeMiddleware::class,
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
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
     * Return middleware aliases for tooling compatibility.
     *
     * @return array<string, class-string|string>
     */
    public function getMiddlewareAliases(): array
    {
        return self::CANONICAL_MIDDLEWARE_ALIASES;
    }
    
    /**
     * Return middleware stack for tooling compatibility.
     *
     * @return array<int, class-string|string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Return middleware groups for tooling compatibility.
     *
     * @return array<string, array<int, class-string|string>>
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Return route middleware map for tooling compatibility.
     *
     * @return array<string, class-string|string>
     */
    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
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
