<?php declare(strict_types=1);

namespace App\Http\Controllers\Unified;

use App\Http\Controllers\Controller;
use App\Services\UserManagementService;
use App\Http\Requests\Unified\UserManagementRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * UserManagementController
 * 
 * Unified controller for all user management operations
 * Replaces multiple user controllers (Api/App/UserController, Api/Admin/UserController, Web/UserController, etc.)
 */
class UserManagementController extends Controller
{
    protected UserManagementService $userService;

    public function __construct(UserManagementService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display users list (Web)
     */
    public function index(UserManagementRequest $request): View
    {
        $filters = $request->only(['search', 'status', 'role', 'is_active']);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $perPage = $request->get('per_page', 15);

        $users = $this->userService->getUsers(
            $filters,
            $perPage,
            $sortBy,
            $sortDirection
        );

        $stats = $this->userService->getUserStats();

        return view('app.users.index', compact('users', 'stats', 'filters'));
    }

    /**
     * Get users (API)
     */
    public function getUsers(UserManagementRequest $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'role', 'is_active']);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $perPage = $request->get('per_page', 15);

        $users = $this->userService->getUsers(
            $filters,
            $perPage,
            $sortBy,
            $sortDirection
        );

        return $this->userService->successResponse($users);
    }

    /**
     * Get user by ID (API)
     */
    public function getUser(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        
        if (!$user) {
            return $this->userService->errorResponse('User not found', 404);
        }

        return $this->userService->successResponse($user);
    }

    /**
     * Create user (API)
     */
    public function createUser(UserManagementRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->createUser($request->all());
            
            return $this->userService->successResponse(
                $user,
                'User created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->userService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Update user (API)
     */
    public function updateUser(UserManagementRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->userService->updateUser($id, $request->all());
            
            return $this->userService->successResponse(
                $user,
                'User updated successfully'
            );
        } catch (\Exception $e) {
            return $this->userService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Delete user (API)
     */
    public function deleteUser(int $id): JsonResponse
    {
        try {
            $deleted = $this->userService->deleteUser($id);
            
            if (!$deleted) {
                return $this->userService->errorResponse('Failed to delete user', 500);
            }
            
            return $this->userService->successResponse(
                null,
                'User deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->userService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Bulk delete users (API)
     */
    public function bulkDeleteUsers(UserManagementRequest $request): JsonResponse
    {
        try {
            $count = $this->userService->bulkDeleteUsers($request->input('ids'));
            
            return $this->userService->successResponse(
                ['deleted_count' => $count],
                "Successfully deleted {$count} users"
            );
        } catch (\Exception $e) {
            return $this->userService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Toggle user status (API)
     */
    public function toggleUserStatus(int $id): JsonResponse
    {
        try {
            $user = $this->userService->toggleUserStatus($id);
            
            return $this->userService->successResponse(
                $user,
                'User status updated successfully'
            );
        } catch (\Exception $e) {
            return $this->userService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Update user role (API)
     */
    public function updateUserRole(UserManagementRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->userService->updateUserRole($id, $request->input('role'));
            
            return $this->userService->successResponse(
                $user,
                'User role updated successfully'
            );
        } catch (\Exception $e) {
            return $this->userService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Get user statistics (API)
     */
    public function getUserStats(): JsonResponse
    {
        $stats = $this->userService->getUserStats();
        
        return $this->userService->successResponse($stats);
    }

    /**
     * Search users (API)
     */
    public function searchUsers(UserManagementRequest $request): JsonResponse
    {
        $users = $this->userService->searchUsers(
            $request->input('search'),
            $request->input('limit', 10)
        );

        return $this->userService->successResponse($users);
    }

    /**
     * Get user preferences (API)
     */
    public function getUserPreferences(int $id): JsonResponse
    {
        try {
            $preferences = $this->userService->getUserPreferences($id);
            
            return $this->userService->successResponse($preferences);
        } catch (\Exception $e) {
            return $this->userService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Update user preferences (API)
     */
    public function updateUserPreferences(UserManagementRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->userService->updateUserPreferences(
                $id,
                $request->input('preferences')
            );
            
            return $this->userService->successResponse(
                $user,
                'User preferences updated successfully'
            );
        } catch (\Exception $e) {
            return $this->userService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Show user profile (Web)
     */
    public function show(int $id): View
    {
        $user = $this->userService->getUserById($id);
        
        if (!$user) {
            abort(404, 'User not found');
        }

        return view('app.users.show', compact('user'));
    }

    /**
     * Show create user form (Web)
     */
    public function create(): View
    {
        return view('app.users.create');
    }

    /**
     * Show edit user form (Web)
     */
    public function edit(int $id): View
    {
        $user = $this->userService->getUserById($id);
        
        if (!$user) {
            abort(404, 'User not found');
        }

        return view('app.users.edit', compact('user'));
    }
}
