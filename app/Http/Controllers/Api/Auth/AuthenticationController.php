<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthenticationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Authentication Controller
 * 
 * Handles API authentication operations with proper error handling
 * and structured responses following JSend format.
 */
class AuthenticationController extends Controller
{
    public function __construct(
        private AuthenticationService $authService
    ) {}

    /**
     * Authenticate user and return session + token
     */
    public function login(LoginRequest $request)
    {
        try {
            $credentials = $request->validated();
            
            $result = $this->authService->authenticate(
                $credentials['email'],
                $credentials['password'],
                $credentials['remember'] ?? false
            );

            if (!$result['success']) {
                return ApiResponse::error(
                    $result['error'],
                    401,
                    null,
                    $result['code']
                );
            }

            // Get the actual user model for session authentication
            $user = \App\Models\User::find($result['user']['id']);
            
            // Log in the user for web session (if this is a web request)
            if ($request->header('X-Web-Login')) {
                Auth::login($user, $credentials['remember'] ?? false);
                
                // Log session login
                Log::info('User logged in for web session', [
                    'user_id' => $user->id,
                    'session_id' => session()->getId(),
                    'remember' => $credentials['remember'] ?? false
                ]);
            }

            // Log successful authentication
            Log::info('User authenticated via API', [
                'user_id' => $result['user']['id'],
                'tenant_id' => $result['user']['tenant_id'],
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent(), 0, 50),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::success([
                'session_id' => session()->getId(),
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration') * 60,
                'onboarding_state' => $this->getOnboardingState($result['user']),
                'user' => $result['user']
            ]);

        } catch (\Exception $e) {
            Log::error('Authentication failed', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Authentication failed',
                500,
                null,
                'AUTH_FAILED'
            );
        }
    }

    /**
     * Logout user and revoke tokens
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user) {
                // Revoke all tokens for the user
                $user->tokens()->delete();
                
                // Log logout
                Log::info('User logged out via API', [
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'ip' => $request->ip(),
                    'request_id' => $request->header('X-Request-Id')
                ]);
            }

            return ApiResponse::success([
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Logout failed',
                500,
                null,
                'LOGOUT_FAILED'
            );
        }
    }

    /**
     * Refresh authentication token
     */
    public function refresh(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'AUTH_REQUIRED'
                );
            }

            // Revoke current token
            $request->user()->currentAccessToken()->delete();
            
            // Create new token
            $token = $user->createToken('API Token')->plainTextToken;

            return ApiResponse::success([
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration') * 60
            ]);

        } catch (\Exception $e) {
            Log::error('Token refresh failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Token refresh failed',
                500,
                null,
                'REFRESH_FAILED'
            );
        }
    }

    /**
     * Validate current token
     */
    public function validateToken(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'Invalid token',
                    401,
                    null,
                    'INVALID_TOKEN'
                );
            }

            return ApiResponse::success([
                'valid' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id,
                ]
            ]);

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Token validation failed',
                500,
                null,
                'VALIDATION_FAILED'
            );
        }
    }

    /**
     * Get current user information
     * Uses MeService to build standardized response with permissions and tenant context
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'AUTH_REQUIRED'
                );
            }

            // Use MeService to build standardized response
            $meService = app(\App\Services\MeService::class);
            $meData = $meService->buildMeResponse($user, $request);
            
            // Ensure current_tenant_permissions is always an array (never null/undefined)
            if (!isset($meData['current_tenant_permissions']) || !is_array($meData['current_tenant_permissions'])) {
                $meData['current_tenant_permissions'] = [];
            }
            
            // For super_admin with active tenant, ensure they have full tenant permissions
            // Super admin bypasses tenant middleware but FE still needs permissions to make API calls
            $isSuperAdmin = ($user->role === 'super_admin') || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin());
            $hasActiveTenant = !empty($meData['user']['tenant_id']);
            $hasNoTenantPermissions = empty($meData['current_tenant_permissions']);
            
            if ($isSuperAdmin && $hasActiveTenant && $hasNoTenantPermissions) {
                // Super admin with active tenant but no tenant role membership
                // Grant full tenant permissions for the active tenant so FE can make API calls
                $meData['current_tenant_permissions'] = [
                    'tenant.view_projects',
                    'tenant.manage_projects',
                    'tenant.view_tasks',
                    'tenant.manage_tasks',
                    'tenant.view_users',
                    'tenant.manage_users',
                    'tenant.view_reports',
                    'tenant.manage_settings',
                ];
            }

            return ApiResponse::success($meData);

        } catch (\Exception $e) {
            Log::error('Get user info failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Failed to get user information',
                500,
                null,
                'USER_INFO_FAILED'
            );
        }
    }

    /**
     * Get user permissions
     */
    public function permissions(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'AUTH_REQUIRED'
                );
            }

            // Get user permissions based on role
            $permissions = $this->getUserPermissions($user);

            return ApiResponse::success([
                'permissions' => $permissions,
                'role' => $user->role ?? 'member'
            ]);

        } catch (\Exception $e) {
            Log::error('Get permissions failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Failed to get permissions',
                500,
                null,
                'PERMISSIONS_FAILED'
            );
        }
    }

    /**
     * Get onboarding state for user
     */
    private function getOnboardingState($user): string
    {
        if (!$user['email_verified_at']) {
            return 'email_verification';
        }

        if (!$user['tenant_id']) {
            return 'tenant_setup';
        }

        return 'completed';
    }

    /**
     * Get user permissions based on role
     */
    private function getUserPermissions($user): array
    {
        $role = $user->role ?? 'member';
        
        $permissions = config('permissions.roles.' . $role, []);
        
        return $permissions;
    }
}
