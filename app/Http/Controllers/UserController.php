<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Support\ApiResponse;

/**
 * Controller xử lý các hoạt động CRUD cho User
 * 
 * @package App\Http\Controllers
 */
class UserController extends Controller
{
    private UserManagementService $userService;

    /**
     * Constructor - inject UserManagementService
     */
    public function __construct(UserManagementService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Lấy danh sách users
     * GET /api/v1/users
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (!$this->hasPermission($request, 'user.view')) {
                return ApiResponse::error('Không có quyền xem danh sách users', 403);
            }

            $result = $this->userService->getUsers($request);
            return ApiResponse::success($result);

        } catch (\Exception $e) {
            return ApiResponse::error('Không thể lấy danh sách users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tạo user mới
     * POST /api/v1/users
     */
    public function store(Request $request): JsonResponse
    {
        try {
            if (!$this->hasPermission($request, 'user.create')) {
                return ApiResponse::error('Không có quyền tạo user', 403);
            }

            $result = $this->userService->createUser($request);
            return ApiResponse::success($result, 201);

        } catch (\InvalidArgumentException $e) {
            return ApiResponse::fail(['validation' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Không thể tạo user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy thông tin chi tiết user
     * GET /api/v1/users/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            if (!$this->hasPermission($request, 'user.view')) {
                return ApiResponse::error('Không có quyền xem thông tin user', 403);
            }

            $user = $this->userService->getUserById($request, $id);
            return ApiResponse::success(['user' => $user]);

        } catch (\UnauthorizedHttpException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return ApiResponse::error('Không thể lấy thông tin user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cập nhật thông tin user
     * PUT/PATCH /api/v1/users/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            if (!$this->hasPermission($request, 'user.update')) {
                return ApiResponse::error('Không có quyền cập nhật user', 403);
            }

            $result = $this->userService->updateUser($request, $id);
            return ApiResponse::success($result);

        } catch (\UnauthorizedHttpException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::fail(['validation' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Không thể cập nhật user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xóa user
     * DELETE /api/v1/users/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            if (!$this->hasPermission($request, 'user.delete')) {
                return ApiResponse::error('Không có quyền xóa user', 403);
            }

            $result = $this->userService->deleteUser($request, $id);
            return ApiResponse::success($result);

        } catch (\UnauthorizedHttpException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return ApiResponse::error('Không thể xóa user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Kiểm tra quyền truy cập
     */
    private function hasPermission(Request $request, string $permission): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Check if user has the specific permission
        // This should integrate with your RBAC system (e.g., Spatie Permissions)
        return $user->can($permission);
    }
}