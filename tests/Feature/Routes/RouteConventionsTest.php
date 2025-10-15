<?php

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteConventionsTest extends TestCase
{
    /** @test */
    public function app_routes_must_have_app_name_prefix(): void
    {
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            $uri  = $route->uri();
            if (str_starts_with($uri, 'app/')) {
                $this->assertTrue(
                    str_starts_with($name, 'app.'),
                    "Route {$uri} must use name prefix app.* (got: {$name})"
                );
            }
        }
        $this->assertTrue(true);
    }

    /** @test */
    public function admin_routes_must_have_admin_name_prefix(): void
    {
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            $uri  = $route->uri();
            if (str_starts_with($uri, 'admin/')) {
                $this->assertTrue(
                    str_starts_with($name, 'admin.'),
                    "Route {$uri} must use name prefix admin.* (got: {$name})"
                );
            }
        }
        $this->assertTrue(true);
    }

    /** @test */
    public function debug_routes_must_have_debug_name_prefix(): void
    {
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            $uri  = $route->uri();
            if (str_starts_with($uri, '_debug/')) {
                $this->assertTrue(
                    str_starts_with($name, 'debug.'),
                    "Route {$uri} must use name prefix debug.* (got: {$name})"
                );
            }
        }
        $this->assertTrue(true);
    }

    /** @test */
    public function app_routes_must_have_proper_middleware(): void
    {
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'app/')) {
                $middleware = $route->gatherMiddleware();
                $this->assertTrue(in_array('web', $middleware), "Route {$uri} thiếu 'web' middleware");
                $this->assertTrue(in_array('auth:web', $middleware), "Route {$uri} thiếu 'auth:web' middleware");
            }
        }
        $this->assertTrue(true);
    }
}
