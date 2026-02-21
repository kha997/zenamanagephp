<?php declare(strict_types=1);

namespace Tests\Feature\RouteMiddleware;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantIsolationV1ContractTest extends TestCase
{
    private const TARGET_PREFIXES = [
        'api/v1/notifications',
        'api/v1/notification-rules',
        'api/v1/work-template',
    ];

    public function test_v1_target_prefixes_require_tenant_isolation_middleware(): void
    {
        foreach (self::TARGET_PREFIXES as $prefix) {
            $matchedRoutes = $this->routesForPrefix($prefix);

            $this->assertGreaterThan(
                0,
                count($matchedRoutes),
                sprintf('Expected at least one route for prefix "%s".', $prefix)
            );

            foreach ($matchedRoutes as $route) {
                $middleware = $route->gatherMiddleware();

                $this->assertTrue(
                    $this->hasTenantIsolation($middleware),
                    sprintf(
                        'Route [%s] (%s) is missing tenant isolation middleware. Stack: %s',
                        $route->uri(),
                        implode('|', array_values(array_filter($route->methods(), static fn (string $method): bool => $method !== 'HEAD'))),
                        implode(', ', $middleware)
                    )
                );
            }
        }
    }

    /**
     * @return list<RoutingRoute>
     */
    private function routesForPrefix(string $prefix): array
    {
        $matched = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            if (Str::startsWith($route->uri(), $prefix)) {
                $matched[] = $route;
            }
        }

        return $matched;
    }

    /**
     * @param list<string> $middleware
     */
    private function hasTenantIsolation(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if (
                $entry === 'tenant.isolation'
                || Str::startsWith($entry, 'tenant.isolation:')
                || $entry === 'App\\Http\\Middleware\\TenantIsolationMiddleware'
                || Str::startsWith($entry, 'App\\Http\\Middleware\\TenantIsolationMiddleware:')
            ) {
                return true;
            }
        }

        return false;
    }
}
