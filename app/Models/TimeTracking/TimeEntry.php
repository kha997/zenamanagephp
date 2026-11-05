<?php

declare(strict_types=1);

namespace App\Models\TimeTracking;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GAP-112 — one block of a person's working time: on a task (and so its project) or on a fixed non-project category
 * (C-1). Timer entries are running until stopped; manual and stopped entries wait for review (R-1).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string|null $project_id
 * @property string|null $task_id
 * @property string|null $category
 * @property \Illuminate\Support\Carbon $work_date
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property int $minutes
 * @property string $source
 * @property bool $auto_stopped
 * @property string|null $note
 * @property string $status
 * @property string|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property-read Task|null $task
 * @property-read Project|null $project
 * @property-read User $user
 */
class TimeEntry extends Model
{
    use HasUlids;
    use TenantScope;

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_RUNNING => 'Đang chạy',
        self::STATUS_SUBMITTED => 'Chờ duyệt',
        self::STATUS_APPROVED => 'Đã duyệt',
        self::STATUS_REJECTED => 'Bị từ chối',
    ];

    public const SOURCE_TIMER = 'timer';
    public const SOURCE_MANUAL = 'manual';

    public const CATEGORY_MEETING = 'meeting';
    public const CATEGORY_TRAINING = 'training';
    public const CATEGORY_ADMIN = 'admin';
    public const CATEGORY_SALES = 'sales';
    public const CATEGORY_IDLE = 'idle';
    public const CATEGORY_OTHER = 'other';

    /** C-1: fixed non-project categories. */
    public const CATEGORIES = [
        self::CATEGORY_MEETING => 'Họp nội bộ',
        self::CATEGORY_TRAINING => 'Đào tạo',
        self::CATEGORY_ADMIN => 'Hành chính',
        self::CATEGORY_SALES => 'Kinh doanh / báo giá',
        self::CATEGORY_IDLE => 'Chờ việc',
        self::CATEGORY_OTHER => 'Khác',
    ];

    protected $table = 'time_entries';

    protected $fillable = [
        'tenant_id', 'user_id', 'project_id', 'task_id', 'category', 'work_date', 'started_at', 'ended_at', 'minutes',
        'source', 'auto_stopped', 'note', 'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'work_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'minutes' => 'integer',
        'auto_stopped' => 'boolean',
    ];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function label(): string
    {
        if ($this->task_id !== null) {
            return (string) (data_get($this, 'task.name') ?? data_get($this, 'task.title') ?? '—');
        }

        return self::CATEGORIES[(string) $this->category] ?? (string) $this->category;
    }
}
