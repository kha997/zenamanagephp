<?php

namespace Tests\Feature;

use Tests\TestCase;

class RouteDebugTest extends TestCase
{
    /**
     * Debug duplicate routes
     */
    public function test_debug_duplicate_routes()
    {
        $routes = app('router')->getRoutes();
        $routeMap = [];
        
        foreach ($routes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();
            
            foreach ($methods as $method) {
                if ($method === 'HEAD') continue;
                
                $key = $method . ':' . $uri;
                
                if (isset($routeMap[$key])) {
                    echo "DUPLICATE: $key\n";
                    echo "  First: " . $routeMap[$key] . "\n";
                    echo "  Second: " . $route->getActionName() . "\n";
                    echo "  File: " . $route->getAction()['controller'] ?? 'Closure' . "\n";
                    echo "---\n";
                }
                
                $routeMap[$key] = $route->getActionName();
            }
        }
        
        $this->assertTrue(true, 'Debug completed');
    }
}
