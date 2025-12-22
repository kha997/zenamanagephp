<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TaskTemplate Model
 * 
 * Represents a task template that belongs to a Template (project template).
 * Used to define checklist items that can be instantiated when creating projects from templates.
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id Tenant ID
 * @property string $template_id Template ID (FK to templates.id)
 * @property string $name Task template name
 * @property string|null $description Task template description
 * @property int|null $order_index Order index for sorting
 * @property float|null $estimated_hours Estimated hours for the task
 * @property bool $is_required Whether the task is required
 * @property array|null $metadata Additional metadata (JSON)
 * @property string|null $created_by Creator user ID
 * @property string|null $updated_by Last updater user ID
 */
class TaskTemplate extends Model
{
    use HasUlids, HasFactory, SoftDeletes, TenantScope;

    protected $table = 'task_templates';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'template_id',
        'name',
        'description',
        'order_index',
        'phase_code',
        'phase_label',
        'group_label',
        'estimated_hours',
        'is_required',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'order_index' => 'integer',
        'estimated_hours' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_required' => true,
    ];

    /**
     * Relationships
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Scopes
     */
    public function scopeByTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByTemplate($query, string $templateId)
    {
        return $query->where('template_id', $templateId);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index', 'asc')->orderBy('name', 'asc');
    }
}
