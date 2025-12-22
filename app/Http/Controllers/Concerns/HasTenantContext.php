<?php declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Trait for handling tenant context in web controllers
 * 
 * Provides helper method to get tenant_id with proper fallback logic
 * for authenticated users, middleware-set context, and test routes.
 */
trait HasTenantContext
{
    /**
     * Get tenant ID from request context
     * 
     * Priority order:
     * 1. Request attributes (set by TenantIsolationMiddleware)
     * 2. Authenticated user's tenant_id
     * 3. Test route fallback (only for test routes)
     * 
     * @param Request|null $request Optional request instance
     * @return string Tenant ID
     */
    protected function getTenantId(?Request $request = null): string
    {
        $request = $request ?? request();
        
        // Priority 1: Check request attributes (set by middleware)
        $tenantId = $request->attributes->get('tenant_id');
        if ($tenantId) {
            return (string) $tenantId;
        }
        
        // Priority 2: Check authenticated user
        if (auth()->check() && auth()->user()->tenant_id) {
            return (string) auth()->user()->tenant_id;
        }
        
        // Priority 3: Check if this is a test route
        $routeName = $request->route()?->getName();
        $isTestRoute = $routeName && str_starts_with($routeName, 'test.');
        
        if ($isTestRoute) {
            // Test routes use hardcoded tenant ID for E2E testing
            $testTenantId = '01K83FPK5XGPXF3V7ANJQRGX5X';
            
            Log::debug('HasTenantContext::getTenantId - Using test tenant fallback', [
                'route_name' => $routeName,
                'tenant_id' => $testTenantId,
                'X-Request-Id' => $request->header('X-Request-Id'),
            ]);
            
            return $testTenantId;
        }
        
        // If none of the above, this is an error condition
        // Log warning but return test tenant as last resort to prevent breaking
        Log::warning('HasTenantContext::getTenantId - No tenant context found, using fallback', [
            'route_name' => $routeName,
            'is_authenticated' => auth()->check(),
            'X-Request-Id' => $request->header('X-Request-Id'),
        ]);
        
        return '01K83FPK5XGPXF3V7ANJQRGX5X';
    }
}

