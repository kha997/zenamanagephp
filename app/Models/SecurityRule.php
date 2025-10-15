<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * SecurityRule Model - Security monitoring rules
 */
class SecurityRule extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'category',
        'type',
        'is_enabled',
        'severity',
        'conditions',
        'actions',
        'destinations',
        'trigger_count',
        'last_triggered_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'destinations' => 'array',
        'is_enabled' => 'boolean',
        'trigger_count' => 'integer',
        'last_triggered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship with Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship with Alerts
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(SecurityAlert::class, 'rule_id');
    }

    /**
     * Scope: Enabled rules
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope: By category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: By type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: System-wide rules
     */
    public function scopeSystemWide($query)
    {
        return $query->whereNull('tenant_id');
    }

    /**
     * Scope: Tenant-specific rules
     */
    public function scopeByTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Increment trigger count
     */
    public function incrementTriggerCount(): void
    {
        $this->increment('trigger_count');
        $this->update(['last_triggered_at' => now()]);
    }
}
