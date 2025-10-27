<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Http\Requests\Auth\PasswordResetTokenRequest;
use App\Services\PasswordResetService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Password Reset Controller
 * 
 * Handles password reset functionality with proper security measures
 */
class PasswordResetController extends Controller
{
    public function __construct(
        private PasswordResetService $passwordResetService
    ) {}

    /**
     * Send password reset email
     */
    public function sendResetLink(PasswordResetRequest $request)
    {
        try {
            $email = $request->validated()['email'];
            
            $result = $this->passwordResetService->sendResetLink($email);
            
            if (!$result['success']) {
                return ApiResponse::error(
                    $result['error'],
                    400,
                    null,
                    $result['code']
                );
            }
            
            Log::info('Password reset link sent', [
                'email' => $email,
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);
            
            return ApiResponse::success([
                'message' => 'Password reset link sent to your email address.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Password reset link send failed', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
                'ip' => $request->ip()
            ]);
            
            return ApiResponse::error(
                'Failed to send password reset link. Please try again.',
                500,
                null,
                'PASSWORD_RESET_SEND_FAILED'
            );
        }
    }
    
    /**
     * Reset password with token
     */
    public function resetPassword(PasswordResetTokenRequest $request)
    {
        try {
            $data = $request->validated();
            
            $result = $this->passwordResetService->resetPassword(
                $data['token'],
                $data['email'],
                $data['password']
            );
            
            if (!$result['success']) {
                return ApiResponse::error(
                    $result['error'],
                    400,
                    null,
                    $result['code']
                );
            }
            
            Log::info('Password reset successful', [
                'email' => $data['email'],
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);
            
            return ApiResponse::success([
                'message' => 'Password has been reset successfully. You can now log in with your new password.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Password reset failed', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
                'ip' => $request->ip()
            ]);
            
            return ApiResponse::error(
                'Failed to reset password. Please try again.',
                500,
                null,
                'PASSWORD_RESET_FAILED'
            );
        }
    }
    
    /**
     * Verify password reset token
     */
    public function verifyToken(Request $request)
    {
        try {
            $token = $request->input('token');
            $email = $request->input('email');
            
            if (!$token || !$email) {
                return ApiResponse::error(
                    'Token and email are required.',
                    400,
                    null,
                    'MISSING_TOKEN_EMAIL'
                );
            }
            
            $result = $this->passwordResetService->verifyToken($token, $email);
            
            if (!$result['success']) {
                return ApiResponse::error(
                    $result['error'],
                    400,
                    null,
                    $result['code']
                );
            }
            
            return ApiResponse::success([
                'valid' => true,
                'message' => 'Token is valid.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Token verification failed', [
                'error' => $e->getMessage(),
                'token' => $request->input('token'),
                'email' => $request->input('email')
            ]);
            
            return ApiResponse::error(
                'Failed to verify token.',
                500,
                null,
                'TOKEN_VERIFICATION_FAILED'
            );
        }
    }
}
