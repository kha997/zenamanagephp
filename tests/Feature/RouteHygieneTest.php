<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Feature\Zena\ZenaRouteSurfaceInvariantTest;
use Tests\TestCase;

class RouteHygieneTest extends TestCase
{
    private const REQUIRED_MIDDLEWARE = [
        'auth:sanctum',
        'tenant.isolation',
        'rbac',
    ];

    private const TARGET_PREFIXES = [
        'api/projects',
        'api/tasks',
        'api/documents',
        'api/v1/change-requests',
        'api/v1/interaction-logs',
        'api/analytics/dashboard',
        'api/dashboard',
    ];

    private const ZENA_REQUIRED_MIDDLEWARE = [
        'auth:sanctum',
        'tenant.isolation',
    ];

    private const ZENA_PUBLIC_ROUTES = ZenaRouteSurfaceInvariantTest::PUBLIC_ALLOWLIST_URIS;

    public function test_business_routes_reuse_the_security_stack(): void
    {
        $routes = Route::getRoutes();
        $violations = [];

        foreach ($routes as $route) {
            $uri = $route->uri();

            if (!$this->isBusinessRoute($uri)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $missing = array_filter(self::REQUIRED_MIDDLEWARE, fn (string $required) => !$this->hasMiddleware($middleware, $required));
            $methods = $this->routeMethods($route->methods());

            if (!empty($missing)) {
                $violations[] = [
                    'name' => $route->getName(),
                    'uri' => $uri,
                    'methods' => $methods,
                    'middleware' => $middleware,
                    'missing' => $missing,
                ];
            }
        }

        if (!empty($violations)) {
            $messages = array_map(
                fn ($violation) => sprintf(
                    '%s | %s | %s | %s | missing: %s',
                    $violation['uri'],
                    $violation['name'] ?? 'unnamed',
                    $violation['methods'],
                    implode(', ', $violation['middleware']),
                    implode(', ', $violation['missing'])
                ),
                $violations
            );

            $this->fail('Route hygiene violations:' . PHP_EOL . implode(PHP_EOL, $messages));
        }

        $this->assertEmpty($violations, 'Business routes must always include the required middleware stack.');
    }

    private function isBusinessRoute(string $uri): bool
    {
        foreach (self::TARGET_PREFIXES as $prefix) {
            if (Str::startsWith($uri, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function hasMiddleware(array $middleware, string $required): bool
    {
        foreach ($middleware as $entry) {
            if ($entry === $required || Str::startsWith($entry, $required . ':')) {
                return true;
            }
        }

        return false;
    }

    private function routeMethods(array $methods): string
    {
        $filtered = array_filter($methods, fn (string $method) => $method !== 'HEAD');

        if (empty($filtered)) {
            return implode('|', $methods);
        }

        return implode('|', $filtered);
    }

    /**
     * @group zena-invariants
     */
    public function test_zena_routes_reuse_sanctum_and_tenant_isolation(): void
    {
        $routes = Route::getRoutes();
        $violations = [];

        foreach ($routes as $route) {
            $uri = $route->uri();

            if (!Str::startsWith($uri, 'api/zena')) {
                continue;
            }

            if (in_array($uri, self::ZENA_PUBLIC_ROUTES, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $missing = array_filter(
                self::ZENA_REQUIRED_MIDDLEWARE,
                fn (string $required) => !$this->hasMiddleware($middleware, $required)
            );

            if (!empty($missing)) {
                $violations[] = [
                    'name' => $route->getName(),
                    'uri' => $uri,
                    'methods' => $this->routeMethods($route->methods()),
                    'middleware' => $middleware,
                    'missing' => $missing,
                ];
            }
        }

        if (!empty($violations)) {
            $messages = array_map(
                fn ($violation) => sprintf(
                    '%s | %s | %s | %s | missing: %s',
                    $violation['uri'],
                    $violation['name'] ?? 'unnamed',
                    $violation['methods'],
                    implode(', ', $violation['middleware']),
                    implode(', ', $violation['missing'])
                ),
                $violations
            );

            $this->fail('Z.E.N.A routes must include auth:sanctum and tenant.isolation:' . PHP_EOL . implode(PHP_EOL, $messages));
        }

        $this->assertEmpty($violations, 'Z.E.N.A routes must include auth:sanctum and tenant.isolation.');
    }

    /**
     * @group zena-invariants
     */
    public function test_zena_public_allowlist_is_consistent(): void
    {
        $this->assertSame(
            ZenaRouteSurfaceInvariantTest::PUBLIC_ALLOWLIST_URIS,
            self::ZENA_PUBLIC_ROUTES,
            'Z.E.N.A public allowlist must match the invariant definition.'
        );
    }
}
