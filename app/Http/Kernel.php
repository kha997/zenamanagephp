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
            // Temporarily disabled all web middleware for debugging
        ],

        'api' => [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\ErrorEnvelopeMiddleware::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.api' => \App\Http\Middleware\ApiAuthenticationMiddleware::class,
        'auth.session' => \App\Http\Middleware\SessionManagementMiddleware::class,
        'admin.only' => \App\Http\Middleware\AdminOnly::class,
        'tenant.scope' => \App\Http\Middleware\TenantScopeMiddleware::class,
        'tenant.isolation' => \App\Http\Middleware\TenantIsolationMiddleware::class,
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