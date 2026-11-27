<?php declare(strict_types=1);

namespace Src\RBAC\Services;

use App\Support\PlatformPermissions;
use App\Support\Rbac\PermissionRevocations;
use App\Support\Rbac\RbacProtection;
use App\Support\Rbac\SecurityAudit;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Src\RBAC\Models\UserRoleSystem;
use Src\RBAC\Models\UserRoleCustom;
use Src\RBAC\Models\UserRoleProject;
use Src\Foundation\EventBus;
use Src\Foundation\Helpers\AuthHelper;
use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
     * GAP-042 Gate-3 Round-1 Correction 3 (defense-in-depth): a role passed
     * to assignSystemRole() must be a GENUINE global/system role — scope
     * `system` AND `tenant_id IS NULL` — not merely a row whose scope column
     * happens to say `system` while carrying a tenant_id (a malformed/
     * tenant-owned row must never be usable to grant a system-wide role).
     */
    private function isGenuineSystemRole(?Role $role): bool
    {
        return $role !== null && $role->scope === Role::SCOPE_SYSTEM && $role->tenant_id === null;
    }

    /**
     * GAP-042 Gate-3 Round-1 Correction 4: revokeRole()'s per-scope role/
     * project ownership checks, mirroring the assign*Role() methods' own
     * fail-closed identity validation exactly, so a DELETE cannot succeed
     * against a role/project it was never authorized to touch merely
     * because the target USER happens to belong to the caller's tenant.
     * Returns false (nothing deleted) for any check that fails; all checks
     * run BEFORE the delete.
     */
    private function revokeRoleIdentitiesValid(string $roleId, string $scope, ?string $projectId, string $tenantId): bool
    {
        switch ($scope) {
            case 'system':
                $role = Role::find($roleId);
                return $this->isGenuineSystemRole($role);

            case 'custom':
                return Role::where('id', $roleId)
                    ->where('scope', Role::SCOPE_CUSTOM)
                    ->where('tenant_id', $tenantId)
                    ->exists();

            case 'project':
                if ($projectId === null || !$this->projectBelongsToTenant($projectId, $tenantId)) {
                    return false;
                }

                return Role::where('id', $roleId)
                    ->where('scope', Role::SCOPE_PROJECT)
                    ->where('tenant_id', $tenantId)
                    ->exists();

            default:
                return false;
        }
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
        // OWN-2026-024 H2a (Owner Gate 2, D6): a role carrying platform authority is never assignable through tenant role
        // management (HTTP or bulk). Platform Operators are provisioned by a separate server-only step (H2b).
        if (PlatformPermissions::roleHoldsPlatformPermission($roleId)) {
            return false;
        }

        if (!$this->userBelongsToTenant($userId, $tenantId)) {
            return false;
        }

        // GAP-042 Gate-3 Round-1 Correction 3 (defense-in-depth): the role
        // must be a GENUINE global/system role (scope=system AND
        // tenant_id IS NULL) — a malformed row that merely has
        // scope='system' while carrying a non-null tenant_id must never be
        // usable to grant a system-wide role.
        $role = Role::where('id', $roleId)
            ->where('scope', Role::SCOPE_SYSTEM)
            ->whereNull('tenant_id')
            ->first();

        if (!$this->isGenuineSystemRole($role)) {
            return false;
        }

        // OWN-2026-024 H2b (Owner Gate 2, D5): privileged roles (the Owner role, any role with a protected code) need real
        // canonical Owner authority, same tenant, never self; fail closed with no write.
        $actor = RbacProtection::currentActor();
        if (RbacProtection::privilegedRoleChangeDenial($actor, $userId, $roleId, false) !== null) {
            return false;
        }
        $privileged = RbacProtection::isPrivilegedRole($roleId);

        // Thay thế firstOrCreate() bằng exists() check và create() riêng biệt
        // UAT-RECOVERY-01 S2: `system_user_roles` stays the GAP-042 assignment ledger, but authorization
        // (User::hasPermission → rbac: gate) reads `user_roles`. Both rows are written in ONE transaction
        // (all-or-nothing, idempotent) so a successful assignment is always visible to authorization.
        try {
            DB::transaction(function () use ($userId, $roleId, $tenantId, $actor, $privileged): void {
                $exists = UserRoleSystem::where('user_id', $userId)
                    ->where('role_id', $roleId)
                    ->exists();

                if ($privileged && !DB::table('user_roles')->where('user_id', $userId)->where('role_id', $roleId)->exists()) {
                    // OWN-2026-024 H2b: transactional audit for every successful privileged assignment (rolls back with it).
                    SecurityAudit::record((string) $actor?->id, $tenantId, 'rbac.privileged_role.assigned', 'user', $userId, PermissionRevocations::actorLabel($actor), [
                        'role_id' => $roleId,
                        'role' => RbacProtection::roleName($roleId),
                    ]);
                }

                if (!$exists) {
                    UserRoleSystem::create([
                        'user_id' => $userId,
                        'role_id' => $roleId
                    ]);
                }

                DB::table('user_roles')->insertOrIgnore([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // MySQL's INSERT IGNORE downgrades FK/other errors to warnings: never trust the insert,
                // verify the row, and roll BOTH writes back if the projection is not really there.
                if (!DB::table('user_roles')->where('user_id', $userId)->where('role_id', $roleId)->exists()) {
                    throw new \RuntimeException('user_roles projection was not persisted');
                }
            });
        } catch (\Throwable $e) {
            Log::warning('rbac.assignSystemRole.rolled_back', ['user_id' => $userId, 'role_id' => $roleId, 'reason' => $e->getMessage()]);

            return false;
        }

        // Xóa cache
        $this->clearUserPermissionsCache($userId);

        // Phát sự kiện — GAP-042 Gate-3 Round-1 Correction 6: actorId is the
        // real authenticated acting user (or the established 'system'
        // fallback), never the tenant id; projectId uses the established
        // 'system' convention for a non-project RBAC event, never the
        // tenant id either.
        $this->eventBus->publish('rbac.assignment.changed', [
            'entityId' => $userId,
            'projectId' => 'system',
            'actorId' => AuthHelper::idOrSystem(),
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
        // OWN-2026-024 H2b (D5): no privileged role through custom/project assignment without real Owner authority.
        if (RbacProtection::privilegedRoleChangeDenial(RbacProtection::currentActor(), $userId, $roleId, false) !== null) {
            return false;
        }

        // OWN-2026-024 H2a (Owner Gate 2, D6): a role carrying platform authority is never assignable through tenant role
        // management (HTTP or bulk). Platform Operators are provisioned by a separate server-only step (H2b).
        if (PlatformPermissions::roleHoldsPlatformPermission($roleId)) {
            return false;
        }

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

        // Phát sự kiện (Correction 6: truthful actor/project identities)
        $this->eventBus->publish('rbac.assignment.changed', [
            'entityId' => $userId,
            'projectId' => 'system',
            'actorId' => AuthHelper::idOrSystem(),
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
        // OWN-2026-024 H2b (D5): no privileged role through custom/project assignment without real Owner authority.
        if (RbacProtection::privilegedRoleChangeDenial(RbacProtection::currentActor(), $userId, $roleId, false) !== null) {
            return false;
        }

        // OWN-2026-024 H2a (Owner Gate 2, D6): a role carrying platform authority is never assignable through tenant role
        // management (HTTP or bulk). Platform Operators are provisioned by a separate server-only step (H2b).
        if (PlatformPermissions::roleHoldsPlatformPermission($roleId)) {
            return false;
        }

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

        // Phát sự kiện (Correction 6: truthful actor identity; projectId here
        // IS the real project id — a genuine project-scope event).
        $this->eventBus->publish('rbac.assignment.changed', [
            'entityId' => $userId,
            'actorId' => AuthHelper::idOrSystem(),
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

        // GAP-042 Gate-3 Round-1 Correction 4: the target ROLE (and, for
        // project scope, the target PROJECT) must also belong to the
        // caller's tenant / be a genuine system role — verifying only the
        // target user is not sufficient. All checks complete before any
        // DELETE; a failure here leaves every assignment table untouched.
        if (!$this->revokeRoleIdentitiesValid($roleId, $scope, $projectId, $tenantId)) {
            return false;
        }

        // OWN-2026-024 H2b (D5): removing a privileged role (incl. the last active Owner) needs real Owner authority, never self.
        $actor = RbacProtection::currentActor();
        if (RbacProtection::privilegedRoleChangeDenial($actor, $userId, $roleId, true) !== null) {
            return false;
        }
        $privileged = RbacProtection::isPrivilegedRole($roleId);

        $deleted = false;

        switch ($scope) {
            case 'system':
                // UAT-RECOVERY-01 S2: remove the ledger row and its authorization projection together.
                // `user_roles` is touched ONLY when a ledger row was actually revoked here, so an
                // independently-sourced user_roles row (no ledger row) is never deleted by this path.
                $deleted = DB::transaction(function () use ($userId, $roleId, $tenantId, $actor, $privileged): bool {
                    $removed = UserRoleSystem::where('user_id', $userId)
                        ->where('role_id', $roleId)
                        ->delete() > 0;

                    if ($removed && $privileged) {
                        SecurityAudit::record((string) $actor?->id, $tenantId, 'rbac.privileged_role.removed', 'user', $userId, PermissionRevocations::actorLabel($actor), [
                            'role_id' => $roleId,
                            'role' => RbacProtection::roleName($roleId),
                        ]);
                    }

                    if ($removed) {
                        DB::table('user_roles')
                            ->where('user_id', $userId)
                            ->where('role_id', $roleId)
                            ->delete();
                    }

                    return $removed;
                });
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

            // Phát sự kiện (Correction 6: truthful actor identity; projectId
            // is the real project id for project-scope revokes, else the
            // established 'system' convention).
            $this->eventBus->publish('rbac.assignment.changed', [
                'entityId' => $userId,
                'actorId' => AuthHelper::idOrSystem(),
                'projectId' => $projectId ?? 'system',
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