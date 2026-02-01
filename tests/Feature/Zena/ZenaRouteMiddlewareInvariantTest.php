<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaRouteMiddlewareInvariantTest extends TestCase
{
    private const PROTECTED_ROUTE_MIDDLEWARE = [
        'api/zena/rfis' => 'rfi.view',
        'api/zena/submittals' => 'submittal.view',
        'api/zena/inspections' => 'inspection.view',
    ];

    private const AUTH_LOGIN_URI = 'api/zena/auth/login';

    public function test_login_route_remains_public(): void
    {
        $middlewares = $this->gatherMiddlewaresFor(self::AUTH_LOGIN_URI);

        $this->assertNotContains(
            'auth:sanctum',
            $middlewares,
            $this->formatMiddlewareMessage(self::AUTH_LOGIN_URI, 'auth:sanctum', $middlewares)
        );

        $this->assertNotContains(
            'tenant.isolation',
            $middlewares,
            $this->formatMiddlewareMessage(self::AUTH_LOGIN_URI, 'tenant.isolation', $middlewares)
        );

        $this->assertFalse(
            $this->hasRbacMiddleware($middlewares),
            $this->formatMiddlewareMessage(self::AUTH_LOGIN_URI, 'rbac:*', $middlewares)
        );
    }

    public function test_protected_routes_cache_the_expected_middleware_stack(): void
    {
        foreach (self::PROTECTED_ROUTE_MIDDLEWARE as $uri => $permission) {
            $middlewares = $this->gatherMiddlewaresFor($uri);

            $this->assertContains(
                'auth:sanctum',
                $middlewares,
                $this->formatMiddlewareMessage($uri, 'auth:sanctum', $middlewares)
            );

            $this->assertContains(
                'tenant.isolation',
                $middlewares,
                $this->formatMiddlewareMessage($uri, 'tenant.isolation', $middlewares)
            );

            $expectedRbacMiddleware = sprintf('rbac:%s', $permission);
            $this->assertContains(
                $expectedRbacMiddleware,
                $middlewares,
                $this->formatMiddlewareMessage($uri, $expectedRbacMiddleware, $middlewares)
            );
        }
    }

    private function gatherMiddlewaresFor(string $uri): array
    {
        $route = $this->findRouteByUri($uri);
        return $route->gatherMiddleware();
    }

    private function findRouteByUri(string $uri): RoutingRoute
    {
        $route = collect(Route::getRoutes())->first(static fn (RoutingRoute $route) => $route->uri() === $uri);
        $this->assertNotNull($route, sprintf('Route %s could not be located', $uri));
        return $route;
    }

    private function hasRbacMiddleware(array $middlewares): bool
    {
        foreach ($middlewares as $middleware) {
            if (Str::startsWith($middleware, 'rbac:')) {
                return true;
            }
        }

        return false;
    }

    private function formatMiddlewareMessage(string $uri, string $expected, array $middlewares): string
    {
        return sprintf(
            'Route %s middleware stack expected %s, actual [%s]',
            $uri,
            $expected,
            implode(', ', $middlewares)
        );
    }
}
