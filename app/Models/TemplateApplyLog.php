<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TemplateApplyLog Model - Log of template applications to projects
 * 
 * Records each time a template set is applied to a project, including
 * selections, counts, and performance metrics.
 * 
 * @property string $id ULID primary key
 * @property string $project_id Project ID
 * @property string $tenant_id Tenant ID
 * @property string $set_id Template set ID
 * @property string|null $preset_code Preset code used (if any)
 * @property string|null $preset_id Preset ID (ULID) used (if any)
 * @property array $selections Selections made (phases, disciplines, tasks)
 * @property array|null $options Application options (include_dependencies, etc.)
 * @property array $counts Summary counts (tasks_created, dependencies_created, etc.)
 * @property string $executor_id User ID who applied the template
 * @property int $duration_ms Duration in milliseconds
 * @property \Carbon\Carbon $created_at
 */
class TemplateApplyLog extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'template_apply_logs';

    protected $keyType = 'string';
    public $incrementing = false;

    public $timestamps = false; // Only created_at, no updated_at

    protected $fillable = [
        'project_id',
        'tenant_id',
        'set_id',
        'preset_code',
        'preset_id',
        'selections',
        'options',
        'counts',
        'executor_id',
        'duration_ms',
        'created_at',
    ];

    protected $casts = [
        'selections' => 'array',
        'options' => 'array',
        'counts' => 'array',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the project this log entry belongs to
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the tenant this log entry belongs to
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the template set that was applied
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(TemplateSet::class, 'set_id');
    }

    /**
     * Get the user who applied the template
     */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }
}

