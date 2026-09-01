<?php declare(strict_types=1);

namespace Src\RBAC\Services;

use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Src\RBAC\Models\UserRoleSystem;
use Src\RBAC\Models\UserRoleCustom;
use Src\RBAC\Models\UserRoleProject;
use Src\Foundation\EventBus;
use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service quản lý RBAC - tính toán quyền hiệu lực
 * Thực hiện logic 3 lớp quyền với nguyên tắc least privilege và allow_override
 * 
 * Logic: Project-Specific > Custom > System-Wide
 * - Mỗi lớp có thể override lớp thấp hơn nếu có allow_override=true
 * - Áp dụng least privilege: chỉ lấy quyền có ở tất cả các lớp được assign
 * - Allow override: cho phép lớp cao hơn thêm quyền không có ở lớp thấp hơn
 */
class RBACManager
{
    private EventBus $eventBus;
    private int $cacheTimeout = 300; // 5 minutes

    public function __construct(EventBus $eventBus)
    {
        $this->eventBus = $eventBus;
    }

    /**
     * Tính toán quyền hiệu lực của user cho một project
     * 
     * @param string $userId ID của user (ULID)
     * @param string|null $projectId ID của project (ULID, null nếu kiểm tra quyền hệ thống)
     * @return array Danh sách permission codes
     */
    public function calculateEffectivePermissions(string $userId, ?string $projectId = null): array
    {
        $cacheKey = "effective_permissions_{$userId}_" . ($projectId ?? 'system');
        
        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($userId, $projectId) {
            return $this->computeEffectivePermissions($userId, $projectId);
        });
    }

    /**
     * Tính toán thực tế quyền hiệu lực (không cache)
     */
    private function computeEffectivePermissions(string $userId, ?string $projectId = null): array
    {
        // Lấy quyền từ 3 lớp với thông tin allow_override
        $systemData = $this->getSystemPermissionsWithOverride($userId);
        $customData = $this->getCustomPermissionsWithOverride($userId);
        $projectData = $projectId ? $this->getProjectPermissionsWithOverride($userId, $projectId) : ['permissions' => [], 'overrides' => []];

        // Logic tính toán quyền hiệu lực theo nguyên tắc least privilege và allow_override
        $effectivePermissions = [];
        
        // Nếu user có system roles, bắt đầu với system permissions
        if (!empty($systemData['permissions'])) {
            $effectivePermissions = $systemData['permissions'];
            
            // Nếu user cũng có custom roles, áp dụng least privilege
            if (!empty($customData['permissions'])) {
                $effectivePermissions = array_intersect($effectivePermissions, $customData['permissions']);
                // Thêm override permissions từ custom layer
                $effectivePermissions = array_unique(array_merge($effectivePermissions, $customData['overrides']));
            }
            
            // Nếu user cũng có project roles, áp dụng least privilege
            if ($projectId && !empty($projectData['permissions'])) {
                $effectivePermissions = array_intersect($effectivePermissions, $projectData['permissions']);
                // Thêm override permissions từ project layer (ưu tiên cao nhất)
                $effectivePermissions = array_unique(array_merge($effectivePermissions, $projectData['overrides']));
            }
        }
        // Nếu user chỉ có custom roles (không có system roles)
        elseif (!empty($customData['permissions'])) {
            $effectivePermissions = $customData['permissions'];
            $effectivePermissions = array_unique(array_merge($effectivePermissions, $customData['overrides']));
            
            // Nếu user cũng có project roles, áp dụng least privilege
            if ($projectId && !empty($projectData['permissions'])) {
                $effectivePermissions = array_intersect($effectivePermissions, $projectData['permissions']);
                $effectivePermissions = array_unique(array_merge($effectivePermissions, $projectData['overrides']));
            }
        }
        // Nếu user chỉ có project roles
        elseif ($projectId && !empty($projectData['permissions'])) {
            $effectivePermissions = $projectData['permissions'];
            $effectivePermissions = array_unique(array_merge($effectivePermissions, $projectData['overrides']));
        }

        return array_values(array_unique($effectivePermissions));
    }

    /**
     * Lấy quyền hệ thống với thông tin override
     */
    private function getSystemPermissionsWithOverride(string $userId): array
    {
        $userRoles = UserRoleSystem::where('user_id', $userId)
            ->active() // Sử dụng scope active để lọc soft delete
            ->with(['role.permissions'])
            ->get();

        $permissions = [];
        $overrides = [];

        foreach ($userRoles as $userRole) {
            foreach ($userRole->role->permissions as $permission) {
                $permissions[] = $permission->code;
                
                // Kiểm tra allow_override ở pivot table role_permissions
                if ($permission->pivot->allow_override) {
                    $overrides[] = $permission->code;
                }
            }
        }

        return [
            'permissions' => array_unique($permissions),
            'overrides' => array_unique($overrides)
        ];
    }

    /**
     * Lấy quyền tùy chỉnh với thông tin override
     */
    private function getCustomPermissionsWithOverride(string $userId): array
    {
        $userRoles = UserRoleCustom::where('user_id', $userId)
            ->active() // Sử dụng scope active để lọc soft delete
            ->with(['role.permissions'])
            ->get();

        $permissions = [];
        $overrides = [];

        foreach ($userRoles as $userRole) {
            foreach ($userRole->role->permissions as $permission) {
                $permissions[] = $permission->code;
                
                // Kiểm tra allow_override ở pivot table role_permissions
                if ($permission->pivot->allow_override) {
                    $overrides[] = $permission->code;
                }
            }
        }

        return [
            'permissions' => array_unique($permissions),
            'overrides' => array_unique($overrides)
        ];
    }

    /**
     * Lấy quyền dự án với thông tin override
     */
    private function getProjectPermissionsWithOverride(string $userId, string $projectId): array
    {
        $userRoles = UserRoleProject::where('user_id', $userId)
            ->where('project_id', $projectId)
            ->active() // Sử dụng scope active để lọc soft delete
            ->with(['role.permissions'])
            ->get();

        $permissions = [];
        $overrides = [];

        foreach ($userRoles as $userRole) {
            foreach ($userRole->role->permissions as $permission) {
                $permissions[] = $permission->code;
                
                // Kiểm tra allow_override ở pivot table role_permissions
                if ($permission->pivot->allow_override) {
                    $overrides[] = $permission->code;
                }
            }
        }

        return [
            'permissions' => array_unique($permissions),
            'overrides' => array_unique($overrides)
        ];
    }

    /**
     * Kiểm tra user có quyền cụ thể không
     */
    public function hasPermission(string $userId, string $permissionCode, ?string $projectId = null): bool
    {
        $effectivePermissions = $this->calculateEffectivePermissions($userId, $projectId);
        return in_array($permissionCode, $effectivePermissions, true);
    }

    /**
     * Kiểm tra user có tất cả quyền yêu cầu không
     */
    public function hasAllPermissions(string $userId, array $permissionCodes, ?string $projectId = null): bool
    {
        $effectivePermissions = $this->calculateEffectivePermissions($userId, $projectId);
        return empty(array_diff($permissionCodes, $effectivePermissions));
    }

    /**
     * Kiểm tra user có ít nhất một trong các quyền yêu cầu không
     */
    public function hasAnyPermission(string $userId, array $permissionCodes, ?string $projectId = null): bool
    {
        $effectivePermissions = $this->calculateEffectivePermissions($userId, $projectId);
        return !empty(array_intersect($permissionCodes, $effectivePermissions));
    }

    /**
     * Lấy danh sách quyền hiệu lực với thông tin chi tiết
     */
    public function getDetailedPermissions(string $userId, ?string $projectId = null): array
    {
        $systemData = $this->getSystemPermissionsWithOverride($userId);
        $customData = $this->getCustomPermissionsWithOverride($userId);
        $projectData = $projectId ? $this->getProjectPermissionsWithOverride($userId, $projectId) : ['permissions' => [], 'overrides' => []];
        
        $effectivePermissions = $this->calculateEffectivePermissions($userId, $projectId);

        return [
            'effective_permissions' => $effectivePermissions,
            'system_layer' => $systemData,
            'custom_layer' => $customData,
            'project_layer' => $projectData,
            'computed_at' => now()->toISOString()
        ];
    }

    /**
     * Xóa cache permissions cho user
     */
    public function clearUserPermissionsCache(string $userId): void
    {
        // Xóa cache cho tất cả projects của user
        $patterns = [
            "effective_permissions_{$userId}_system"
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
        
        // Xóa cache cho tất cả project-specific permissions
        // Note: Laravel Cache không hỗ trợ wildcard delete, cần implement riêng nếu cần
        // Hoặc sử dụng cache tags nếu driver hỗ trợ
    }

    /**
     * GAP-042 §6a: target user must belong to the caller's tenant. No
     * global/system-user exception exists — none is evidenced anywhere in the
     * codebase, per the approved Gate-2 design.
     */
    private function userBelongsToTenant(string $userId, string $tenantId): bool
    {
        return User::where('id', $userId)->where('tenant_id', $tenantId)->exists();
    }

    /**
     * GAP-042 §6a: target project must belong to the caller's tenant.
     */
    private function projectBelongsToTenant(string $projectId, string $tenantId): bool
    {
        return Project::where('id', $projectId)->where('tenant_id', $tenantId)->exists();
    }

    /**
     * Gán role cho user ở lớp system — GAP-042 §6a fail-closed tenant checks.
     *
     * System-scope roles are global (tenant_id IS NULL) by definition (§6), so
     * once scope is confirmed no further role-tenant check is needed; the
     * target user must still belong to the caller's tenant.
     */
    public function assignSystemRole(string $userId, string $roleId, string $tenantId): bool
    {
        if (!$this->userBelongsToTenant($userId, $tenantId)) {
            return false;
        }

        $role = Role::where('id', $roleId)
            ->where('scope', Role::SCOPE_SYSTEM)
            ->first();

        if (!$role) {
            return false;
        }

        // Thay thế firstOrCreate() bằng exists() check và create() riêng biệt
        $exists = UserRoleSystem::where('user_id', $userId)
            ->where('role_id', $roleId)
            ->exists();

        if (!$exists) {
            UserRoleSystem::create([
                'user_id' => $userId,
                'role_id' => $roleId
            ]);
        }

        // Xóa cache
        $this->clearUserPermissionsCache($userId);

        // Phát sự kiện
        $this->eventBus->publish('rbac.assignment.changed', [
            'entityId' => $userId,
            'projectId' => $tenantId,
            'actorId' => $tenantId,
            'userId' => $userId,
            'roleId' => $roleId,
            'scope' => 'system',
            'action' => 'assigned',
            'timestamp' => now()->toISOString()
        ]);

        return true;
    }

    /**
     * Gán role cho user ở lớp custom — GAP-042 §6a fail-closed tenant checks.
     *
     * Fails closed (no write) unless the target user AND the target role both
     * belong to the caller's tenant.
     */
    public function assignCustomRole(string $userId, string $roleId, string $tenantId): bool
    {
        if (!$this->userBelongsToTenant($userId, $tenantId)) {
            return false;
        }

        $role = Role::where('id', $roleId)
            ->where('scope', Role::SCOPE_CUSTOM)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$role) {
            return false;
        }

        UserRoleCustom::firstOrCreate([
            'user_id' => $userId,
            'role_id' => $roleId
        ]);

        // Xóa cache
        $this->clearUserPermissionsCache($userId);

        // Phát sự kiện
        $this->eventBus->publish('rbac.assignment.changed', [
            'entityId' => $userId,
            'projectId' => $tenantId,
            'actorId' => $tenantId,
            'userId' => $userId,
            'roleId' => $roleId,
            'scope' => 'custom',
            'action' => 'assigned',
            'timestamp' => now()->toISOString()
        ]);

        return true;
    }

    /**
     * Gán role cho user ở lớp project — GAP-042 §6a fail-closed tenant checks.
     *
     * Fails closed unless the target user, the target role, AND the target
     * project all belong to the caller's tenant. All identities are validated
     * before any write (no partial writes).
     */
    public function assignProjectRole(string $userId, string $roleId, string $projectId, string $tenantId): bool
    {
        if (!$this->userBelongsToTenant($userId, $tenantId)) {
            return false;
        }

        if (!$this->projectBelongsToTenant($projectId, $tenantId)) {
            return false;
        }

        $role = Role::where('id', $roleId)
            ->where('scope', Role::SCOPE_PROJECT)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$role) {
            return false;
        }

        UserRoleProject::firstOrCreate([
            'user_id' => $userId,
            'role_id' => $roleId,
            'project_id' => $projectId
        ]);

        // Xóa cache
        $this->clearUserPermissionsCache($userId);

        // Phát sự kiện
        $this->eventBus->publish('rbac.assignment.changed', [
            'entityId' => $userId,
            'actorId' => $tenantId,
            'userId' => $userId,
            'roleId' => $roleId,
            'projectId' => $projectId,
            'scope' => 'project',
            'action' => 'assigned',
            'timestamp' => now()->toISOString()
        ]);

        return true;
    }

    /**
     * Hủy gán role cho user — GAP-042 §6a: verify the existing assignment
     * row's user belongs to the caller's tenant before deleting; behaves like
     * "not found" (0 rows deleted) for a cross-tenant target, never revealing
     * whether a cross-tenant row exists.
     */
    public function revokeRole(string $userId, string $roleId, string $scope, ?string $projectId, string $tenantId): bool
    {
        if (!$this->userBelongsToTenant($userId, $tenantId)) {
            return false;
        }

        $deleted = false;

        switch ($scope) {
            case 'system':
                $deleted = UserRoleSystem::where('user_id', $userId)
                    ->where('role_id', $roleId)
                    ->delete() > 0;
                break;

            case 'custom':
                $deleted = UserRoleCustom::where('user_id', $userId)
                    ->where('role_id', $roleId)
                    ->delete() > 0;
                break;

            case 'project':
                if ($projectId) {
                    $deleted = UserRoleProject::where('user_id', $userId)
                        ->where('role_id', $roleId)
                        ->where('project_id', $projectId)
                        ->delete() > 0;
                }
                break;
        }

        if ($deleted) {
            // Xóa cache
            $this->clearUserPermissionsCache($userId);

            // Phát sự kiện
            $this->eventBus->publish('rbac.assignment.changed', [
                'entityId' => $userId,
                'actorId' => $tenantId,
                'projectId' => $projectId ?? $tenantId,
                'userId' => $userId,
                'roleId' => $roleId,
                'scope' => $scope,
                'action' => 'revoked',
                'timestamp' => now()->toISOString()
            ]);
        }

        return $deleted;
    }
}