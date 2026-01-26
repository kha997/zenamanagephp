<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Authentication Controller
 * 
 * Handles user authentication, token management, and session handling
 */
class AuthenticationController extends Controller
{
    protected AuthenticationService $authService;
    
    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }
    
    /**
     * Login user
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'remember' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
                'code' => 'VALIDATION_ERROR'
            ], 422);
        }
        
        $result = $this->authService->authenticate(
            $request->email,
            $request->password,
            $request->boolean('remember', false)
        );
        
        if (!$result['success']) {
            return response()->json($result, 401);
        }

        $expiresAt = $result['expires_at'];
        if ($expiresAt instanceof \DateTimeInterface) {
            $expiresAt = $expiresAt->format(\DateTime::ATOM);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $result['user'],
                'token' => $result['token'],
                'expires_at' => $expiresAt,
            ],
        ], 200);
    }
    
    /**
     * Logout user
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], 401);
        }
        
        $token = $request->bearerToken();
        $result = $this->authService->logout($user, $token);
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }
    
    /**
     * Refresh token
     * POST /api/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => 'Token not provided',
                'code' => 'TOKEN_NOT_PROVIDED'
            ], 401);
        }
        
        $result = $this->authService->refreshToken($token);
        
        return response()->json($result, $result['success'] ? 200 : 401);
    }
    
    /**
     * Get current user
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], 401);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toISOString(),
            ]
        ]);
    }
    
    /**
     * Validate token
     * GET /api/auth/validate
     */
    public function validateToken(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => 'Token not provided',
                'code' => 'TOKEN_NOT_PROVIDED'
            ], 401);
        }
        
        $user = $this->authService->validateToken($token);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired token',
                'code' => 'INVALID_TOKEN'
            ], 401);
        }
        
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id
            ]
        ]);
    }
    
    /**
     * Get user permissions
     * GET /api/auth/permissions
     */
    public function permissions(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], 401);
        }
        
        // Get user roles and permissions
        $roles = $this->getUserRoles($user);
        $permissions = $this->getUserPermissions($user);
        
        return response()->json([
            'success' => true,
            'data' => [
                'roles' => $roles,
                'permissions' => $permissions
            ]
        ]);
    }
    
    /**
     * Get user roles
     */
    private function getUserRoles($user): array
    {
        $roles = [];
        
        if ($user->isSuperAdmin()) {
            $roles[] = 'admin';
        }
        
        // Add more role logic based on your system
        if ($user->hasRole('project_manager')) {
            $roles[] = 'project_manager';
        }
        
        if ($user->hasRole('team_member')) {
            $roles[] = 'team_member';
        }
        
        return $roles;
    }
    
    /**
     * Get user permissions
     */
    private function getUserPermissions($user): array
    {
        $permissions = [];
        
        if ($user->isSuperAdmin()) {
            $permissions = [
                'create_project', 'edit_project', 'delete_project', 'view_project',
                'create_task', 'edit_task', 'delete_task', 'view_task',
                'manage_team', 'view_team', 'manage_documents', 'view_documents',
                'view_analytics', 'manage_settings', 'view_settings'
            ];
        } else {
            // Add permission logic based on user's roles
            if ($user->hasRole('project_manager')) {
                $permissions = array_merge($permissions, [
                    'create_project', 'edit_project', 'view_project',
                    'create_task', 'edit_task', 'view_task',
                    'manage_team', 'view_team', 'view_documents',
                    'view_analytics', 'view_settings'
                ]);
            }
            
            if ($user->hasRole('team_member')) {
                $permissions = array_merge($permissions, [
                    'view_project', 'create_task', 'edit_task', 'view_task',
                    'view_team', 'view_documents'
                ]);
            }
        }
        
        return array_unique($permissions);
    }
}
