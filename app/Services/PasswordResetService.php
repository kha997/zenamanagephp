<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Password Reset Service
 * 
 * Handles password reset functionality with proper security measures
 */
class PasswordResetService
{
    private const TOKEN_LENGTH = 64;
    private const TOKEN_EXPIRY_HOURS = 1;
    
    /**
     * Send password reset link to user
     */
    public function sendResetLink(string $email): array
    {
        try {
            // Find user by email
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'No account found with this email address.',
                    'code' => 'USER_NOT_FOUND'
                ];
            }
            
            // Check if user is active
            if (!$user->is_active) {
                return [
                    'success' => false,
                    'error' => 'Account is inactive. Please contact support.',
                    'code' => 'ACCOUNT_INACTIVE'
                ];
            }
            
            // Generate reset token
            $token = Str::random(self::TOKEN_LENGTH);
            
            // Store reset token in database
            PasswordReset::updateOrCreate(
                ['email' => $email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now()
                ]
            );
            
            // Send reset email
            $this->sendResetEmail($user, $token);
            
            Log::info('Password reset link sent', [
                'user_id' => $user->id,
                'email' => $email,
                'token_generated' => true
            ]);
            
            return [
                'success' => true,
                'message' => 'Password reset link sent to your email address.'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to send password reset link', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to send password reset link. Please try again.',
                'code' => 'SEND_FAILED'
            ];
        }
    }
    
    /**
     * Reset password with token
     */
    public function resetPassword(string $token, string $email, string $password): array
    {
        try {
            // Find user by email
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'No account found with this email address.',
                    'code' => 'USER_NOT_FOUND'
                ];
            }
            
            // Find reset record
            $resetRecord = PasswordReset::where('email', $email)->first();
            
            if (!$resetRecord) {
                return [
                    'success' => false,
                    'error' => 'Invalid or expired reset token.',
                    'code' => 'INVALID_TOKEN'
                ];
            }
            
            // Check if token is expired
            if ($this->isTokenExpired($resetRecord->created_at)) {
                $resetRecord->delete();
                
                return [
                    'success' => false,
                    'error' => 'Reset token has expired. Please request a new one.',
                    'code' => 'TOKEN_EXPIRED'
                ];
            }
            
            // Verify token
            if (!Hash::check($token, $resetRecord->token)) {
                return [
                    'success' => false,
                    'error' => 'Invalid reset token.',
                    'code' => 'INVALID_TOKEN'
                ];
            }
            
            // Update user password
            $user->update([
                'password' => Hash::make($password),
                'password_changed_at' => now()
            ]);
            
            // Delete reset record
            $resetRecord->delete();
            
            // Invalidate all user sessions
            $this->invalidateUserSessions($user);
            
            Log::info('Password reset successful', [
                'user_id' => $user->id,
                'email' => $email
            ]);
            
            return [
                'success' => true,
                'message' => 'Password has been reset successfully.'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to reset password', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to reset password. Please try again.',
                'code' => 'RESET_FAILED'
            ];
        }
    }
    
    /**
     * Verify password reset token
     */
    public function verifyToken(string $token, string $email): array
    {
        try {
            // Find reset record
            $resetRecord = PasswordReset::where('email', $email)->first();
            
            if (!$resetRecord) {
                return [
                    'success' => false,
                    'error' => 'Invalid or expired reset token.',
                    'code' => 'INVALID_TOKEN'
                ];
            }
            
            // Check if token is expired
            if ($this->isTokenExpired($resetRecord->created_at)) {
                $resetRecord->delete();
                
                return [
                    'success' => false,
                    'error' => 'Reset token has expired.',
                    'code' => 'TOKEN_EXPIRED'
                ];
            }
            
            // Verify token
            if (!Hash::check($token, $resetRecord->token)) {
                return [
                    'success' => false,
                    'error' => 'Invalid reset token.',
                    'code' => 'INVALID_TOKEN'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Token is valid.'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to verify token', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to verify token.',
                'code' => 'VERIFICATION_FAILED'
            ];
        }
    }
    
    /**
     * Check if token is expired
     */
    private function isTokenExpired(Carbon $createdAt): bool
    {
        return $createdAt->addHours(self::TOKEN_EXPIRY_HOURS)->isPast();
    }
    
    /**
     * Send reset email
     */
    private function sendResetEmail(User $user, string $token): void
    {
        // In a real application, you would send an actual email
        // For now, we'll just log it
        Log::info('Password reset email would be sent', [
            'user_id' => $user->id,
            'email' => $user->email,
            'reset_url' => url("/password/reset?token={$token}&email=" . urlencode($user->email))
        ]);
        
        // TODO: Implement actual email sending
        // Mail::to($user->email)->send(new PasswordResetMail($token));
    }
    
    /**
     * Invalidate all user sessions
     */
    private function invalidateUserSessions(User $user): void
    {
        // Clear user sessions from cache
        cache()->forget("user_sessions_{$user->id}");
        
        // In a real application, you might also:
        // - Revoke all Sanctum tokens
        // - Clear session storage
        // - Notify other devices
    }
}
