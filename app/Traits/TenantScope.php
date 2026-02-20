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
    protected static function bootTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = null;

            if (app()->has('tenant')) {
                $tenantId = app('tenant')?->id;
            } elseif (app()->bound('current_tenant_id')) {
                $tenantId = app('current_tenant_id');
            } elseif (function_exists('request') && request()->attributes->has('tenant_id')) {
                $tenantId = request()->attributes->get('tenant_id');
            }

            if ($tenantId) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });
    }

    /**
     * Get the tenant ID for this model
     */
    public function getTenantId(): ?string
    {
        $tenantId = $this->getAttribute('tenant_id');

        return $tenantId !== null ? (string) $tenantId : null;
    }

    /**
     * Check if model belongs to a specific tenant
     */
    public function belongsToTenant(string $tenantId): bool
    {
        return $this->getTenantId() === $tenantId;
    }

    /**
     * Scope to get models for a specific tenant
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to get models for current tenant context
     */
    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $tenantId = null;

        if (app()->has('tenant')) {
            $tenantId = app('tenant')?->id;
        }

        if ($tenantId === null && app()->bound('current_tenant_id')) {
            $tenantId = app('current_tenant_id');
        } elseif ($tenantId === null && function_exists('request') && request()->attributes->has('tenant_id')) {
            $tenantId = request()->attributes->get('tenant_id');
        }
        
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }
        
        return $query;
    }
}
