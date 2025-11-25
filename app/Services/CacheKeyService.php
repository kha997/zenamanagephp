<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * Cache Key Service
 * 
 * Centralized service for generating standardized cache keys.
 * Format: {env}:{tenant_id}:{domain}:{id}
 * 
 * Examples:
 * - prod:tenant_abc123:projects:proj_xyz789
 * - local:tenant_abc123:kpi:dashboard
 * - staging:tenant_abc123:media:quota
 * 
 * This service ensures all cache keys follow the same format across the application.
 */
class CacheKeyService
{
    /**
     * Generate standardized cache key
     * 
     * Format: {env}:{tenant}:{domain}:{id}:{view}
     * 
     * @param string $domain Domain/namespace (e.g., 'projects', 'kpi', 'media')
     * @param string|null $id Optional identifier (e.g., 'proj_xyz789', 'dashboard', 'quota')
     * @param string|null $tenantId Optional tenant ID (uses current tenant if not provided)
     * @param string|null $view Optional view type (e.g., 'detail', 'list', 'kpis')
     * @return string Cache key in format {env}:{tenant}:{domain}:{id}:{view}
     */
    public static function key(string $domain, ?string $id = null, ?string $tenantId = null, ?string $view = null): string
    {
        $env = App::environment();
        $tenant = $tenantId ?? self::getCurrentTenantId() ?? 'system';
        
        $parts = [$env, $tenant, $domain];
        
        if ($id !== null) {
            $parts[] = $id;
        }
        
        if ($view !== null) {
            $parts[] = $view;
        }
        
        return implode(':', $parts);
    }

    /**
     * Get current tenant ID from multiple sources (priority order):
     * 1. Request context (from middleware) - most reliable
     * 2. Auth user tenant_id
     * 3. App instance binding
     * 
     * @return string|null
     */
    private static function getCurrentTenantId(): ?string
    {
        // Priority 1: Request context (set by middleware)
        if (App::bound('request')) {
            $request = App::make('request');
            $tenantId = $request->attributes->get('tenant_id');
            if ($tenantId) {
                return $tenantId;
            }
        }
        
        // Priority 2: Auth user tenant_id
        if (App::bound('auth') && Auth::check()) {
            $user = Auth::user();
            if ($user && $user->tenant_id) {
                return $user->tenant_id;
            }
        }
        
        // Priority 3: App instance binding (set by middleware)
        if (App::bound('current_tenant_id')) {
            return App::make('current_tenant_id');
        }
        
        return null;
    }

    /**
     * Generate cache key pattern for invalidation
     * 
     * @param string $domain Domain/namespace
     * @param string|null $tenantId Optional tenant ID
     * @return string Cache key pattern (e.g., "prod:tenant_abc123:projects:*")
     */
    public static function pattern(string $domain, ?string $tenantId = null): string
    {
        $env = App::environment();
        $tenant = $tenantId ?? self::getCurrentTenantId() ?? '*';
        
        return "{$env}:{$tenant}:{$domain}:*";
    }

    /**
     * Generate cache key for tenant-wide invalidation
     * 
     * @param string|null $tenantId Optional tenant ID
     * @return string Cache key pattern (e.g., "prod:tenant_abc123:*")
     */
    public static function tenantPattern(?string $tenantId = null): string
    {
        $env = App::environment();
        $tenant = $tenantId ?? self::getCurrentTenantId() ?? '*';
        
        return "{$env}:{$tenant}:*";
    }
}

