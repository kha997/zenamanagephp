<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * Global HTTP middleware (mọi request).
     */
    protected $middleware = [
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\SecurityHeadersMiddleware::class,
    ];

    /**
     * Route middleware groups.
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            // ✅ BẬT LẠI: session + errors + CSRF + bindings
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\PerformanceLoggingMiddleware::class,
        ],

        'web.test' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            // No CSRF for test routes
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\PerformanceLoggingMiddleware::class,
            'auth.web.test', // Add test authentication bypass
        ],

        'api' => [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\PerformanceLoggingMiddleware::class,
        ],
    ];

        /**
         * Route middleware aliases (assign theo tên ngắn).
         */
        protected $routeMiddleware = [
            'auth'                 => \App\Http\Middleware\Authenticate::class,
            'guest'                => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'auth.sanctum'         => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle'             => \Illuminate\Routing\Middleware\ThrottleRequests::class,

            // ✳️ Authentication & Authorization
            'auth.api'             => \App\Http\Middleware\ApiAuthenticationMiddleware::class,
            'ability'               => \App\Http\Middleware\AbilityMiddleware::class,
            'test.auth.bypass'      => \App\Http\Middleware\TestAuthBypassMiddleware::class,
            'auth.web.test'         => \App\Http\Middleware\AuthenticateWithTestBypass::class,

            // ✳️ Tenancy
            'tenant.scope'         => \App\Http\Middleware\TenantScopeMiddleware::class,
            // 'tenant.isolation'     => \App\Http\Middleware\TenantIsolationMiddleware::class,

            // ✳️ Rate limit (unified)
            'rate.limit'           => \App\Http\Middleware\Unified\UnifiedRateLimitMiddleware::class,
            'rate.limit.sliding'    => \App\Http\Middleware\Unified\UnifiedRateLimitMiddleware::class,
            'rate.limit.token'      => \App\Http\Middleware\Unified\UnifiedRateLimitMiddleware::class,
            'rate.limit.fixed'      => \App\Http\Middleware\Unified\UnifiedRateLimitMiddleware::class,

            // ✳️ Security (unified)
            'security'              => \App\Http\Middleware\Unified\UnifiedSecurityMiddleware::class,
            'security.headers'      => \App\Http\Middleware\Unified\UnifiedSecurityMiddleware::class,

            // ✳️ Validation (unified)
            'validation'            => \App\Http\Middleware\Unified\UnifiedValidationMiddleware::class,
            'input.validation'       => \App\Http\Middleware\Unified\UnifiedValidationMiddleware::class,
            'feature.flags'        => \App\Http\Middleware\EnsureFeatureFlags::class,
            'demo.user'            => \App\Http\Middleware\DemoUserMiddleware::class,

            // ⚠️ CORS: nếu dùng custom CorsMiddleware thì bỏ HandleCors ở global trên.
            // 'cors'               => \App\Http\Middleware\CorsMiddleware::class,
            // 'token.only'           => \App\Http\Middleware\TokenOnly::class,
        ];

    /**
     * Override terminate để tránh crash do bind lỗi.
     */
    public function terminate($request, $response)
    {
        try {
            parent::terminate($request, $response);
        } catch (\ReflectionException $e) {
            \Log::warning('Middleware resolution error during terminate', [
                'error' => $e->getMessage(),
                'request_uri' => $request->getRequestUri(),
                'method' => $request->getMethod(),
            ]);
        } catch (\Illuminate\Contracts\Container\BindingResolutionException $e) {
            \Log::warning('Middleware binding resolution error during terminate', [
                'error' => $e->getMessage(),
                'request_uri' => $request->getRequestUri(),
                'method' => $request->getMethod(),
            ]);
        }
    }
}