<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Traits\ScopesByAdminAccess;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

/**
 * TemplateSet Model - WBS Task Template Set
 * 
 * This model represents a WBS-style task template set with phases, disciplines, tasks, and dependencies.
 * It is distinct from App\Models\Template (generic template) and App\Models\ProjectTemplate.
 * 
 * A TemplateSet can be:
 * - Global (tenant_id = null): Accessible to all tenants, manageable only by super-admin
 * - Tenant-specific (tenant_id set): Accessible only to that tenant
 * 
 * @property string $id ULID primary key
 * @property string|null $tenant_id Tenant ID (null for global templates)
 * @property string $code Unique code for the template set
 * @property string $name Template set name
 * @property string|null $description Template set description
 * @property string $version Version string (e.g., "2025.1")
 * @property bool $is_active Whether the template set is active
 * @property bool $is_global Whether this is a global template (tenant_id is null)
 * @property string $created_by User ID who created this template set
 * @property array|null $metadata Additional metadata (JSON)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class TemplateSet extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes, ScopesByAdminAccess;

    protected $table = 'template_sets';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'version',
        'is_active',
        'is_global',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_global' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'is_global' => false,
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        // Set is_global based on tenant_id
        static::creating(function ($model) {
            if ($model->tenant_id === null) {
                $model->is_global = true;
            }
        });

        static::updating(function ($model) {
            if ($model->tenant_id === null) {
                $model->is_global = true;
            } else {
                $model->is_global = false;
            }
        });
    }

    /**
     * Get the phases for this template set
     */
    public function phases(): HasMany
    {
        return $this->hasMany(TemplatePhase::class, 'set_id')->orderBy('order_index');
    }

    /**
     * Get the disciplines for this template set
     */
    public function disciplines(): HasMany
    {
        return $this->hasMany(TemplateDiscipline::class, 'set_id')->orderBy('order_index');
    }

    /**
     * Get the tasks for this template set
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(TemplateTask::class, 'set_id')->orderBy('order_index');
    }

    /**
     * Get the presets for this template set
     */
    public function presets(): HasMany
    {
        return $this->hasMany(TemplatePreset::class, 'set_id');
    }

    /**
     * Get the apply logs for this template set
     */
    public function applyLogs(): HasMany
    {
        return $this->hasMany(TemplateApplyLog::class, 'set_id');
    }

    /**
     * Get the user who created this template set
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get only active template sets
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get template sets for a tenant or global templates
     * 
     * This scope returns:
     * - Template sets where tenant_id matches the provided tenant_id
     * - Global template sets (where tenant_id IS NULL)
     * 
     * @param string $tenantId The tenant ID to filter by
     */
    public function scopeForTenantOrGlobal(Builder $query, string $tenantId): Builder
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)
              ->orWhereNull('tenant_id');
        });
    }
}

