<?php
declare(strict_types=1);

namespace Src\Foundation;

use Src\Foundation\Constants;
use Exception;

/**
 * Lớp Permission xử lý hệ thống phân quyền theo mô hình RBAC
 * Hỗ trợ 3 lớp quyền: Project-Specific > Custom > System-Wide
 */
class Permission {
    /**
     * Tính toán quyền hiệu lực dựa trên 3 lớp quyền với nguyên tắc least privilege
     * 
     * @param array $projectSpecificPermissions Quyền cụ thể cho dự án (ưu tiên cao nhất)
     * @param array $customPermissions Quyền tùy chỉnh (ưu tiên trung bình)
     * @param array $systemWidePermissions Quyền toàn hệ thống (ưu tiên thấp nhất)
     * @param array $overrideRules Quy tắc ghi đè với allow_override=true
     * @return array Danh sách quyền hiệu lực
     */
    public static function calculateEffectivePermissions(
        array $projectSpecificPermissions,
        array $customPermissions,
        array $systemWidePermissions,
        array $overrideRules = []
    ): array {
        $effectivePermissions = [];
        
        // Bước 1: Áp dụng quyền toàn hệ thống (mức thấp nhất)
        foreach ($systemWidePermissions as $permission) {
            $effectivePermissions[$permission] = [
                'granted' => true,
                'source' => 'system',
                'allow_override' => $overrideRules[$permission] ?? false
            ];
        }
        
        // Bước 2: Áp dụng quyền tùy chỉnh (mức trung bình)
        // Theo nguyên tắc least privilege: chỉ thêm quyền mới, không ghi đè quyền đã có
        foreach ($customPermissions as $permission) {
            if (!isset($effectivePermissions[$permission])) {
                $effectivePermissions[$permission] = [
                    'granted' => true,
                    'source' => 'custom',
                    'allow_override' => $overrideRules[$permission] ?? false
                ];
            } elseif ($effectivePermissions[$permission]['allow_override']) {
                // Chỉ ghi đè nếu có allow_override=true
                $effectivePermissions[$permission]['source'] = 'custom';
            }
        }
        
        // Bước 3: Áp dụng quyền cụ thể cho dự án (mức cao nhất)
        foreach ($projectSpecificPermissions as $permission) {
            if (!isset($effectivePermissions[$permission])) {
                $effectivePermissions[$permission] = [
                    'granted' => true,
                    'source' => 'project',
                    'allow_override' => $overrideRules[$permission] ?? false
                ];
            } elseif ($effectivePermissions[$permission]['allow_override']) {
                // Chỉ ghi đè nếu có allow_override=true
                $effectivePermissions[$permission]['source'] = 'project';
            }
        }
        
        // Trả về chỉ danh sách tên quyền
        return array_keys(array_filter($effectivePermissions, function($perm) {
            return $perm['granted'];
        }));
    }
    
    /**
     * Kiểm tra xem người dùng có đủ quyền yêu cầu hay không
     * 
     * @param array $userPermissions Danh sách quyền của người dùng
     * @param array $requiredPermissions Danh sách quyền yêu cầu
     * @return bool True nếu có đủ quyền, ngược lại là False
     */
    public static function hasRequiredPermissions(array $userPermissions, array $requiredPermissions): bool {
        foreach ($requiredPermissions as $permission) {
            if (!in_array($permission, $userPermissions, true)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Lấy quyền của người dùng từ context (header/JWT)
     * 
     * @param string $userId ID người dùng
     * @param string|null $projectId ID dự án (nullable)
     * @param array $userRoles Danh sách role của người dùng theo 3 lớp
     * @return array Danh sách quyền hiệu lực
     */
    public static function getUserPermissions(string $userId, ?string $projectId, array $userRoles): array {
        $systemPermissions = [];
        $customPermissions = [];
        $projectPermissions = [];
        $overrideRules = [];
        
        // Phân loại quyền theo scope
        foreach ($userRoles as $role) {
            $rolePermissions = self::getRolePermissions($role['id']);
            
            switch ($role['scope']) {
                case Constants::PERMISSION_SCOPE_SYSTEM:
                    $systemPermissions = array_merge($systemPermissions, $rolePermissions['permissions']);
                    $overrideRules = array_merge($overrideRules, $rolePermissions['overrides']);
                    break;
                    
                case Constants::PERMISSION_SCOPE_CUSTOM:
                    $customPermissions = array_merge($customPermissions, $rolePermissions['permissions']);
                    $overrideRules = array_merge($overrideRules, $rolePermissions['overrides']);
                    break;
                    
                case Constants::PERMISSION_SCOPE_PROJECT:
                    if ($projectId && $role['project_id'] === $projectId) {
                        $projectPermissions = array_merge($projectPermissions, $rolePermissions['permissions']);
                        $overrideRules = array_merge($overrideRules, $rolePermissions['overrides']);
                    }
                    break;
            }
        }
        
        return self::calculateEffectivePermissions(
            array_unique($projectPermissions),
            array_unique($customPermissions),
            array_unique($systemPermissions),
            $overrideRules
        );
    }
    
    /**
     * Lấy quyền của một role từ cơ sở dữ liệu
     * 
     * @param string $roleId ID của role
     * @return array Mảng chứa permissions và override rules
     */
    private static function getRolePermissions(string $roleId): array {
        try {
            // Define role permissions mapping
            $rolePermissionsMap = [
                'super_admin' => [
                    'permissions' => [
                        'projects.create', 'projects.read', 'projects.update', 'projects.delete',
                        'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
                        'documents.create', 'documents.read', 'documents.update', 'documents.delete',
                        'users.create', 'users.read', 'users.update', 'users.delete',
                        'teams.create', 'teams.read', 'teams.update', 'teams.delete',
                        'settings.read', 'settings.update',
                        'reports.read', 'reports.create',
                        'admin.access'
                    ],
                    'overrides' => [
                        'projects.delete' => true,
                        'users.delete' => true,
                        'admin.access' => true
                    ]
                ],
                'admin' => [
                    'permissions' => [
                        'projects.create', 'projects.read', 'projects.update',
                        'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
                        'documents.create', 'documents.read', 'documents.update', 'documents.delete',
                        'users.create', 'users.read', 'users.update',
                        'teams.create', 'teams.read', 'teams.update',
                        'settings.read', 'settings.update',
                        'reports.read', 'reports.create'
                    ],
                    'overrides' => [
                        'projects.update' => true,
                        'users.update' => true
                    ]
                ],
                'project_manager' => [
                    'permissions' => [
                        'projects.read', 'projects.update',
                        'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
                        'documents.create', 'documents.read', 'documents.update', 'documents.delete',
                        'teams.read', 'teams.update',
                        'reports.read'
                    ],
                    'overrides' => [
                        'tasks.delete' => true,
                        'documents.delete' => true
                    ]
                ],
                'member' => [
                    'permissions' => [
                        'projects.read',
                        'tasks.read', 'tasks.update',
                        'documents.read', 'documents.create',
                        'teams.read'
                    ],
                    'overrides' => []
                ],
                'client' => [
                    'permissions' => [
                        'projects.read',
                        'documents.read'
                    ],
                    'overrides' => []
                ]
            ];

            return $rolePermissionsMap[$roleId] ?? [
                'permissions' => [],
                'overrides' => []
            ];
        } catch (\Exception $e) {
            // Log error and return empty permissions
            error_log("Error getting role permissions for role {$roleId}: " . $e->getMessage());
            return [
                'permissions' => [],
                'overrides' => []
            ];
        }
    }
    
    /**
     * Kiểm tra quyền với thông tin chi tiết
     * 
     * @param array $userPermissions Quyền của người dùng
     * @param array $requiredPermissions Quyền yêu cầu
     * @return array Kết quả kiểm tra chi tiết
     */
    public static function checkPermissionsDetailed(array $userPermissions, array $requiredPermissions): array {
        $result = [
            'granted' => true,
            'missing_permissions' => [],
            'available_permissions' => $userPermissions
        ];
        
        foreach ($requiredPermissions as $permission) {
            if (!in_array($permission, $userPermissions, true)) {
                $result['granted'] = false;
                $result['missing_permissions'][] = $permission;
            }
        }
        
        return $result;
    }
    
    /**
     * Parse permission string thành module và action
     * 
     * @param string $permission Permission string (VD: 'task.create')
     * @return array Mảng chứa module và action
     * @throws Exception Nếu format không hợp lệ
     */
    public static function parsePermission(string $permission): array {
        $parts = explode('.', $permission);
        
        if (count($parts) !== 2) {
            throw new Exception("Permission format không hợp lệ: {$permission}. Định dạng đúng: 'module.action'");
        }
        
        return [
            'module' => $parts[0],
            'action' => $parts[1],
            'full' => $permission
        ];
    }
}