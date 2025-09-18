<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
        $roles = $user->roles()->pluck('name')->toArray();
        $permissions = $user->roles()->with('permissions')->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->toArray();

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
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
            ],
        ]);
    }

    /**
     * Logout user and revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }
        
        $user->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Get current user profile with roles and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }
        
        // Get user roles and permissions
        $roles = $user->roles()->pluck('name')->toArray();
        $permissions = $user->roles()->with('permissions')->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => [
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
            ],
        ]);
    }

    /**
     * Refresh user token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        
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

        return response()->json([
            'status' => 'success',
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 7200, // 2 hours
            ],
        ]);
    }

    /**
     * Get user dashboard redirect URL based on role.
     */
    public function getDashboardUrl(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }
        
        $roles = $user->roles()->pluck('name')->toArray();

        $dashboardUrl = $this->getDashboardUrlByRole($roles);

        return response()->json([
            'status' => 'success',
            'data' => [
                'dashboard_url' => $dashboardUrl,
                'roles' => $roles,
            ],
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
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $hasPermission = $user->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('name', $permission);
        })->exists();

        return response()->json([
            'status' => 'success',
            'data' => [
                'has_permission' => $hasPermission,
                'permission' => $permission,
            ],
        ]);
    }

    /**
     * Get user notifications.
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        
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

        $notifications = $query->limit($limit)->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $user->zenaNotifications()->whereNull('read_at')->count(),
            ],
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markNotificationAsRead(Request $request, string $notificationId): JsonResponse
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }
        
        $notification = $user->zenaNotifications()->findOrFail($notificationId);
        
        $notification->markAsRead();

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read',
        ]);
    }
}
