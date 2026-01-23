<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasRoles;
use App\Collections\UserCollection;

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
    use HasUlids, HasFactory, HasApiTokens, HasRoles, SoftDeletes;

    /**
     * Cấu hình ULID primary key
     */
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'role',
        'name',
        'email',
        'password',
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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'id' => 'string',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'preferences' => 'array',
        'oidc_data' => 'array',
        'saml_data' => 'array',
    ];

    protected $attributes = [
        'is_active' => true
    ];

    /**
     * Relationship: User có nhiều Z.E.N.A roles
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->using(UserRole::class)
            ->withPivot('id')
            ->withTimestamps();
    }

    /**
     * Relationship: User thuộc về một tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: User quản lý nhiều projects
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'manager_id');
    }

    /**
     * Relationship: User có nhiều Z.E.N.A notifications
     */
    public function zenaNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Check if user has at least one of the given roles.
     *
     * @param string|string[] $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = (array)$roles;

        if (in_array($this->role, $roles, true)) {
            return true;
        }

        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Relationship: User belongs to many teams
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
                    ->withPivot(['role', 'joined_at', 'left_at'])
                    ->withTimestamps();
    }

    /**
     * Relationship: User has many task assignments
     */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Relationship: User has many assigned tasks
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * Relationship: User has many created tasks
     */
    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function newCollection(array $models = []): UserCollection
    {
        return new UserCollection($models);
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        if (!empty($this->role) && in_array($this->role, $roles, true)) {
            return true;
        }

        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Check if user has permission.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'super_admin' || $this->hasAnyRole(['super_admin'])) {
            return true;
        }

        return $this->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('name', $permission)
                  ->orWhere('code', $permission);
        })->exists();
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->role === 'super_admin' || $this->hasAnyRole(['super_admin'])) {
            return true;
        }

        return $this->roles()->whereHas('permissions', function ($query) use ($permissions) {
            $query->whereIn('name', $permissions)
                  ->orWhereIn('code', $permissions);
        })->exists();
    }

    /**
     * Kiểm tra user có active không
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Scope: Chỉ lấy users active
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
     * Check if user is admin (super_admin, admin, project_manager)
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'project_manager']);
    }

    /**
     * Check if user can manage invitations
     */
    public function canManageInvitations(): bool
    {
        return $this->isAdmin();
    }

}
