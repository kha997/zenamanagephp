<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SubmittalShowRouteInvariantTest extends TestCase
{
    public function test_canonical_submittal_show_route_stays_owned_and_guarded(): void
    {
        $route = Route::getRoutes()->getByName('api.zena.submittals.show');

        $this->assertNotNull($route, 'Expected route [api.zena.submittals.show] to exist.');
        $this->assertSame('api/zena/submittals/{id}', $route->uri());
        $this->assertSame(['GET'], $this->businessMethods($route));
        $this->assertSame(
            'App\\Http\\Controllers\\Api\\SubmittalController',
            $this->controllerFromRoute($route)
        );

        $middleware = $route->gatherMiddleware();

        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('tenant.isolation', $middleware);
        $this->assertContains('input.sanitization', $middleware);
        $this->assertContains('error.envelope', $middleware);
        $this->assertContains('rbac:submittal.view', $middleware);
    }

    private function businessMethods(RoutingRoute $route): array
    {
        return array_values(array_diff($route->methods(), ['HEAD']));
    }

    private function controllerFromRoute(RoutingRoute $route): string
    {
        $controller = $route->getControllerClass();
        $this->assertNotNull($controller, sprintf('Route [%s] is missing a controller.', $route->getName()));

        return $controller;
    }
}
