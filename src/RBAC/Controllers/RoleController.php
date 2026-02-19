<?php declare(strict_types=1);

namespace Src\RBAC\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Src\RBAC\Resources\RoleResource;
use Src\RBAC\Resources\RoleCollection;
use Src\RBAC\Services\RBACManager;
use Src\Foundation\EventBus;
use Src\Foundation\Helpers\ValidationHelper;

/**
 * Controller quản lý roles trong hệ thống RBAC
 * Hỗ trợ CRUD operations và sync permissions
 */
class RoleController
{
    private RBACManager $rbacManager;
    private EventBus $eventBus;

    public function __construct(RBACManager $rbacManager, EventBus $eventBus)
    {
        $this->rbacManager = $rbacManager;
        $this->eventBus = $eventBus;
    }

    /**
     * Lấy danh sách roles
     * GET /api/v1/rbac/roles
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::query();

        // Filter theo scope nếu có
        if ($request->has('scope')) {
            $scope = $request->get('scope');
            if (in_array($scope, Role::VALID_SCOPES, true)) {
                $query->where('scope', $scope);
            }
        }

        // Pagination
        $perPage = min((int) $request->get('per_page', 15), 100);
        $roles = $query->with('permissions')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => [
                'roles' => RoleCollection::make($roles->items()),
                'pagination' => [
                    'current_page' => $roles->currentPage(),
                    'per_page' => $roles->perPage(),
                    'total' => $roles->total(),
                    'last_page' => $roles->lastPage()
                ]
            ]
        ]);
    }

    /**
     * Tạo role mới
     * POST /api/v1/rbac/roles
     */
    public function store(Request $request): JsonResponse
    {
        // Validation
        $errors = [];
        
        if (empty($request->get('name'))) {
            $errors['name'] = 'Tên role không được để trống';
        }
        
        $scope = $request->get('scope');
        if (!in_array($scope, Role::VALID_SCOPES, true)) {
            $errors['scope'] = 'Scope không hợp lệ. Chỉ chấp nhận: ' . implode(', ', Role::VALID_SCOPES);
        }
        
        // Kiểm tra trùng lặp name + scope
        if (Role::where('name', $request->get('name'))
               ->where('scope', $scope)
               ->exists()) {
            $errors['name'] = 'Role với tên này đã tồn tại trong scope ' . $scope;
        }
        
        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $errors
            ], 400);
        }

        // Tạo role
        $role = Role::create([
            'name' => $request->get('name'),
            'scope' => $scope,
            'allow_override' => (bool) $request->get('allow_override', false),
            'description' => $request->get('description')
        ]);

        // Phát sự kiện
        $this->eventBus->publish('rbac.role.created', [
            'entityId' => $role->id,
            'projectId' => (string) ($request->attributes->get('tenant_id') ?? 'system'),
            'actorId' => (string) ($request->user()?->id ?? 'system'),
            'roleId' => $role->id,
            'name' => $role->name,
            'scope' => $role->scope,
            'timestamp' => now()->toISOString()
        ]);

        return response()->json([
            'status' => 'success',
            'data' => ['role' => RoleResource::make($role)]
        ], 201);
    }

    /**
     * Lấy thông tin role cụ thể
     * GET /api/v1/rbac/roles/{id}
     */
    public function show(string $id): JsonResponse
    {
        $role = Role::with('permissions')->find($id);
        
        if (!$role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role không tồn tại'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => ['role' => RoleResource::make($role)]
        ]);
    }

    /**
     * Cập nhật role
     * PUT /api/v1/rbac/roles/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);
        
        if (!$role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role không tồn tại'
            ], 404);
        }

        // Validation tương tự store
        $errors = [];
        
        if ($request->has('name') && empty($request->get('name'))) {
            $errors['name'] = 'Tên role không được để trống';
        }
        
        if ($request->has('scope')) {
            $scope = $request->get('scope');
            if (!in_array($scope, Role::VALID_SCOPES, true)) {
                $errors['scope'] = 'Scope không hợp lệ';
            }
        }
        
        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $errors
            ], 400);
        }

        // Cập nhật
        $oldData = $role->toArray();
        
        $role->update($request->only([
            'name', 'scope', 'allow_override', 'description'
        ]));

        // Phát sự kiện
        $this->eventBus->publish('rbac.role.updated', [
            'entityId' => $role->id,
            'projectId' => (string) ($request->attributes->get('tenant_id') ?? 'system'),
            'actorId' => (string) ($request->user()?->id ?? 'system'),
            'roleId' => $role->id,
            'oldData' => $oldData,
            'newData' => $role->fresh()->toArray(),
            'timestamp' => now()->toISOString()
        ]);

        return response()->json([
            'status' => 'success',
            'data' => ['role' => RoleResource::make($role->fresh()->load('permissions'))]
        ]);
    }

    /**
     * Xóa role
     * DELETE /api/v1/rbac/roles/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);
        
        if (!$role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role không tồn tại'
            ], 404);
        }

        // Kiểm tra role có đang được sử dụng không
        $inUse = $role->systemUsers()->exists();

        if (method_exists($role, 'customUsers')) {
            $inUse = $inUse || $role->customUsers()->exists();
        }

        if (method_exists($role, 'projectUsers')) {
            $inUse = $inUse || $role->projectUsers()->exists();
        }
        
        if ($inUse) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xóa role đang được sử dụng'
            ], 400);
        }

        $roleData = $role->toArray();
        $role->delete();

        // Phát sự kiện
        $this->eventBus->publish('rbac.role.deleted', [
            'entityId' => $id,
            'projectId' => (string) ($request->attributes->get('tenant_id') ?? 'system'),
            'actorId' => (string) ($request->user()?->id ?? 'system'),
            'roleId' => $id,
            'roleData' => $roleData,
            'timestamp' => now()->toISOString()
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'message' => 'Vai trò đã được xóa thành công'
            ]
        ]);
    }

    /**
     * Sync permissions cho role
     * POST /api/v1/rbac/roles/{id}/permissions:sync
     */
    public function syncPermissions(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);
        
        if (!$role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role không tồn tại'
            ], 404);
        }

        $permissionCodes = $request->get('permission_codes', []);
        
        if (!is_array($permissionCodes)) {
            return response()->json([
                'status' => 'error',
                'message' => 'permission_codes phải là array'
            ], 400);
        }

        // Validate permission codes tồn tại
        $validCodes = Permission::whereIn('code', $permissionCodes)->pluck('code')->toArray();
        $invalidCodes = array_diff($permissionCodes, $validCodes);
        
        if (!empty($invalidCodes)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission codes không hợp lệ: ' . implode(', ', $invalidCodes)
            ], 400);
        }

        // Sync permissions
        $oldPermissions = $role->permissions->pluck('code')->toArray();
        $role->permissions()->sync($validCodes);
        $newPermissions = $role->fresh()->permissions->pluck('code')->toArray();

        // Phát sự kiện
        $this->eventBus->publish('rbac.role.permissions.synced', [
            'roleId' => $role->id,
            'oldPermissions' => $oldPermissions,
            'newPermissions' => $newPermissions,
            'actorId' => $request->get('user_id'),
            'timestamp' => now()->toISOString()
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'role' => RoleResource::make($role->fresh()->load('permissions')),
                'synced_permissions' => $newPermissions
            ]
        ]);
    }
}
