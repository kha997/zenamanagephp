<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Foundation\Traits\HasTimestamps;
use Src\Foundation\Traits\HasSoftDeletes;
use Src\Foundation\Traits\HasAuditLog;
use Src\RBAC\Traits\HasRBACContext;

/**
 * Model User - Quản lý người dùng với RBAC và Multi-tenancy
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id ID công ty (ULID)
 * @property string $name Tên người dùng
 * @property string $email Email
 * @property \Carbon\Carbon|null $email_verified_at Thời gian xác thực email
 * @property string $password Mật khẩu đã hash
 * @property bool $is_active Trạng thái hoạt động
 * @property array|null $profile_data Dữ liệu profile bổ sung
 * @property string|null $remember_token Token ghi nhớ
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at Soft delete timestamp
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasTimestamps, HasUlids, HasSoftDeletes, HasAuditLog, HasRBACContext;

    /**
     * Cấu hình ULID primary key
     */
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'is_active',
        'profile_data'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'profile_data' => 'array'
    ];

    protected $attributes = [
        'is_active' => true
    ];

    /**
     * Relationship: User thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: User có nhiều system roles
     */
    public function systemRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            \Src\RBAC\Models\Role::class,
            'user_roles_system',
            'user_id',
            'role_id'
        )->withTimestamps();
    }

    /**
     * Relationship: User có nhiều project roles
     */
    public function projectRoles(): HasMany
    {
        return $this->hasMany(\Src\RBAC\Models\UserRoleProject::class);
    }

    /**
     * Relationship: User có nhiều custom roles
     */
    public function customRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            \Src\RBAC\Models\Role::class,
            'user_roles_custom',
            'user_id',
            'role_id'
        )->withTimestamps();
    }

    /**
     * Relationship: User có nhiều task assignments
     */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(\Src\CoreProject\Models\TaskAssignment::class);
    }

    /**
     * Relationship: User có nhiều notifications
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(\Src\Notification\Models\Notification::class);
    }

    /**
     * Relationship: User có nhiều notification rules
     */
    public function notificationRules(): HasMany
    {
        return $this->hasMany(\Src\Notification\Models\NotificationRule::class);
    }

    /**
     * Kiểm tra user có active không
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->tenant->isActive();
    }

    /**
     * Lấy profile data theo key
     */
    public function getProfileData(string $key, $default = null)
    {
        return data_get($this->profile_data, $key, $default);
    }

    /**
     * Cập nhật profile data
     */
    public function updateProfileData(string $key, $value): void
    {
        $profileData = $this->profile_data ?? [];
        data_set($profileData, $key, $value);
        $this->update(['profile_data' => $profileData]);
    }

    /**
     * Scope: Chỉ lấy users active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->whereHas('tenant', function($q) {
                        $q->where('is_active', true);
                    });
    }

    /**
     * Scope: Lọc theo tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
