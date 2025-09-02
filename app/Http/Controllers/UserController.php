<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Src\Foundation\Utils\JSendResponse;
use Src\RBAC\Middleware\RBACMiddleware;
use Src\RBAC\Traits\HasRBACContext;

/**
 * Controller xử lý các hoạt động CRUD cho User
 * 
 * @package App\Http\Controllers
 */
class UserController extends Controller
{
    use HasRBACContext;

    /**
     * Constructor - áp dụng RBAC middleware
     */
    public function __construct()
    {
        // Xóa middleware khỏi constructor - sẽ áp dụng trong routes
        // $this->middleware(RBACMiddleware::class);
    }

    /**
     * Lấy danh sách users
     * GET /api/v1/users
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'user.view')) {
                return JSendResponse::error('Không có quyền xem danh sách users', 403);
            }

            $query = User::with(['tenant']);

            // Filter theo tenant (multi-tenancy)
            $tenantId = $this->getCurrentTenantId($request);
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
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
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
     * POST /api/v1/users
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'user.create')) {
                return JSendResponse::error('Không có quyền tạo user', 403);
            }

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
                'password' => Hash::make($request->input('password')),
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
     * GET /api/v1/users/{id}
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'user.view')) {
                return JSendResponse::error('Không có quyền xem thông tin user', 403);
            }

            $user = User::with(['tenant'])->findOrFail($id);

            // Kiểm tra tenant access
            $tenantId = $this->getCurrentTenantId($request);
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
     * PUT/PATCH /api/v1/users/{id}
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'user.update')) {
                return JSendResponse::error('Không có quyền cập nhật user', 403);
            }

            $user = User::findOrFail($id);

            // Kiểm tra tenant access
            $tenantId = $this->getCurrentTenantId($request);
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
                $updateData['password'] = Hash::make($request->input('password'));
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
     * DELETE /api/v1/users/{id}
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'user.delete')) {
                return JSendResponse::error('Không có quyền xóa user', 403);
            }

            $user = User::findOrFail($id);

            // Kiểm tra tenant access
            $tenantId = $this->getCurrentTenantId($request);
            if ($tenantId && $user->tenant_id !== $tenantId) {
                return JSendResponse::error('Không có quyền xóa user này', 403);
            }

            // Không cho phép xóa chính mình
            $currentUserId = $this->getCurrentUserId($request);
            if ($currentUserId === $id) {
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
     * GET /api/v1/users/profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId($request);
            if (!$userId) {
                return JSendResponse::error('User chưa đăng nhập', 401);
            }

            $user = User::with(['tenant'])->findOrFail($userId);

            return JSendResponse::success([
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cập nhật profile của user hiện tại
     * PUT /api/v1/users/profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId($request);
            if (!$userId) {
                return JSendResponse::error('User chưa đăng nhập', 401);
            }

            $user = User::findOrFail($userId);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $userId,
                'current_password' => 'required_with:password|string',
                'password' => 'sometimes|required|string|min:8|confirmed'
            ]);

            if ($validator->fails()) {
                return JSendResponse::fail($validator->errors(), 422);
            }

            // Kiểm tra current password nếu muốn đổi password
            if ($request->has('password')) {
                if (!Hash::check($request->input('current_password'), $user->password)) {
                    return JSendResponse::fail([
                        'current_password' => ['Mật khẩu hiện tại không đúng']
                    ], 422);
                }
            }

            $updateData = $request->only(['name', 'email']);
            
            if ($request->has('password')) {
                $updateData['password'] = Hash::make($request->input('password'));
            }

            $user->update($updateData);

            return JSendResponse::success([
                'user' => $user->fresh(['tenant']),
                'message' => 'Profile đã được cập nhật thành công'
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật profile: ' . $e->getMessage(), 500);
        }
    }
}