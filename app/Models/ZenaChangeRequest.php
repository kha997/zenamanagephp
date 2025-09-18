<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Src\Foundation\Traits\HasTimestamps;
use Src\Foundation\Traits\HasOwnership;
use Src\Foundation\Traits\HasAuditLog;

/**
 * ZenaChangeRequest Model - Manages project change requests
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id ID công ty (ULID)
 * @property string $project_id ID dự án (ULID)
 * @property string $task_id ID task liên quan (ULID, nullable)
 * @property string $change_number Mã số change request
 * @property string $title Tiêu đề change request
 * @property string $description Mô tả chi tiết
 * @property string $change_type Loại thay đổi
 * @property string $priority Mức độ ưu tiên
 * @property string $status Trạng thái
 * @property string $impact_level Mức độ tác động
 * @property string $requested_by ID người yêu cầu
 * @property string|null $assigned_to ID người được giao xử lý
 * @property string|null $approved_by ID người phê duyệt
 * @property string|null $rejected_by ID người từ chối
 * @property \Carbon\Carbon|null $requested_at Ngày yêu cầu
 * @property \Carbon\Carbon|null $due_date Ngày hạn xử lý
 * @property \Carbon\Carbon|null $approved_at Ngày phê duyệt
 * @property \Carbon\Carbon|null $rejected_at Ngày từ chối
 * @property \Carbon\Carbon|null $implemented_at Ngày triển khai
 * @property float $estimated_cost Chi phí ước tính
 * @property float $actual_cost Chi phí thực tế
 * @property int $estimated_days Số ngày ước tính
 * @property int $actual_days Số ngày thực tế
 * @property string|null $approval_notes Ghi chú phê duyệt
 * @property string|null $rejection_reason Lý do từ chối
 * @property string|null $implementation_notes Ghi chú triển khai
 * @property array|null $attachments Danh sách file đính kèm
 * @property array|null $impact_analysis Phân tích tác động
 * @property array|null $risk_assessment Đánh giá rủi ro
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class ZenaChangeRequest extends Model
{
    use HasFactory, HasUlids, HasTimestamps, HasOwnership, HasAuditLog, SoftDeletes;

    protected $table = 'change_requests';

    protected $fillable = [
        'id',
        'tenant_id',
        'project_id',
        'task_id',
        'change_number',
        'title',
        'description',
        'change_type',
        'priority',
        'status',
        'impact_level',
        'requested_by',
        'assigned_to',
        'approved_by',
        'rejected_by',
        'requested_at',
        'due_date',
        'approved_at',
        'rejected_at',
        'implemented_at',
        'estimated_cost',
        'actual_cost',
        'estimated_days',
        'actual_days',
        'approval_notes',
        'rejection_reason',
        'implementation_notes',
        'attachments',
        'impact_analysis',
        'risk_assessment',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'due_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'implemented_at' => 'datetime',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'estimated_days' => 'integer',
        'actual_days' => 'integer',
        'attachments' => 'array',
        'impact_analysis' => 'array',
        'risk_assessment' => 'array',
    ];

    protected $attributes = [
        'status' => 'pending',
        'priority' => 'medium',
        'impact_level' => 'low',
        'estimated_cost' => 0.00,
        'actual_cost' => 0.00,
        'estimated_days' => 0,
        'actual_days' => 0,
    ];

    /**
     * Boot method to auto-generate change request number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($changeRequest) {
            if (empty($changeRequest->change_number)) {
                $changeRequest->change_number = 'CR-' . strtoupper(substr($changeRequest->id, -8));
            }
            if (empty($changeRequest->requested_at)) {
                $changeRequest->requested_at = now();
            }
        });
    }

    /**
     * Relationship: Change request belongs to a tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Change request belongs to a project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ZenaProject::class, 'project_id');
    }

    /**
     * Relationship: Change request belongs to a task
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ZenaTask::class, 'task_id');
    }

    /**
     * Relationship: Change request requested by user
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Relationship: Change request assigned to user
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Relationship: Change request approved by user
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relationship: Change request rejected by user
     */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Relationship: Change request has many comments
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ChangeRequestComment::class, 'change_request_id');
    }

    /**
     * Relationship: Change request has many approvals
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(ChangeRequestApproval::class, 'change_request_id');
    }

    /**
     * Scope: Pending change requests
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Approved change requests
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Rejected change requests
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope: Implemented change requests
     */
    public function scopeImplemented(Builder $query): Builder
    {
        return $query->where('status', 'implemented');
    }

    /**
     * Scope: High priority change requests
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->where('priority', 'high');
    }

    /**
     * Scope: Overdue change requests
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now())
                    ->whereIn('status', ['pending', 'approved']);
    }

    /**
     * Scope: Change requests assigned to user
     */
    public function scopeAssignedTo(Builder $query, string $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Check if change request is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date && 
               $this->due_date->isPast() && 
               in_array($this->status, ['pending', 'approved']);
    }

    /**
     * Check if change request is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if change request is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if change request is implemented
     */
    public function isImplemented(): bool
    {
        return $this->status === 'implemented';
    }

    /**
     * Get cost variance
     */
    public function getCostVariance(): float
    {
        return $this->actual_cost - $this->estimated_cost;
    }

    /**
     * Get cost variance percentage
     */
    public function getCostVariancePercentage(): float
    {
        if ($this->estimated_cost === 0) {
            return 0;
        }

        return round(($this->getCostVariance() / $this->estimated_cost) * 100, 2);
    }

    /**
     * Get time variance
     */
    public function getTimeVariance(): int
    {
        return $this->actual_days - $this->estimated_days;
    }

    /**
     * Get time variance percentage
     */
    public function getTimeVariancePercentage(): float
    {
        if ($this->estimated_days === 0) {
            return 0;
        }

        return round(($this->getTimeVariance() / $this->estimated_days) * 100, 2);
    }

    /**
     * Get remaining days
     */
    public function getRemainingDays(): int
    {
        if (!$this->due_date) {
            return 0;
        }

        return max(0, now()->diffInDays($this->due_date, false));
    }

    /**
     * Approve change request
     */
    public function approve(User $approver, string $notes = null): bool
    {
        return $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }

    /**
     * Reject change request
     */
    public function reject(User $rejector, string $reason): bool
    {
        return $this->update([
            'status' => 'rejected',
            'rejected_by' => $rejector->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Mark as implemented
     */
    public function markAsImplemented(string $notes = null): bool
    {
        return $this->update([
            'status' => 'implemented',
            'implemented_at' => now(),
            'implementation_notes' => $notes,
        ]);
    }

    /**
     * Assign change request to user
     */
    public function assignTo(User $user): bool
    {
        return $this->update([
            'assigned_to' => $user->id,
        ]);
    }

    /**
     * Get status color class
     */
    public function getStatusColorClass(): string
    {
        return match($this->status) {
            'pending' => 'zena-badge-warning',
            'approved' => 'zena-badge-success',
            'rejected' => 'zena-badge-danger',
            'implemented' => 'zena-badge-info',
            default => 'zena-badge-neutral',
        };
    }

    /**
     * Get priority color class
     */
    public function getPriorityColorClass(): string
    {
        return match($this->priority) {
            'high' => 'zena-badge-danger',
            'medium' => 'zena-badge-warning',
            'low' => 'zena-badge-success',
            default => 'zena-badge-neutral',
        };
    }

    /**
     * Get impact level color class
     */
    public function getImpactLevelColorClass(): string
    {
        return match($this->impact_level) {
            'high' => 'zena-badge-danger',
            'medium' => 'zena-badge-warning',
            'low' => 'zena-badge-success',
            default => 'zena-badge-neutral',
        };
    }
}
