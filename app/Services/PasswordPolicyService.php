<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Password Policy Service
 * 
 * Handles advanced password policies, validation, and security
 */
class PasswordPolicyService
{
    private SecureAuditService $auditService;
    private array $policyConfig;

    public function __construct(SecureAuditService $auditService)
    {
        $this->auditService = $auditService;
        $this->policyConfig = config('password.policy', [
            'min_length' => 8,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numbers' => true,
            'require_symbols' => true,
            'max_age_days' => 90,
            'history_count' => 5,
            'max_failed_attempts' => 5,
            'lockout_duration_minutes' => 30,
            'common_passwords_check' => true
        ]);
    }

    /**
     * Validate password against policy
     */
    public function validatePassword(string $password, User $user = null): array
    {
        $errors = [];
        $score = 0;

        // Length check
        if (strlen($password) < $this->policyConfig['min_length']) {
            $errors[] = "Password must be at least {$this->policyConfig['min_length']} characters long";
        } else {
            $score += 20;
        }

        // Uppercase check
        if ($this->policyConfig['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        } else {
            $score += 20;
        }

        // Lowercase check
        if ($this->policyConfig['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        } else {
            $score += 20;
        }

        // Numbers check
        if ($this->policyConfig['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        } else {
            $score += 20;
        }

        // Symbols check
        if ($this->policyConfig['require_symbols'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        } else {
            $score += 20;
        }

        // Common passwords check
        if ($this->policyConfig['common_passwords_check'] && $this->isCommonPassword($password)) {
            $errors[] = 'Password is too common and easily guessable';
            $score = max(0, $score - 30);
        }

        // User-specific checks
        if ($user) {
            // Check against user info
            if ($this->containsUserInfo($password, $user)) {
                $errors[] = 'Password cannot contain your name, email, or other personal information';
                $score = max(0, $score - 20);
            }

            // Check password history
            if ($this->isPasswordInHistory($password, $user)) {
                $errors[] = 'Password has been used recently. Please choose a different password';
                $score = max(0, $score - 30);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'score' => min(100, $score),
            'strength' => $this->getPasswordStrength($score)
        ];
    }

    /**
     * Update user password with policy enforcement
     */
    public function updatePassword(User $user, string $newPassword, string $currentPassword = null): array
    {
        try {
            // Validate new password
            $validation = $this->validatePassword($newPassword, $user);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Password does not meet policy requirements',
                    'errors' => $validation['errors']
                ];
            }

            // Verify current password if provided
            if ($currentPassword && !Hash::check($currentPassword, $user->password)) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }

            // Update password history
            $this->updatePasswordHistory($user, $user->password);

            // Update user
            $user->update([
                'password' => Hash::make($newPassword),
                'password_changed_at' => Carbon::now(),
                'password_expires_at' => Carbon::now()->addDays($this->policyConfig['max_age_days']),
                'password_failed_attempts' => 0,
                'password_locked_until' => null
            ]);

            // Log password change
            $this->auditService->logPasswordChange(
                userId: $user->id,
                targetUserId: $user->id,
                tenantId: $user->tenant_id
            );

            return [
                'success' => true,
                'message' => 'Password updated successfully',
                'expires_at' => $user->password_expires_at
            ];

        } catch (\Exception $e) {
            Log::error('Password update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to update password'
            ];
        }
    }

    /**
     * Check if password is expired
     */
    public function isPasswordExpired(User $user): bool
    {
        return $user->password_expires_at && 
               $user->password_expires_at->isPast();
    }

    /**
     * Check if user is locked out
     */
    public function isUserLockedOut(User $user): bool
    {
        return $user->password_locked_until && 
               $user->password_locked_until->isFuture();
    }

    /**
     * Record failed login attempt
     */
    public function recordFailedAttempt(User $user): array
    {
        try {
            $attempts = $user->password_failed_attempts + 1;
            $maxAttempts = $this->policyConfig['max_failed_attempts'];
            $lockoutDuration = $this->policyConfig['lockout_duration_minutes'];

            $updateData = ['password_failed_attempts' => $attempts];

            // Lock account if max attempts reached
            if ($attempts >= $maxAttempts) {
                $updateData['password_locked_until'] = Carbon::now()->addMinutes($lockoutDuration);
                
                // Log account lockout
                $this->auditService->logAction(
                    userId: $user->id,
                    action: 'account_locked',
                    entityType: 'User',
                    entityId: $user->id,
                    newData: [
                        'failed_attempts' => $attempts,
                        'locked_until' => $updateData['password_locked_until']
                    ],
                    tenantId: $user->tenant_id
                );
            }

            $user->update($updateData);

            return [
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
                'locked' => $attempts >= $maxAttempts,
                'locked_until' => $updateData['password_locked_until'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('Failed attempt recording failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return [
                'attempts' => 0,
                'max_attempts' => $this->policyConfig['max_failed_attempts'],
                'locked' => false,
                'locked_until' => null
            ];
        }
    }

    /**
     * Reset failed attempts on successful login
     */
    public function resetFailedAttempts(User $user): void
    {
        try {
            if ($user->password_failed_attempts > 0) {
                $user->update([
                    'password_failed_attempts' => 0,
                    'password_locked_until' => null
                ]);

                // Log successful login after failed attempts
                $this->auditService->logAction(
                    userId: $user->id,
                    action: 'login_success_after_failures',
                    entityType: 'User',
                    entityId: $user->id,
                    tenantId: $user->tenant_id
                );
            }
        } catch (\Exception $e) {
            Log::error('Reset failed attempts failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get password policy status for user
     */
    public function getPasswordStatus(User $user): array
    {
        $isExpired = $this->isPasswordExpired($user);
        $isLocked = $this->isUserLockedOut($user);
        $daysUntilExpiry = null;

        if ($user->password_expires_at && !$isExpired) {
            $daysUntilExpiry = Carbon::now()->diffInDays($user->password_expires_at);
        }

        return [
            'is_expired' => $isExpired,
            'is_locked' => $isLocked,
            'failed_attempts' => $user->password_failed_attempts,
            'max_attempts' => $this->policyConfig['max_failed_attempts'],
            'password_changed_at' => $user->password_changed_at,
            'password_expires_at' => $user->password_expires_at,
            'days_until_expiry' => $daysUntilExpiry,
            'locked_until' => $user->password_locked_until,
            'policy' => $this->policyConfig
        ];
    }

    /**
     * Check if password is in history
     */
    private function isPasswordInHistory(string $password, User $user): bool
    {
        if (!$user->password_history) {
            return false;
        }

        $history = json_decode($user->password_history, true);
        
        foreach ($history as $hashedPassword) {
            if (Hash::check($password, $hashedPassword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update password history
     */
    private function updatePasswordHistory(User $user, string $currentPassword): void
    {
        $history = $user->password_history ? json_decode($user->password_history, true) : [];
        
        // Add current password to history
        array_unshift($history, $currentPassword);
        
        // Keep only the required number of passwords
        $history = array_slice($history, 0, $this->policyConfig['history_count']);
        
        $user->update(['password_history' => json_encode($history)]);
    }

    /**
     * Check if password contains user information
     */
    private function containsUserInfo(string $password, User $user): bool
    {
        $userInfo = [
            $user->name,
            $user->email,
            explode('@', $user->email)[0] // email username part
        ];

        $passwordLower = strtolower($password);
        
        foreach ($userInfo as $info) {
            if ($info && strlen($info) > 2 && strpos($passwordLower, strtolower($info)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if password is common
     */
    private function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', 'monkey',
            '1234567890', 'password1', 'qwerty123', 'dragon', 'master'
        ];

        return in_array(strtolower($password), $commonPasswords);
    }

    /**
     * Get password strength description
     */
    private function getPasswordStrength(int $score): string
    {
        if ($score < 40) return 'Very Weak';
        if ($score < 60) return 'Weak';
        if ($score < 80) return 'Medium';
        if ($score < 90) return 'Strong';
        return 'Very Strong';
    }
}
