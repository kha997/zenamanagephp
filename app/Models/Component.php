<?php declare(strict_types=1);

namespace App\Models;
use Illuminate\Support\Facades\Auth;


use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory; // Thêm import
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Src\Foundation\EventBus;
use Src\Foundation\Helpers\AuthHelper;

/**
 * Model Component - Quản lý thành phần dự án
 * 
 * @property string $id ULID của component (primary key)
 * @property string $project_id ID dự án (ULID)
 * @property string|null $parent_component_id ID component cha (ULID)
 * @property string $name Tên component
 * @property float $progress_percent Tiến độ %
 * @property float $planned_cost Chi phí dự kiến
 * @property float $actual_cost Chi phí thực tế
 */
class Component extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'components';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'project_id',
        'parent_component_id',
        'name',
        'description',
        'type',
        'status',
        'priority',
        'progress_percent',
        'planned_cost',
        'actual_cost',
        'start_date',
        'end_date',
        'budget',
        'dependencies',
        'metadata',
        'created_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'dependencies' => 'array',
        'metadata' => 'array',
        'progress_percent' => 'float',
        'planned_cost' => 'float',
        'actual_cost' => 'float',
        'budget' => 'float'
    ];

    protected $attributes = [
        'type' => 'general',
        'status' => 'planning',
        'priority' => 'medium',
        'progress_percent' => 0.0,
        'planned_cost' => 0.0,
        'actual_cost' => 0.0,
        'budget' => 0.0
    ];

    /**
     * Relationship: Component thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Component thuộc về project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relationship: Component được tạo bởi user
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Component có thể có parent
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'parent_component_id');
    }

    /**
     * Relationship: Component có nhiều children
     */
    public function children(): HasMany
    {
        return $this->hasMany(Component::class, 'parent_component_id');
    }

    /**
     * Relationship alias for children components
     */
    public function childComponents(): HasMany
    {
        return $this->children();
    }

    /**
     * Relationship: Component có nhiều tasks
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Relationship với ComponentKpis
     */
    public function kpis(): HasMany
    {
        return $this->hasMany(ComponentKpi::class);
    }

    /**
     * Lấy KPI theo code
     */
    public function getKpi(string $kpiCode): ?ComponentKpi
    {
        return $this->kpis()->where('kpi_code', $kpiCode)->latest('measured_date')->first();
    }

    /**
     * Cập nhật KPI
     */
    public function updateKpi(string $kpiCode, float $value, ?string $unit = null, ?string $description = null): ComponentKpi
    {
        return $this->kpis()->create([
            'kpi_code' => $kpiCode,
            'value' => $value,
            'unit' => $unit,
            'description' => $description,
            'measured_date' => now()->toDateString(),
            'created_by' => $this->resolveActorId(),
        ]);
    }

    /**
     * Cập nhật tiến độ và trigger recalculation
     */
    public function updateProgress(float $newProgress): void
    {
        $oldProgress = $this->progress_percent;
        $this->update(['progress_percent' => $newProgress]);
        
        // Dispatch event với payload chuẩn
        EventBus::dispatch('Project.Component.ProgressUpdated', [
            'entityId' => $this->id,
            'projectId' => $this->project_id,
            'actorId' => $this->resolveActorId(),
            'changedFields' => [
                'progress_percent' => [
                    'old' => $oldProgress,
                    'new' => $newProgress
                ]
            ],
            'componentId' => $this->id // Backward compatibility
        ]);
    }
    
    /**
     * Cập nhật chi phí thực tế và trigger recalculation
     */
    public function updateActualCost(float $newCost): void
    {
        $oldCost = $this->actual_cost;
        $this->update(['actual_cost' => $newCost]);
        
        // Dispatch event với payload chuẩn
        EventBus::dispatch('Project.Component.CostUpdated', [
            'entityId' => $this->id,
            'projectId' => $this->project_id,
            'actorId' => AuthHelper::idOrSystem(), // Thay đổi từ Auth::id()
            'changedFields' => [
                'actual_cost' => [
                    'old' => $oldCost,
                    'new' => $newCost
                ]
            ],
            'componentId' => $this->id // Backward compatibility
        ]);
    }

    /**
     * Tính toán lại từ children components
     */
    public function recalculateFromChildren(): void
    {
        $children = $this->children;
        
        if ($children->isEmpty()) {
            return;
        }

        // Recalculate progress
        $totalWeight = $children->sum('planned_cost');
        if ($totalWeight > 0) {
            $weightedProgress = $children->sum(function ($child) {
                return $child->progress_percent * $child->planned_cost;
            });
            $newProgress = $weightedProgress / $totalWeight;
            $this->progress_percent = $newProgress;
        }

        // Recalculate actual cost
        $this->actual_cost = $children->sum('actual_cost');
        
        $this->save();
        
        // Continue up the chain
        if ($this->parent) {
            $this->parent->recalculateFromChildren();
        } else {
            $this->project->recalculateProgress();
            $this->project->recalculateActualCost();
        }
    }

    /**
     * Scope: Lọc theo project
     */
    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope: Chỉ lấy root components
     */
    public function scopeRootComponents($query)
    {
        return $query->whereNull('parent_component_id');
    }

    /**
     * Tạo factory instance mới cho model
     */
    protected static function newFactory()
    {
        return \Database\Factories\ComponentFactory::new();
    }
    
    /**
     * Lấy ID của actor hiện tại một cách an toàn
     * 
     * @return string ID của user hiện tại hoặc 'system' nếu không có auth context
     */
    private function resolveActorId(): string
    {
        try {
            return AuthHelper::idOrSystem(); // Thay đổi từ Auth::id()
        } catch (\Exception $e) {
            Log::warning('Cannot resolve actor ID in Component context', [
                'component_id' => $this->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return 'system';
        }
    }
}
