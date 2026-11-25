<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasRoles;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Model User - Quản lý người dùng với RBAC và Multi-tenancy.
 *
 * @property string $id ULID primary key
 * @property string $tenant_id ID công ty (ULID)
 * @property string $name Tên người dùng
 * @property string $email Email
 * @property \Carbon\Carbon|null $email_verified_at Thời gian xác thực email
 * @property string $password Mật khẩu đã hash
 * @property \Carbon\Carbon|null $password_updated_at Thời gian đổi mật khẩu gần nhất
 * @property bool $is_active Trạng thái hoạt động
 * @property array|null $profile_data Dữ liệu profile bổ sung
 * @property string|null $remember_token Token ghi nhớ
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at Soft delete timestamp
 */
class User extends Authenticatable
{
    use HasUlids;
    use HasFactory;
    use HasApiTokens;
    use SoftDeletes;
    use HasRoles;
    use Notifiable;

    /**
     * Cấu hình ULID primary key.
     */
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'status',
        'name',
        'email',
        'password',
        'password_updated_at',
        'phone',
        'avatar',
        'preferences',
        'last_login_at',
        'last_login_ip',
        'is_active',
        'oidc_provider',
        'oidc_subject_id',
        'oidc_data',
        'saml_provider',
        'saml_name_id',
        'saml_data',
        'first_name',
        'last_name',
        'department',
        'job_title',
        'manager',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password_updated_at' => 'datetime',
        'is_active' => 'boolean',
        'preferences' => 'array',
        'oidc_data' => 'array',
        'saml_data' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Relationship: User có nhiều Z.E.N.A roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->using(UserRole::class)
            ->withTimestamps();
    }

    /**
     * Relationship: User có nhiều system roles (RBAC).
     */
    public function systemRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'system_user_roles', 'user_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * Relationship: User thuộc về một tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: User có nhiều Z.E.N.A notifications.
     */
    public function zenaNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Check if user has role.
     */
    public function hasRole(string|array $role): bool
    {
        if (is_array($role)) {
            return $this->hasAnyRole($role);
        }

        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Relationship: User belongs to many teams.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
                    ->withPivot(['role', 'joined_at', 'left_at'])
                    ->withTimestamps();
    }

    /**
     * Relationship: User has many task assignments.
     */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Relationship: User has many assigned tasks.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * Relationship: User has many created tasks.
     */
    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    /**
     * Relationship: User manages many projects.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'pm_id');
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        if ($this->role && in_array($this->role, $roles, true)) {
            return true;
        }

        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Check if user has permission.
     */
    public function hasPermission(string $permission): bool
    {
        // OWN-2026-024 H2b (D6): platform authority additionally needs an ACTIVE server-provisioned operator ledger row; a role
        // grant alone (or a role name) never establishes it.
        if (\App\Support\PlatformPermissions::isPlatformCode($permission) && !\App\Support\Rbac\PlatformOperators::isActive((string) $this->id)) {
            return false;
        }

        return $this->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('name', $permission);
            self::excludeRevokedGrants($query);
        })->exists();
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        $nonPlatform = array_values(array_filter($permissions, static fn ($p): bool => !\App\Support\PlatformPermissions::isPlatformCode(is_string($p) ? $p : null)));
        if ($nonPlatform !== $permissions && \App\Support\Rbac\PlatformOperators::isActive((string) $this->id)) {
            $nonPlatform = $permissions;
        }
        if ($nonPlatform === []) {
            return false;
        }

        return $this->roles()->whereHas('permissions', function ($query) use ($nonPlatform) {
            $query->whereIn('name', $nonPlatform);
            self::excludeRevokedGrants($query);
        })->exists();
    }

    /**
     * OWN-2026-024 H4: every permission name this user is CURRENTLY authorized for, with exactly hasPermission()'s rules —
     * canonical grants through user_roles, durable revocations excluded, platform.* codes only for an active Platform
     * Operator. For bulk consumers (navigation visibility) that must not re-implement those rules.
     *
     * @return list<string>
     */
    public function grantedPermissionNames(): array
    {
        $query = \Illuminate\Support\Facades\DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', (string) $this->id)
            ->whereNotNull('permissions.name');
        self::excludeRevokedGrants($query);

        $names = $query->distinct()->pluck('permissions.name')->map(static fn ($n): string => (string) $n)->all();
        if (!\App\Support\Rbac\PlatformOperators::isActive((string) $this->id)) {
            $names = array_filter($names, static fn (string $n): bool => !\App\Support\PlatformPermissions::isPlatformCode($n));
        }
        sort($names);

        return $names;
    }

    /**
     * OWN-2026-024 H2b (D4): authorization-time enforcement of durable revocations. Inside roles()->whereHas('permissions'), the
     * subquery joins role_permissions; a (role, permission) pair with an ACTIVE revocation never authorizes, even if a pivot row
     * was re-added by a path that bypassed the seed filter.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder $query
     */
    private static function excludeRevokedGrants($query): void
    {
        if (!\App\Support\Rbac\RbacSchema::hasRevocations()) {
            return; // pre-migration: no revocation can exist yet (the table is created by the H2b migration)
        }
        $query->whereNotExists(function ($sub): void {
            $sub->selectRaw('1')
                ->from('role_permission_revocations as rpr')
                ->whereColumn('rpr.role_id', 'role_permissions.role_id')
                ->whereColumn('rpr.permission_id', 'role_permissions.permission_id')
                ->whereNull('rpr.superseded_at');
        });
    }

    /**
     * Kiểm tra user có active không.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Scope: Chỉ lấy users active.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the organization for the user.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the invitation that created this user.
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * Get invitations sent by this user.
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    /**
     * Check if user is admin (super_admin, admin, project_manager).
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'project_manager']);
    }

    /**
     * Check if user can manage invitations.
     */
    public function canManageInvitations(): bool
    {
        return $this->isAdmin();
    }

    public function notificationRules(): HasMany
    {
        return $this->hasMany(NotificationRule::class);
    }
}
