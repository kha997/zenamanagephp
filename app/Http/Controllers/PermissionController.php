<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\RBAC\Models\Permission;
use Src\RBAC\Resources\PermissionResource;

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
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
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

        // Phát sự kiện
        $this->eventBus->publish('rbac.permission.created', [
            'permissionId' => $permission->id,
            'code' => $permission->code,
            'module' => $permission->module,
            'action' => $permission->action,
            'actorId' => $request->get('user_id'),
            'timestamp' => now()->toISOString()
        ]);

        return response()->json([
            'status' => 'success',
            'data' => ['permission' => PermissionResource::make($permission)]
        ], 201);
    }
}