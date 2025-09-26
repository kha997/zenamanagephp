<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\UserRoleCustom;
use Src\RBAC\Models\UserRoleProject;
use Src\RBAC\Models\UserRoleSystem;

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
        $permissionData = $this->getAllPermissionData($userId, $projectId);
        
        return $this->calculatePermissionsFromData($permissionData);
    }

    /**
     * Lấy tất cả dữ liệu quyền từ 3 lớp
     */
    private function getAllPermissionData(string $userId, ?string $projectId = null): array
    {
        return [
            'system' => $this->getSystemPermissionsWithOverride($userId),
            'custom' => $this->getCustomPermissionsWithOverride($userId),
            'project' => $projectId ? $this->getProjectPermissionsWithOverride($userId, $projectId) : ['permissions' => [], 'overrides' => []]
        ];
    }

    /**
     * Tính toán quyền hiệu lực từ dữ liệu
     */
    private function calculatePermissionsFromData(array $data): array
    {
        $effectivePermissions = [];

        if ($this->hasSystemPermissions($data)) {
            $effectivePermissions = $this->processSystemPermissions($data);
        } elseif ($this->hasCustomPermissions($data)) {
            $effectivePermissions = $this->processCustomPermissions($data);
        } elseif ($this->hasProjectPermissions($data)) {
            $effectivePermissions = $this->processProjectPermissions($data);
        }

        return array_values(array_unique($effectivePermissions));
    }

    /**
     * Kiểm tra có quyền hệ thống không
     */
    private function hasSystemPermissions(array $data): bool
    {
        return !empty($data['system']['permissions']);
    }

    /**
     * Kiểm tra có quyền custom không
     */
    private function hasCustomPermissions(array $data): bool
    {
        return !empty($data['custom']['permissions']);
    }

    /**
     * Kiểm tra có quyền project không
     */
    private function hasProjectPermissions(array $data): bool
    {
        return !empty($data['project']['permissions']);
    }

    /**
     * Xử lý quyền hệ thống
     */
    private function processSystemPermissions(array $data): array
    {
        $effectivePermissions = $data['system']['permissions'];

        if ($this->hasCustomPermissions($data)) {
            $effectivePermissions = $this->applyLeastPrivilege($effectivePermissions, $data['custom']['permissions']);
            $effectivePermissions = $this->addOverrides($effectivePermissions, $data['custom']['overrides']);
        }

        if ($this->hasProjectPermissions($data)) {
            $effectivePermissions = $this->applyLeastPrivilege($effectivePermissions, $data['project']['permissions']);
            $effectivePermissions = $this->addOverrides($effectivePermissions, $data['project']['overrides']);
        }

        return $effectivePermissions;
    }

    /**
     * Xử lý quyền custom
     */
    private function processCustomPermissions(array $data): array
    {
        $effectivePermissions = $data['custom']['permissions'];
        $effectivePermissions = $this->addOverrides($effectivePermissions, $data['custom']['overrides']);

        if ($this->hasProjectPermissions($data)) {
            $effectivePermissions = $this->applyLeastPrivilege($effectivePermissions, $data['project']['permissions']);
            $effectivePermissions = $this->addOverrides($effectivePermissions, $data['project']['overrides']);
        }

        return $effectivePermissions;
    }

    /**
     * Xử lý quyền project
     */
    private function processProjectPermissions(array $data): array
    {
        $effectivePermissions = $data['project']['permissions'];
        return $this->addOverrides($effectivePermissions, $data['project']['overrides']);
    }

    /**
     * Áp dụng nguyên tắc least privilege
     */
    private function applyLeastPrivilege(array $permissions1, array $permissions2): array
    {
        return array_intersect($permissions1, $permissions2);
    }

    /**
     * Thêm override permissions
     */
    private function addOverrides(array $permissions, array $overrides): array
    {
        return array_unique(array_merge($permissions, $overrides));
    }

    /**
     * Lấy quyền hệ thống với thông tin override
     */
    private function getSystemPermissionsWithOverride(string $userId): array
    {
        $systemRoles = UserRoleSystem::where('user_id', $userId)
            ->with(['role.permissions'])
            ->get();

        $permissions = [];
        $overrides = [];

        foreach ($systemRoles as $userRole) {
            $rolePermissions = $userRole->role->permissions->pluck('code')->toArray();
            $permissions = array_merge($permissions, $rolePermissions);

            if ($userRole->allow_override) {
                $overrides = array_merge($overrides, $rolePermissions);
            }
        }

        return [
            'permissions' => array_unique($permissions),
            'overrides' => array_unique($overrides)
        ];
    }

    /**
     * Lấy quyền custom với thông tin override
     */
    private function getCustomPermissionsWithOverride(string $userId): array
    {
        $customRoles = UserRoleCustom::where('user_id', $userId)
            ->with(['role.permissions'])
            ->get();

        $permissions = [];
        $overrides = [];

        foreach ($customRoles as $userRole) {
            $rolePermissions = $userRole->role->permissions->pluck('code')->toArray();
            $permissions = array_merge($permissions, $rolePermissions);

            if ($userRole->allow_override) {
                $overrides = array_merge($overrides, $rolePermissions);
            }
        }

        return [
            'permissions' => array_unique($permissions),
            'overrides' => array_unique($overrides)
        ];
    }

    /**
     * Lấy quyền project với thông tin override
     */
    private function getProjectPermissionsWithOverride(string $userId, string $projectId): array
    {
        $projectRoles = UserRoleProject::where('user_id', $userId)
            ->where('project_id', $projectId)
            ->with(['role.permissions'])
            ->get();

        $permissions = [];
        $overrides = [];

        foreach ($projectRoles as $userRole) {
            $rolePermissions = $userRole->role->permissions->pluck('code')->toArray();
            $permissions = array_merge($permissions, $rolePermissions);

            if ($userRole->allow_override) {
                $overrides = array_merge($overrides, $rolePermissions);
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
    public function hasPermission(string $userId, string $permission, ?string $projectId = null): bool
    {
        $permissions = $this->calculateEffectivePermissions($userId, $projectId);
        return in_array($permission, $permissions);
    }

    /**
     * Kiểm tra user có bất kỳ quyền nào trong danh sách không
     */
    public function hasAnyPermission(string $userId, array $permissions, ?string $projectId = null): bool
    {
        $userPermissions = $this->calculateEffectivePermissions($userId, $projectId);
        return !empty(array_intersect($permissions, $userPermissions));
    }

    /**
     * Kiểm tra user có tất cả quyền trong danh sách không
     */
    public function hasAllPermissions(string $userId, array $permissions, ?string $projectId = null): bool
    {
        $userPermissions = $this->calculateEffectivePermissions($userId, $projectId);
        return empty(array_diff($permissions, $userPermissions));
    }

    /**
     * Xóa cache permissions của user
     */
    public function clearUserPermissionsCache(string $userId, ?string $projectId = null): void
    {
        $cacheKey = "effective_permissions_{$userId}_" . ($projectId ?? 'system');
        Cache::forget($cacheKey);
    }

    /**
     * Xóa tất cả cache permissions
     */
    public function clearAllPermissionsCache(): void
    {
        Cache::flush();
    }
}