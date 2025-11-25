<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\FeatureFlagService;
use Symfony\Component\HttpFoundation\Response;

/**
 * AppModuleRoutingMiddleware
 * 
 * Routes /app/* requests to either React SPA or Blade views based on feature flags.
 * 
 * Strategy:
 * - If feature flag enabled: Route to React SPA (view('app.spa'))
 * - If feature flag disabled: Route to Blade views (legacy)
 * 
 * This allows safe migration with instant rollback capability.
 */
class AppModuleRoutingMiddleware
{
    protected FeatureFlagService $featureFlagService;

    public function __construct(FeatureFlagService $featureFlagService)
    {
        $this->featureFlagService = $featureFlagService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        
        // Only process /app/* routes
        if (!str_starts_with($path, 'app/')) {
            return $next($request);
        }

        // Extract module from path
        $module = $this->extractModule($path);
        
        if (!$module) {
            // No specific module, default to React SPA
            return $next($request);
        }

        // Get user context for feature flag check
        $user = $request->user();
        $tenantId = $user?->tenant_id;
        $userId = $user?->id;

        // Check feature flag for this module
        $flagKey = "app.{$module}";
        $isReactEnabled = $this->featureFlagService->isEnabled($flagKey, $tenantId, $userId);

        if ($isReactEnabled) {
            // Feature flag enabled: Route to React SPA
            // The catch-all route in web.php will handle this
            return $next($request);
        } else {
            // Feature flag disabled: Route to Blade (legacy)
            // Store module info in request for legacy route handler
            $request->attributes->set('legacy_module', $module);
            $request->attributes->set('use_blade', true);
            return $next($request);
        }
    }

    /**
     * Extract module name from path
     * 
     * Examples:
     * - /app/projects -> projects
     * - /app/projects/123 -> projects
     * - /app/tasks/kanban -> tasks
     * - /app/dashboard -> dashboard
     * 
     * @param string $path
     * @return string|null
     */
    private function extractModule(string $path): ?string
    {
        // Remove /app/ prefix
        $path = str_replace('app/', '', $path);
        
        // Split by /
        $parts = explode('/', $path);
        
        if (empty($parts) || empty($parts[0])) {
            return null;
        }

        $firstPart = $parts[0];

        // Map known modules
        $modules = [
            'projects',
            'tasks',
            'clients',
            'quotes',
            'documents',
            'change-requests',
            'qc',
            'reports',
            'calendar',
            'team',
            'settings',
            'dashboard', // Dashboard is also a module
        ];

        // Check if first part is a known module
        if (in_array($firstPart, $modules)) {
            // Handle special case: change-requests -> change_requests (flag uses underscore)
            return $firstPart === 'change-requests' ? 'change_requests' : $firstPart;
        }

        // If not a known module, return null (will default to React)
        return null;
    }
}

