<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class AuditRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zena:audit-routes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit routes for conflicts and middleware verification';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Starting Route Audit...');
        
        // Create audit directory
        $auditDir = storage_path('app/audit');
        if (!is_dir($auditDir)) {
            mkdir($auditDir, 0755, true);
        }

        // Get all routes
        $routes = Route::getRoutes();
        
        // Group routes by path and method
        $routeGroups = [];
        $conflicts = [];
        
        foreach ($routes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();
            $middleware = $route->gatherMiddleware();
            
            foreach ($methods as $method) {
                if ($method === 'HEAD') continue; // Skip HEAD method
                
                $key = "{$method} {$uri}";
                
                if (isset($routeGroups[$key])) {
                    $conflicts[] = [
                        'path' => $key,
                        'existing' => $routeGroups[$key],
                        'conflicting' => [
                            'name' => $route->getName(),
                            'middleware' => $middleware,
                            'action' => $route->getActionName()
                        ]
                    ];
                } else {
                    $routeGroups[$key] = [
                        'name' => $route->getName(),
                        'middleware' => $middleware,
                        'action' => $route->getActionName()
                    ];
                }
            }
        }

        // Generate audit reports
        $this->generateApiV1Report($routes);
        $this->generateAppRoutesReport($routes);
        $this->generateAdminRoutesReport($routes);
        $this->generateMiddlewareReport($routes);
        $this->generateConflictsReport($conflicts);

        $this->info('✅ Route audit completed!');
        $this->info("📁 Audit files saved to: {$auditDir}");
        
        if (!empty($conflicts)) {
            $this->error('⚠️  Found route conflicts!');
            foreach ($conflicts as $conflict) {
                $this->error("Conflict: {$conflict['path']}");
            }
        } else {
            $this->info('✅ No route conflicts found!');
        }
    }

    private function generateApiV1Report($routes)
    {
        $apiV1Routes = [];
        
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'api/v1/')) {
                $apiV1Routes[] = [
                    'method' => implode('|', array_filter($route->methods(), fn($m) => $m !== 'HEAD')),
                    'uri' => $uri,
                    'name' => $route->getName(),
                    'middleware' => $route->gatherMiddleware(),
                    'action' => $route->getActionName()
                ];
            }
        }

        $content = "API v1 Routes Audit Report\n";
        $content .= "Generated: " . now()->toISOString() . "\n";
        $content .= "Total Routes: " . count($apiV1Routes) . "\n\n";
        
        foreach ($apiV1Routes as $route) {
            $content .= sprintf("%-8s %-50s %-30s %-20s\n", 
                $route['method'], 
                $route['uri'], 
                $route['name'] ?? 'N/A',
                implode(',', $route['middleware'])
            );
        }

        file_put_contents(storage_path('app/audit/routelist_api_v1.txt'), $content);
        $this->info('📄 API v1 routes report generated');
    }

    private function generateAppRoutesReport($routes)
    {
        $appRoutes = [];
        
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'app/')) {
                $appRoutes[] = [
                    'method' => implode('|', array_filter($route->methods(), fn($m) => $m !== 'HEAD')),
                    'uri' => $uri,
                    'name' => $route->getName(),
                    'middleware' => $route->gatherMiddleware(),
                    'action' => $route->getActionName()
                ];
            }
        }

        $content = "App Routes Audit Report\n";
        $content .= "Generated: " . now()->toISOString() . "\n";
        $content .= "Total Routes: " . count($appRoutes) . "\n\n";
        
        foreach ($appRoutes as $route) {
            $content .= sprintf("%-8s %-50s %-30s %-20s\n", 
                $route['method'], 
                $route['uri'], 
                $route['name'] ?? 'N/A',
                implode(',', $route['middleware'])
            );
        }

        file_put_contents(storage_path('app/audit/routelist_app.txt'), $content);
        $this->info('📄 App routes report generated');
    }

    private function generateAdminRoutesReport($routes)
    {
        $adminRoutes = [];
        
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'admin/')) {
                $adminRoutes[] = [
                    'method' => implode('|', array_filter($route->methods(), fn($m) => $m !== 'HEAD')),
                    'uri' => $uri,
                    'name' => $route->getName(),
                    'middleware' => $route->gatherMiddleware(),
                    'action' => $route->getActionName()
                ];
            }
        }

        $content = "Admin Routes Audit Report\n";
        $content .= "Generated: " . now()->toISOString() . "\n";
        $content .= "Total Routes: " . count($adminRoutes) . "\n\n";
        
        foreach ($adminRoutes as $route) {
            $content .= sprintf("%-8s %-50s %-30s %-20s\n", 
                $route['method'], 
                $route['uri'], 
                $route['name'] ?? 'N/A',
                implode(',', $route['middleware'])
            );
        }

        file_put_contents(storage_path('app/audit/routelist_admin.txt'), $content);
        $this->info('📄 Admin routes report generated');
    }

    private function generateMiddlewareReport($routes)
    {
        $middlewareGroups = [
            'auth:sanctum' => [],
            'ability:admin' => [],
            'ability:tenant' => [],
            'role:super_admin' => [],
            'tenant.scope' => [],
            'admin.only' => []
        ];

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $uri = $route->uri();
            
            foreach ($middlewareGroups as $middlewareName => &$routes) {
                if (in_array($middlewareName, $middleware)) {
                    $routes[] = [
                        'method' => implode('|', array_filter($route->methods(), fn($m) => $m !== 'HEAD')),
                        'uri' => $uri,
                        'name' => $route->getName()
                    ];
                }
            }
        }

        $content = "Middleware Audit Report\n";
        $content .= "Generated: " . now()->toISOString() . "\n\n";
        
        foreach ($middlewareGroups as $middlewareName => $routes) {
            $content .= "=== {$middlewareName} ===\n";
            $content .= "Total Routes: " . count($routes) . "\n\n";
            
            foreach ($routes as $route) {
                $content .= sprintf("%-8s %-50s %-30s\n", 
                    $route['method'], 
                    $route['uri'], 
                    $route['name'] ?? 'N/A'
                );
            }
            $content .= "\n";
        }

        file_put_contents(storage_path('app/audit/middleware_audit.txt'), $content);
        $this->info('📄 Middleware audit report generated');
    }

    private function generateConflictsReport($conflicts)
    {
        $content = "Route Conflicts Report\n";
        $content .= "Generated: " . now()->toISOString() . "\n";
        $content .= "Total Conflicts: " . count($conflicts) . "\n\n";
        
        if (empty($conflicts)) {
            $content .= "✅ No route conflicts found!\n";
        } else {
            foreach ($conflicts as $conflict) {
                $content .= "CONFLICT: {$conflict['path']}\n";
                $content .= "  Existing: {$conflict['existing']['name']} - {$conflict['existing']['action']}\n";
                $content .= "  Conflicting: {$conflict['conflicting']['name']} - {$conflict['conflicting']['action']}\n\n";
            }
        }

        file_put_contents(storage_path('app/audit/route_conflicts.txt'), $content);
        $this->info('📄 Route conflicts report generated');
    }
}
