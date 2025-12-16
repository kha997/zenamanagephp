<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

class ValidateFrontendConfig extends Command
{
    protected $signature = 'frontend:validate';
    protected $description = 'Validate frontend configuration to ensure single source of truth';

    public function handle()
    {
        $this->info('🔍 Validating Frontend Configuration...');
        $this->newLine();

        $errors = [];
        $warnings = [];

        // 1. Check config file
        $config = config('frontend');
        if (!$config) {
            $errors[] = '❌ Frontend config file not found: config/frontend.php';
            return $this->exitWithErrors($errors);
        }

        // 2. Check only one system is active
        $active = $config['active'] ?? null;
        if (!$active) {
            $errors[] = '❌ No active frontend system defined';
        }

        $systems = $config['systems'] ?? [];
        $enabledCount = 0;
        foreach ($systems as $name => $system) {
            if ($system['enabled'] ?? false) {
                $enabledCount++;
            }
        }

        if ($enabledCount > 1) {
            $errors[] = "❌ Multiple frontend systems enabled ({$enabledCount}). Only ONE can be active.";
        }

        if ($enabledCount === 0) {
            $errors[] = '❌ No frontend system enabled';
        }

        // 3. Check route conflicts
        $reactRoutes = $systems['react']['routes'] ?? [];
        $bladeRoutes = $systems['blade']['routes'] ?? [];

        $conflicts = $this->findRouteConflicts($reactRoutes, $bladeRoutes);
        if (!empty($conflicts)) {
            $errors[] = '❌ Route conflicts detected:';
            foreach ($conflicts as $conflict) {
                $errors[] = "   - {$conflict}";
            }
        }

        // 4. Check Blade routes if React is active
        if ($active === 'react') {
            $webRoutes = File::get(base_path('routes/web.php'));
            $reactRoutes = $systems['react']['routes'] ?? [];
            
            // Check for active Blade routes that should be handled by React
            $routesToCheck = ['/login', '/register', '/forgot-password', '/reset-password'];
            foreach ($routesToCheck as $route) {
                // Check if route is in React config
                $routeInReact = false;
                foreach ($reactRoutes as $reactRoute) {
                    $normalizedReact = rtrim($reactRoute, '*');
                    $normalizedCheck = rtrim($route, '*');
                    if ($normalizedReact === $normalizedCheck || str_starts_with($normalizedCheck, $normalizedReact)) {
                        $routeInReact = true;
                        break;
                    }
                }
                
                if ($routeInReact) {
                    // Check for active (non-commented) Blade route
                    $pattern = '/Route::get\([\'"]' . preg_quote($route, '/') . '[\'"]/';
                    if (preg_match($pattern, $webRoutes)) {
                        // Check if it's commented out
                        $commentedPattern = '/\/\/\s*Route::get\([\'"]' . preg_quote($route, '/') . '[\'"]/';
                        if (!preg_match($commentedPattern, $webRoutes)) {
                            $warnings[] = "⚠️  Blade route '{$route}' is active but React handles it. Consider disabling Blade route.";
                        }
                    }
                }
            }
            
            // Verify React routes actually exist in React router
            $reactRouterPath = base_path('frontend/src/app/router.tsx');
            if (File::exists($reactRouterPath)) {
                $reactRouterContent = File::get($reactRouterPath);
                foreach ($reactRoutes as $reactRoute) {
                    $normalizedRoute = rtrim($reactRoute, '*');
                    // Skip wildcard routes like /app/* and /admin/* as they're handled differently
                    if (str_ends_with($reactRoute, '/*')) {
                        // For wildcard routes, check if base path exists (e.g., '/app' for '/app/*')
                        $basePath = rtrim($normalizedRoute, '/');
                        $pattern = '/path:\s*[\'"]' . preg_quote($basePath, '/') . '[\'"]/';
                        if (!preg_match($pattern, $reactRouterContent)) {
                            $warnings[] = "⚠️  Wildcard route '{$reactRoute}' base path '{$basePath}' not found in React router.";
                        }
                    } else {
                        // For exact routes, check if path exists
                        $pattern = '/path:\s*[\'"]' . preg_quote($normalizedRoute, '/') . '[\'"]/';
                        if (!preg_match($pattern, $reactRouterContent)) {
                            $warnings[] = "⚠️  Route '{$normalizedRoute}' is in config but not found in React router.";
                        }
                    }
                }
            }
        }

        // 5. Check ports are different
        $reactPort = $systems['react']['port'] ?? null;
        $bladePort = $systems['blade']['port'] ?? null;
        if ($reactPort && $bladePort && $reactPort === $bladePort) {
            $errors[] = "❌ React and Blade use same port ({$reactPort}). Ports must be different.";
        }

        // Display results
        if (!empty($errors)) {
            $this->error('Validation FAILED:');
            foreach ($errors as $error) {
                $this->line($error);
            }
            $this->newLine();
            return 1;
        }

        if (!empty($warnings)) {
            $this->warn('Warnings:');
            foreach ($warnings as $warning) {
                $this->line($warning);
            }
            $this->newLine();
        }

        $this->info('✅ Frontend configuration is valid!');
        $this->info("   Active system: {$active}");
        $this->info("   React enabled: " . ($systems['react']['enabled'] ? 'Yes' : 'No'));
        $this->info("   Blade enabled: " . ($systems['blade']['enabled'] ? 'Yes' : 'No'));
        $this->newLine();

        return 0;
    }

    private function findRouteConflicts(array $reactRoutes, array $bladeRoutes): array
    {
        $conflicts = [];

        foreach ($reactRoutes as $reactRoute) {
            foreach ($bladeRoutes as $bladeRoute) {
                if ($this->routesConflict($reactRoute, $bladeRoute)) {
                    $conflicts[] = "React route '{$reactRoute}' conflicts with Blade route '{$bladeRoute}'";
                }
            }
        }

        return $conflicts;
    }

    private function routesConflict(string $route1, string $route2): bool
    {
        // Normalize routes
        $r1 = rtrim($route1, '*');
        $r2 = rtrim($route2, '*');

        // Check if one is prefix of another
        if (str_starts_with($r1, $r2) || str_starts_with($r2, $r1)) {
            return true;
        }

        // Exact match
        if ($r1 === $r2) {
            return true;
        }

        return false;
    }

    private function exitWithErrors(array $errors): int
    {
        $this->error('Validation FAILED:');
        foreach ($errors as $error) {
            $this->line($error);
        }
        $this->newLine();
        $this->warn('Fix these issues and run: php artisan frontend:validate');
        return 1;
    }
}

