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

    protected $table = 'zena_roles';
    
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
        'description'
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
            'zena_role_permissions',
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
            'zena_user_roles',
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
}