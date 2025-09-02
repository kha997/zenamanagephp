<?php
declare(strict_types=1);

namespace Src\Foundation\Decorators;

use Src\Foundation\Middleware\PermissionMiddleware;
use Src\Foundation\PermissionContext;
use Exception;

/**
 * Decorator để khai báo quyền yêu cầu cho các API endpoint
 */
class RequirePermissions {
    private array $requiredPermissions;
    private PermissionMiddleware $middleware;
    
    /**
     * Constructor
     * 
     * @param array $requiredPermissions Danh sách quyền yêu cầu
     */
    public function __construct(array $requiredPermissions) {
        $this->requiredPermissions = $requiredPermissions;
        $this->middleware = new PermissionMiddleware();
    }
    
    /**
     * Thực thi kiểm tra quyền trước khi gọi method
     * 
     * @param callable $callback Method cần được bảo vệ
     * @param array $request Request data
     * @return mixed Kết quả của method hoặc lỗi permission
     */
    public function execute(callable $callback, array $request) {
        try {
            // Kiểm tra quyền
            $result = $this->middleware->handle($request, $this->requiredPermissions);
            
            // Thiết lập context cho request
            PermissionContext::setContext($result['context']);
            PermissionContext::setContext(array_merge(
                $result['context'],
                ['permissions' => $result['permissions']]
            ));
            
            // Thực thi method gốc
            return $callback($request);
            
        } catch (Exception $e) {
            return PermissionMiddleware::createPermissionDeniedResponse(
                $e->getMessage(),
                ['required_permissions' => $this->requiredPermissions]
            );
        } finally {
            // Xóa context sau khi hoàn thành
            PermissionContext::clearContext();
        }
    }
    
    /**
     * Static method để tạo decorator nhanh
     * 
     * @param array $permissions Danh sách quyền yêu cầu
     * @return self
     */
    public static function require(array $permissions): self {
        return new self($permissions);
    }
}