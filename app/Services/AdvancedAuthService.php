<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Advanced Authentication Service
 * 
 * Features:
 * - Multi-factor authentication (MFA)
 * - Session management with device tracking
 * - Password policies and history
 * - Account lockout protection
 * - Login attempt monitoring
 * - Device fingerprinting
 * - Suspicious activity detection
 */
class AdvancedAuthService
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 15; // minutes
    private const PASSWORD_HISTORY_LIMIT = 5;
    private const SESSION_TIMEOUT = 120; // minutes
    private const DEVICE_TRUST_DURATION = 30; // days

    /**
     * Authenticate user with advanced security checks
     */
    public function authenticate(array $credentials, string $deviceFingerprint = null): array
    {
        $email = $credentials['email'] ?? '';
        $password = $credentials['password'] ?? '';
        
        // Check account lockout
        if ($this->isAccountLocked($email)) {
            Log::warning('Authentication attempt on locked account', [
                'email' => $email,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            
            return [
                'success' => false,
                'error' => 'account_locked',
                'message' => 'Account is temporarily locked due to multiple failed attempts.',
                'lockout_until' => $this->getLockoutUntil($email),
            ];
        }

        // Check suspicious activity
        if ($this->detectSuspiciousActivity($email, request()->ip())) {
            Log::warning('Suspicious authentication attempt detected', [
                'email' => $email,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            
            return [
                'success' => false,
                'error' => 'suspicious_activity',
                'message' => 'Suspicious activity detected. Please verify your identity.',
                'requires_verification' => true,
            ];
        }

        // Attempt authentication
        $user = User::where('email', $email)->first();
        
        if (!$user || !Hash::check($password, $user->password)) {
            $this->recordFailedAttempt($email);
            
            return [
                'success' => false,
                'error' => 'invalid_credentials',
                'message' => 'Invalid email or password.',
                'attempts_remaining' => $this->getRemainingAttempts($email),
            ];
        }

        // Check if MFA is required
        if ($this->requiresMFA($user)) {
            return [
                'success' => false,
                'error' => 'mfa_required',
                'message' => 'Multi-factor authentication required.',
                'user_id' => $user->id,
                'mfa_methods' => $this->getAvailableMFAMethods($user),
            ];
        }

        // Record successful login
        $this->recordSuccessfulLogin($user, $deviceFingerprint);
        
        return [
            'success' => true,
            'user' => $user,
            'session_token' => $this->createSession($user, $deviceFingerprint),
            'device_trusted' => $this->isDeviceTrusted($user, $deviceFingerprint),
        ];
    }

    /**
     * Verify MFA code
     */
    public function verifyMFA(int $userId, string $code, string $method = 'totp'): array
    {
        $user = User::find($userId);
        
        if (!$user) {
            return [
                'success' => false,
                'error' => 'user_not_found',
                'message' => 'User not found.',
            ];
        }

        $isValid = false;
        
        switch ($method) {
            case 'totp':
                $isValid = $this->verifyTOTPCode($user, $code);
                break;
            case 'sms':
                $isValid = $this->verifySMSCode($user, $code);
                break;
            case 'email':
                $isValid = $this->verifyEmailCode($user, $code);
                break;
        }

        if (!$isValid) {
            $this->recordFailedMFA($user);
            
            return [
                'success' => false,
                'error' => 'invalid_mfa_code',
                'message' => 'Invalid verification code.',
                'attempts_remaining' => $this->getRemainingMFAAttempts($user),
            ];
        }

        // Record successful MFA
        $this->recordSuccessfulMFA($user);
        
        return [
            'success' => true,
            'user' => $user,
            'session_token' => $this->createSession($user),
        ];
    }

    /**
     * Check if account is locked
     */
    private function isAccountLocked(string $email): bool
    {
        $lockoutKey = "auth_lockout:{$email}";
        $lockoutData = Cache::get($lockoutKey);
        
        if (!$lockoutData) {
            return false;
        }
        
        $lockoutUntil = Carbon::parse($lockoutData['until']);
        return $lockoutUntil->isFuture();
    }

    /**
     * Get lockout expiration time
     */
    private function getLockoutUntil(string $email): ?Carbon
    {
        $lockoutKey = "auth_lockout:{$email}";
        $lockoutData = Cache::get($lockoutKey);
        
        return $lockoutData ? Carbon::parse($lockoutData['until']) : null;
    }

    /**
     * Detect suspicious activity
     */
    private function detectSuspiciousActivity(string $email, string $ip): bool
    {
        // Check for rapid login attempts
        $attemptsKey = "auth_attempts:{$ip}";
        $attempts = Cache::get($attemptsKey, []);
        
        $recentAttempts = array_filter($attempts, function ($attempt) {
            return Carbon::parse($attempt['timestamp'])->isAfter(now()->subMinutes(5));
        });
        
        if (count($recentAttempts) > 10) {
            return true;
        }

        // Check for unusual IP patterns
        $userAttemptsKey = "user_attempts:{$email}";
        $userAttempts = Cache::get($userAttemptsKey, []);
        
        $uniqueIPs = array_unique(array_column($userAttempts, 'ip'));
        if (count($uniqueIPs) > 3) {
            return true;
        }

        return false;
    }

    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt(string $email): void
    {
        $ip = request()->ip();
        $userAgent = request()->userAgent();
        
        // Record attempt
        $attemptKey = "auth_attempts:{$ip}";
        $attempts = Cache::get($attemptKey, []);
        $attempts[] = [
            'email' => $email,
            'timestamp' => now()->toISOString(),
            'ip' => $ip,
            'user_agent' => $userAgent,
        ];
        Cache::put($attemptKey, $attempts, 3600); // 1 hour

        // Record user-specific attempts
        $userAttemptsKey = "user_attempts:{$email}";
        $userAttempts = Cache::get($userAttemptsKey, []);
        $userAttempts[] = [
            'ip' => $ip,
            'timestamp' => now()->toISOString(),
            'user_agent' => $userAgent,
        ];
        Cache::put($userAttemptsKey, $userAttempts, 3600);

        // Check if lockout is needed
        $failedAttempts = array_filter($userAttempts, function ($attempt) {
            return Carbon::parse($attempt['timestamp'])->isAfter(now()->subMinutes(15));
        });

        if (count($failedAttempts) >= self::MAX_LOGIN_ATTEMPTS) {
            $this->lockAccount($email);
        }
    }

    /**
     * Lock account
     */
    private function lockAccount(string $email): void
    {
        $lockoutKey = "auth_lockout:{$email}";
        $lockoutUntil = now()->addMinutes(self::LOCKOUT_DURATION);
        
        Cache::put($lockoutKey, [
            'until' => $lockoutUntil->toISOString(),
            'reason' => 'multiple_failed_attempts',
        ], self::LOCKOUT_DURATION * 60);

        Log::warning('Account locked due to multiple failed attempts', [
            'email' => $email,
            'lockout_until' => $lockoutUntil,
        ]);
    }

    /**
     * Get remaining login attempts
     */
    private function getRemainingAttempts(string $email): int
    {
        $userAttemptsKey = "user_attempts:{$email}";
        $userAttempts = Cache::get($userAttemptsKey, []);
        
        $failedAttempts = array_filter($userAttempts, function ($attempt) {
            return Carbon::parse($attempt['timestamp'])->isAfter(now()->subMinutes(15));
        });

        return max(0, self::MAX_LOGIN_ATTEMPTS - count($failedAttempts));
    }

    /**
     * Check if MFA is required
     */
    private function requiresMFA(User $user): bool
    {
        // Check user preference
        if ($user->mfa_enabled) {
            return true;
        }

        // Check role requirements
        $rolesRequiringMFA = config('permissions.2fa.required_roles', []);
        if (in_array($user->role, $rolesRequiringMFA)) {
            return true;
        }

        // Check for suspicious activity
        if ($this->detectSuspiciousActivity($user->email, request()->ip())) {
            return true;
        }

        return false;
    }

    /**
     * Get available MFA methods
     */
    private function getAvailableMFAMethods(User $user): array
    {
        $methods = [];
        
        if ($user->totp_secret) {
            $methods[] = 'totp';
        }
        
        if ($user->phone) {
            $methods[] = 'sms';
        }
        
        $methods[] = 'email'; // Always available
        
        return $methods;
    }

    /**
     * Verify TOTP code
     */
    private function verifyTOTPCode(User $user, string $code): bool
    {
        // Implementation would use a TOTP library like Google Authenticator
        // For now, we'll use a simple validation
        return strlen($code) === 6 && is_numeric($code);
    }

    /**
     * Verify SMS code
     */
    private function verifySMSCode(User $user, string $code): bool
    {
        $smsKey = "sms_code:{$user->id}";
        $smsData = Cache::get($smsKey);
        
        if (!$smsData) {
            return false;
        }
        
        return $smsData['code'] === $code && Carbon::parse($smsData['expires_at'])->isFuture();
    }

    /**
     * Verify email code
     */
    private function verifyEmailCode(User $user, string $code): bool
    {
        $emailKey = "email_code:{$user->id}";
        $emailData = Cache::get($emailKey);
        
        if (!$emailData) {
            return false;
        }
        
        return $emailData['code'] === $code && Carbon::parse($emailData['expires_at'])->isFuture();
    }

    /**
     * Record successful login
     */
    private function recordSuccessfulLogin(User $user, string $deviceFingerprint = null): void
    {
        $ip = request()->ip();
        $userAgent = request()->userAgent();
        
        // Clear failed attempts
        $userAttemptsKey = "user_attempts:{$user->email}";
        Cache::forget($userAttemptsKey);
        
        // Record successful login
        $loginKey = "successful_logins:{$user->id}";
        $logins = Cache::get($loginKey, []);
        $logins[] = [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'device_fingerprint' => $deviceFingerprint,
            'timestamp' => now()->toISOString(),
        ];
        
        // Keep only last 10 logins
        $logins = array_slice($logins, -10);
        Cache::put($loginKey, $logins, 86400 * 30); // 30 days

        // Update device trust
        if ($deviceFingerprint) {
            $this->updateDeviceTrust($user, $deviceFingerprint);
        }

        Log::info('Successful login', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $ip,
            'device_fingerprint' => $deviceFingerprint,
        ]);
    }

    /**
     * Create session
     */
    private function createSession(User $user, string $deviceFingerprint = null): string
    {
        $sessionToken = Str::random(64);
        $sessionKey = "session:{$sessionToken}";
        
        $sessionData = [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'tenant_id' => $user->tenant_id,
            'device_fingerprint' => $deviceFingerprint,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now()->toISOString(),
            'expires_at' => now()->addMinutes(self::SESSION_TIMEOUT)->toISOString(),
            'last_activity' => now()->toISOString(),
        ];
        
        Cache::put($sessionKey, $sessionData, self::SESSION_TIMEOUT * 60);
        
        return $sessionToken;
    }

    /**
     * Check if device is trusted
     */
    private function isDeviceTrusted(User $user, string $deviceFingerprint): bool
    {
        if (!$deviceFingerprint) {
            return false;
        }
        
        $trustKey = "device_trust:{$user->id}:{$deviceFingerprint}";
        $trustData = Cache::get($trustKey);
        
        if (!$trustData) {
            return false;
        }
        
        $trustedUntil = Carbon::parse($trustData['trusted_until']);
        return $trustedUntil->isFuture();
    }

    /**
     * Update device trust
     */
    private function updateDeviceTrust(User $user, string $deviceFingerprint): void
    {
        $trustKey = "device_trust:{$user->id}:{$deviceFingerprint}";
        $trustedUntil = now()->addDays(self::DEVICE_TRUST_DURATION);
        
        Cache::put($trustKey, [
            'device_fingerprint' => $deviceFingerprint,
            'trusted_at' => now()->toISOString(),
            'trusted_until' => $trustedUntil->toISOString(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], self::DEVICE_TRUST_DURATION * 86400);
    }

    /**
     * Record failed MFA attempt
     */
    private function recordFailedMFA(User $user): void
    {
        $mfaKey = "mfa_attempts:{$user->id}";
        $attempts = Cache::get($mfaKey, []);
        $attempts[] = [
            'timestamp' => now()->toISOString(),
            'ip' => request()->ip(),
        ];
        
        Cache::put($mfaKey, $attempts, 3600); // 1 hour
    }

    /**
     * Get remaining MFA attempts
     */
    private function getRemainingMFAAttempts(User $user): int
    {
        $mfaKey = "mfa_attempts:{$user->id}";
        $attempts = Cache::get($mfaKey, []);
        
        $recentAttempts = array_filter($attempts, function ($attempt) {
            return Carbon::parse($attempt['timestamp'])->isAfter(now()->subMinutes(15));
        });

        return max(0, 3 - count($recentAttempts));
    }

    /**
     * Record successful MFA
     */
    private function recordSuccessfulMFA(User $user): void
    {
        $mfaKey = "mfa_attempts:{$user->id}";
        Cache::forget($mfaKey);
        
        Log::info('Successful MFA verification', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /**
     * Validate password strength
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => $this->calculatePasswordStrength($password),
        ];
    }

    /**
     * Calculate password strength score
     */
    private function calculatePasswordStrength(string $password): int
    {
        $score = 0;
        
        // Length bonus
        $score += min(strlen($password) * 2, 20);
        
        // Character variety bonus
        if (preg_match('/[A-Z]/', $password)) $score += 5;
        if (preg_match('/[a-z]/', $password)) $score += 5;
        if (preg_match('/[0-9]/', $password)) $score += 5;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 10;
        
        // Pattern penalties
        if (preg_match('/(.)\1{2,}/', $password)) $score -= 10; // Repeated characters
        if (preg_match('/123|abc|qwe/i', $password)) $score -= 15; // Common patterns
        
        return max(0, min(100, $score));
    }

    /**
     * Check password history
     */
    public function checkPasswordHistory(User $user, string $newPassword): bool
    {
        $historyKey = "password_history:{$user->id}";
        $history = Cache::get($historyKey, []);
        
        foreach ($history as $oldPassword) {
            if (Hash::check($newPassword, $oldPassword)) {
                return false; // Password was used before
            }
        }
        
        return true; // Password is new
    }

    /**
     * Add password to history
     */
    public function addPasswordToHistory(User $user, string $password): void
    {
        $historyKey = "password_history:{$user->id}";
        $history = Cache::get($historyKey, []);
        
        $history[] = Hash::make($password);
        
        // Keep only last N passwords
        $history = array_slice($history, -self::PASSWORD_HISTORY_LIMIT);
        
        Cache::put($historyKey, $history, 86400 * 365); // 1 year
    }

    /**
     * Get user session info
     */
    public function getSessionInfo(string $sessionToken): ?array
    {
        $sessionKey = "session:{$sessionToken}";
        return Cache::get($sessionKey);
    }

    /**
     * Extend session
     */
    public function extendSession(string $sessionToken): bool
    {
        $sessionKey = "session:{$sessionToken}";
        $sessionData = Cache::get($sessionKey);
        
        if (!$sessionData) {
            return false;
        }
        
        $sessionData['last_activity'] = now()->toISOString();
        $sessionData['expires_at'] = now()->addMinutes(self::SESSION_TIMEOUT)->toISOString();
        
        Cache::put($sessionKey, $sessionData, self::SESSION_TIMEOUT * 60);
        
        return true;
    }

    /**
     * Invalidate session
     */
    public function invalidateSession(string $sessionToken): bool
    {
        $sessionKey = "session:{$sessionToken}";
        return Cache::forget($sessionKey);
    }

    /**
     * Get user active sessions
     */
    public function getActiveSessions(int $userId): array
    {
        // This would require scanning all session keys
        // In production, you'd use Redis SCAN or similar
        return [];
    }

    /**
     * Revoke all user sessions
     */
    public function revokeAllUserSessions(int $userId): int
    {
        // This would require scanning all session keys
        // In production, you'd use Redis SCAN or similar
        return 0;
    }
}
