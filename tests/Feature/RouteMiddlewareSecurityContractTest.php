<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class RouteMiddlewareSecurityContractTest extends TestCase
{
    private const REQUIRED_STACK = [
        'auth',
        'tenant.isolation',
        'rbac',
        'input.sanitization',
        'error.envelope',
    ];

    private const TARGET_NAME_PREFIXES = [
        'api.v1.dashboard.',
        'api.v1.project_manager.dashboard.',
        'api.zena.',
        'api.support.',
    ];

    private const TARGET_URI_PREFIX_SEGMENTS = [
        ['api', 'v1', 'dashboard'],
        ['api', 'v1', 'project-manager', 'dashboard'],
        ['api', 'zena'],
        ['api', 'support'],
    ];

    private const EXCEPTION_REASON_PUBLIC_ZENA_INFO = 'PUBLIC_ZENA_INFO';
    private const EXCEPTION_REASON_PUBLIC_ZENA_HEALTH = 'PUBLIC_ZENA_HEALTH';
    private const EXCEPTION_REASON_PUBLIC_ZENA_AUTH_BOOTSTRAP = 'PUBLIC_ZENA_AUTH_BOOTSTRAP';

    private const EXCEPTION_REASON_ENUM = [
        self::EXCEPTION_REASON_PUBLIC_ZENA_INFO,
        self::EXCEPTION_REASON_PUBLIC_ZENA_HEALTH,
        self::EXCEPTION_REASON_PUBLIC_ZENA_AUTH_BOOTSTRAP,
    ];

    private const EXCEPTIONS = [
        [
            'name' => 'api.zena.api.info',
            'reason' => self::EXCEPTION_REASON_PUBLIC_ZENA_INFO,
            'token' => 'SSOT_ROUTE_MW_EXCEPTION(reason=PUBLIC_ZENA_INFO)', // SSOT_ROUTE_MW_EXCEPTION(reason=PUBLIC_ZENA_INFO)
        ],
        [
            'name' => 'api.zena.api.health',
            'reason' => self::EXCEPTION_REASON_PUBLIC_ZENA_HEALTH,
            'token' => 'SSOT_ROUTE_MW_EXCEPTION(reason=PUBLIC_ZENA_HEALTH)', // SSOT_ROUTE_MW_EXCEPTION(reason=PUBLIC_ZENA_HEALTH)
        ],
        [
            'name' => 'api.zena.auth.login',
            'reason' => self::EXCEPTION_REASON_PUBLIC_ZENA_AUTH_BOOTSTRAP,
            'token' => 'SSOT_ROUTE_MW_EXCEPTION(reason=PUBLIC_ZENA_AUTH_BOOTSTRAP)', // SSOT_ROUTE_MW_EXCEPTION(reason=PUBLIC_ZENA_AUTH_BOOTSTRAP)
        ],
    ];

    public function test_targeted_routes_enforce_security_middleware_contract(): void
    {
        $violations = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) ($route->getName() ?? '');
            $uri = (string) $route->uri();

            if (!$this->isTargetRoute($name, $uri)) {
                continue;
            }

            if ($this->isAllowlistedException($name, $uri)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $missing = [];

            foreach (self::REQUIRED_STACK as $required) {
                if (!$this->hasRequiredMiddleware($middleware, $required)) {
                    $missing[] = $required;
                }
            }

            if (!empty($missing)) {
                $violations[] = sprintf(
                    '%s | %s | %s | missing: %s | middleware: %s',
                    $uri,
                    $name !== '' ? $name : 'unnamed',
                    implode('|', array_filter($route->methods(), static fn (string $method): bool => $method !== 'HEAD')),
                    implode(', ', $missing),
                    implode(', ', $middleware)
                );
            }
        }

        $this->assertEmpty(
            $violations,
            'Route middleware SSOT contract violations:' . PHP_EOL . implode(PHP_EOL, $violations)
        );
    }

    private function isTargetRoute(string $name, string $uri): bool
    {
        foreach (self::TARGET_NAME_PREFIXES as $prefix) {
            if ($name !== '' && Str::startsWith($name, $prefix)) {
                return true;
            }
        }

        foreach (self::TARGET_URI_PREFIX_SEGMENTS as $segments) {
            $prefix = implode('/', $segments);
            if (Str::startsWith($uri, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isAllowlistedException(string $name, string $uri): bool
    {
        foreach (self::EXCEPTIONS as $exception) {
            $this->assertContains(
                $exception['reason'],
                self::EXCEPTION_REASON_ENUM,
                'Invalid SSOT middleware exception reason enum: ' . $exception['reason']
            );

            $this->assertSame(
                'SSOT_ROUTE_MW_EXCEPTION(reason=' . $exception['reason'] . ')',
                $exception['token'],
                'Exception token must match SSOT_ROUTE_MW_EXCEPTION(reason=REASON_ENUM)'
            );

            if (($exception['name'] ?? null) === $name) {
                return true;
            }

            if (($exception['uri'] ?? null) === $uri) {
                return true;
            }
        }

        return false;
    }

    private function hasRequiredMiddleware(array $middleware, string $required): bool
    {
        $aliases = match ($required) {
            'auth' => ['auth', 'auth:sanctum', 'Illuminate\\Auth\\Middleware\\Authenticate'],
            'tenant.isolation' => ['tenant.isolation', 'App\\Http\\Middleware\\TenantIsolationMiddleware'],
            'rbac' => ['rbac', 'App\\Http\\Middleware\\RoleBasedAccessControlMiddleware'],
            'input.sanitization' => ['input.sanitization', 'App\\Http\\Middleware\\InputSanitizationMiddleware'],
            'error.envelope' => ['error.envelope', 'App\\Http\\Middleware\\ErrorEnvelopeMiddleware'],
            default => [$required],
        };

        foreach ($middleware as $entry) {
            foreach ($aliases as $alias) {
                if ($entry === $alias || Str::startsWith($entry, $alias . ':')) {
                    return true;
                }
            }
        }

        return false;
    }
}
