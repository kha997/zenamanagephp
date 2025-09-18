<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * CalendarEvent Model - Calendar events từ external calendars
 * 
 * @property string $id ULID primary key
 * @property string|null $project_id ID dự án
 * @property string|null $task_id ID task
 * @property string|null $milestone_id ID milestone
 * @property string $calendar_integration_id ID calendar integration
 * @property string $external_event_id External event ID
 * @property string $title Tiêu đề event
 * @property string|null $description Mô tả
 * @property \Carbon\Carbon $start_time Thời gian bắt đầu
 * @property \Carbon\Carbon $end_time Thời gian kết thúc
 * @property string|null $location Địa điểm
 * @property array|null $attendees Danh sách attendees
 * @property string $status Trạng thái
 * @property bool $all_day Sự kiện cả ngày
 * @property string|null $recurrence Quy tắc lặp lại
 * @property array|null $metadata Dữ liệu bổ sung
 * @property bool $is_synced Đã sync
 * @property \Carbon\Carbon|null $last_synced_at Last sync time
 */
class CalendarEvent extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'calendar_events';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'project_id',
        'task_id',
        'milestone_id',
        'calendar_integration_id',
        'external_event_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'location',
        'attendees',
        'status',
        'all_day',
        'recurrence',
        'metadata',
        'is_synced',
        'last_synced_at'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'attendees' => 'array',
        'all_day' => 'boolean',
        'metadata' => 'array',
        'is_synced' => 'boolean',
        'last_synced_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => 'confirmed',
        'all_day' => false,
        'is_synced' => false
    ];

    /**
     * Status constants
     */
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_TENTATIVE = 'tentative';
    public const STATUS_CANCELLED = 'cancelled';

    public const VALID_STATUSES = [
        self::STATUS_CONFIRMED,
        self::STATUS_TENTATIVE,
        self::STATUS_CANCELLED,
    ];

    /**
     * Relationships
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class);
    }

    public function calendarIntegration(): BelongsTo
    {
        return $this->belongsTo(CalendarIntegration::class);
    }

    /**
     * Scopes
     */
    public function scopeByProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByTask($query, string $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    public function scopeByMilestone($query, string $milestoneId)
    {
        return $query->where('milestone_id', $milestoneId);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>=', now());
    }

    public function scopeToday($query)
    {
        return $query->whereDate('start_time', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('start_time', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('start_time', [
            now()->startOfMonth(),
            now()->endOfMonth()
        ]);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeSynced($query)
    {
        return $query->where('is_synced', true);
    }

    /**
     * Check if event is happening now
     */
    public function isHappeningNow(): bool
    {
        $now = now();
        return $now->between($this->start_time, $this->end_time);
    }

    /**
     * Check if event is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->start_time->isFuture();
    }

    /**
     * Check if event is past
     */
    public function isPast(): bool
    {
        return $this->end_time->isPast();
    }

    /**
     * Get event duration in minutes
     */
    public function getDurationInMinutes(): int
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }

    /**
     * Get event duration in hours
     */
    public function getDurationInHours(): float
    {
        return $this->start_time->diffInHours($this->end_time);
    }

    /**
     * Check if event conflicts with another event
     */
    public function conflictsWith(CalendarEvent $otherEvent): bool
    {
        return $this->start_time->lt($otherEvent->end_time) &&
               $this->end_time->gt($otherEvent->start_time);
    }

    /**
     * Get conflicting events
     */
    public function getConflictingEvents(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('id', '!=', $this->id)
                  ->where('start_time', '<', $this->end_time)
                  ->where('end_time', '>', $this->start_time)
                  ->where('status', '!=', self::STATUS_CANCELLED)
                  ->get();
    }

    /**
     * Create event from project milestone
     */
    public static function createFromMilestone(ProjectMilestone $milestone, CalendarIntegration $integration): self
    {
        return self::create([
            'project_id' => $milestone->project_id,
            'milestone_id' => $milestone->id,
            'calendar_integration_id' => $integration->id,
            'external_event_id' => 'milestone_' . $milestone->id,
            'title' => "Milestone: {$milestone->name}",
            'description' => $milestone->description,
            'start_time' => $milestone->target_date ?? now(),
            'end_time' => $milestone->target_date ?? now()->addHour(),
            'all_day' => true,
            'metadata' => [
                'type' => 'milestone',
                'milestone_id' => $milestone->id,
                'project_id' => $milestone->project_id
            ]
        ]);
    }

    /**
     * Create event from task
     */
    public static function createFromTask(Task $task, CalendarIntegration $integration): self
    {
        return self::create([
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'calendar_integration_id' => $integration->id,
            'external_event_id' => 'task_' . $task->id,
            'title' => "Task: {$task->name}",
            'description' => $task->description,
            'start_time' => $task->due_date ?? now(),
            'end_time' => $task->due_date ?? now()->addHour(),
            'all_day' => false,
            'metadata' => [
                'type' => 'task',
                'task_id' => $task->id,
                'project_id' => $task->project_id
            ]
        ]);
    }

    /**
     * Get events for project timeline
     */
    public static function getProjectTimeline(string $projectId, Carbon $startDate, Carbon $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return self::byProject($projectId)
                  ->whereBetween('start_time', [$startDate, $endDate])
                  ->with(['calendarIntegration', 'task', 'milestone'])
                  ->orderBy('start_time')
                  ->get();
    }

    /**
     * Get user's calendar overview
     */
    public static function getUserCalendarOverview(string $userId, int $days = 30): array
    {
        $startDate = now();
        $endDate = now()->addDays($days);
        
        $events = self::whereHas('calendarIntegration', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->whereBetween('start_time', [$startDate, $endDate])
        ->with(['project', 'task', 'milestone', 'calendarIntegration'])
        ->orderBy('start_time')
        ->get();

        return [
            'total_events' => $events->count(),
            'project_events' => $events->whereNotNull('project_id')->count(),
            'task_events' => $events->whereNotNull('task_id')->count(),
            'milestone_events' => $events->whereNotNull('milestone_id')->count(),
            'upcoming_events' => $events->where('start_time', '>', now())->take(10),
            'today_events' => $events->where('start_time', '>=', today())->where('start_time', '<', today()->addDay()),
            'this_week_events' => $events->where('start_time', '>=', now()->startOfWeek())->where('start_time', '<', now()->endOfWeek()),
            'conflicts' => $events->filter(function ($event) {
                return $event->getConflictingEvents()->isNotEmpty();
            })
        ];
    }

    /**
     * Accessors
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_CONFIRMED => 'green',
            self::STATUS_TENTATIVE => 'yellow',
            self::STATUS_CANCELLED => 'red',
            default => 'gray'
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_TENTATIVE => 'Tentative',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown'
        };
    }

    public function getDurationAttribute(): string
    {
        if ($this->all_day) {
            return 'All day';
        }
        
        $duration = $this->getDurationInMinutes();
        
        if ($duration < 60) {
            return "{$duration} minutes";
        }
        
        $hours = floor($duration / 60);
        $minutes = $duration % 60;
        
        if ($minutes === 0) {
            return "{$hours} hour" . ($hours > 1 ? 's' : '');
        }
        
        return "{$hours}h {$minutes}m";
    }

    public function getTypeAttribute(): string
    {
        if ($this->milestone_id) {
            return 'milestone';
        }
        
        if ($this->task_id) {
            return 'task';
        }
        
        if ($this->project_id) {
            return 'project';
        }
        
        return 'external';
    }
}