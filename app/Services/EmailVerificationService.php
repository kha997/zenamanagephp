<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Email Verification Service
 * 
 * Handles email verification and email change functionality
 */
class EmailVerificationService
{
    private int $tokenExpiryHours;
    private SecureAuditService $auditService;

    public function __construct(SecureAuditService $auditService)
    {
        $this->tokenExpiryHours = config('email.verification_token_expiry', 24);
        $this->auditService = $auditService;
    }

    /**
     * Send email verification to user
     */
    public function sendVerificationEmail(User $user): bool
    {
        try {
            // Generate verification token
            $token = $this->generateVerificationToken();
            $expiresAt = Carbon::now()->addHours($this->tokenExpiryHours);

            // Update user with token
            $user->update([
                'email_verification_token' => Hash::make($token),
                'email_verification_token_expires_at' => $expiresAt
            ]);

            // Send email
            $this->sendVerificationEmailNotification($user, $token);

            // Log action
            $this->auditService->logAction(
                userId: $user->id,
                action: 'email_verification_sent',
                entityType: 'User',
                entityId: $user->id,
                newData: ['email' => $user->email],
                tenantId: $user->tenant_id
            );

            return true;

        } catch (\Exception $e) {
            Log::error('Email verification send failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verify email with token
     */
    public function verifyEmail(string $token): array
    {
        try {
            // Find user with valid token
            $user = User::whereNotNull('email_verification_token')
                ->where('email_verification_token_expires_at', '>', Carbon::now())
                ->where('email_verification_token', $token)
                ->first();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired verification token'
                ];
            }

            // Verify email
            $user->update([
                'email_verified' => true,
                'email_verified_at' => Carbon::now(),
                'email_verification_token' => null,
                'email_verification_token_expires_at' => null
            ]);

            // Log action
            $this->auditService->logAction(
                userId: $user->id,
                action: 'email_verified',
                entityType: 'User',
                entityId: $user->id,
                newData: ['email_verified_at' => Carbon::now()],
                tenantId: $user->tenant_id
            );

            return [
                'success' => true,
                'message' => 'Email verified successfully',
                'user' => $user->fresh()
            ];

        } catch (\Exception $e) {
            Log::error('Email verification failed', [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Email verification failed'
            ];
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(User $user): bool
    {
        // Check if already verified
        if ($user->email_verified) {
            return false;
        }

        // Check rate limiting (max 3 attempts per hour)
        $key = "email_verification_resend:{$user->id}";
        $attempts = cache()->get($key, 0);
        
        if ($attempts >= 3) {
            return false;
        }

        // Increment attempts
        cache()->put($key, $attempts + 1, 3600); // 1 hour

        return $this->sendVerificationEmail($user);
    }

    /**
     * Initiate email change process
     */
    public function initiateEmailChange(User $user, string $newEmail): array
    {
        try {
            // Validate new email
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid email format'
                ];
            }

            // Check if email is already taken
            if (User::where('email', $newEmail)->where('id', '!=', $user->id)->exists()) {
                return [
                    'success' => false,
                    'message' => 'Email is already taken'
                ];
            }

            // Generate change token
            $token = $this->generateVerificationToken();
            $expiresAt = Carbon::now()->addHours($this->tokenExpiryHours);

            // Update user with pending email and token
            $user->update([
                'pending_email' => $newEmail,
                'email_change_token' => Hash::make($token),
                'email_change_token_expires_at' => $expiresAt
            ]);

            // Send verification email to new address
            $this->sendEmailChangeVerificationNotification($user, $newEmail, $token);

            // Log action
            $this->auditService->logAction(
                userId: $user->id,
                action: 'email_change_initiated',
                entityType: 'User',
                entityId: $user->id,
                newData: ['pending_email' => $newEmail],
                tenantId: $user->tenant_id
            );

            return [
                'success' => true,
                'message' => 'Verification email sent to new address'
            ];

        } catch (\Exception $e) {
            Log::error('Email change initiation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Email change failed'
            ];
        }
    }

    /**
     * Confirm email change with token
     */
    public function confirmEmailChange(string $token): array
    {
        try {
            // Find user with valid token
            $user = User::whereNotNull('email_change_token')
                ->whereNotNull('pending_email')
                ->where('email_change_token_expires_at', '>', Carbon::now())
                ->where('email_change_token', $token)
                ->first();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired email change token'
                ];
            }

            $oldEmail = $user->email;
            $newEmail = $user->pending_email;

            // Update email
            $user->update([
                'email' => $newEmail,
                'pending_email' => null,
                'email_change_token' => null,
                'email_change_token_expires_at' => null,
                'email_verified' => true, // New email is verified
                'email_verified_at' => Carbon::now()
            ]);

            // Log action
            $this->auditService->logAction(
                userId: $user->id,
                action: 'email_changed',
                entityType: 'User',
                entityId: $user->id,
                oldData: ['email' => $oldEmail],
                newData: ['email' => $newEmail],
                tenantId: $user->tenant_id
            );

            return [
                'success' => true,
                'message' => 'Email changed successfully',
                'user' => $user->fresh()
            ];

        } catch (\Exception $e) {
            Log::error('Email change confirmation failed', [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Email change failed'
            ];
        }
    }

    /**
     * Check if user can login (email verified)
     */
    public function canUserLogin(User $user): bool
    {
        return $user->email_verified;
    }

    /**
     * Generate verification token
     */
    private function generateVerificationToken(): string
    {
        return Str::random(64);
    }

    /**
     * Send verification email notification
     */
    private function sendVerificationEmailNotification(User $user, string $token): void
    {
        $verificationUrl = config('app.frontend_url') . "/verify-email?token={$token}";
        
        Mail::send('emails.verify-email', [
            'user' => $user,
            'verificationUrl' => $verificationUrl,
            'expiryHours' => $this->tokenExpiryHours
        ], function ($message) use ($user) {
            $message->to($user->email)
                   ->subject('Verify Your Email Address');
        });
    }

    /**
     * Send email change verification notification
     */
    private function sendEmailChangeVerificationNotification(User $user, string $newEmail, string $token): void
    {
        $verificationUrl = config('app.frontend_url') . "/verify-email-change?token={$token}";
        
        Mail::send('emails.verify-email-change', [
            'user' => $user,
            'newEmail' => $newEmail,
            'verificationUrl' => $verificationUrl,
            'expiryHours' => $this->tokenExpiryHours
        ], function ($message) use ($newEmail) {
            $message->to($newEmail)
                   ->subject('Confirm Email Change');
        });
    }
}