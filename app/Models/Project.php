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
 * Unified Project Model - Thay thế tất cả Project models khác
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id ID công ty (ULID)
 * @property string $code Mã dự án (auto-generated)
 * @property string $name Tên dự án
 * @property string|null $description Mô tả
 * @property string|null $client_id ID khách hàng
 * @property string|null $pm_id ID Project Manager
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
class Project extends Model
{
    use HasFactory, HasUlids, HasTimestamps, HasOwnership, HasAuditLog, SoftDeletes;

    protected $table = 'projects';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'client_id',
        'pm_id',
        'start_date',
        'end_date',
        'status',
        'progress',
        'budget_planned',
        'budget_actual',
        'priority',
        'tags',
        'settings'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'progress' => 'decimal:2',
        'budget_planned' => 'decimal:2',
        'budget_actual' => 'decimal:2',
        'tags' => 'array',
        'settings' => 'array'
    ];

    protected $attributes = [
        'status' => 'draft',
        'progress' => 0.0,
        'budget_planned' => 0.0,
        'budget_actual' => 0.0,
        'priority' => 'medium'
    ];

    /**
     * Các trạng thái hợp lệ
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PLANNING = 'planning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ARCHIVED = 'archived';

    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PLANNING,
        self::STATUS_ACTIVE,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_ARCHIVED,
    ];

    /**
     * Các mức độ ưu tiên
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const VALID_PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    /**
     * Boot method để tự động generate code
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            if (empty($project->code)) {
                $project->code = $project->generateCode();
            }
        });
    }

    /**
     * Generate project code tự động
     */
    public function generateCode(): string
    {
        $year = date('Y');
        $count = self::whereYear('created_at', $year)->count() + 1;
        return "PRJ-{$year}-" . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Tính toán tiến độ dự án từ tasks
     */
    public function calculateProgress(): float
    {
        $tasks = $this->tasks()->where('status', '!=', 'cancelled');
        
        if ($tasks->count() === 0) {
            return 0.0;
        }
        
        // Tính theo weight nếu có, nếu không thì tính theo số lượng
        $totalWeight = $tasks->sum('weight') ?: $tasks->count();
        $completedWeight = $tasks->where('status', 'completed')->sum('weight') ?: 
                          $tasks->where('status', 'completed')->count();
        
        if ($totalWeight == 0) {
            return 0.0;
        }
        
        return round(($completedWeight / $totalWeight) * 100, 2);
    }

    /**
     * Cập nhật tiến độ dự án
     */
    public function updateProgress(): void
    {
        $this->update(['progress' => $this->calculateProgress()]);
    }

    /**
     * Kiểm tra có thể chuyển sang trạng thái mới không
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $transitions = [
            self::STATUS_DRAFT => [self::STATUS_PLANNING, self::STATUS_CANCELLED],
            self::STATUS_PLANNING => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
            self::STATUS_ACTIVE => [self::STATUS_ON_HOLD, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_ON_HOLD => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [self::STATUS_ARCHIVED],
            self::STATUS_CANCELLED => [],
            self::STATUS_ARCHIVED => []
        ];
        
        return in_array($newStatus, $transitions[$this->status] ?? []);
    }

    /**
     * Chuyển trạng thái dự án
     */
    public function transitionTo(string $newStatus, string $reason = null): bool
    {
        if (!$this->canTransitionTo($newStatus)) {
            return false;
        }

        $oldStatus = $this->status;
        $this->update(['status' => $newStatus]);

        // Log audit
        if (class_exists('\App\Services\AuditService')) {
            \App\Services\AuditService::log(
                'project_status_changed',
                'Project',
                $this->id,
                ['status' => $oldStatus, 'reason' => $reason],
                ['status' => $newStatus]
            );
        }

        return true;
    }

    /**
     * Tính toán budget utilization
     */
    public function getBudgetUtilization(): float
    {
        if ($this->budget_planned == 0) {
            return 0.0;
        }
        
        return round(($this->budget_actual / $this->budget_planned) * 100, 2);
    }

    /**
     * Kiểm tra dự án có overdue không
     */
    public function isOverdue(): bool
    {
        return $this->end_date && $this->end_date->isPast() && $this->status !== self::STATUS_COMPLETED;
    }

    /**
     * Tính số ngày còn lại
     */
    public function getDaysRemaining(): ?int
    {
        if (!$this->end_date) {
            return null;
        }
        
        return max(0, now()->diffInDays($this->end_date, false));
    }

    /**
     * RELATIONSHIPS
     */

    /**
     * Relationship: Project thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Project thuộc về client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Relationship: Project thuộc về Project Manager
     */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_id');
    }

    /**
     * Relationship: Project có nhiều tasks
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Relationship: Project có nhiều documents
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Relationship: Project có nhiều team members
     */
    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_team_members')
                    ->withPivot(['role', 'joined_at', 'left_at'])
                    ->withTimestamps();
    }

    /**
     * Relationship: Project có nhiều milestones
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    /**
     * Relationship: Project có nhiều components (nếu có)
     */
    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }

    /**
     * Relationship: Project có nhiều root components
     */
    public function rootComponents(): HasMany
    {
        return $this->hasMany(Component::class)
                    ->whereNull('parent_component_id');
    }

    /**
     * Relationship: Project có nhiều teams
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_teams', 'project_id', 'team_id')
                    ->withPivot(['role', 'joined_at', 'left_at'])
                    ->withTimestamps();
    }

    /**
     * Relationship: Project có nhiều change requests
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(\Src\ChangeRequest\Models\ChangeRequest::class);
    }

    /**
     * Relationship: Project có nhiều interaction logs
     */
    public function interactionLogs(): HasMany
    {
        return $this->hasMany(\Src\InteractionLogs\Models\InteractionLog::class);
    }

    /**
     * SCOPES
     */

    /**
     * Scope: Lọc theo tenant
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Lọc theo status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Chỉ lấy projects active
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PLANNING, self::STATUS_ACTIVE]);
    }

    /**
     * Scope: Lọc theo priority
     */
    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope: Lọc projects overdue
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('end_date', '<', now())
                     ->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    /**
     * Scope: Lọc theo date range
     */
    public function scopeInDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('start_date', [$startDate, $endDate])
                     ->orWhereBetween('end_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Search projects
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%");
        });
    }

    /**
     * ACCESSORS & MUTATORS
     */

    /**
     * Get project status with color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PLANNING => 'blue',
            self::STATUS_ACTIVE => 'green',
            self::STATUS_ON_HOLD => 'yellow',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_CANCELLED => 'red',
            self::STATUS_ARCHIVED => 'gray',
            default => 'gray'
        };
    }

    /**
     * Get priority color
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'green',
            self::PRIORITY_MEDIUM => 'blue',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_URGENT => 'red',
            default => 'blue'
        };
    }

    /**
     * Get project duration in days
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->start_date || !$this->end_date) {
            return null;
        }
        
        return $this->start_date->diffInDays($this->end_date);
    }

    /**
     * Tạo factory instance mới cho model
     */
    protected static function newFactory()
    {
        return \Database\Factories\ProjectFactory::new();
    }
}