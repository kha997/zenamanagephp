<?php
declare(strict_types=1);

require_once '../src/Foundation/Permission.php';
require_once '../src/Foundation/Middleware/PermissionMiddleware.php';
require_once '../src/Foundation/PermissionContext.php';
require_once '../src/Foundation/Decorators/RequirePermissions.php';

use zenamanage\Foundation\Decorators\RequirePermissions;
use zenamanage\Foundation\PermissionContext;

/**
 * Ví dụ API endpoint được bảo vệ bởi permission
 */
class TaskController {
    /**
     * Tạo task mới - yêu cầu quyền 'task.create'
     */
    public function createTask(array $request): array {
        $decorator = RequirePermissions::require(['task.create']);
        
        return $decorator->execute(function($request) {
            // Logic tạo task
            $userId = PermissionContext::getUserId();
            $projectId = PermissionContext::getProjectId();
            
            return [
                'status' => 'success',
                'data' => [
                    'message' => 'Task created successfully',
                    'created_by' => $userId,
                    'project_id' => $projectId
                ]
            ];
        }, $request);
    }
    
    /**
     * Xóa task - yêu cầu quyền 'task.delete'
     */
    public function deleteTask(array $request): array {
        $decorator = RequirePermissions::require(['task.delete']);
        
        return $decorator->execute(function($request) {
            // Kiểm tra quyền bổ sung nếu cần
            if (!PermissionContext::hasPermission('task.delete.any')) {
                // Chỉ cho phép xóa task của chính mình
                $taskOwnerId = $request['task_owner_id'] ?? null;
                if ($taskOwnerId !== PermissionContext::getUserId()) {
                    throw new Exception('Bạn chỉ có thể xóa task của chính mình');
                }
            }
            
            return [
                'status' => 'success',
                'data' => ['message' => 'Task deleted successfully']
            ];
        }, $request);
    }
}

// Ví dụ request
$request = [
    'headers' => [
        'X-User-ID' => 'user123',
        'X-Project-ID' => 'project456',
        'X-Tenant-ID' => 'tenant789',
        'X-User-Roles' => json_encode([
            ['id' => 'role1', 'scope' => 'system'],
            ['id' => 'role2', 'scope' => 'project', 'project_id' => 'project456']
        ])
    ],
    'task_owner_id' => 'user123'
];

$controller = new TaskController();
$result = $controller->createTask($request);
echo json_encode($result, JSON_PRETTY_PRINT);