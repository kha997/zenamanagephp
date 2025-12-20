<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\UserManagementService;
use App\Support\AdminRouteContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AdminUserApiOverride
{
    public function __construct(private readonly UserManagementService $userService)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (!$this->isAdminUserRoute($request)) {
            return $next($request);
        }

        if ($request->method() === 'PUT') {
            return $this->handleUpdate($request);
        }

        if ($request->method() === 'DELETE') {
            return $this->handleDelete($request);
        }

        return $next($request);
    }

    private function handleUpdate(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanManageUsers($request)) {
            return $response;
        }

        try {
            $routeUser = $request->route('user');
            $userId = $routeUser instanceof Model
                ? $routeUser->getKey()
                : (string) $routeUser;

            $result = $this->userService->updateUserByIdentifier($userId, $request->all());
            return ApiResponse::success($result);
        } catch (UnauthorizedHttpException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::fail(['validation' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Không thể cập nhật user: ' . $e->getMessage(), 500);
        }
    }

    private function handleDelete(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanManageUsers($request)) {
            return $response;
        }

        try {
            $routeUser = $request->route('user');
            $userId = $routeUser instanceof Model
                ? $routeUser->getKey()
                : (string) $routeUser;

            $result = $this->userService->deleteUserByIdentifier($userId);
            return ApiResponse::success($result);
        } catch (UnauthorizedHttpException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return ApiResponse::error('Không thể xóa user: ' . $e->getMessage(), 500);
        }
    }

    private function ensureCanManageUsers(Request $request): ?JsonResponse
    {
        try {
            Gate::forUser($request->user())->authorize('users.manage');
            return null;
        } catch (AuthorizationException $e) {
            return ApiResponse::error('Missing users.manage permission', 403);
        }
    }

    private function isAdminUserRoute(Request $request): bool
    {
        return AdminRouteContext::matches($request);
    }
}
