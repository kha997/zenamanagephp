<?php declare(strict_types=1);

namespace Src\RBAC\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Support\ApiResponse;
use Src\RBAC\Services\AuthService;

/**
 * Controller xử lý authentication với JWT
 * 
 * @package Src\RBAC\Controllers
 */
class AuthController
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Đăng nhập user
     * POST /api/v1/auth/login
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();
            $result = $this->authService->login($credentials);

            if (!$result['success']) {
                return ApiResponse::fail([
                    'message' => $result['message']
                ], 401);
            }

            return ApiResponse::success([
                'user' => $result['user'],
                'token' => $result['token'],
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Đăng nhập thất bại: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Đăng ký user mới
     * POST /api/v1/auth/register
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $userData = $request->getUserData();
            $tenantData = $request->getTenantData();
            
            $result = $this->authService->register($userData, $tenantData);

            if (!$result['success']) {
                return ApiResponse::fail([
                    'message' => $result['message']
                ], 400);
            }

            return ApiResponse::success([
                'user' => $result['user'],
                'tenant' => $result['tenant'],
                'token' => $result['token'],
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'message' => 'Đăng ký thành công'
            ], 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Đăng ký thất bại: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy thông tin user hiện tại
     * GET /api/v1/auth/me
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $this->authService->getCurrentUser();
            
            if (!$user) {
                return ApiResponse::fail([
                    'message' => 'User không tồn tại'
                ], 404);
            }

            return ApiResponse::success([
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Không thể lấy thông tin user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Refresh JWT token
     * POST /api/v1/auth/refresh
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        try {
            $result = $this->authService->refreshToken();

            if (!$result['success']) {
                return ApiResponse::fail([
                    'message' => $result['message']
                ], 401);
            }

            return ApiResponse::success([
                'token' => $result['token'],
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Không thể refresh token: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Đăng xuất user
     * POST /api/v1/auth/logout
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        try {
            $result = $this->authService->logout();

            if (!$result['success']) {
                return ApiResponse::fail([
                    'message' => $result['message']
                ], 400);
            }

            return ApiResponse::success([
                'message' => 'Đăng xuất thành công'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Đăng xuất thất bại: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Kiểm tra quyền của user
     * POST /api/v1/auth/check-permission
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkPermission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permission' => 'required|string',
            'project_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::fail($validator->errors(), 422);
        }

        try {
            $permission = $request->input('permission');
            $projectId = $request->input('project_id');
            
            $hasPermission = $this->authService->checkPermission($permission, $projectId);

            return ApiResponse::success([
                'has_permission' => $hasPermission,
                'permission' => $permission,
                'project_id' => $projectId
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Không thể kiểm tra quyền: ' . $e->getMessage(), 500);
        }
    }
}