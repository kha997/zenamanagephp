<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Src\Foundation\Utils\JSendResponse;

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
                return JSendResponse::error('Không có quyền xem danh sách users', 403);
            }

            $result = $this->userService->getUsers($request);
            return JSendResponse::success($result);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách users: ' . $e->getMessage(), 500);
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
                return JSendResponse::error('Không có quyền tạo user', 403);
            }

            $result = $this->userService->createUser($request);
            return JSendResponse::success($result, 201);

        } catch (\InvalidArgumentException $e) {
            return JSendResponse::fail(['validation' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo user: ' . $e->getMessage(), 500);
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
                return JSendResponse::error('Không có quyền xem thông tin user', 403);
            }

            $user = $this->userService->getUserById($request, $id);
            return JSendResponse::success(['user' => $user]);

        } catch (\UnauthorizedHttpException $e) {
            return JSendResponse::error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin user: ' . $e->getMessage(), 500);
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
                return JSendResponse::error('Không có quyền cập nhật user', 403);
            }

            $result = $this->userService->updateUser($request, $id);
            return JSendResponse::success($result);

        } catch (\UnauthorizedHttpException $e) {
            return JSendResponse::error($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            return JSendResponse::fail(['validation' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật user: ' . $e->getMessage(), 500);
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
                return JSendResponse::error('Không có quyền xóa user', 403);
            }

            $result = $this->userService->deleteUser($request, $id);
            return JSendResponse::success($result);

        } catch (\UnauthorizedHttpException $e) {
            return JSendResponse::error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể xóa user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Kiểm tra quyền truy cập
     */
    private function hasPermission(Request $request, string $permission): bool
    {
        // TODO: Implement permission checking logic
        // This should integrate with your RBAC system
        return true; // Temporary implementation
    }
}