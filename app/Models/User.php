<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasRoles;

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
    use HasUlids, HasFactory, HasApiTokens, Notifiable, HasRoles;

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
        'phone',
        'avatar',
        'preferences',
        'last_login_at',
        'is_active',
        'status',
        'role',
        'mfa_enabled',
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
     * Relationship: User có nhiều Z.E.N.A notifications
     */
    public function zenaNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Check if user has role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
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

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Check if user has permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($permission) {
            return $query->where('code', $permission);
        })->exists();
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($permissions) {
            return $query->whereIn('code', $permissions);
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
     * Relationship: User has many change requests
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class, 'created_by');
    }

    /**
     * Relationship: User has many assigned change requests
     */
    public function assignedChangeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class, 'assigned_to');
    }

    /**
     * Relationship: User has many projects as project manager
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'pm_id');
    }

    /**
     * Relationship: User has many support tickets
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'created_by');
    }

    /**
     * Relationship: User has many assigned support tickets
     */
    public function assignedSupportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    /**
     * Relationship: User has many calendar events
     */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'created_by');
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

    /**
     * Relationship: User has many system roles
     */
    public function systemRoles(): BelongsToMany
    {
        return $this->belongsToMany(ZenaRole::class, 'user_roles', 'user_id', 'role_id');
    }

    /**
     * Relationship: User has many role permissions through roles
     */
    public function rolePermissions(): BelongsToMany
    {
        return $this->belongsToMany(ZenaPermission::class, 'zena_role_permissions', 'role_id', 'permission_id')
            ->join('user_roles', 'user_roles.role_id', '=', 'zena_role_permissions.role_id')
            ->where('user_roles.user_id', $this->id);
    }

    /**
     * Relationship: User has many dashboard metrics
     */
    public function dashboardMetrics(): HasMany
    {
        return $this->hasMany(DashboardMetric::class, 'created_by');
    }

    /**
     * Relationship: User has many documents uploaded
     */
    public function documentsUploaded(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    /**
     * Relationship: User has many dashboards
     */
    public function dashboards(): HasMany
    {
        return $this->hasMany(Dashboard::class, 'user_id');
    }

    /**
     * Relationship: User has many widgets
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class, 'user_id');
    }

    /**
     * Relationship: User belongs to many tenants (multi-tenant membership)
     * Uses user_tenants pivot table with role and is_default columns
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'user_tenants', 'user_id', 'tenant_id')
            ->using(UserTenant::class)
            ->withPivot(['role', 'is_default', 'created_at', 'updated_at'])
            ->withTimestamps();
    }

    /**
     * Get membership tenants for user (from pivot table)
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getMembershipTenants(): \Illuminate\Support\Collection
    {
        return $this->tenants()->get();
    }

    /**
     * Get default tenant for user
     * 
     * Priority:
     * 1. Default tenant from pivot (is_default = true)
     * 2. Legacy tenant_id (if exists)
     * 3. First tenant from membership (fallback)
     * 4. E2E/testing fallback for super_admin
     * 5. null if no tenant at all
     * 
     * @return Tenant|null
     */
    public function defaultTenant(): ?Tenant
    {
        // 1) Default tenant from pivot (is_default = true)
        $defaultTenant = $this->tenants()
            ->wherePivot('is_default', true)
            ->first();
        
        if ($defaultTenant) {
            return $defaultTenant;
        }

        // 2) Legacy tenant_id (if exists and not already in pivot)
        if (isset($this->tenant_id) && $this->tenant_id) {
            $legacyTenant = Tenant::query()->whereKey($this->tenant_id)->first();
            if ($legacyTenant) {
                return $legacyTenant;
            }
        }

        // 3) First tenant from membership (fallback)
        $firstTenant = $this->tenants()->first();
        if ($firstTenant) {
            return $firstTenant;
        }

        // 4) E2E/local/testing fallback for super_admin to avoid blocking bootstrap
        if (app()->environment(['local', 'testing', 'e2e']) && ($this->role ?? null) === 'super_admin') {
            $fallbackTenant = Tenant::query()->orderBy('created_at')->first();
            if ($fallbackTenant) {
                return $fallbackTenant;
            }
        }

        return null;
    }

}