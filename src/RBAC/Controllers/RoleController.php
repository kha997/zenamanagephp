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
use App\Services\TenantContext;

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
        $tenantId = TenantContext::id($request);
        $query = Role::query()->tenantVisible($tenantId);

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

        // GAP-042 §6 global-role read/write policy: a tenant-scoped POST /roles
        // request may never create a scope=system (global) role.
        if ($scope === Role::SCOPE_SYSTEM) {
            $errors['scope'] = 'Không thể tạo role scope=system qua endpoint này';
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

        // GAP-042 §6: tenant_id is always server-derived from the authenticated
        // request's own tenant context — never accepted from the client body.
        $tenantId = TenantContext::id($request);

        // Tạo role
        $role = Role::create([
            'name' => $request->get('name'),
            'scope' => $scope,
            'allow_override' => (bool) $request->get('allow_override', false),
            'description' => $request->get('description'),
            'tenant_id' => $tenantId,
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
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = TenantContext::id($request);
        $role = Role::tenantVisible($tenantId)->with('permissions')->whereKey($id)->first();

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
        $tenantId = TenantContext::id($request);
        $role = Role::tenantVisible($tenantId)->whereKey($id)->first();

        // GAP-042 §6 global-role read/write policy: a role that is visible
        // (global, or another tenant filtered out already by the predicate
        // above) but globally-owned may not be mutated through this surface —
        // treated identically to "not found for write purposes".
        if (!$role || $role->isGlobal()) {
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
        $tenantId = TenantContext::id($request);
        $role = Role::tenantVisible($tenantId)->whereKey($id)->first();

        if (!$role || $role->isGlobal()) {
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
        $tenantId = TenantContext::id($request);
        $role = Role::tenantVisible($tenantId)->whereKey($id)->first();

        if (!$role || $role->isGlobal()) {
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

        // Validate permission codes tồn tại, resolve code -> id (BelongsToMany::sync()
        // requires the related model's primary key, not its code — GAP-042 fix, this
        // previously passed codes directly into sync() and silently failed).
        $validPermissions = Permission::whereIn('code', $permissionCodes)->get(['id', 'code']);
        $validCodes = $validPermissions->pluck('code')->toArray();
        $validIds = $validPermissions->pluck('id')->toArray();
        $invalidCodes = array_diff($permissionCodes, $validCodes);

        if (!empty($invalidCodes)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission codes không hợp lệ: ' . implode(', ', $invalidCodes)
            ], 400);
        }

        // Sync permissions
        $oldPermissions = $role->permissions->pluck('code')->toArray();
        $role->permissions()->sync($validIds);
        $newPermissions = $role->fresh()->permissions->pluck('code')->toArray();

        // Phát sự kiện
        $this->eventBus->publish('rbac.role.permissionsSynced', [
            'entityId' => $role->id,
            'projectId' => (string) ($tenantId ?? 'system'),
            'roleId' => $role->id,
            'oldPermissions' => $oldPermissions,
            'newPermissions' => $newPermissions,
            'actorId' => (string) ($request->get('user_id') ?? $request->user()?->id ?? 'system'),
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
