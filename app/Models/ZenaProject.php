<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Src\Foundation\Traits\HasTimestamps;
use Src\Foundation\Traits\HasOwnership;
use Src\Foundation\Traits\HasAuditLog;

/**
 * ZenaProject Model - Alias for Project model with Zena-specific features
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id ID công ty (ULID)
 * @property string $code Mã dự án (auto-generated)
 * @property string $name Tên dự án
 * @property string|null $description Mô tả
 * @property string|null $client_id ID khách hàng
 * @property string|null $pm_id ID Project Manager
 * @property string|null $created_by ID người tạo
 * @property \Carbon\Carbon|null $start_date Ngày bắt đầu
 * @property \Carbon\Carbon|null $end_date Ngày kết thúc
 * @property string $status Trạng thái dự án
 * @property float $progress Tiến độ % (calculated)
 * @property float $budget_planned Ngân sách dự kiến
 * @property float $budget_actual Chi phí thực tế
 * @property string $priority Mức độ ưu tiên
 * @property array|null $tags Tags
 * @property array|null $settings Cài đặt dự án
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class ZenaProject extends Model
{
    use HasFactory, HasUlids, HasTimestamps, HasOwnership, HasAuditLog, SoftDeletes;

    protected $table = 'projects';

    protected $fillable = [
        'id',
        'tenant_id',
        'code',
        'name',
        'description',
        'client_id',
        'pm_id',
        'created_by',
        'start_date',
        'end_date',
        'status',
        'progress',
        'budget_planned',
        'budget_actual',
        'priority',
        'tags',
        'settings',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'progress' => 'decimal:2',
        'budget_planned' => 'decimal:2',
        'budget_actual' => 'decimal:2',
        'tags' => 'array',
        'settings' => 'array',
    ];

    protected $attributes = [
        'status' => 'planning',
        'progress' => 0.00,
        'budget_planned' => 0.00,
        'budget_actual' => 0.00,
        'priority' => 'medium',
    ];

    /**
     * Boot method to auto-generate project code
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            if (empty($project->code)) {
                $project->code = 'PRJ-' . strtoupper(substr($project->id, -8));
            }
        });
    }

    /**
     * Relationship: Project belongs to a tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Project belongs to a creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Project belongs to a project manager
     */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_id');
    }

    /**
     * Relationship: Project has many tasks
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ZenaTask::class, 'project_id');
    }

    /**
     * Relationship: Project has many documents
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ZenaDocument::class, 'project_id');
    }

    /**
     * Relationship: Project has many RFIs
     */
    public function rfis(): HasMany
    {
        return $this->hasMany(ZenaRfi::class, 'project_id');
    }

    /**
     * Relationship: Project has many submittals
     */
    public function submittals(): HasMany
    {
        return $this->hasMany(ZenaSubmittal::class, 'project_id');
    }

    /**
     * Relationship: Project has many change requests
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(ZenaChangeRequest::class, 'project_id');
    }

    /**
     * Relationship: Project has many team members
     */
    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_team_members', 'project_id', 'user_id')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Scope: Active projects
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['planning', 'active', 'in_progress']);
    }

    /**
     * Scope: Completed projects
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Overdue projects
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('end_date', '<', now())
                    ->where('status', '!=', 'completed');
    }

    /**
     * Calculate project progress based on tasks
     */
    public function calculateProgress(): float
    {
        $totalTasks = $this->tasks()->count();
        
        if ($totalTasks === 0) {
            return 0.00;
        }

        $completedTasks = $this->tasks()->where('status', 'completed')->count();
        
        return round(($completedTasks / $totalTasks) * 100, 2);
    }

    /**
     * Check if project is overdue
     */
    public function isOverdue(): bool
    {
        return $this->end_date && 
               $this->end_date->isPast() && 
               $this->status !== 'completed';
    }

    /**
     * Get project duration in days
     */
    public function getDurationInDays(): int
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        return $this->start_date->diffInDays($this->end_date);
    }

    /**
     * Get remaining days
     */
    public function getRemainingDays(): int
    {
        if (!$this->end_date) {
            return 0;
        }

        return max(0, now()->diffInDays($this->end_date, false));
    }

    /**
     * Get budget variance
     */
    public function getBudgetVariance(): float
    {
        return $this->budget_actual - $this->budget_planned;
    }

    /**
     * Get budget variance percentage
     */
    public function getBudgetVariancePercentage(): float
    {
        if ($this->budget_planned === 0) {
            return 0;
        }

        return round(($this->getBudgetVariance() / $this->budget_planned) * 100, 2);
    }

    /**
     * Get active tasks count
     */
    public function getActiveTasksCount(): int
    {
        return $this->tasks()->whereIn('status', ['pending', 'in_progress'])->count();
    }

    /**
     * Get completed tasks count
     */
    public function getCompletedTasksCount(): int
    {
        return $this->tasks()->where('status', 'completed')->count();
    }

    /**
     * Get overdue tasks count
     */
    public function getOverdueTasksCount(): int
    {
        return $this->tasks()->where('end_date', '<', now())
                           ->where('status', '!=', 'completed')
                           ->count();
    }
}
