<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Email Verification Service
 * 
 * Handles email verification tokens and sending verification emails.
 */
class EmailVerificationService
{
    /**
     * Queue email verification for user
     */
    public function queueVerificationEmail(User $user): void
    {
        try {
            // Generate verification token
            $token = $this->generateVerificationToken($user);

            // Store token in cache with expiration
            Cache::put(
                "email_verification:{$user->id}",
                $token,
                now()->addHours(24)
            );

            // Send verification email
            Mail::to($user->email)->send(new \App\Mail\EmailVerificationMail($user, $token));

            Log::info('Email verification queued', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to queue email verification', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verify email verification token
     */
    public function verifyToken(string $token): ?User
    {
        try {
            // Decode token to get user ID
            $payload = $this->decodeToken($token);
            
            if (!$payload || !isset($payload['user_id'])) {
                return null;
            }

            $userId = $payload['user_id'];

            // Check if token exists in cache
            $cachedToken = Cache::get("email_verification:{$userId}");
            
            if (!$cachedToken || $cachedToken !== $token) {
                return null;
            }

            // Get user
            $user = User::find($userId);
            
            if (!$user || $user->email_verified_at) {
                return null;
            }

            // Remove token from cache
            Cache::forget("email_verification:{$userId}");

            return $user;

        } catch (\Exception $e) {
            Log::error('Token verification failed', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10) . '...',
            ]);

            return null;
        }
    }

    /**
     * Generate verification token
     */
    private function generateVerificationToken(User $user): string
    {
        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'expires_at' => now()->addHours(24)->timestamp,
        ];

        return base64_encode(json_encode($payload));
    }

    /**
     * Decode verification token
     */
    private function decodeToken(string $token): ?array
    {
        try {
            $decoded = base64_decode($token);
            $payload = json_decode($decoded, true);

            if (!$payload || !isset($payload['expires_at'])) {
                return null;
            }

            // Check if token is expired
            if (now()->timestamp > $payload['expires_at']) {
                return null;
            }

            return $payload;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(User $user): array
    {
        try {
            // Check if user is already verified
            if ($user->email_verified_at) {
                return [
                    'success' => false,
                    'error' => 'Email is already verified',
                    'code' => 'ALREADY_VERIFIED'
                ];
            }

            // Queue new verification email
            $this->queueVerificationEmail($user);

            return [
                'success' => true,
                'message' => 'Verification email sent successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to resend verification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to send verification email',
                'code' => 'SEND_FAILED'
            ];
        }
    }
}