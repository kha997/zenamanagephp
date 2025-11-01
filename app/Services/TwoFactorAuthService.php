<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate 2FA secret for user.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Generate QR code URL for 2FA setup.
     */
    public function getQRCodeUrl(string $email, string $secret, string $company = 'ZenaManage'): string
    {
        return $this->google2fa->getQRCodeUrl($company, $email, $secret);
    }

    /**
     * Verify 2FA code.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * Generate backup codes.
     */
    public function generateBackupCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(md5(uniqid()), 0, 8));
        }
        return $codes;
    }

    /**
     * Check if user has 2FA enabled.
     */
    public function isEnabled($user): bool
    {
        return !empty($user->two_factor_secret);
    }

    /**
     * Check if 2FA is required for user role.
     */
    public function isRequired($user): bool
    {
        $requiredRoles = config('permissions.2fa.required_roles', []);
        return in_array($user->role, $requiredRoles);
    }

    /**
     * Enable 2FA for user.
     */
    public function enable($user, string $secret, array $backupCodes): void
    {
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_backup_codes' => encrypt(json_encode($backupCodes)),
            'two_factor_enabled_at' => now(),
        ]);
    }

    /**
     * Disable 2FA for user.
     */
    public function disable($user): void
    {
        $user->update([
            'two_factor_secret' => null,
            'two_factor_backup_codes' => null,
            'two_factor_enabled_at' => null,
        ]);
    }

    /**
     * Verify backup code.
     */
    public function verifyBackupCode($user, string $code): bool
    {
        $backupCodes = json_decode(decrypt($user->two_factor_backup_codes), true);
        
        if (in_array($code, $backupCodes)) {
            // Remove used backup code
            $backupCodes = array_diff($backupCodes, [$code]);
            $user->update([
                'two_factor_backup_codes' => encrypt(json_encode($backupCodes)),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Check if user is in grace period.
     */
    public function isInGracePeriod($user): bool
    {
        if (!$this->isRequired($user)) {
            return false;
        }

        $gracePeriod = config('permissions.2fa.grace_period', 7);
        $enabledAt = $user->two_factor_enabled_at;

        if (!$enabledAt) {
            return true; // Not enabled yet, still in grace period
        }

        return $enabledAt->addDays($gracePeriod)->isFuture();
    }

    /**
     * Get remaining grace period days.
     */
    public function getRemainingGracePeriod($user): int
    {
        if (!$this->isRequired($user) || !$user->two_factor_enabled_at) {
            return 0;
        }

        $gracePeriod = config('permissions.2fa.grace_period', 7);
        $endDate = $user->two_factor_enabled_at->addDays($gracePeriod);
        
        return max(0, now()->diffInDays($endDate, false));
    }
}
