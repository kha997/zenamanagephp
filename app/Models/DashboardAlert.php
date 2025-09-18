<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Src\Foundation\Traits\HasTimestamps;

/**
 * Model DashboardAlert - Quản lý alerts và notifications
 * 
 * @property string $id
 * @property string $user_id ID của user
 * @property string|null $project_id ID của project
 * @property string $tenant_id ID của tenant
 * @property string $type Loại alert (info, warning, error, success)
 * @property string $category Danh mục alert
 * @property string $title Tiêu đề alert
 * @property string $message Nội dung alert
 * @property array|null $data Dữ liệu liên quan
 * @property bool $is_read Đã đọc chưa
 * @property \Carbon\Carbon|null $read_at Thời gian đọc
 * @property \Carbon\Carbon|null $expires_at Thời gian hết hạn
 */
class DashboardAlert extends Model
{
    use HasFactory, HasUlids, HasTimestamps;

    protected $table = 'dashboard_alerts';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'user_id',
        'project_id',
        'tenant_id',
        'type',
        'category',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
        'expires_at'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Các loại alert hợp lệ
     */
    public const TYPE_INFO = 'info';
    public const TYPE_WARNING = 'warning';
    public const TYPE_ERROR = 'error';
    public const TYPE_SUCCESS = 'success';

    public const VALID_TYPES = [
        self::TYPE_INFO,
        self::TYPE_WARNING,
        self::TYPE_ERROR,
        self::TYPE_SUCCESS,
    ];

    /**
     * Các danh mục alert hợp lệ
     */
    public const CATEGORY_TASK = 'task';
    public const CATEGORY_BUDGET = 'budget';
    public const CATEGORY_QUALITY = 'quality';
    public const CATEGORY_SAFETY = 'safety';
    public const CATEGORY_SCHEDULE = 'schedule';
    public const CATEGORY_SYSTEM = 'system';

    public const VALID_CATEGORIES = [
        self::CATEGORY_TASK,
        self::CATEGORY_BUDGET,
        self::CATEGORY_QUALITY,
        self::CATEGORY_SAFETY,
        self::CATEGORY_SCHEDULE,
        self::CATEGORY_SYSTEM,
    ];

    /**
     * Relationship: Alert thuộc về user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Alert thuộc về project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relationship: Alert thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope: Lọc theo user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Lọc theo project
     */
    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope: Lọc theo tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Lọc theo type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Lọc theo category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Chỉ lấy alert chưa đọc
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope: Chỉ lấy alert đã đọc
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope: Lọc alert chưa hết hạn
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope: Lọc alert đã hết hạn
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Đánh dấu alert đã đọc
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Đánh dấu alert chưa đọc
     */
    public function markAsUnread(): void
    {
        $this->update([
            'is_read' => false,
            'read_at' => null
        ]);
    }

    /**
     * Kiểm tra alert có hết hạn không
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Kiểm tra alert có hợp lệ không (chưa hết hạn)
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Tạo alert mới
     */
    public static function createAlert(
        string $userId,
        string $tenantId,
        string $type,
        string $category,
        string $title,
        string $message,
        ?string $projectId = null,
        array $data = [],
        ?\Carbon\Carbon $expiresAt = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'project_id' => $projectId,
            'tenant_id' => $tenantId,
            'type' => $type,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'expires_at' => $expiresAt
        ]);
    }
}
