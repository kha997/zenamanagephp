<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserManagementService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminUserManagementController extends Controller
{
    use AuthorizesRequests;

    private UserManagementService $userService;

    public function __construct(UserManagementService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Update the specified user from the admin panel.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize('users.manage');

        try {
            if (!$this->hasPermission($request, 'user.update')) {
                return ApiResponse::error('Không có quyền cập nhật user', 403);
            }

            $result = $this->userService->updateUserByIdentifier($id, $request->all());
            return ApiResponse::success($result);

        } catch (AuthorizationException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::fail(['validation' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Không thể cập nhật user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified user from storage via the admin API.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorize('users.manage');

        try {
            if (!$this->hasPermission($request, 'user.delete')) {
                return ApiResponse::error('Không có quyền xóa user', 403);
            }

            $result = $this->userService->deleteUserByIdentifier($id);
            return ApiResponse::success($result);

        } catch (AuthorizationException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return ApiResponse::error('Không thể xóa user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Determine if the current user has the requested permission.
     */
    private function hasPermission(Request $request, string $permission): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->can('admin.access')) {
            return true;
        }

        return $user->can($permission);
    }

}
