<?php
declare(strict_types=1);

namespace Src\Foundation\Middleware;

use Src\Foundation\Permission;
use Src\Foundation\Foundation;
use Exception;

/**
 * Middleware xử lý phân quyền cho mọi API request
 * Kiểm tra quyền dựa trên header/context và requiredPermissions
 */
class PermissionMiddleware {
    /**
     * Xử lý request và kiểm tra quyền
     * 
     * @param array $request Request data
     * @param array $requiredPermissions Danh sách quyền yêu cầu
     * @return array Kết quả kiểm tra quyền
     * @throws Exception Nếu không có quyền truy cập
     */
    public function handle(array $request, array $requiredPermissions = []): array {
        // Bước 1: Lấy thông tin từ header/context
        $context = $this->extractContext($request);
        
        // Bước 2: Validate context
        $this->validateContext($context);
        
        // Bước 3: Lấy quyền của người dùng
        $userPermissions = Permission::getUserPermissions(
            $context['user_id'],
            $context['project_id'],
            $context['user_roles']
        );
        
        // Bước 4: Kiểm tra quyền yêu cầu
        if (!empty($requiredPermissions)) {
            $permissionCheck = Permission::checkPermissionsDetailed($userPermissions, $requiredPermissions);
            
            if (!$permissionCheck['granted']) {
                throw new Exception(
                    'Không có quyền truy cập. Thiếu quyền: ' . implode(', ', $permissionCheck['missing_permissions'])
                );
            }
        }
        
        // Bước 5: Trả về context đã được validate
        return [
            'context' => $context,
            'permissions' => $userPermissions,
            'permission_check' => $permissionCheck ?? ['granted' => true]
        ];
    }
    
    /**
     * Trích xuất thông tin context từ request
     * 
     * @param array $request Request data
     * @return array Context information
     */
    private function extractContext(array $request): array {
        $headers = $request['headers'] ?? [];
        
        return [
            'user_id' => $headers['X-User-ID'] ?? null,
            'project_id' => $headers['X-Project-ID'] ?? null,
            'tenant_id' => $headers['X-Tenant-ID'] ?? null,
            'user_roles' => json_decode($headers['X-User-Roles'] ?? '[]', true),
            'timestamp' => Foundation::getCurrentTime()
        ];
    }
    
    /**
     * Validate context information
     * 
     * @param array $context Context data
     * @throws Exception Nếu context không hợp lệ
     */
    private function validateContext(array $context): void {
        if (empty($context['user_id'])) {
            throw new Exception('Header X-User-ID là bắt buộc');
        }
        
        if (!is_array($context['user_roles'])) {
            throw new Exception('Header X-User-Roles phải là JSON array hợp lệ');
        }
        
        // Validate user_roles structure
        foreach ($context['user_roles'] as $role) {
            if (!isset($role['id'], $role['scope'])) {
                throw new Exception('Mỗi role phải có id và scope');
            }
        }
    }
    
    /**
     * Tạo response lỗi chuẩn cho permission denied
     * 
     * @param string $message Thông báo lỗi
     * @param array $details Chi tiết lỗi
     * @return array Response lỗi theo format JSend
     */
    public static function createPermissionDeniedResponse(string $message, array $details = []): array {
        return [
            'status' => 'error',
            'message' => $message,
            'code' => 403,
            'details' => $details,
            'timestamp' => Foundation::getCurrentTime()
        ];
    }
}