<?php declare(strict_types=1);

namespace Src\RBAC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Src\Foundation\Traits\HasTimestamps;

/**
 * Model Role - Quản lý vai trò trong hệ thống RBAC
 * 
 * @property string $id
 * @property string $name Tên vai trò
 * @property string $scope Phạm vi áp dụng (system, custom, project)
 * @property bool $allow_override Cho phép ghi đè quyền
 * @property string|null $description Mô tả vai trò
 */
class Role extends Model
{
    use HasFactory, HasUlids, HasTimestamps;

    protected $table = 'roles';

    /**
     * Kiểu dữ liệu của khóa chính
     */
    protected $keyType = 'string';

    /**
     * Tắt auto increment cho khóa chính
     */
    public $incrementing = false;

    protected $fillable = [
        'name',
        'scope',
        'allow_override',
        'description',
        'tenant_id',
        'is_active',
    ];

    protected $casts = [
        'allow_override' => 'boolean',
    ];

    /**
     * Các scope hợp lệ cho role
     */
    public const SCOPE_SYSTEM = 'system';
    public const SCOPE_CUSTOM = 'custom';
    public const SCOPE_PROJECT = 'project';

    public const VALID_SCOPES = [
        self::SCOPE_SYSTEM,
        self::SCOPE_CUSTOM,
        self::SCOPE_PROJECT,
    ];

    /**
     * Relationship: Role có nhiều permissions
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id',
            'id',
            'id'
        )->withPivot(['allow_override'])
          ->withTimestamps();
    }

    /**
     * Relationship: Role được assign cho nhiều users ở system level
     */
    public function systemUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'user_roles',
            'role_id',
            'user_id'
        )->withTimestamps();
    }

    /**
     * Relationship: Role có nhiều project assignments
     */
    public function projectAssignments(): HasMany
    {
        return $this->hasMany(UserRoleProject::class);
    }

    /**
     * Kiểm tra role có permission cụ thể không
     */
    public function hasPermission(string $permissionCode): bool
    {
        return $this->permissions()->where('code', $permissionCode)->exists();
    }

    /**
     * Kiểm tra role có thể override permission không
     */
    public function canOverridePermission(string $permissionCode): bool
    {
        $permission = $this->permissions()
            ->where('code', $permissionCode)
            ->first();
            
        return $permission && $permission->pivot->allow_override;
    }

    /**
     * Scope: Lọc theo scope
     */
    public function scopeByScope($query, string $scope)
    {
        return $query->where('scope', $scope);
    }

    /**
     * Scope: Chỉ lấy system roles
     */
    public function scopeSystemRoles($query)
    {
        return $query->where('scope', self::SCOPE_SYSTEM);
    }

    /**
     * Scope: Chỉ lấy project roles
     */
    public function scopeProjectRoles($query)
    {
        return $query->where('scope', self::SCOPE_PROJECT);
    }

    /**
     * Scope: Grouped tenant-visibility predicate — GAP-042 §6.
     *
     * Produces the safely-composable SQL
     * `(tenant_id IS NULL OR tenant_id = ?) AND <any further chained filter>`.
     * Never chain a bare `whereNull('tenant_id')->orWhere('tenant_id', $tenantId)`
     * directly against this model — that ungrouped form silently leaks past any
     * filter chained after it (GAP-042 Gate 2 §6, Round 3 correction).
     */
    public function scopeTenantVisible($query, ?string $tenantId)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id');
            if ($tenantId !== null) {
                $q->orWhere('tenant_id', $tenantId);
            }
        });
    }

    /**
     * True when this role is global/system-owned (tenant_id IS NULL) — GAP-042 §6's
     * global-role read-only policy: global roles are readable everywhere but may only
     * be mutated (update/destroy/syncPermissions/CSV import) when this returns false.
     */
    public function isGlobal(): bool
    {
        return $this->tenant_id === null;
    }
}