<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Project Health Snapshot Model
 * 
 * Round 86: Project Health History (snapshots + history API, backend-only)
 * 
 * Stores persistent snapshots of project health metrics over time.
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id Tenant ID (ULID)
 * @property string $project_id Project ID (ULID)
 * @property \Carbon\Carbon $snapshot_date Logical date of the snapshot
 * @property string $schedule_status Schedule status (on_track, at_risk, delayed, no_tasks)
 * @property string $cost_status Cost status (on_budget, over_budget, at_risk, no_data)
 * @property string $overall_status Overall status (good, warning, critical)
 * @property float|null $tasks_completion_rate Task completion rate (0-1)
 * @property float|null $blocked_tasks_ratio Blocked tasks ratio (0-1)
 * @property int $overdue_tasks Count of overdue tasks
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class ProjectHealthSnapshot extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'project_health_snapshots';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'project_id',
        'snapshot_date',
        'schedule_status',
        'cost_status',
        'overall_status',
        'tasks_completion_rate',
        'blocked_tasks_ratio',
        'overdue_tasks',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'tasks_completion_rate' => 'decimal:4',
        'blocked_tasks_ratio' => 'decimal:4',
        'overdue_tasks' => 'integer',
    ];

    /**
     * Get the project that owns this snapshot
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
