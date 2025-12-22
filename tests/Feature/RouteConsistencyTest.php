<?php

namespace Tests\Feature;

use Tests\TestCase;

class RouteConsistencyTest extends TestCase
{
    /**
     * Test that no duplicate routes exist for same endpoints
     */
    public function test_no_duplicate_routes()
    {
        $routes = app('router')->getRoutes();
        $routeMap = [];
        
        foreach ($routes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();
            
            foreach ($methods as $method) {
                if ($method === 'HEAD') continue; // Skip HEAD as it's auto-generated
                
                $key = $method . ':' . $uri;
                
                if (isset($routeMap[$key])) {
                    $this->fail("Duplicate route found: $key - violates single source of truth");
                }
                
                $routeMap[$key] = $route->getActionName();
            }
        }
        
        $this->assertTrue(true, 'No duplicate routes found');
    }

    /**
     * Test that all app routes use consistent middleware
     */
    public function test_app_routes_middleware_consistency()
    {
        $routes = app('router')->getRoutes();
        $appRoutes = [];
        
        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'app/')) {
                $appRoutes[] = [
                    'uri' => $route->uri(),
                    'middleware' => $route->gatherMiddleware(),
                    'action' => $route->getActionName()
                ];
            }
        }
        
        // All app routes should have consistent middleware
        foreach ($appRoutes as $route) {
            $this->assertNotEmpty(
                $route['middleware'], 
                "App route {$route['uri']} should have middleware"
            );
        }
    }

    /**
     * Test that all views exist and are accessible
     */
    public function test_all_views_exist()
    {
        $views = [
            'app.projects',
            'app.tasks', 
            'app.dashboard',
            'app.calendar',
            'app.team',
            'app.documents',
            'app.templates',
            'app.settings',
            'layouts.app-layout'
        ];

        foreach ($views as $view) {
            $this->assertTrue(
                view()->exists($view),
                "View $view does not exist"
            );
        }
    }
}
