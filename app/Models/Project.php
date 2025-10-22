<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Src\Foundation\EventBus;
use Src\Foundation\Helpers\AuthHelper;

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
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'projects';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'start_date',
        'end_date',
        'due_date',
        'status',
        'progress_pct',
        'budget_total',
        'budget_planned',
        'budget_actual',
        'estimated_hours',
        'actual_hours',
        'risk_level',
        'is_template',
        'template_id',
        'last_activity_at',
        'completion_percentage',
        'owner_id',
        'tags',
        'priority',
        'settings'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'due_date' => 'date',
        'progress_pct' => 'integer',
        'budget_total' => 'float',
        'budget_planned' => 'float',
        'budget_actual' => 'float',
        'estimated_hours' => 'float',
        'actual_hours' => 'float',
        'completion_percentage' => 'float',
        'last_activity_at' => 'datetime',
        'tags' => 'array',
        'settings' => 'array'
    ];

    protected $attributes = [
        'status' => 'active',
        'progress_pct' => 0,
        'budget_total' => 0.0,
        'budget_planned' => 0.0,
        'budget_actual' => 0.0,
        'estimated_hours' => 0.0,
        'actual_hours' => 0.0,
        'risk_level' => 'low',
        'is_template' => false,
        'completion_percentage' => 0.0,
        'priority' => 'normal'
    ];

    /**
     * Các trạng thái hợp lệ
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PLANNING = 'planning';

    /**
     * Accessors for consistent field names
     */
    public function getProgressPercentAttribute(): int
    {
        return $this->progress_pct;
    }

    public function getBudgetAttribute(): float
    {
        return $this->budget_total;
    }

    public const VALID_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
        self::STATUS_COMPLETED,
        self::STATUS_ON_HOLD,
        self::STATUS_CANCELLED,
        self::STATUS_PLANNING,
    ];

    /**
     * Các mức độ ưu tiên
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const VALID_PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    /**
     * Relationship: Project thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /**
     * Relationship: Project belongs to client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    /**
     * Relationship: Project owner
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    /**
     * Relationship: Project có nhiều components
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
     * Relationship: Project có nhiều tasks
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Relationship: Project có nhiều users thông qua roles
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'project_user_roles',
            'project_id',
            'user_id'
        )->withPivot(['role_id'])
          ->withTimestamps();
    }

    /**
     * Relationship: Project có nhiều change requests
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    /**
     * Relationship: Project có nhiều documents
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
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
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: Chỉ lấy projects archived
     */
    public function scopeArchived($query)
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    /**
     * Tạo factory instance mới cho model
     */
    protected static function newFactory()
    {
        return \Database\Factories\ProjectFactory::new();
    }

    /**
     * Resolve route model binding with tenant isolation
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        return $this->where($field ?? $this->getRouteKeyName(), $value)
                   ->where('tenant_id', $user->tenant_id)
                   ->first();
    }

    /**
     * Accessor: Map actual_cost to budget_actual for backward compatibility
     */
    public function getActualCostAttribute()
    {
        return $this->budget_actual;
    }

    /**
     * Mutator: Map actual_cost to budget_actual for backward compatibility
     */
    public function setActualCostAttribute($value)
    {
        $this->budget_actual = $value;
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