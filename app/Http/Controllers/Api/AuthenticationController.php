<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * Authentication Controller
 * 
 * Handles authentication-related API endpoints
 */
class AuthenticationController extends Controller
{
    /**
     * Get current user info
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'tenant_id' => $user->tenant_id,
                        'is_active' => $user->is_active
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get User Info Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->check() ? auth()->id() : null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get user info'
            ], 500);
        }
    }

    /**
     * Get user permissions
     */
    public function permissions(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Mock permissions based on role
            $permissions = match($user->role) {
                'super_admin' => [
                    'projects.create', 'projects.read', 'projects.update', 'projects.delete',
                    'users.create', 'users.read', 'users.update', 'users.delete',
                    'admin.access', 'system.settings'
                ],
                'project_manager' => [
                    'projects.create', 'projects.read', 'projects.update',
                    'tasks.create', 'tasks.read', 'tasks.update',
                    'team.manage'
                ],
                'member' => [
                    'projects.read', 'tasks.read', 'tasks.update'
                ],
                default => ['projects.read']
            };

            return response()->json([
                'success' => true,
                'data' => [
                    'permissions' => $permissions,
                    'role' => $user->role
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get User Permissions Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->check() ? auth()->id() : null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get user permissions'
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $credentials = $request->only('email', 'password');
            
            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                $token = $user->createToken('API Token')->plainTextToken;
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'user' => $user,
                        'token' => $token,
                        'token_type' => 'Bearer'
                    ]
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);

        } catch (\Exception $e) {
            Log::error('Login Error', [
                'error' => $e->getMessage(),
                'email' => $request->input('email')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Login failed'
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if ($user) {
                $user->currentAccessToken()->delete();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Logout Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->check() ? auth()->id() : null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Delete current token
            $user->currentAccessToken()->delete();
            
            // Create new token
            $token = $user->createToken('API Token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Token Refresh Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->check() ? auth()->id() : null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed'
            ], 500);
        }
    }

    /**
     * Validate token
     */
    public function validateToken(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => true,
                    'user_id' => $user->id,
                    'expires_at' => $user->currentAccessToken()->expires_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Token Validation Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token validation failed'
            ], 500);
        }
    }
}
