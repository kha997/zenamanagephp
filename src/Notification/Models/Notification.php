<?php declare(strict_types=1);

namespace Src\Notification\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Models\User;

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
    use HasFactory, HasUlids;

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
        'project_id',
        'type',
        'priority',
        'title',
        'body',
        'link_url',
        'channel',
        'read_at',
        'data',
        'metadata',
        'event_key',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Notification $notification) {
            if (empty($notification->tenant_id) && $notification->user_id) {
                $user = User::find($notification->user_id);
                if ($user) {
                    $notification->tenant_id = $user->tenant_id;
                }
            }
        });
    }

    /**
     * Quan hệ với User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return $query->orderByRaw("FIELD(priority, 'critical', 'normal', 'low')");
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
