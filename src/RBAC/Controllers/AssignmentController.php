<?php declare(strict_types=1);

namespace Src\RBAC\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Src\RBAC\Services\RBACManager;
use Src\RBAC\Models\Role;

/**
 * Controller quản lý việc gán role cho user
 * Hỗ trợ 3 lớp: system, custom, project
 */
class AssignmentController
{
    private RBACManager $rbacManager;

    public function __construct(RBACManager $rbacManager)
    {
        $this->rbacManager = $rbacManager;
    }

    /**
     * Gán role hệ thống cho user
     * POST /api/v1/rbac/assign/system
     */
    public function assignSystem(Request $request): JsonResponse
    {
        $errors = [];
        
        $userId = $request->get('user_id');
        $roleId = $request->get('role_id');
        
        if (empty($userId)) {
            $errors['user_id'] = 'User ID không được để trống';
        }
        
        if (empty($roleId)) {
            $errors['role_id'] = 'Role ID không được để trống';
        }
        
        // Kiểm tra role có scope system không
        $role = Role::find($roleId);
        if (!$role || $role->scope !== Role::SCOPE_SYSTEM) {
            $errors['role_id'] = 'Role không tồn tại hoặc không phải scope system';
        }
        
        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $errors
            ], 400);
        }

        $success = $this->rbacManager->assignSystemRole($userId, $roleId);
        
        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gán role'
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gán role system thành công'
        ]);
    }

    /**
     * Gán role tùy chỉnh cho user
     * POST /api/v1/rbac/assign/custom
     */
    public function assignCustom(Request $request): JsonResponse
    {
        $errors = [];
        
        $userId = $request->get('user_id');
        $roleId = $request->get('role_id');
        
        if (empty($userId)) {
            $errors['user_id'] = 'User ID không được để trống';
        }
        
        if (empty($roleId)) {
            $errors['role_id'] = 'Role ID không được để trống';
        }
        
        // Kiểm tra role có scope custom không
        $role = Role::find($roleId);
        if (!$role || $role->scope !== Role::SCOPE_CUSTOM) {
            $errors['role_id'] = 'Role không tồn tại hoặc không phải scope custom';
        }
        
        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $errors
            ], 400);
        }

        $success = $this->rbacManager->assignCustomRole($userId, $roleId);
        
        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gán role'
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gán role custom thành công'
        ]);
    }

    /**
     * Gán role dự án cho user
     * POST /api/v1/rbac/assign/project
     */
    public function assignProject(Request $request): JsonResponse
    {
        $errors = [];
        
        $userId = $request->get('user_id');
        $roleId = $request->get('role_id');
        $projectId = $request->get('project_id');
        
        if (empty($userId)) {
            $errors['user_id'] = 'User ID không được để trống';
        }
        
        if (empty($roleId)) {
            $errors['role_id'] = 'Role ID không được để trống';
        }
        
        if (empty($projectId)) {
            $errors['project_id'] = 'Project ID không được để trống';
        }
        
        // Kiểm tra role có scope project không
        $role = Role::find($roleId);
        if (!$role || $role->scope !== Role::SCOPE_PROJECT) {
            $errors['role_id'] = 'Role không tồn tại hoặc không phải scope project';
        }
        
        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $errors
            ], 400);
        }

        $success = $this->rbacManager->assignProjectRole($userId, $roleId, $projectId);
        
        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gán role'
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gán role project thành công'
        ]);
    }

    /**
     * Lấy quyền hiệu lực của user
     * GET /api/v1/rbac/effective-permissions
     */
    public function getEffectivePermissions(Request $request): JsonResponse
    {
        $userId = (int) $request->get('user_id');
        $projectId = $request->has('project_id') ? (int) $request->get('project_id') : null;
        
        if ($userId <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'User ID không hợp lệ'
            ], 400);
        }

        $permissions = $this->rbacManager->calculateEffectivePermissions($userId, $projectId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user_id' => $userId,
                'project_id' => $projectId,
                'effective_permissions' => $permissions,
                'permission_count' => count($permissions)
            ]
        ]);
    }
}