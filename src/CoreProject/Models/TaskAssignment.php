<?php declare(strict_types=1);

namespace Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Src\Foundation\Traits\HasTimestamps;
use Src\Compensation\Models\TaskCompensation;

/**
 * Model TaskAssignment - Quản lý phân công công việc
 * 
 * @property int $id ID của assignment (primary key)
 * @property string $task_id ID task (ULID)
 * @property string $user_id ID user được assign (ULID)
 * @property float $split_percent Phần trăm phân chia công việc (đã đổi tên từ split_percentage)
 * @property string|null $role Vai trò trong task
 */
class TaskAssignment extends Model
{
    use HasTimestamps;
    // OWN-2026-024 SPLIT-PERCENT: task_assignments.id is CHAR(26) with no default. Without ULID generation every v1 insert
    // failed on MySQL (1364 "Field 'id' doesn't have a default value"); SQLite silently stored a NULL primary key.
    use HasUlids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'task_assignments';
    
    protected $fillable = [
        'task_id',
        'user_id',
        'split_percent', // Đã đổi tên từ split_percentage
        'role',
        // OWN-2026-024 SPLIT-PERCENT: set by the controller from the task / actor, never from request input.
        'tenant_id',
        'assigned_at',
        'assigned_by',
    ];

    protected $casts = [
        'split_percent' => 'float',
        // OWN-2026-024: assigned_at is now written on every v1 path; the resource formats it as a date (was always NULL here).
        'assigned_at' => 'datetime',
    ];

    protected $attributes = [
        'split_percent' => 100.0
    ];

    /**
     * Relationship: Assignment thuộc về task
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Relationship: Assignment thuộc về user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Relationship: Assignment có thể có compensation (thông qua task)
     */
    public function taskCompensation(): HasOne
    {
        return $this->hasOne(TaskCompensation::class, 'task_id', 'task_id');
    }

    /**
     * Scope: Lọc theo task
     */
    public function scopeForTask($query, string $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    /**
     * Scope: Lọc theo user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Tính toán compensation value cho assignment này
     */
    public function calculateCompensationValue(): float
    {
        $compensation = $this->taskCompensation;
        if (!$compensation || !$compensation->contract) {
            return 0.0;
        }

        $taskCompensationValue = $compensation->calculateCurrentValue($compensation->contract);
        return $taskCompensationValue * ($this->split_percent / 100);
    }

    /**
     * Kiểm tra assignment có compensation không
     */
    public function hasCompensation(): bool
    {
        return $this->taskCompensation !== null;
    }
}