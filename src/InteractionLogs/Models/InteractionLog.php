<?php declare(strict_types=1);

namespace Src\InteractionLogs\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use App\Models\User;

/**
 * Model InteractionLog để quản lý các log tương tác
 * 
 * @property string $project_id
 * @property string|null $linked_task_id
 * @property string $type
 * @property string $description
 * @property string|null $tag_path
 * @property string $visibility
 * @property bool $client_approved
 * @property string $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class InteractionLog extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'interaction_logs';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Các loại interaction log
     */
    public const TYPE_CALL = 'call';
    public const TYPE_EMAIL = 'email';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_NOTE = 'note';
    public const TYPE_FEEDBACK = 'feedback';

    /**
     * Các mức độ hiển thị
     */
    public const VISIBILITY_INTERNAL = 'internal';
    public const VISIBILITY_CLIENT = 'client';

    /**
     * Danh sách các loại hợp lệ
     */
    public const VALID_TYPES = [
        self::TYPE_CALL,
        self::TYPE_EMAIL,
        self::TYPE_MEETING,
        self::TYPE_NOTE,
        self::TYPE_FEEDBACK,
    ];

    /**
     * Danh sách các mức độ hiển thị hợp lệ
     */
    public const VALID_VISIBILITIES = [
        self::VISIBILITY_INTERNAL,
        self::VISIBILITY_CLIENT,
    ];

    protected $fillable = [
        'tenant_id',
        'project_id',
        'linked_task_id',
        'type',
        'description',
        'tag_path',
        'visibility',
        'client_approved',
        'created_by',
    ];

    protected $casts = [
        'client_approved' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Quan hệ với Project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Quan hệ với Task (nếu có liên kết)
     */
    public function linkedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'linked_task_id');
    }

    /**
     * Quan hệ với User (người tạo)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope để lọc theo loại
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope để lọc theo mức độ hiển thị
     */
    public function scopeWithVisibility(Builder $query, string $visibility): Builder
    {
        return $query->where('visibility', $visibility);
    }

    /**
     * Scope để lấy các log hiển thị cho client (đã được approve)
     */
    public function scopeClientVisible(Builder $query): Builder
    {
        return $query->where('visibility', self::VISIBILITY_CLIENT)
                    ->where('client_approved', true);
    }

    /**
     * Scope để lọc theo dự án
     */
    public function scopeForProject(Builder $query, string $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope để lọc theo task
     */
    public function scopeForTask(Builder $query, string $taskId): Builder
    {
        return $query->where('linked_task_id', $taskId);
    }

    /**
     * Scope để lọc theo tag path
     */
    public function scopeWithTagPath(Builder $query, string $tagPath): Builder
    {
        return $query->where('tag_path', 'LIKE', "%{$tagPath}%");
    }

    /**
     * Kiểm tra xem log có hiển thị cho client không
     */
    public function isClientVisible(): bool
    {
        return $this->visibility === self::VISIBILITY_CLIENT && $this->client_approved;
    }

    /**
     * Kiểm tra xem log có phải là internal không
     */
    public function isInternal(): bool
    {
        return $this->visibility === self::VISIBILITY_INTERNAL;
    }

    /**
     * Approve log để hiển thị cho client
     */
    public function approveForClient(): bool
    {
        if ($this->visibility !== self::VISIBILITY_CLIENT) {
            return false;
        }

        $this->client_approved = true;
        return $this->save();
    }

    /**
     * Revoke approval cho client
     */
    public function revokeClientApproval(): bool
    {
        $this->client_approved = false;
        return $this->save();
    }

    /**
     * Lấy các tag từ tag_path
     */
    public function getTags(): array
    {
        if (empty($this->tag_path)) {
            return [];
        }

        return explode('/', $this->tag_path);
    }

    /**
     * Tạo factory instance mới cho model
     */
    protected static function newFactory()
    {
        return \Database\Factories\InteractionLogFactory::new();
    }
}
