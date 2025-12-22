<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\Tenant;
use App\Services\UserManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Unified UserShellController
 * 
 * Consolidates functionality from:
 * - Web/UserController.php
 * - Api/Admin/UserController.php  
 * - Api/App/UserController.php
 * - Admin/UsersApiController.php
 * - App/TeamUsersController.php
 */
class UserShellController extends Controller
{
    public function __construct(
        private UserManagementService $userService
    ) {
        $this->middleware('auth');
    }

    /**
     * Display a listing of users based on context
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('super_admin');
        $isApiRequest = $request->expectsJson();
        
        // Determine context and permissions
        $context = $this->determineContext($request);
        $permissions = $this->getUserPermissions($user, $context);
        
        if (!$permissions['can_view']) {
            if ($isApiRequest) {
                return ApiResponse::error('Insufficient permissions', 403);
            }
            abort(403, 'Insufficient permissions');
        }

        try {
            // Get users based on context
            $users = $this->getUsersForContext($user, $context, $request);
            
            if ($isApiRequest) {
                return ApiResponse::success($users, 'Users retrieved successfully');
            }
            
            // Return view for web requests
            return $this->getViewForContext($context, compact('users', 'permissions'));
            
        } catch (\Exception $e) {
            if ($isApiRequest) {
                return ApiResponse::error('Failed to retrieve users', 500, null, 'USERS_FETCH_ERROR');
            }
            
            return redirect()->back()->with('error', 'Failed to retrieve users');
        }
    }

    /**
     * Show the form for creating a new user
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getUserPermissions($user, $context);
        
        if (!$permissions['can_create']) {
            abort(403, 'Insufficient permissions');
        }

        $tenants = $this->getAvailableTenants($user, $context);
        $roles = $this->getAvailableRoles($user, $context);
        
        return $this->getCreateViewForContext($context, compact('tenants', 'roles', 'permissions'));
    }

    /**
     * Store a newly created user
     */
    public function store(StoreUserRequest $request): JsonResponse|Response
    {
        $user = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getUserPermissions($user, $context);
        
        if (!$permissions['can_create']) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Insufficient permissions', 403);
            }
            abort(403, 'Insufficient permissions');
        }

        try {
            $userData = $this->prepareUserData($request, $context);
            $newUser = $this->userService->createUser($userData, $user);
            
            if ($request->expectsJson()) {
                return ApiResponse::created($newUser, 'User created successfully');
            }
            
            return redirect()->route($this->getIndexRouteForContext($context))
                           ->with('success', 'User created successfully');
                           
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to create user', 500, null, 'USER_CREATE_ERROR');
            }
            
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified user
     */
    public function show(Request $request, User $user): View|JsonResponse
    {
        $currentUser = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getUserPermissions($currentUser, $context);
        
        // Check if user can view this specific user
        if (!$this->canViewUser($currentUser, $user, $context)) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Insufficient permissions', 403);
            }
            abort(403, 'Insufficient permissions');
        }

        try {
            $userData = $this->enrichUserData($user, $context);
            
            if ($request->expectsJson()) {
                return ApiResponse::success($userData, 'User retrieved successfully');
            }
            
            return $this->getShowViewForContext($context, compact('user', 'userData', 'permissions'));
            
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to retrieve user', 500, null, 'USER_FETCH_ERROR');
            }
            
            return redirect()->back()->with('error', 'Failed to retrieve user');
        }
    }

    /**
     * Show the form for editing the specified user
     */
    public function edit(Request $request, User $user): View
    {
        $currentUser = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getUserPermissions($currentUser, $context);
        
        if (!$this->canEditUser($currentUser, $user, $context)) {
            abort(403, 'Insufficient permissions');
        }

        $tenants = $this->getAvailableTenants($currentUser, $context);
        $roles = $this->getAvailableRoles($currentUser, $context);
        
        return $this->getEditViewForContext($context, compact('user', 'tenants', 'roles', 'permissions'));
    }

    /**
     * Update the specified user
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse|Response
    {
        $currentUser = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getUserPermissions($currentUser, $context);
        
        if (!$this->canEditUser($currentUser, $user, $context)) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Insufficient permissions', 403);
            }
            abort(403, 'Insufficient permissions');
        }

        try {
            $userData = $this->prepareUserData($request, $context, $user);
            $updatedUser = $this->userService->updateUser($user, $userData, $currentUser);
            
            if ($request->expectsJson()) {
                return ApiResponse::success($updatedUser, 'User updated successfully');
            }
            
            return redirect()->route($this->getIndexRouteForContext($context))
                           ->with('success', 'User updated successfully');
                           
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to update user', 500, null, 'USER_UPDATE_ERROR');
            }
            
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to update user: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified user from storage
     */
    public function destroy(Request $request, User $user): JsonResponse|Response
    {
        $currentUser = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getUserPermissions($currentUser, $context);
        
        if (!$this->canDeleteUser($currentUser, $user, $context)) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Insufficient permissions', 403);
            }
            abort(403, 'Insufficient permissions');
        }

        try {
            $this->userService->deleteUser($user, $currentUser);
            
            if ($request->expectsJson()) {
                return ApiResponse::success(null, 'User deleted successfully');
            }
            
            return redirect()->route($this->getIndexRouteForContext($context))
                           ->with('success', 'User deleted successfully');
                           
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to delete user', 500, null, 'USER_DELETE_ERROR');
            }
            
            return redirect()->back()->with('error', 'Failed to delete user: ' . $e->getMessage());
        }
    }

    /**
     * Bulk operations on users
     */
    public function bulkAction(Request $request): JsonResponse|Response
    {
        $user = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getUserPermissions($user, $context);
        
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate,delete,change_role',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id'
        ]);
        
        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return ApiResponse::validationError($validator->errors());
            }
            return redirect()->back()->withErrors($validator);
        }
        
        $action = $request->input('action');
        $userIds = $request->input('user_ids');
        
        if (!$this->canPerformBulkAction($user, $action, $context)) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Insufficient permissions for bulk action', 403);
            }
            abort(403, 'Insufficient permissions for bulk action');
        }

        try {
            $result = $this->userService->bulkAction($action, $userIds, $user, $request->all());
            
            if ($request->expectsJson()) {
                return ApiResponse::success($result, 'Bulk action completed successfully');
            }
            
            return redirect()->back()->with('success', 'Bulk action completed successfully');
            
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to perform bulk action', 500, null, 'BULK_ACTION_ERROR');
            }
            
            return redirect()->back()->with('error', 'Failed to perform bulk action: ' . $e->getMessage());
        }
    }

    /**
     * Get user statistics/KPIs
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getUserPermissions($user, $context);
        
        if (!$permissions['can_view']) {
            return ApiResponse::error('Insufficient permissions', 403);
        }

        try {
            $stats = $this->userService->getUserStatistics($user, $context);
            return ApiResponse::success($stats, 'User statistics retrieved successfully');
            
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve user statistics', 500, null, 'USER_STATS_ERROR');
        }
    }

    // Private helper methods

    private function determineContext(Request $request): string
    {
        $route = $request->route();
        $routeName = $route ? $route->getName() : '';
        
        if (str_contains($routeName, 'admin')) {
            return 'admin';
        } elseif (str_contains($routeName, 'app') || str_contains($routeName, 'team')) {
            return 'app';
        } elseif (str_contains($routeName, 'api')) {
            return 'api';
        }
        
        return 'web';
    }

    private function getUserPermissions(User $user, string $context): array
    {
        $isSuperAdmin = $user->hasRole('super_admin');
        $isAdmin = $user->hasRole('admin');
        
        switch ($context) {
            case 'admin':
                return [
                    'can_view' => $isSuperAdmin,
                    'can_create' => $isSuperAdmin,
                    'can_edit' => $isSuperAdmin,
                    'can_delete' => $isSuperAdmin,
                    'can_bulk_action' => $isSuperAdmin
                ];
            case 'app':
            case 'team':
                return [
                    'can_view' => $isAdmin || $isSuperAdmin,
                    'can_create' => $isAdmin || $isSuperAdmin,
                    'can_edit' => $isAdmin || $isSuperAdmin,
                    'can_delete' => $isSuperAdmin,
                    'can_bulk_action' => $isAdmin || $isSuperAdmin
                ];
            default:
                return [
                    'can_view' => true,
                    'can_create' => false,
                    'can_edit' => false,
                    'can_delete' => false,
                    'can_bulk_action' => false
                ];
        }
    }

    private function getUsersForContext(User $user, string $context, Request $request)
    {
        $query = User::query();
        
        switch ($context) {
            case 'admin':
                // Super admin can see all users
                if ($user->hasRole('super_admin')) {
                    $query->with(['tenant']);
                } else {
                    $query->where('id', $user->id);
                }
                break;
            case 'app':
            case 'team':
                // App/Team context - users within same tenant
                $query->where('tenant_id', $user->tenant_id)
                      ->with(['tenant']);
                break;
            default:
                // Default - only current user
                $query->where('id', $user->id);
        }
        
        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }
        
        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        
        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        return $query->paginate($perPage);
    }

    private function canViewUser(User $currentUser, User $targetUser, string $context): bool
    {
        if ($currentUser->hasRole('super_admin')) {
            return true;
        }
        
        if ($context === 'app' || $context === 'team') {
            return $targetUser->tenant_id === $currentUser->tenant_id;
        }
        
        return $currentUser->id === $targetUser->id;
    }

    private function canEditUser(User $currentUser, User $targetUser, string $context): bool
    {
        if ($currentUser->hasRole('super_admin')) {
            return true;
        }
        
        if ($context === 'app' || $context === 'team') {
            return $targetUser->tenant_id === $currentUser->tenant_id && 
                   $currentUser->hasRole('admin');
        }
        
        return $currentUser->id === $targetUser->id;
    }

    private function canDeleteUser(User $currentUser, User $targetUser, string $context): bool
    {
        if ($currentUser->hasRole('super_admin')) {
            return true;
        }
        
        // Prevent self-deletion
        if ($currentUser->id === $targetUser->id) {
            return false;
        }
        
        if ($context === 'app' || $context === 'team') {
            return $targetUser->tenant_id === $currentUser->tenant_id && 
                   $currentUser->hasRole('admin');
        }
        
        return false;
    }

    private function canPerformBulkAction(User $user, string $action, string $context): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        if ($context === 'app' || $context === 'team') {
            return $user->hasRole('admin') && in_array($action, ['activate', 'deactivate', 'change_role']);
        }
        
        return false;
    }

    private function getAvailableTenants(User $user, string $context): array
    {
        if ($user->hasRole('super_admin')) {
            return Tenant::where('status', 'active')->get()->toArray();
        }
        
        if ($context === 'app' || $context === 'team') {
            return [$user->tenant];
        }
        
        return [];
    }

    private function getAvailableRoles(User $user, string $context): array
    {
        if ($user->hasRole('super_admin')) {
            return ['super_admin', 'admin', 'member', 'client'];
        }
        
        if ($context === 'app' || $context === 'team') {
            return ['admin', 'member', 'client'];
        }
        
        return ['member'];
    }

    private function prepareUserData(Request $request, string $context, ?User $user = null): array
    {
        $data = $request->validated();
        
        // Set tenant_id based on context
        if ($context === 'app' || $context === 'team') {
            $data['tenant_id'] = Auth::user()->tenant_id;
        }
        
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        
        return $data;
    }

    private function enrichUserData(User $user, string $context): array
    {
        $userData = $user->toArray();
        
        if ($context === 'admin') {
            $userData['tenant'] = $user->tenant;
            $userData['projects_count'] = $user->projects()->count();
            $userData['tasks_count'] = $user->tasks()->count();
        }
        
        return $userData;
    }

    private function getViewForContext(string $context, array $data): View
    {
        switch ($context) {
            case 'admin':
                return view('admin.users.index', $data);
            case 'app':
            case 'team':
                return view('app.team.index', $data);
            default:
                return view('users.index', $data);
        }
    }

    private function getCreateViewForContext(string $context, array $data): View
    {
        switch ($context) {
            case 'admin':
                return view('admin.users.create', $data);
            case 'app':
            case 'team':
                return view('app.team.create', $data);
            default:
                return view('users.create', $data);
        }
    }

    private function getShowViewForContext(string $context, array $data): View
    {
        switch ($context) {
            case 'admin':
                return view('admin.users.show', $data);
            case 'app':
            case 'team':
                return view('app.team.show', $data);
            default:
                return view('users.show', $data);
        }
    }

    private function getEditViewForContext(string $context, array $data): View
    {
        switch ($context) {
            case 'admin':
                return view('admin.users.edit', $data);
            case 'app':
            case 'team':
                return view('app.team.edit', $data);
            default:
                return view('users.edit', $data);
        }
    }

    private function getIndexRouteForContext(string $context): string
    {
        switch ($context) {
            case 'admin':
                return 'admin.users.index';
            case 'app':
            case 'team':
                return 'app.team.index';
            default:
                return 'users.index';
        }
    }
}
