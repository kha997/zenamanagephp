<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Email Verification Controller
 * 
 * Handles email verification operations including resending verification emails
 */
class EmailVerificationController extends Controller
{
    public function __construct(
        private EmailVerificationService $emailVerificationService
    ) {}

    /**
     * Resend verification email
     * 
     * Supports both authenticated and unauthenticated requests
     */
    public function resend(ResendVerificationRequest $request)
    {
        try {
            $email = $request->getEmail();
            
            // Find user by email
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return ApiResponse::error(
                    'No account found with this email address.',
                    404,
                    null,
                    'USER_NOT_FOUND'
                );
            }

            // Check if email is already verified
            if ($user->email_verified_at) {
                return ApiResponse::error(
                    'Email is already verified.',
                    422,
                    null,
                    'ALREADY_VERIFIED'
                );
            }

            // Resend verification email
            $result = $this->emailVerificationService->resendVerificationEmail($user);

            if (!$result['success']) {
                return ApiResponse::error(
                    $result['error'],
                    422,
                    null,
                    $result['code'] ?? 'SEND_FAILED'
                );
            }

            Log::info('Verification email resent', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::success([
                'message' => 'Verification email sent successfully. Please check your inbox.'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to resend verification email', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Failed to send verification email. Please try again later.',
                500,
                null,
                'SEND_FAILED'
            );
        }
    }
}

