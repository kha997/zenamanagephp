<?php declare(strict_types=1);

namespace Src\RBAC\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Src\RBAC\Models\Permission;
use Src\RBAC\Resources\PermissionResource;
use Src\Foundation\EventBus;

/**
 * Controller quản lý permissions trong hệ thống RBAC
 */
class PermissionController
{
    private EventBus $eventBus;

    public function __construct(EventBus $eventBus)
    {
        $this->eventBus = $eventBus;
    }

    /**
     * Lấy danh sách permissions
     * GET /api/v1/rbac/permissions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query();

        // Filter theo module
        if ($request->has('module')) {
            $query->where('module', $request->get('module'));
        }

        // Filter theo action
        if ($request->has('action')) {
            $query->where('action', $request->get('action'));
        }

        // Search theo code hoặc description
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Group by module nếu yêu cầu
        if ($request->get('group_by') === 'module') {
            $permissions = $query->get()->groupBy('module');
            
            // Transform grouped data using PermissionResource
            $transformedData = [];
            foreach ($permissions as $module => $modulePermissions) {
                $transformedData[$module] = PermissionResource::collection($modulePermissions);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => ['permissions_by_module' => $transformedData]
            ]);
        }

        // Pagination
        $perPage = min((int) $request->get('per_page', 50), 200);
        $permissions = $query->orderBy('module')->orderBy('action')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => [
                'permissions' => PermissionResource::collection($permissions->items()),
                'pagination' => [
                    'current_page' => $permissions->currentPage(),
                    'per_page' => $permissions->perPage(),
                    'total' => $permissions->total(),
                    'last_page' => $permissions->lastPage()
                ]
            ]
        ]);
    }

    /**
     * Tạo permission mới
     * POST /api/v1/rbac/permissions
     */
    public function store(Request $request): JsonResponse
    {
        // Validation
        $errors = [];
        
        $module = $request->get('module');
        $action = $request->get('action');
        
        if (empty($module)) {
            $errors['module'] = 'Module không được để trống';
        }
        
        if (empty($action)) {
            $errors['action'] = 'Action không được để trống';
        }
        
        if (!empty($module) && !empty($action)) {
            $code = Permission::generateCode($module, $action);
            
            if (Permission::where('code', $code)->exists()) {
                $errors['code'] = 'Permission với code này đã tồn tại: ' . $code;
            }
        }
        
        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $errors
            ], 400);
        }

        // Tạo permission
        $permission = Permission::create([
            'code' => Permission::generateCode($module, $action),
            'module' => $module,
            'action' => $action,
            'description' => $request->get('description')
        ]);

        // Phát sự kiện — GAP-042 Gate-3 Round-1 Correction 1: entityId/
        // projectId are validator-required fields the prior code omitted
        // (causing this now-live route to mutate the row THEN throw via
        // EventBus::validatePayload()); actorId is the real authenticated
        // user (Correction 6), never a client-suppliable request field.
        // Permissions carry no tenant_id/project concept (§2e/§3), so the
        // established 'system' convention is used for projectId.
        $this->eventBus->publish('rbac.permission.created', [
            'entityId' => $permission->id,
            'projectId' => 'system',
            'permissionId' => $permission->id,
            'code' => $permission->code,
            'module' => $permission->module,
            'action' => $permission->action,
            'actorId' => (string) ($request->user()?->id ?? 'system'),
            'timestamp' => now()->toISOString()
        ]);

        return response()->json([
            'status' => 'success',
            'data' => ['permission' => PermissionResource::make($permission)]
        ], 201);
    }

    /**
     * Lấy thông tin permission cụ thể.
     * GET /api/v1/rbac/permissions/{id}
     *
     * GAP-042 §0.1 / §2a-analogue: this route was already live and wired but
     * targeted a nonexistent method (unconditional HTTP 500) — restored using
     * the same remediation pattern §2a already established for
     * AssignmentController's missing methods. Permissions carry no tenant_id
     * column (§2e/§3) — no tenant scoping applies here, unlike RoleController.
     */
    public function show(string $id): JsonResponse
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission không tồn tại'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => ['permission' => PermissionResource::make($permission)]
        ]);
    }

    /**
     * Cập nhật permission.
     * PUT /api/v1/rbac/permissions/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission không tồn tại'
            ], 404);
        }

        $permission->update($request->only(['description']));

        $this->eventBus->publish('rbac.permission.updated', [
            'entityId' => $permission->id,
            'projectId' => 'system',
            'permissionId' => $permission->id,
            'code' => $permission->code,
            'actorId' => (string) ($request->user()?->id ?? 'system'),
            'timestamp' => now()->toISOString()
        ]);

        return response()->json([
            'status' => 'success',
            'data' => ['permission' => PermissionResource::make($permission->fresh())]
        ]);
    }

    /**
     * Xóa permission.
     * DELETE /api/v1/rbac/permissions/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission không tồn tại'
            ], 404);
        }

        $inUse = $permission->roles()->exists();

        if ($inUse) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xóa permission đang được sử dụng'
            ], 400);
        }

        $permissionId = $permission->id;
        $permission->delete();

        $this->eventBus->publish('rbac.permission.deleted', [
            'entityId' => $permissionId,
            'projectId' => 'system',
            'permissionId' => $permissionId,
            'actorId' => (string) ($request->user()?->id ?? 'system'),
            'timestamp' => now()->toISOString()
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'message' => 'Permission đã được xóa thành công'
            ]
        ]);
    }
}