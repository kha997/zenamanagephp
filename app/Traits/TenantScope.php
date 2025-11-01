<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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
            // Only apply scope if we have an authenticated user with tenant_id
            if (Auth::check() && Auth::user()->tenant_id) {
                $tenantId = Auth::user()->tenant_id;
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
        if (Auth::check() && Auth::user()->tenant_id) {
            return $query->where('tenant_id', Auth::user()->tenant_id);
        }
        
        return $query;
    }

    /**
     * Scope to bypass tenant filtering (use with caution)
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}