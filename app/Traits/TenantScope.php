<?php

namespace App\Traits;

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
            // Only apply scope if we have a tenant context
            if (app()->has('tenant') || request()->has('tenant_id')) {
                $tenantId = app('tenant')?->id ?? request('tenant_id');
                
                if ($tenantId) {
                    $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
                }
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
        $tenantId = app('tenant')?->id ?? request('tenant_id');
        
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }
        
        return $query;
    }
}
