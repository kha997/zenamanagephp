<?php declare(strict_types=1);

namespace App\Models;

use App\Models\User;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Notification để quản lý thông báo
 * 
 * @property string $user_id
 * @property string $priority
 * @property string $title
 * @property string $body
 * @property string|null $link_url
 * @property string $channel
 * @property \Carbon\Carbon|null $read_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Notification extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'notifications';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Các mức độ ưu tiên
     */
    public const PRIORITY_CRITICAL = 'critical';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_LOW = 'low';

    /**
     * Các kênh thông báo
     */
    public const CHANNEL_INAPP = 'inapp';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WEBHOOK = 'webhook';

    /**
     * Danh sách các mức độ ưu tiên hợp lệ
     */
    public const VALID_PRIORITIES = [
        self::PRIORITY_CRITICAL,
        self::PRIORITY_NORMAL,
        self::PRIORITY_LOW,
    ];

    /**
     * Danh sách các kênh hợp lệ
     */
    public const VALID_CHANNELS = [
        self::CHANNEL_INAPP,
        self::CHANNEL_EMAIL,
        self::CHANNEL_WEBHOOK,
    ];

    protected $fillable = [
        'user_id',
        'tenant_id',
        'type',
        'priority',
        'title',
        'body',
        'status',
        'link_url',
        'channel',
        'read_at',
        'data',
        'metadata',
        'event_key',
        'project_id',
        'expires_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'data' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
    ];

    protected $appends = [
        'message',
        'is_expired',
    ];

    public function getMessageAttribute(): ?string
    {
        return $this->attributes['body'] ?? null;
    }

    public function setMessageAttribute(?string $value): void
    {
        $this->attributes['body'] = $value;
    }

    public function getIsExpiredAttribute(): bool
    {
        if (! isset($this->attributes['expires_at']) || $this->attributes['expires_at'] === null) {
            return false;
        }

        return $this->getAttribute('expires_at')->isPast();
    }

    /**
     * Quan hệ với User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Quan hệ với Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Quan hệ với Project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Scope để lọc theo user
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope để lọc theo mức độ ưu tiên
     */
    public function scopeWithPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope để lọc theo kênh
     */
    public function scopeWithChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope để lấy thông báo chưa đọc
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope để lấy thông báo đã đọc
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope để lấy thông báo critical
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('priority', self::PRIORITY_CRITICAL);
    }

    /**
     * Scope để sắp xếp theo mức độ ưu tiên
     */
    public function scopeOrderByPriority(Builder $query): Builder
    {
        // SQLite doesn't support FIELD() function, use CASE instead
        return $query->orderByRaw("CASE 
            WHEN priority = 'critical' THEN 1 
            WHEN priority = 'normal' THEN 2 
            WHEN priority = 'low' THEN 3 
            ELSE 4 
        END");
    }

    /**
     * Kiểm tra xem thông báo đã được đọc chưa
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Kiểm tra xem thông báo có phải critical không
     */
    public function isCritical(): bool
    {
        return $this->priority === self::PRIORITY_CRITICAL;
    }

    /**
     * Đánh dấu thông báo là đã đọc
     */
    public function markAsRead(): bool
    {
        if ($this->isRead()) {
            return true;
        }

        $this->read_at = now();
        return $this->save();
    }

    /**
     * Đánh dấu thông báo là chưa đọc
     */
    public function markAsUnread(): bool
    {
        $this->read_at = null;
        return $this->save();
    }

    /**
     * Tạo thông báo mới
     */
    public static function createNotification(array $data): self
    {
        return static::create([
            'user_id' => $data['user_id'],
            'priority' => $data['priority'] ?? self::PRIORITY_NORMAL,
            'title' => $data['title'],
            'body' => $data['body'],
            'link_url' => $data['link_url'] ?? null,
            'channel' => $data['channel'] ?? self::CHANNEL_INAPP,
        ]);
    }

    /**
     * Lấy số lượng thông báo chưa đọc của user
     */
    public static function getUnreadCount(string $userId): int
    {
        return static::forUser($userId)->unread()->count();
    }

    /**
     * Đánh dấu tất cả thông báo của user là đã đọc
     */
    public static function markAllAsReadForUser(string $userId): int
    {
        return static::forUser($userId)
                    ->unread()
                    ->update(['read_at' => now()]);
    }

    /**
     * Xóa các thông báo cũ (đã đọc và quá 30 ngày)
     */
    public static function cleanupOldNotifications(): int
    {
        return static::read()
                    ->where('read_at', '<', now()->subDays(30))
                    ->delete();
    }
}
