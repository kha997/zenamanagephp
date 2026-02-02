<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Src\Foundation\EventBus;
use Src\Foundation\Helpers\AuthHelper;
use App\Models\UserRoleProject;
use App\Models\User;

/**
 * Model Project - Quản lý dự án
 * 
 * @property string $id ULID của project (primary key)
 * @property string $tenant_id ID công ty (ULID)
 * @property string $name Tên dự án
 * @property string|null $description Mô tả
 * @property \Carbon\Carbon|null $start_date Ngày bắt đầu
 * @property \Carbon\Carbon|null $end_date Ngày kết thúc
 * @property string $status Trạng thái dự án
 * @property float $progress Tiến độ %
 * @property float $actual_cost Chi phí thực tế
 */
class Project extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'projects';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'pm_id',
        'manager_id',
        'start_date',
        'end_date',
        'status',
        'priority',
        'progress',
        'budget_total',
        'budget_actual',
        'budget',
        'spent_amount'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'progress' => 'float',
        'budget_total' => 'float',
        'budget_planned' => 'float',
        'budget_actual' => 'float',
        'tags' => 'array',
        'settings' => 'array'
    ];

    protected $attributes = [
        'status' => 'planning',
        'progress' => 0.0,
        'budget_total' => 0.0
    ];

    public function getBudgetAttribute(): float
    {
        return (float) ($this->attributes['budget_total'] ?? 0.0);
    }

    public function setBudgetAttribute($value): void
    {
        $this->attributes['budget_total'] = $value;
    }

    public function getSpentAmountAttribute(): float
    {
        return (float) ($this->attributes['budget_actual'] ?? 0.0);
    }

    public function setSpentAmountAttribute($value): void
    {
        $this->attributes['budget_actual'] = $value;
    }

    /**
     * Các trạng thái hợp lệ
     */
    public const STATUS_PLANNING = 'planning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const VALID_STATUSES = [
        self::STATUS_PLANNING,
        self::STATUS_ACTIVE,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const VALID_PRIORITIES = [
        'low',
        'medium',
        'high',
        'urgent',
    ];

    /**
     * Relationship: Project thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_id');
    }

    public function getManagerIdAttribute(): ?string
    {
        return $this->attributes['pm_id'] ?? null;
    }

    public function setManagerIdAttribute(?string $value): void
    {
        $this->attributes['pm_id'] = $value;
    }

    /**
     * Relationship: Project có nhiều components
     */
    public function components(): HasMany
    {
        return $this->hasMany(\Src\CoreProject\Models\Component::class);
    }

    /**
     * Relationship: Project có nhiều root components
     */
    public function rootComponents(): HasMany
    {
        return $this->hasMany(\Src\CoreProject\Models\Component::class)
                    ->whereNull('parent_component_id');
    }

    /**
     * Relationship: Project có nhiều tasks
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(\Src\CoreProject\Models\Task::class);
    }

    /**
     * Relationship: Project có nhiều user roles
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(\Src\RBAC\Models\UserRoleProject::class);
    }

    /**
     * Relationship: Project có nhiều bản ghi project-user cụ thể
     */
    public function projectUsers(): HasMany
    {
        return $this->hasMany(UserRoleProject::class, 'project_id');
    }

    /**
     * Relationship: Project có nhiều users thông qua roles
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'project_user_roles',
            'project_id',
            'user_id'
        )->withPivot(['role_id'])
          ->withTimestamps();
    }

    /**
     * Relationship: Project có nhiều baselines
     */
    public function baselines(): HasMany
    {
        return $this->hasMany(\Src\CoreProject\Models\Baseline::class);
    }

    /**
     * Relationship: Project có nhiều change requests
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(\Src\ChangeRequest\Models\ChangeRequest::class);
    }

    /**
     * Relationship: Project có nhiều documents
     */
    public function documents(): HasMany
    {
        return $this->hasMany(\Src\DocumentManagement\Models\Document::class);
    }

    /**
     * Relationship: Project có nhiều teams
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Team::class,
            'project_teams',
            'project_id',
            'team_id'
        )->withPivot('role')->withTimestamps();
    }

    /**
     * Relationship: Project có nhiều interaction logs
     */
    public function interactionLogs(): HasMany
    {
        return $this->hasMany(\Src\InteractionLogs\Models\InteractionLog::class);
    }

    /**
     * Tính toán lại tiến độ dự án từ components
     */
    public function recalculateProgress(): void
    {
        $rootComponents = $this->rootComponents;
        
        if ($rootComponents->isEmpty()) {
            $this->update(['progress' => 0.0]);
            return;
        }

        $totalWeight = $rootComponents->sum('planned_cost');
        
        if ($totalWeight == 0) {
            $this->update(['progress' => 0.0]);
            return;
        }

        $weightedProgress = $rootComponents->sum(function ($component) {
            return $component->progress_percent * $component->planned_cost;
        });

        $newProgress = $weightedProgress / $totalWeight;
        $oldProgress = $this->progress;
        $this->update(['progress' => $newProgress]);
        
        // Dispatch event với payload chuẩn
        EventBus::dispatch('Project.Project.ProgressUpdated', [
            'entityId' => $this->id,
            'projectId' => $this->id,
            'actorId' => $this->resolveActorId(),
            'changedFields' => [
                'progress' => [
                    'old' => $oldProgress,
                    'new' => $newProgress
                ]
            ]
        ]);
    }
    
    /**
     * Tính toán lại chi phí thực tế từ components
     */
    public function recalculateActualCost(): void
    {
        $totalCost = $this->rootComponents->sum('actual_cost');
        $oldCost = $this->actual_cost;
        $this->update(['actual_cost' => $totalCost]);
        
        // Dispatch event với payload chuẩn
        EventBus::dispatch('Project.Project.CostUpdated', [
            'entityId' => $this->id,
            'projectId' => $this->id,
            'actorId' => $this->resolveActorId(),
            'changedFields' => [
                'actual_cost' => [
                    'old' => $oldCost,
                    'new' => $totalCost
                ]
            ]
        ]);
    }

    /**
     * Scope: Lọc theo tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Lọc theo status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Chỉ lấy projects active
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PLANNING, self::STATUS_ACTIVE]);
    }

    /**
     * Tạo factory instance mới cho model
     */
    protected static function newFactory()
    {
        return \Database\Factories\ProjectFactory::new();
    }

    /**
     * Resolve actor ID từ Auth facade với xử lý ngoại lệ
     * 
     * @return string|int
     * @throws AuthenticationException
     */
    private function resolveActorId(): string|int
    {
        try {
            if (AuthHelper::check()) {
                return AuthHelper::id();
            }
            
            // Trả về 'system' nếu không có người dùng xác thực
            return 'system';
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve actor ID from Auth facade', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 'system';
        }
    }
}
