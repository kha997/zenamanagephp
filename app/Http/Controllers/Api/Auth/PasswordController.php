<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Http\Requests\Auth\PasswordResetConfirmRequest;
use App\Services\PasswordPolicyService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Password Controller
 * 
 * Handles password reset operations with proper validation
 * and security measures.
 */
class PasswordController extends Controller
{
    public function __construct(
        private PasswordPolicyService $passwordPolicyService
    ) {}

    /**
     * Send password reset link
     */
    public function forgot(PasswordResetRequest $request)
    {
        try {
            $data = $request->validated();
            
            // Send password reset link
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                Log::info('Password reset link sent', [
                    'email' => $data['email'],
                    'ip' => $request->ip(),
                    'request_id' => $request->header('X-Request-Id')
                ]);

                return ApiResponse::success([
                    'message' => 'Password reset link sent to your email address.'
                ]);
            }

            // Handle different status cases
            $errorMessage = $this->getStatusMessage($status);
            
            return ApiResponse::error(
                $errorMessage,
                'RESET_LINK_FAILED',
                422
            );

        } catch (\Exception $e) {
            Log::error('Password reset request failed', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Password reset request failed',
                500,
                null,
                'RESET_REQUEST_FAILED'
            );
        }
    }

    /**
     * Reset password with token
     */
    public function reset(PasswordResetConfirmRequest $request)
    {
        try {
            $data = $request->validated();
            
            // Validate password policy
            $policyResult = $this->passwordPolicyService->validatePassword($data['password']);
            if (!$policyResult['valid']) {
                return ApiResponse::error(
                    $policyResult['message'],
                    'PASSWORD_POLICY_VIOLATION',
                    422
                );
            }

            // Reset password using Laravel's built-in functionality
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->setRememberToken(Str::random(60));

                    $user->save();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                Log::info('Password reset successful', [
                    'email' => $data['email'],
                    'ip' => $request->ip(),
                    'request_id' => $request->header('X-Request-Id')
                ]);

                return ApiResponse::success([
                    'message' => 'Password has been reset successfully.'
                ]);
            }

            // Handle different status cases
            $errorMessage = $this->getStatusMessage($status);
            
            return ApiResponse::error(
                $errorMessage,
                'PASSWORD_RESET_FAILED',
                422
            );

        } catch (\Exception $e) {
            Log::error('Password reset failed', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Password reset failed',
                'RESET_FAILED',
                500
            );
        }
    }

    /**
     * Get status message for password reset
     */
    private function getStatusMessage(string $status): string
    {
        return match ($status) {
            Password::RESET_LINK_SENT => 'Password reset link sent.',
            Password::PASSWORD_RESET => 'Password has been reset.',
            Password::INVALID_USER => 'User not found.',
            Password::INVALID_TOKEN => 'Invalid or expired token.',
            Password::THROTTLED => 'Too many reset attempts. Please try again later.',
            default => 'Password reset failed.',
        };
    }
}
