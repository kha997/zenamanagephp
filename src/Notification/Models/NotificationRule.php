<?php declare(strict_types=1);

namespace Src\Notification\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\CoreProject\Models\Project;
use Src\Notification\Models\Notification;

/**
 * Model NotificationRule để quản lý quy tắc thông báo
 * 
 * @property string $id
 * @property string $user_id
 * @property string $tenant_id
 * @property string|null $project_id
 * @property string $event_key
 * @property string $min_priority
 * @property array $channels
 * @property bool $is_enabled
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class NotificationRule extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'notification_rules';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Các event key có thể có
     */
    public const EVENT_TASK_ASSIGNED = 'task.assigned';
    public const EVENT_TASK_COMPLETED = 'task.completed';
    public const EVENT_TASK_OVERDUE = 'task.overdue';
    public const EVENT_PROJECT_MILESTONE = 'project.milestone';
    public const EVENT_CHANGE_REQUEST_SUBMITTED = 'change_request.submitted';
    public const EVENT_CHANGE_REQUEST_APPROVED = 'change_request.approved';
    public const EVENT_CHANGE_REQUEST_REJECTED = 'change_request.rejected';
    public const EVENT_DOCUMENT_UPLOADED = 'document.uploaded';
    public const EVENT_INTERACTION_LOG_CREATED = 'interaction_log.created';

    /**
     * Danh sách các event key hợp lệ
     */
    public const VALID_EVENT_KEYS = [
        self::EVENT_TASK_ASSIGNED,
        self::EVENT_TASK_COMPLETED,
        self::EVENT_TASK_OVERDUE,
        self::EVENT_PROJECT_MILESTONE,
        self::EVENT_CHANGE_REQUEST_SUBMITTED,
        self::EVENT_CHANGE_REQUEST_APPROVED,
        self::EVENT_CHANGE_REQUEST_REJECTED,
        self::EVENT_DOCUMENT_UPLOADED,
        self::EVENT_INTERACTION_LOG_CREATED,
    ];

    protected $fillable = [
        'user_id',
        'tenant_id',
        'project_id',
        'event_key',
        'min_priority',
        'channels',
        'is_enabled',
    ];

    protected $casts = [
        'channels' => 'array',
        'is_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Quan hệ với User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Quan hệ với Project (nullable)
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope để lọc theo user
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope để lọc theo project
     */
    public function scopeForProject(Builder $query, string $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope để lọc theo event key
     */
    public function scopeForEvent(Builder $query, string $eventKey): Builder
    {
        return $query->where('event_key', $eventKey);
    }

    /**
     * Scope để lấy các rule đang enabled
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope để lấy các rule global (không specific cho project)
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('project_id');
    }

    /**
     * Scope để lấy các rule specific cho project
     */
    public function scopeProjectSpecific(Builder $query): Builder
    {
        return $query->whereNotNull('project_id');
    }

    /**
     * Kiểm tra xem rule có enabled không
     */
    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    /**
     * Kiểm tra xem rule có phải global không
     */
    public function isGlobal(): bool
    {
        return is_null($this->project_id);
    }

    /**
     * Kiểm tra xem priority có thỏa mãn rule không
     */
    public function matchesPriority(string $priority): bool
    {
        $priorityLevels = [
            Notification::PRIORITY_LOW => 1,
            Notification::PRIORITY_NORMAL => 2,
            Notification::PRIORITY_CRITICAL => 3,
        ];

        $minLevel = $priorityLevels[$this->min_priority] ?? 1;
        $currentLevel = $priorityLevels[$priority] ?? 1;

        return $currentLevel >= $minLevel;
    }

    /**
     * Kiểm tra xem channel có được enable không
     */
    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels ?? []);
    }

    /**
     * Enable rule
     */
    public function enable(): bool
    {
        $this->is_enabled = true;
        return $this->save();
    }

    /**
     * Disable rule
     */
    public function disable(): bool
    {
        $this->is_enabled = false;
        return $this->save();
    }

    /**
     * Split the event key into its segments
     */
    public function getEventKeyParts(): array
    {
        if (empty($this->event_key)) {
            return [];
        }

        return explode('.', $this->event_key);
    }

    /**
     * Get a human-friendly label for the minimum priority
     */
    public function getMinPriorityLabel(): string
    {
        return ucfirst($this->min_priority ?? Notification::PRIORITY_NORMAL);
    }

    /**
     * Map each channel to a friendly label
     */
    public function getChannelsLabels(): array
    {
        $labels = [
            Notification::CHANNEL_INAPP => 'In-App',
            Notification::CHANNEL_EMAIL => 'Email',
            Notification::CHANNEL_WEBHOOK => 'Webhook',
        ];

        $channels = $this->channels ?? [];
        if (!is_array($channels)) {
            return [];
        }

        return array_map(fn ($channel) => $labels[$channel] ?? ucfirst($channel), $channels);
    }

    /**
     * Summarize the conditions for display
     */
    public function getConditionsSummary(): array
    {
        $conditions = $this->conditions;

        if (empty($conditions) || !is_array($conditions)) {
            return [];
        }

        $summary = [];
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '';
            $value = $condition['value'] ?? '';
            $summary[] = trim("{$field} {$operator} {$value}");
        }

        return $summary;
    }

    /**
     * Describe the scope of the rule
     */
    public function getScope(): string
    {
        return $this->isGlobal() ? 'global' : 'project';
    }

    /**
     * Check if rule targets a specific project
     */
    public function isProjectSpecific(): bool
    {
        return !is_null($this->project_id);
    }

    /**
     * Build a short description of the rule
     */
    public function getRuleDescription(): string
    {
        $parts = $this->getEventKeyParts();

        if (empty($parts)) {
            return 'Notification rule';
        }

        return 'Notify on ' . implode(' > ', $parts);
    }

    /**
     * Lấy các rule áp dụng cho event và user cụ thể
     */
    public static function getApplicableRules(string $userId, string $eventKey, ?string $projectId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::forUser($userId)
                      ->forEvent($eventKey)
                      ->enabled();

        // Lấy cả global rules và project-specific rules
        if ($projectId) {
            $query->where(function ($q) use ($projectId) {
                $q->whereNull('project_id')
                  ->orWhere('project_id', $projectId);
            });
        } else {
            $query->global();
        }

        return $query->get();
    }

    /**
     * Tạo rule mặc định cho user mới
     */
    public static function createDefaultRulesForUser(string $userId): void
    {
        $defaultRules = [
            [
                'event_key' => self::EVENT_TASK_ASSIGNED,
                'min_priority' => Notification::PRIORITY_NORMAL,
                'channels' => [Notification::CHANNEL_INAPP, Notification::CHANNEL_EMAIL],
            ],
            [
                'event_key' => self::EVENT_TASK_OVERDUE,
                'min_priority' => Notification::PRIORITY_CRITICAL,
                'channels' => [Notification::CHANNEL_INAPP, Notification::CHANNEL_EMAIL],
            ],
            [
                'event_key' => self::EVENT_CHANGE_REQUEST_SUBMITTED,
                'min_priority' => Notification::PRIORITY_NORMAL,
                'channels' => [Notification::CHANNEL_INAPP],
            ],
        ];

        foreach ($defaultRules as $rule) {
            static::create([
                'user_id' => $userId,
                'project_id' => null, // Global rule
                'event_key' => $rule['event_key'],
                'min_priority' => $rule['min_priority'],
                'channels' => $rule['channels'],
                'is_enabled' => true,
            ]);
        }
    }
}
