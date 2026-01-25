<?php

namespace App\Traits;

use App\Services\TenancyService;
use Illuminate\Database\Eloquent\Builder;

/**
 * TenantScope Trait
 * 
 * Automatically applies tenant_id scope to all queries
 * for models that belong to a tenant
 */
trait TenantScope
{
    /**
     * Boot the trait
     */
    protected static function bootTenantScope()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = static::resolveTenantIdForScope();

            if ($tenantId) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });
    }

    /**
     * Get the tenant ID for this model
     */
    public function getTenantId()
    {
        return $this->tenant_id;
    }

    /**
     * Check if model belongs to a specific tenant
     */
    public function belongsToTenant($tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }

    /**
     * Scope to get models for a specific tenant
     */
    public function scopeForTenant(Builder $query, $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to get models for current tenant context
     */
    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $tenantId = static::resolveTenantIdForScope();
        
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }
        
        return $query;
    }

    /**
     * Determine tenant id for scopes using the tenancy service or request.
     */
    private static function resolveTenantIdForScope(): ?string
    {
        $tenantService = app(TenancyService::class);
        $tenantId = $tenantService->currentTenantId();

        if ($tenantId) {
            return $tenantId;
        }

        if (app()->bound('request')) {
            $request = app('request');
            $headerTenantId = $request->get('tenant_id');

            if ($headerTenantId !== null && (string) $headerTenantId !== '') {
                return (string) $headerTenantId;
            }
        }

        return null;
    }
}
