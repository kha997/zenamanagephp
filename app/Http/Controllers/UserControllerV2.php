<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Src\Foundation\Utils\JSendResponse;

/**
 * User Controller V2 - Sử dụng SimpleJwtAuth middleware
 * 
 * @package App\Http\Controllers
 */
class UserControllerV2 extends Controller
{
    /**
     * Constructor - áp dụng SimpleJwtAuth middleware
     */
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'tenant.isolation', 'rbac']);
    }

    private const USER_ROLE_ALLOWLIST = ['super_admin', 'admin', 'pm'];

    private function authorizeUserManagement(Request $request): void
    {
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(self::USER_ROLE_ALLOWLIST)) {
            abort(403);
        }
    }

    /**
     * Lấy danh sách users
     * GET /api/v1/users-v2
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeUserManagement($request);

        try {
            $query = User::with(['tenant']);

            // Filter theo tenant (multi-tenancy)
            $tenantId = $request->get('tenant_context');
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            // Filter theo status
            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            // Search theo name hoặc email
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $users = $query->paginate($perPage);

            return JSendResponse::success([
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total()
                ]
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tạo user mới
     * POST /api/v1/users-v2
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeUserManagement($request);

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'tenant_id' => 'required|exists:tenants,id'
            ]);

            if ($validator->fails()) {
                return JSendResponse::fail($validator->errors(), 422);
            }

            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => bcrypt($request->input('password')),
                'tenant_id' => $request->input('tenant_id'),
                'status' => 'active'
            ]);

            return JSendResponse::success([
                'user' => $user->load('tenant'),
                'message' => 'User đã được tạo thành công'
            ], 201);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy thông tin chi tiết user
     * GET /api/v1/users-v2/{id}
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizeUserManagement($request);

        try {
            $user = User::with(['tenant'])->findOrFail($id);

            // Kiểm tra tenant access
            $tenantId = $request->get('tenant_context');
            if ($tenantId && $user->tenant_id !== $tenantId) {
                return JSendResponse::error('Không có quyền truy cập user này', 403);
            }

            return JSendResponse::success([
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cập nhật thông tin user
     * PUT/PATCH /api/v1/users-v2/{id}
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeUserManagement($request);

        try {
            $user = User::findOrFail($id);

            // Kiểm tra tenant access
            $tenantId = $request->get('tenant_context');
            if ($tenantId && $user->tenant_id !== $tenantId) {
                return JSendResponse::error('Không có quyền cập nhật user này', 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $id,
                'password' => 'sometimes|required|string|min:8|confirmed',
                'status' => 'sometimes|required|in:active,inactive,suspended'
            ]);

            if ($validator->fails()) {
                return JSendResponse::fail($validator->errors(), 422);
            }

            $updateData = $request->only(['name', 'email', 'status']);
            
            if ($request->has('password')) {
                $updateData['password'] = bcrypt($request->input('password'));
            }

            $user->update($updateData);

            return JSendResponse::success([
                'user' => $user->fresh(['tenant']),
                'message' => 'User đã được cập nhật thành công'
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xóa user
     * DELETE /api/v1/users-v2/{id}
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeUserManagement($request);

        try {
            $user = User::findOrFail($id);

            // Kiểm tra tenant access
            $tenantId = $request->get('tenant_context');
            if ($tenantId && $user->tenant_id !== $tenantId) {
                return JSendResponse::error('Không có quyền xóa user này', 403);
            }

            // Không cho phép xóa chính mình
            $currentUser = $request->get('auth_user');
            if ($currentUser && $currentUser->id === $id) {
                return JSendResponse::error('Không thể xóa chính mình', 400);
            }

            $user->delete();

            return JSendResponse::success([
                'message' => 'User đã được xóa thành công'
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể xóa user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy profile của user hiện tại
     * GET /api/v1/users-v2/profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $user = $request->get('auth_user');
            if (!$user) {
                return JSendResponse::error('User chưa đăng nhập', 401);
            }

            return JSendResponse::success([
                'user' => $user->load('tenant')
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy profile: ' . $e->getMessage(), 500);
        }
    }
}
