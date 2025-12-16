<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RawQueryTenantGuard
 * 
 * Service to validate and protect raw database queries with tenant_id.
 * Ensures that even raw queries are properly scoped to tenant.
 */
class RawQueryTenantGuard
{
    /**
     * Validate that a raw query includes tenant_id filtering
     * 
     * @param string $sql Raw SQL query
     * @param array $bindings Query bindings
     * @return bool True if query is safe (includes tenant_id or is system query)
     */
    public static function validateRawQuery(string $sql, array $bindings = []): bool
    {
        // System tables that don't need tenant_id
        $systemTables = ['users', 'tenants', 'migrations', 'jobs', 'failed_jobs', 'cache', 'sessions'];
        
        // Check if query touches tenant-scoped tables
        $tenantScopedTables = ['projects', 'tasks', 'documents', 'clients', 'quotes', 'templates', 'audit_logs'];
        
        $hasTenantScopedTable = false;
        foreach ($tenantScopedTables as $table) {
            if (stripos($sql, $table) !== false) {
                $hasTenantScopedTable = true;
                break;
            }
        }
        
        // If no tenant-scoped tables, query is safe
        if (!$hasTenantScopedTable) {
            return true;
        }
        
        // Check if query includes tenant_id filter
        $hasTenantId = stripos($sql, 'tenant_id') !== false;
        
        // Check bindings for tenant_id
        $hasTenantIdBinding = false;
        foreach ($bindings as $binding) {
            if (is_string($binding) && strlen($binding) === 26 && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $binding)) {
                // Could be a ULID (tenant_id format)
                $hasTenantIdBinding = true;
            }
        }
        
        // Query is safe if it includes tenant_id in SQL or bindings
        return $hasTenantId || $hasTenantIdBinding;
    }
    
    /**
     * Execute a raw query with tenant_id validation
     * 
     * @param string $sql Raw SQL query
     * @param array $bindings Query bindings
     * @param callable|null $callback Callback to execute if validation fails
     * @return mixed Query result
     * @throws \Exception If query doesn't include tenant_id and validation fails
     */
    public static function executeWithValidation(string $sql, array $bindings = [], ?callable $callback = null)
    {
        if (!self::validateRawQuery($sql, $bindings)) {
            $tenantId = self::getCurrentTenantId();
            
            if ($tenantId && $callback) {
                // Callback can modify query to include tenant_id
                return $callback($sql, $bindings, $tenantId);
            }
            
            // Log warning in development/staging
            if (app()->environment(['local', 'staging', 'testing'])) {
                Log::warning('Raw query without tenant_id detected', [
                    'sql' => $sql,
                    'bindings' => $bindings,
                    'tenant_id' => $tenantId,
                    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
                ]);
            }
            
            // In production, throw exception for security
            if (app()->environment('production')) {
                throw new \Exception('Raw query must include tenant_id filter for security');
            }
        }
        
        return DB::select($sql, $bindings);
    }
    
    /**
     * Get current tenant ID from request context
     * 
     * @return string|null
     */
    protected static function getCurrentTenantId(): ?string
    {
        // Priority 1: Request context
        if (app()->bound('request')) {
            $request = app('request');
            $tenantId = $request->attributes->get('tenant_id');
            if ($tenantId) {
                return $tenantId;
            }
        }
        
        // Priority 2: App instance
        if (app()->bound('current_tenant_id')) {
            return app('current_tenant_id');
        }
        
        // Priority 3: Auth user
        if (app()->bound('auth') && \Illuminate\Support\Facades\Auth::check()) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user && $user->tenant_id) {
                return $user->tenant_id;
            }
        }
        
        return null;
    }
}

