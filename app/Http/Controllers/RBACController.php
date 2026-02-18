<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\RBAC\Models\Permission;
use Src\RBAC\Models\Role;

/**
 * Controller tổng hợp cho các tính năng RBAC nâng cao
 */
class RBACController
{
    private RBACManager $rbacManager;
    private EventBus $eventBus;

    public function __construct(RBACManager $rbacManager, EventBus $eventBus)
    {
        $this->rbacManager = $rbacManager;
        $this->eventBus = $eventBus;
    }

    /**
     * Lấy effective permissions của user trong context cụ thể
     * GET /api/v1/rbac/users/{user}/effective-permissions
     */
    public function getUserEffectivePermissions(Request $request, int $userId): JsonResponse
    {
        try {
            $projectId = $request->get('project_id');
            $tenantId = TenantContext::id($request);
            
            $effectivePermissions = $this->rbacManager->getUserEffectivePermissions(
                $userId,
                $projectId,
                $tenantId
            );
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'user_id' => $userId,
                    'project_id' => $projectId,
                    'tenant_id' => $tenantId,
                    'effective_permissions' => $effectivePermissions,
                    'computed_at' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi tính effective permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kiểm tra permission cụ thể của user
     * POST /api/v1/rbac/users/{user}/check-permission
     */
    public function checkUserPermission(Request $request, int $userId): JsonResponse
    {
        $permissionCode = $request->get('permission_code');
        $projectId = $request->get('project_id');
        $tenantId = TenantContext::id($request);
        
        if (empty($permissionCode)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission code không được để trống'
            ], 400);
        }
        
        try {
            $hasPermission = $this->rbacManager->userHasPermission(
                $userId,
                $permissionCode,
                $projectId,
                $tenantId
            );
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'user_id' => $userId,
                    'permission_code' => $permissionCode,
                    'project_id' => $projectId,
                    'tenant_id' => $tenantId,
                    'has_permission' => $hasPermission,
                    'checked_at' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi kiểm tra permission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách roles có sẵn theo scope
     * GET /api/v1/rbac/roles/by-scope
     */
    public function getRolesByScope(Request $request): JsonResponse
    {
        $scope = $request->get('scope', 'all');
        $tenantId = TenantContext::id($request);
        $query = Role::query();
        
        if ($scope !== 'all') {
            $validScopes = ['system', 'custom', 'project'];
            if (!in_array($scope, $validScopes, true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Scope không hợp lệ. Chỉ chấp nhận: ' . implode(', ', $validScopes)
                ], 400);
            }
            
            $query->where('scope', $scope);
        }
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        $roles = $query->with('permissions')->orderBy('scope')->orderBy('name')->get();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'roles' => $roles,
                'scope_filter' => $scope,
                'tenant_id' => $tenantId
            ]
        ]);
    }

    /**
     * Lấy permission hierarchy (modules và actions)
     * GET /api/v1/rbac/permissions/hierarchy
     */
    public function getPermissionHierarchy(): JsonResponse
    {
        $permissions = Permission::select('module', 'action', 'code', 'description')
                                ->orderBy('module')
                                ->orderBy('action')
                                ->get();
        
        $hierarchy = [];
        
        foreach ($permissions as $permission) {
            if (!isset($hierarchy[$permission->module])) {
                $hierarchy[$permission->module] = [
                    'module' => $permission->module,
                    'actions' => []
                ];
            }
            
            $hierarchy[$permission->module]['actions'][] = [
                'action' => $permission->action,
                'code' => $permission->code,
                'description' => $permission->description
            ];
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'hierarchy' => array_values($hierarchy),
                'total_modules' => count($hierarchy),
                'total_permissions' => $permissions->count()
            ]
        ]);
    }

    /**
     * Bulk assign roles cho multiple users
     * POST /api/v1/rbac/bulk-assign-roles
     */
    public function bulkAssignRoles(Request $request): JsonResponse
    {
        $userIds = $request->get('user_ids', []);
        $roleIds = $request->get('role_ids', []);
        $projectId = $request->get('project_id');
        $scope = $request->get('scope', 'system'); // system hoặc project
        
        if (empty($userIds) || empty($roleIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'user_ids và role_ids không được để trống'
            ], 400);
        }
        
        if ($scope === 'project' && empty($projectId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'project_id bắt buộc khi scope = project'
            ], 400);
        }
        
        try {
            $results = [];
            $actorId = $request->get('user_id');
            
            foreach ($userIds as $userId) {
                foreach ($roleIds as $roleId) {
                    if ($scope === 'system') {
                        $this->rbacManager->assignSystemRole($userId, $roleId, $actorId);
                    } else {
                        $this->rbacManager->assignProjectRole($userId, $projectId, $roleId, $actorId);
                    }
                    
                    $results[] = [
                        'user_id' => $userId,
                        'role_id' => $roleId,
                        'project_id' => $projectId,
                        'scope' => $scope,
                        'assigned' => true
                    ];
                }
            }
            
            // Phát sự kiện
            $this->eventBus->publish('rbac.roles.bulk.assigned', [
                'userIds' => $userIds,
                'roleIds' => $roleIds,
                'projectId' => $projectId,
                'scope' => $scope,
                'actorId' => $actorId,
                'timestamp' => now()->toISOString()
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Bulk assign roles thành công',
                'data' => [
                    'results' => $results,
                    'total_assignments' => count($results)
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi bulk assign roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy audit log của RBAC changes
     * GET /api/v1/rbac/audit-log
     */
    public function getAuditLog(Request $request): JsonResponse
    {
        // Tìm kiếm trong event logs với pattern rbac.*
        $query = \DB::table('event_logs')
                    ->where('event_name', 'LIKE', 'rbac.%')
                    ->orderBy('created_at', 'desc');
        
        // Filter theo user
        if ($request->has('user_id')) {
            $query->where('actor_id', $request->get('user_id'));
        }
        
        // Filter theo project
        if ($request->has('project_id')) {
            $query->where('project_id', $request->get('project_id'));
        }
        
        // Filter theo event type
        if ($request->has('event_type')) {
            $eventType = $request->get('event_type');
            $query->where('event_name', 'LIKE', "rbac.{$eventType}.%");
        }
        
        // Filter theo thời gian
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->get('from_date'));
        }
        
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->get('to_date'));
        }
        
        $perPage = min((int) $request->get('per_page', 50), 200);
        $logs = $query->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'logs' => $logs->items(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage()
                ]
            ]
        ]);
    }
}
