<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProjectMilestone Model - Project milestones management.
 *
 * @property string $id ULID primary key
 * @property string|null $tenant_id ID công ty (GAP-117 T-1, điền từ dự án)
 * @property string $project_id ID dự án
 * @property string $name Tên milestone
 * @property string|null $description Mô tả
 * @property \Carbon\Carbon|null $target_date Ngày mục tiêu
 * @property \Carbon\Carbon|null $completed_date Ngày hoàn thành
 * @property string $status Trạng thái
 * @property int $order Thứ tự
 * @property array|null $metadata Dữ liệu bổ sung
 * @property string|null $created_by ID người tạo
 */
class ProjectMilestone extends Model
{
    use HasUlids;
    use HasFactory;
    use TenantScope;

    protected $table = 'project_milestones';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'name',
        'description',
        'target_date',
        'completed_date',
        'status',
        'order',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'target_date' => 'date',
        'completed_date' => 'date',
        'metadata' => 'array',
        'order' => 'integer',
    ];

    protected $attributes = [
        'status' => 'pending',
        'order' => 0,
    ];

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_OVERDUE,
        self::STATUS_CANCELLED,
    ];

    /**
     * Relationships.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    public function scopeByProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('target_date');
    }

    /**
     * GAP-117 T-1 / O-1: tenant_id comes from the project when not given. "Overdue" is no longer stored; it is computed
     * live by isOverdue() and the readers.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $milestone): void {
            if (($milestone->tenant_id ?? null) === null && $milestone->project_id) {
                $milestone->tenant_id = Project::query()->withoutGlobalScopes()->whereKey($milestone->project_id)->value('tenant_id');
            }
        });
    }

    /**
     * Mark milestone as completed.
     */
    public function markCompleted(string $userId = null): bool
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_date' => now()->toDateString(),
        ]);

        // Audit logging disabled for testing

        return true;
    }

    /**
     * Mark milestone as cancelled.
     */
    public function markCancelled(string $reason = null): bool
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'metadata' => array_merge($this->metadata ?? [], [
                'cancelled_reason' => $reason,
                'cancelled_at' => now()->toISOString(),
            ]),
        ]);

        return true;
    }

    /**
     * Check if milestone is overdue.
     */
    public function isOverdue(): bool
    {
        // GAP-117 O-1: pending (or a legacy stored "overdue") and the target day is before today.
        return $this->target_date !== null
            && in_array($this->status, [self::STATUS_PENDING, self::STATUS_OVERDUE], true)
            && $this->target_date->copy()->startOfDay()->lt(now()->startOfDay());
    }

    /**
     * Get days until target date.
     */
    public function getDaysUntilTarget(): ?int
    {
        if (!$this->target_date) {
            return null;
        }

        return now()->diffInDays($this->target_date, false);
    }

    /**
     * Get completion percentage based on target date.
     */
    public function getCompletionPercentage(): float
    {
        if (!$this->target_date || $this->status === self::STATUS_COMPLETED) {
            return $this->status === self::STATUS_COMPLETED ? 100.0 : 0.0;
        }

        if ($this->status === self::STATUS_CANCELLED) {
            return 0.0;
        }

        $projectStart = $this->project->start_date;
        if (!$projectStart) {
            return 0.0;
        }

        $totalDays = $projectStart->diffInDays($this->target_date);
        $elapsedDays = $projectStart->diffInDays(now());

        if ($totalDays <= 0) {
            return 100.0;
        }

        return min(100.0, max(0.0, ($elapsedDays / $totalDays) * 100));
    }

    /**
     * Update milestone order.
     */
    public function updateOrder(int $newOrder): void
    {
        $this->update(['order' => $newOrder]);
    }

    /**
     * Reorder milestones in project.
     */
    public static function reorderMilestones(string $projectId, array $milestoneIds): void
    {
        foreach ($milestoneIds as $index => $milestoneId) {
            self::where('id', $milestoneId)
                ->where('project_id', $projectId)
                ->update(['order' => $index + 1]);
        }
    }

    /**
     * Get milestone statistics for project.
     */
    public static function getProjectStatistics(string $projectId): array
    {
        $milestones = self::byProject($projectId)->get();

        return [
            'total' => $milestones->count(),
            'completed' => $milestones->where('status', self::STATUS_COMPLETED)->count(),
            'pending' => $milestones->where('status', self::STATUS_PENDING)->count(),
            'overdue' => $milestones->where('status', self::STATUS_OVERDUE)->count(),
            'cancelled' => $milestones->where('status', self::STATUS_CANCELLED)->count(),
            'completion_rate' => $milestones->count() > 0
                ? round(($milestones->where('status', self::STATUS_COMPLETED)->count() / $milestones->count()) * 100, 2)
                : 0,
            'average_delay' => self::calculateAverageDelay($milestones),
        ];
    }

    /**
     * Calculate average delay for completed milestones.
     */
    private static function calculateAverageDelay($milestones): float
    {
        $completedMilestones = $milestones->where('status', self::STATUS_COMPLETED)
                                         ->whereNotNull('target_date')
                                         ->whereNotNull('completed_date');

        if ($completedMilestones->isEmpty()) {
            return 0.0;
        }

        $totalDelay = $completedMilestones->sum(function ($milestone) {
            return $milestone->target_date->diffInDays($milestone->completed_date, false);
        });

        return round($totalDelay / $completedMilestones->count(), 1);
    }

    /**
     * Accessors.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'blue',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_OVERDUE => 'red',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray'
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown'
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->isOverdue();
    }
}
