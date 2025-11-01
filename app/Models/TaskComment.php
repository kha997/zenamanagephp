<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model TaskComment - Quản lý bình luận task
 * 
 * @property string $id ULID của comment (primary key)
 * @property string $tenant_id ID tenant
 * @property string $task_id ID task
 * @property string $user_id ID user tạo comment
 * @property string $content Nội dung comment
 * @property string $type Loại comment
 * @property array|null $metadata Metadata bổ sung
 * @property string|null $parent_id ID comment cha (cho threaded comments)
 * @property bool $is_internal Comment nội bộ
 * @property bool $is_pinned Comment được ghim
 */
class TaskComment extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;
    
    protected $table = 'task_comments';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'task_id',
        'user_id',
        'content',
        'type',
        'metadata',
        'parent_id',
        'is_internal',
        'is_pinned'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_internal' => 'boolean',
        'is_pinned' => 'boolean'
    ];

    protected $attributes = [
        'type' => 'comment',
        'is_internal' => false,
        'is_pinned' => false
    ];

    /**
     * Các loại comment hợp lệ
     */
    public const TYPE_COMMENT = 'comment';
    public const TYPE_STATUS_CHANGE = 'status_change';
    public const TYPE_ASSIGNMENT = 'assignment';
    public const TYPE_MENTION = 'mention';
    public const TYPE_SYSTEM = 'system';

    public const VALID_TYPES = [
        self::TYPE_COMMENT,
        self::TYPE_STATUS_CHANGE,
        self::TYPE_ASSIGNMENT,
        self::TYPE_MENTION,
        self::TYPE_SYSTEM,
    ];

    /**
     * Relationship: Comment thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Comment thuộc về task
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Relationship: Comment được tạo bởi user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Comment cha (cho threaded comments)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'parent_id');
    }

    /**
     * Relationship: Các comment con (replies)
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'parent_id')->orderBy('created_at');
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
    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Lọc theo loại comment
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Chỉ lấy comment gốc (không phải reply)
     */
    public function scopeRootComments($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: Chỉ lấy comment công khai
     */
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    /**
     * Scope: Chỉ lấy comment nội bộ
     */
    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    /**
     * Scope: Lấy comment được ghim
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope: Sắp xếp theo thứ tự hiển thị
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('is_pinned', 'desc')
                    ->orderBy('created_at', 'asc');
    }

    /**
     * Kiểm tra xem comment có phải là reply không
     */
    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Kiểm tra xem comment có replies không
     */
    public function hasReplies(): bool
    {
        return $this->replies()->exists();
    }

    /**
     * Lấy số lượng replies
     */
    public function getRepliesCountAttribute(): int
    {
        return $this->replies()->count();
    }

    /**
     * Tạo comment tự động cho status change
     */
    public static function createStatusChangeComment(string $taskId, string $userId, string $oldStatus, string $newStatus, ?string $tenantId = null): self
    {
        return self::create([
            'tenant_id' => $tenantId,
            'task_id' => $taskId,
            'user_id' => $userId,
            'content' => "Status changed from {$oldStatus} to {$newStatus}",
            'type' => self::TYPE_STATUS_CHANGE,
            'metadata' => [
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ],
            'is_internal' => true
        ]);
    }

    /**
     * Tạo comment tự động cho assignment
     */
    public static function createAssignmentComment(string $taskId, string $userId, ?string $oldAssigneeId, string $newAssigneeId, ?string $tenantId = null): self
    {
        $oldAssignee = $oldAssigneeId ? User::find($oldAssigneeId) : null;
        $newAssignee = User::find($newAssigneeId);
        
        $content = $oldAssignee 
            ? "Reassigned from {$oldAssignee->name} to {$newAssignee->name}"
            : "Assigned to {$newAssignee->name}";

        return self::create([
            'tenant_id' => $tenantId,
            'task_id' => $taskId,
            'user_id' => $userId,
            'content' => $content,
            'type' => self::TYPE_ASSIGNMENT,
            'metadata' => [
                'old_assignee_id' => $oldAssigneeId,
                'new_assignee_id' => $newAssigneeId
            ],
            'is_internal' => true
        ]);
    }

    /**
     * Tạo comment tự động cho mention
     */
    public static function createMentionComment(string $taskId, string $userId, string $mentionedUserId, string $content, ?string $tenantId = null): self
    {
        return self::create([
            'tenant_id' => $tenantId,
            'task_id' => $taskId,
            'user_id' => $userId,
            'content' => $content,
            'type' => self::TYPE_MENTION,
            'metadata' => [
                'mentioned_user_id' => $mentionedUserId
            ],
            'is_internal' => false
        ]);
    }

    /**
     * Tạo factory instance mới cho model
     */
    protected static function newFactory()
    {
        return \Database\Factories\TaskCommentFactory::new();
    }
}