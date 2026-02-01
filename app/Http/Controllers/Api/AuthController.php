<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use App\Services\ZenaAuditLogger;

class AuthController extends Controller
{
    use ZenaContractResponseTrait;

    public function __construct(private ZenaAuditLogger $auditLogger)
    {
    }

    /**
     * Login user and return token with user data.
     */
    public function login(Request $request): JsonResponse
    {
        // Rate limiting: 5 attempts per 15 minutes
        $key = 'login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'status' => 'error',
                'message' => 'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($key, 900); // 15 minutes
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember', false);

        if (!Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($key, 900); // 15 minutes
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();
        
        // Check if user is active
        if (!$user->is_active) {
            Auth::logout();
            return response()->json([
                'status' => 'error',
                'message' => 'Account is deactivated',
            ], 403);
        }

        // Clear rate limiting on successful login
        RateLimiter::clear($key);

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Create token
        $token = $user->createToken('auth-token', ['*'], now()->addHours(2))->plainTextToken;

        // Get user roles and permissions
        $rolesWithPermissions = $user->roles()->with('permissions')->get();
        $roles = $rolesWithPermissions->pluck('name')->toArray();
        $permissions = $rolesWithPermissions
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();

        $this->auditLogger->log(
            $request,
            'zena.auth.login',
            'auth',
            (string) $user->id,
            200,
            null,
            $user->tenant_id,
            (string) $user->id
        );

        return $this->zenaSuccessResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 7200, // 2 hours
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'roles' => $roles,
                'permissions' => $permissions,
                'last_login_at' => $user->last_login_at,
            ],
        ], 'Login successful');
    }

    /**
     * Logout user and revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $this->revokeTokenFromRequest($request, $user);

        $this->auditLogger->log(
            $request,
            'zena.auth.logout',
            'auth',
            (string) $user->id,
            200,
            null,
            $user->tenant_id,
            (string) $user->id
        );

        return $this->zenaSuccessResponse(null, 'Logout successful');
    }

    private function revokeTokenFromRequest(Request $request, User $user): void
    {
        $currentAccessToken = $request->user('sanctum')?->currentAccessToken();

        if ($currentAccessToken instanceof PersonalAccessToken) {
            $currentAccessToken->delete();
            return;
        }

        $bearer = $request->bearerToken();

        if ($bearer) {
            PersonalAccessToken::findToken($bearer)?->delete();
            return;
        }

        // Fallback: delete all tokens so we do not leave any lingering credentials.
        $user->tokens()->delete();
    }

    /**
     * Get current user profile with roles and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }
        
        // Get user roles and permissions
        $rolesWithPermissions = $user->roles()->with('permissions')->get();
        $roles = $rolesWithPermissions->pluck('name')->toArray();
        $permissions = $rolesWithPermissions
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();

        return $this->zenaSuccessResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'roles' => $roles,
                'permissions' => $permissions,
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Refresh user token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }
        
        // Revoke current token
        $user->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken('auth-token', ['*'], now()->addHours(2))->plainTextToken;

        return $this->zenaSuccessResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 7200, // 2 hours
        ], 'Token refreshed successfully');
    }

    /**
     * Get user dashboard redirect URL based on role.
     */
    public function getDashboardUrl(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }
        
        $roles = $user->roles()->pluck('name')->toArray();

        $dashboardUrl = $this->getDashboardUrlByRole($roles);

        return $this->zenaSuccessResponse([
            'dashboard_url' => $dashboardUrl,
            'roles' => $roles,
        ]);
    }

    /**
     * Get dashboard URL based on user roles.
     */
    private function getDashboardUrlByRole(array $roles): string
    {
        // Priority order: SuperAdmin > Admin > PM > Designer > SiteEngineer > QC > Procurement > Finance > Client
        if (in_array('SuperAdmin', $roles) || in_array('Admin', $roles)) {
            return '/admin';
        } elseif (in_array('PM', $roles)) {
            return '/pm';
        } elseif (in_array('Designer', $roles)) {
            return '/design';
        } elseif (in_array('SiteEngineer', $roles)) {
            return '/site';
        } elseif (in_array('QC', $roles)) {
            return '/qc';
        } elseif (in_array('Procurement', $roles)) {
            return '/proc';
        } elseif (in_array('Finance', $roles)) {
            return '/finance';
        } elseif (in_array('Client', $roles)) {
            return '/client';
        }

        return '/dashboard';
    }

    /**
     * Check if user has permission.
     */
    public function checkPermission(Request $request): JsonResponse
    {
        $permission = $request->input('permission');
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $hasPermission = $user->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('name', $permission);
        })->exists();

        return $this->zenaSuccessResponse([
            'has_permission' => $hasPermission,
            'permission' => $permission,
        ]);
    }

    /**
     * Get user notifications.
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }
        
        $limit = $request->input('limit', 10);
        $unreadOnly = $request->boolean('unread_only', false);

        $query = $user->zenaNotifications()->orderBy('created_at', 'desc');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query->limit($limit)->with(['user', 'project'])->get();

        return $this->zenaSuccessResponse([
            'notifications' => $notifications,
            'unread_count' => $user->zenaNotifications()->whereNull('read_at')->count(),
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markNotificationAsRead(Request $request, string $notificationId): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }
        
        $notification = $user->zenaNotifications()->findOrFail($notificationId);
        
        $notification->markAsRead();

        return $this->zenaSuccessResponse(null, 'Notification marked as read');
    }
}
