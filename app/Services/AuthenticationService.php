<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Authentication Service
 * 
 * Handles user authentication, token management, and session handling
 */
class AuthenticationService
{
    /**
     * Authenticate user with email and password
     */
    public function authenticate(string $email, string $password, bool $remember = false): array
    {
        try {
            // Find user by email
            $user = User::where('email', $email)
                ->where('is_active', true)
                ->first();
            
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'Invalid credentials',
                    'code' => 'INVALID_CREDENTIALS'
                ];
            }
            
            // Verify password
            if (!Hash::check($password, $user->password)) {
                return [
                    'success' => false,
                    'error' => 'Invalid credentials',
                    'code' => 'INVALID_CREDENTIALS'
                ];
            }
            
            // Check if user has tenant access
            if (!$user->tenant_id) {
                return [
                    'success' => false,
                    'error' => 'No tenant access',
                    'code' => 'NO_TENANT_ACCESS'
                ];
            }
            
            // Update last login
            $user->update([
                'last_login_at' => now(),
                'last_activity_at' => now()
            ]);
            
            // Generate token
            $token = $this->generateToken($user, $remember);
            
            // Log successful authentication
            Log::info('User authenticated successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'remember' => $remember
            ]);
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id,
                    'avatar' => $user->avatar,
                    'preferences' => $user->preferences
                ],
                'token' => $token,
                'expires_at' => $remember ? now()->addDays(30) : now()->addHours(8)
            ];
            
        } catch (\Exception $e) {
            Log::error('Authentication error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Authentication failed',
                'code' => 'AUTH_ERROR'
            ];
        }
    }
    
    /**
     * Generate authentication token
     */
    public function generateToken(User $user, bool $remember = false): string
    {
        // Revoke existing tokens if needed
        if (!$remember) {
            $user->tokens()->delete();
        }
        
        // Create new token
        $tokenName = $remember ? 'remember_token' : 'api_token';
        $expiresAt = $remember ? now()->addDays(30) : now()->addHours(8);
        
        $token = $user->createToken($tokenName, ['*'], $expiresAt);
        
        return $token->plainTextToken;
    }
    
    /**
     * Refresh authentication token
     */
    public function refreshToken(string $currentToken): array
    {
        try {
            $personalAccessToken = PersonalAccessToken::findToken($currentToken);
            
            if (!$personalAccessToken) {
                return [
                    'success' => false,
                    'error' => 'Invalid token',
                    'code' => 'INVALID_TOKEN'
                ];
            }
            
            $user = $personalAccessToken->tokenable;
            
            if (!$user || !$user->is_active) {
                return [
                    'success' => false,
                    'error' => 'User not found or inactive',
                    'code' => 'USER_NOT_FOUND'
                ];
            }
            
            // Revoke current token
            $personalAccessToken->delete();
            
            // Generate new token
            $newToken = $this->generateToken($user);
            
            return [
                'success' => true,
                'token' => $newToken,
                'expires_at' => now()->addHours(8)
            ];
            
        } catch (\Exception $e) {
            Log::error('Token refresh error', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Token refresh failed',
                'code' => 'REFRESH_ERROR'
            ];
        }
    }
    
    /**
     * Logout user and revoke tokens
     */
    public function logout(User $user, ?string $token = null): array
    {
        try {
            if ($token) {
                // Revoke specific token
                $personalAccessToken = PersonalAccessToken::findToken($token);
                if ($personalAccessToken && $personalAccessToken->tokenable_id === $user->id) {
                    $personalAccessToken->delete();
                }
            } else {
                // Revoke all tokens
                $user->tokens()->delete();
            }
            
            // Update last activity
            $user->update(['last_activity_at' => now()]);
            
            Log::info('User logged out', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return [
                'success' => true,
                'message' => 'Logged out successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Logout error', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Logout failed',
                'code' => 'LOGOUT_ERROR'
            ];
        }
    }
    
    /**
     * Validate token
     */
    public function validateToken(string $token): ?User
    {
        try {
            $personalAccessToken = PersonalAccessToken::findToken($token);
            
            if (!$personalAccessToken) {
                return null;
            }
            
            // Check if token is expired
            if ($personalAccessToken->expires_at && $personalAccessToken->expires_at->isPast()) {
                return null;
            }
            
            $user = $personalAccessToken->tokenable;
            
            if (!$user || !$user->is_active) {
                return null;
            }
            
            // Update last activity
            $personalAccessToken->forceFill([
                'last_used_at' => now(),
            ])->save();
            
            return $user;
            
        } catch (\Exception $e) {
            Log::error('Token validation error', [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Get user from token
     */
    public function getUserFromToken(string $token): ?User
    {
        return $this->validateToken($token);
    }
}
