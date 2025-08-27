<?php
declare(strict_types=1);

namespace Src\Foundation;

/**
 * Lớp helper để quản lý context permission trong suốt request lifecycle
 */
class PermissionContext {
    private static ?array $currentContext = null;
    
    /**
     * Thiết lập context hiện tại
     * 
     * @param array $context Context data
     */
    public static function setContext(array $context): void {
        self::$currentContext = $context;
    }
    
    /**
     * Lấy context hiện tại
     * 
     * @return array|null Context data hoặc null nếu chưa được thiết lập
     */
    public static function getContext(): ?array {
        return self::$currentContext;
    }
    
    /**
     * Lấy user ID từ context
     * 
     * @return string|null User ID
     */
    public static function getUserId(): ?string {
        return self::$currentContext['user_id'] ?? null;
    }
    
    /**
     * Lấy project ID từ context
     * 
     * @return string|null Project ID
     */
    public static function getProjectId(): ?string {
        return self::$currentContext['project_id'] ?? null;
    }
    
    /**
     * Lấy tenant ID từ context
     * 
     * @return string|null Tenant ID
     */
    public static function getTenantId(): ?string {
        return self::$currentContext['tenant_id'] ?? null;
    }
    
    /**
     * Lấy danh sách quyền của user hiện tại
     * 
     * @return array Danh sách quyền
     */
    public static function getUserPermissions(): array {
        return self::$currentContext['permissions'] ?? [];
    }
    
    /**
     * Kiểm tra xem user có quyền cụ thể hay không
     * 
     * @param string $permission Quyền cần kiểm tra
     * @return bool True nếu có quyền
     */
    public static function hasPermission(string $permission): bool {
        $permissions = self::getUserPermissions();
        return in_array($permission, $permissions, true);
    }
    
    /**
     * Kiểm tra xem user có tất cả quyền yêu cầu hay không
     * 
     * @param array $requiredPermissions Danh sách quyền yêu cầu
     * @return bool True nếu có đủ quyền
     */
    public static function hasAllPermissions(array $requiredPermissions): bool {
        return Permission::hasRequiredPermissions(
            self::getUserPermissions(),
            $requiredPermissions
        );
    }
    
    /**
     * Xóa context hiện tại
     */
    public static function clearContext(): void {
        self::$currentContext = null;
    }
}