<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserNotificationPreference - Round 255: Notification Preferences
 * 
 * Stores per-user, per-tenant notification preferences for in-app notifications.
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id Tenant ID
 * @property string $user_id User ID
 * @property string $type Notification type (e.g., 'task.assigned', 'co.approved')
 * @property bool $is_enabled Whether this notification type is enabled for the user
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UserNotificationPreference extends Model
{
    use HasUlids, HasFactory, BelongsToTenant;

    protected $table = 'user_notification_preferences';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_enabled' => true,
    ];

    /**
     * Relationship: Preference belongs to User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Preference belongs to Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
