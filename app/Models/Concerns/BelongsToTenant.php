<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * BelongsToTenant Concern
 *
 * Provides tenant isolation for Eloquent models
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        if (env('DISABLE_TENANT_SCOPE', false)) {
            return;
        }

        static::addGlobalScope('tenant', function (Builder $q) {
            // Chỉ scope khi có user & tenant_id
            if (app()->bound('auth') && Auth::check()) {
                $tid = Auth::user()->tenant_id ?? null;
                if ($tid) {
                    $q->where($q->getModel()->getTable().'.tenant_id', $tid);
                }
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id) && app()->bound('auth') && Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id ?? $model->tenant_id;
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
        if (app()->bound('auth') && Auth::check()) {
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
