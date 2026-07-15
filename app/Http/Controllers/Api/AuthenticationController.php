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
        // ── DIAG-AUTH: earliest entry point ──
        error_log(sprintf('[DIAG-AUTH ENTER] uri=%s mem=%s peak=%s', $_SERVER['REQUEST_URI'] ?? '?', memory_get_usage(true), memory_get_peak_usage(true)));

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'remember' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            error_log('[DIAG-AUTH] validation_failed');
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
                'code' => 'VALIDATION_ERROR'
            ], 422);
        }
        
        error_log(sprintf('[DIAG-AUTH] calling_authenticate email=%s mem=%s', $request->email ?? '?', memory_get_usage(true)));

        $result = $this->authService->authenticate(
            $request->email,
            $request->password,
            $request->boolean('remember', false)
        );
        
        error_log(sprintf('[DIAG-AUTH] authenticate_returned success=%s mem=%s', $result['success'] ?? '?', memory_get_usage(true)));
        
        if (!$result['success']) {
            return response()->json($result, 401);
        }

        $userPayload = $result['user'];
        $token = $result['token'];
        $expiresAt = $result['expires_at'];

        error_log(sprintf('[DIAG-AUTH EXIT] mem=%s peak=%s', memory_get_usage(true), memory_get_peak_usage(true)));

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $userPayload,
                'token' => $token,
                'expires_at' => $expiresAt,
            ],
            'user' => $userPayload,
            'token' => $token,
            'expires_at' => $expiresAt,
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
        
        $userPayload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'avatar' => $user->avatar,
            'preferences' => $user->preferences,
            'last_login_at' => $user->last_login_at,
            'last_activity_at' => $user->last_activity_at,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $userPayload,
            'user' => $userPayload,
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
            ],
            'roles' => $roles,
            'permissions' => $permissions
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
