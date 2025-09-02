<?php declare(strict_types=1);

namespace Src\Compensation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Src\Foundation\Traits\HasTimestamps;
use Src\CoreProject\Models\Task;
use Carbon\Carbon;

/**
 * Model TaskCompensation - Quản lý compensation cho tasks
 * 
 * @property string $id ULID của compensation (primary key)
 * @property string $task_id ID task (ULID)
 * @property float $base_contract_value_percent % giá trị hợp đồng cơ bản
 * @property float $effective_contract_value_percent % giá trị hợp đồng hiệu lực
 * @property float $snapshot_contract_value Giá trị hợp đồng snapshot
 * @property string $status Trạng thái compensation (pending/locked)
 * @property string|null $contract_id ID hợp đồng áp dụng
 * @property \Carbon\Carbon|null $locked_at Thời điểm lock
 * @property string|null $locked_by Người lock
 * @property string|null $notes Ghi chú
 */
class TaskCompensation extends Model
{
    use HasUlids, HasTimestamps;

    protected $table = 'tasks_compensation';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'task_id',
        'base_contract_value_percent',
        'effective_contract_value_percent',
        'snapshot_contract_value',
        'status',
        'contract_id',
        'locked_at',
        'locked_by',
        'notes',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'base_contract_value_percent' => 'float',
        'effective_contract_value_percent' => 'float',
        'snapshot_contract_value' => 'float',
        'locked_at' => 'datetime'
    ];

    protected $attributes = [
        'base_contract_value_percent' => 0.0,
        'effective_contract_value_percent' => 0.0,
        'snapshot_contract_value' => 0.0,
        'status' => 'pending'
    ];

    /**
     * Relationship: Compensation thuộc về task
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Relationship: Compensation thuộc về contract
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Scope: Lọc theo task
     */
    public function scopeForTask($query, string $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    /**
     * Scope: Lọc theo status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Lấy compensation pending (chưa lock)
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Lấy compensation đã lock
     */
    public function scopeLocked($query)
    {
        return $query->where('status', 'locked');
    }

    /**
     * Scope: Lọc theo contract
     */
    public function scopeForContract($query, string $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    /**
     * Lock compensation với contract value hiện tại
     */
    public function lockWithContract(Contract $contract, string $lockedBy): bool
    {
        if ($this->status === 'locked') {
            return false; // Đã lock rồi
        }

        $this->update([
            'status' => 'locked',
            'contract_id' => $contract->id,
            'snapshot_contract_value' => $contract->total_value * ($this->effective_contract_value_percent / 100),
            'locked_at' => Carbon::now(),
            'locked_by' => $lockedBy
        ]);

        return true;
    }

    /**
     * Cập nhật effective percent từ base percent (có thể áp dụng policy)
     */
    public function updateEffectivePercent(?float $customPercent = null): void
    {
        // Nếu có custom percent thì dùng, không thì dùng base percent
        $effectivePercent = $customPercent ?? $this->base_contract_value_percent;
        
        $this->update([
            'effective_contract_value_percent' => $effectivePercent
        ]);
    }

    /**
     * Tính toán compensation value dựa trên contract hiện tại
     */
    public function calculateCurrentValue(Contract $contract): float
    {
        return $contract->total_value * ($this->effective_contract_value_percent / 100);
    }

    /**
     * Kiểm tra xem compensation có thể được cập nhật không
     */
    public function canBeUpdated(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Kiểm tra xem compensation có thể được lock không
     */
    public function canBeLocked(): bool
    {
        return $this->status === 'pending';
    }
}