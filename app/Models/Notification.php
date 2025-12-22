<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Notification - Round 251: Notifications Center Phase 1
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id Tenant ID
 * @property string $user_id User ID who receives the notification
 * @property string|null $module Module: tasks / documents / cost / rbac / system
 * @property string $type Type: e.g., task.assigned / co.needs_approval
 * @property string $title Notification title
 * @property string|null $message Notification message/body
 * @property string|null $entity_type Entity type: "task", "change_order", etc.
 * @property string|null $entity_id Entity ID (ULID)
 * @property bool $is_read Whether notification is read
 * @property array|null $metadata Additional metadata (JSON)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Notification extends Model
{
    use HasUlids, HasFactory, BelongsToTenant;

    protected $table = 'notifications';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Valid modules
     */
    public const MODULE_TASKS = 'tasks';
    public const MODULE_DOCUMENTS = 'documents';
    public const MODULE_COST = 'cost';
    public const MODULE_RBAC = 'rbac';
    public const MODULE_SYSTEM = 'system';

    public const VALID_MODULES = [
        self::MODULE_TASKS,
        self::MODULE_DOCUMENTS,
        self::MODULE_COST,
        self::MODULE_RBAC,
        self::MODULE_SYSTEM,
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'module',
        'type',
        'title',
        'message',
        'entity_type',
        'entity_id',
        'is_read',
        'metadata',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'is_read' => false,
    ];

    /**
     * Relationship: Notification belongs to User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Notification belongs to Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter by module
     */
    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    /**
     * Scope: Filter unread notifications
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope: Filter read notifications
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope: Order by created_at DESC (newest first)
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope: Search by title or message
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('message', 'like', "%{$search}%");
        });
    }

    /**
     * Check if notification is read
     */
    public function isRead(): bool
    {
        return $this->is_read === true;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): bool
    {
        if ($this->isRead()) {
            return true;
        }

        $this->is_read = true;
        return $this->save();
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(): bool
    {
        $this->is_read = false;
        return $this->save();
    }

    /**
     * Get unread count for user
     */
    public static function getUnreadCount(string $userId, ?string $tenantId = null): int
    {
        $query = static::forUser($userId)->unread();
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        return $query->count();
    }

    /**
     * Mark all notifications as read for user
     */
    public static function markAllAsReadForUser(string $userId, ?string $tenantId = null): int
    {
        $query = static::forUser($userId)->unread();
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        return $query->update(['is_read' => true]);
    }
}