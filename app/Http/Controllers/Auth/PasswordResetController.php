<?php declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Http\Requests\Auth\PasswordResetConfirmRequest;
use App\Models\User;
// use App\Services\PasswordPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    // protected PasswordPolicyService $passwordPolicyService;

    // public function __construct(PasswordPolicyService $passwordPolicyService)
    // {
    //     $this->passwordPolicyService = $passwordPolicyService;
    // }

    /**
     * Send password reset link
     */
    public function sendResetLink(PasswordResetRequest $request): JsonResponse
    {
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                Log::info('Password reset link sent', [
                    'email' => $request->email,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'message' => 'Password reset link has been sent to your email.',
                    'status' => 'success'
                ]);
            }

            return response()->json([
                'message' => 'Unable to send password reset link.',
                'status' => 'error'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Password reset link failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'An error occurred while sending password reset link.',
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Reset password
     */
    public function reset(PasswordResetConfirmRequest $request): JsonResponse
    {
        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    // Validate password against policy
                    $validation = $this->passwordPolicyService->validatePassword($password, $user);
                    if (!$validation['valid']) {
                        throw new \Exception('Password does not meet policy requirements: ' . implode(', ', $validation['errors']));
                    }

                    // Update password history
                    $this->passwordPolicyService->updatePasswordHistory($user, $user->password);

                    // Update user password
                    $user->update([
                        'password' => Hash::make($password),
                        'password_changed_at' => Carbon::now(),
                        'password_expires_at' => Carbon::now()->addDays($this->passwordPolicyService->getPolicyConfig()['max_age_days']),
                        'password_failed_attempts' => 0,
                        'password_locked_until' => null,
                        'remember_token' => Str::random(60)
                    ]);

                    // Log password reset
                    Log::info('Password reset completed', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'ip' => request()->ip()
                    ]);
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'message' => 'Password has been reset successfully.',
                    'status' => 'success'
                ]);
            }

            return response()->json([
                'message' => 'Unable to reset password. Please check your token and try again.',
                'status' => 'error'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Password reset failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'An error occurred while resetting password.',
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Check if reset token is valid
     */
    public function checkToken(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string'
        ]);

        try {
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json([
                    'valid' => false,
                    'message' => 'User not found'
                ]);
            }

            // Check if token exists and is not expired
            $tokenRecord = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->where('token', Hash::make($request->token))
                ->first();

            if (!$tokenRecord) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Invalid token'
                ]);
            }

            // Check if token is expired
            if (Carbon::parse($tokenRecord->created_at)->addMinutes(config('auth.passwords.users.expire'))->isPast()) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Token has expired'
                ]);
            }

            return response()->json([
                'valid' => true,
                'message' => 'Token is valid'
            ]);

        } catch (\Exception $e) {
            Log::error('Token validation failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'valid' => false,
                'message' => 'Error validating token'
            ], 500);
        }
    }
}
