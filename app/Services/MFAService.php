<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use PragmaRX\Google2FA\Google2FA;

/**
 * Multi-Factor Authentication Service
 * 
 * Handles TOTP-based MFA and recovery codes
 */
class MFAService
{
    private Google2FA $google2fa;
    private SecureAuditService $auditService;
    private int $recoveryCodesCount;

    public function __construct(SecureAuditService $auditService)
    {
        $this->google2fa = new Google2FA();
        $this->auditService = $auditService;
        $this->recoveryCodesCount = config('mfa.recovery_codes_count', 10);
    }

    /**
     * Generate MFA secret for user
     */
    public function generateSecret(User $user): array
    {
        try {
            $secret = $this->google2fa->generateSecretKey();
            $qrCodeUrl = $this->google2fa->getQRCodeUrl(
                config('app.name', 'ZENA Manage'),
                $user->email,
                $secret
            );

            // Store secret temporarily (not enabled yet)
            $user->update([
                'mfa_secret' => encrypt($secret)
            ]);

            // Log action
            $this->auditService->logAction(
                userId: $user->id,
                action: 'mfa_secret_generated',
                entityType: 'User',
                entityId: $user->id,
                newData: ['mfa_setup_initiated' => true],
                tenantId: $user->tenant_id
            );

            return [
                'success' => true,
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
                'manual_entry_key' => $secret
            ];

        } catch (\Exception $e) {
            Log::error('MFA secret generation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to generate MFA secret'
            ];
        }
    }

    /**
     * Enable MFA for user
     */
    public function enableMFA(User $user, string $code): array
    {
        try {
            $secret = decrypt($user->mfa_secret);
            
            // Verify the code
            if (!$this->google2fa->verifyKey($secret, $code)) {
                return [
                    'success' => false,
                    'message' => 'Invalid verification code'
                ];
            }

            // Generate recovery codes
            $recoveryCodes = $this->generateRecoveryCodes();

            // Enable MFA
            $user->update([
                'mfa_enabled' => true,
                'mfa_enabled_at' => Carbon::now(),
                'mfa_recovery_codes' => json_encode(array_map('hash', $recoveryCodes)),
                'mfa_backup_codes_used' => 0
            ]);

            // Log action
            $this->auditService->logAction(
                userId: $user->id,
                action: 'mfa_enabled',
                entityType: 'User',
                entityId: $user->id,
                newData: ['mfa_enabled_at' => Carbon::now()],
                tenantId: $user->tenant_id
            );

            return [
                'success' => true,
                'message' => 'MFA enabled successfully',
                'recovery_codes' => $recoveryCodes
            ];

        } catch (\Exception $e) {
            Log::error('MFA enable failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to enable MFA'
            ];
        }
    }

    /**
     * Disable MFA for user
     */
    public function disableMFA(User $user, string $code): array
    {
        try {
            $secret = decrypt($user->mfa_secret);
            
            // Verify the code
            if (!$this->google2fa->verifyKey($secret, $code)) {
                return [
                    'success' => false,
                    'message' => 'Invalid verification code'
                ];
            }

            // Disable MFA
            $user->update([
                'mfa_enabled' => false,
                'mfa_secret' => null,
                'mfa_recovery_codes' => null,
                'mfa_enabled_at' => null,
                'mfa_backup_codes_used' => 0
            ]);

            // Log action
            $this->auditService->logAction(
                userId: $user->id,
                action: 'mfa_disabled',
                entityType: 'User',
                entityId: $user->id,
                oldData: ['mfa_enabled' => true],
                newData: ['mfa_enabled' => false],
                tenantId: $user->tenant_id
            );

            return [
                'success' => true,
                'message' => 'MFA disabled successfully'
            ];

        } catch (\Exception $e) {
            Log::error('MFA disable failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to disable MFA'
            ];
        }
    }

    /**
     * Verify MFA code
     */
    public function verifyCode(User $user, string $code): array
    {
        try {
            if (!$user->mfa_enabled || !$user->mfa_secret) {
                return [
                    'success' => false,
                    'message' => 'MFA is not enabled for this user'
                ];
            }

            $secret = decrypt($user->mfa_secret);
            
            // Verify TOTP code
            if ($this->google2fa->verifyKey($secret, $code)) {
                // Log successful verification
                $this->auditService->logAction(
                    userId: $user->id,
                    action: 'mfa_verification_success',
                    entityType: 'User',
                    entityId: $user->id,
                    tenantId: $user->tenant_id
                );

                return [
                    'success' => true,
                    'message' => 'MFA verification successful'
                ];
            }

            // Log failed verification
            $this->auditService->logAction(
                userId: $user->id,
                action: 'mfa_verification_failed',
                entityType: 'User',
                entityId: $user->id,
                tenantId: $user->tenant_id
            );

            return [
                'success' => false,
                'message' => 'Invalid MFA code'
            ];

        } catch (\Exception $e) {
            Log::error('MFA verification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'MFA verification failed'
            ];
        }
    }

    /**
     * Verify recovery code
     */
    public function verifyRecoveryCode(User $user, string $code): array
    {
        try {
            if (!$user->mfa_enabled || !$user->mfa_recovery_codes) {
                return [
                    'success' => false,
                    'message' => 'No recovery codes available'
                ];
            }

            $recoveryCodes = json_decode($user->mfa_recovery_codes, true);
            $hashedCode = hash('sha256', $code);

            // Check if code exists and hasn't been used
            $codeIndex = array_search($hashedCode, $recoveryCodes);
            
            if ($codeIndex === false) {
                // Log failed recovery attempt
                $this->auditService->logAction(
                    userId: $user->id,
                    action: 'mfa_recovery_failed',
                    entityType: 'User',
                    entityId: $user->id,
                    tenantId: $user->tenant_id
                );

                return [
                    'success' => false,
                    'message' => 'Invalid recovery code'
                ];
            }

            // Remove used code
            unset($recoveryCodes[$codeIndex]);
            $recoveryCodes = array_values($recoveryCodes); // Re-index array

            // Update user
            $user->update([
                'mfa_recovery_codes' => json_encode($recoveryCodes),
                'mfa_backup_codes_used' => $user->mfa_backup_codes_used + 1
            ]);

            // Log successful recovery
            $this->auditService->logAction(
                userId: $user->id,
                action: 'mfa_recovery_success',
                entityType: 'User',
                entityId: $user->id,
                newData: ['recovery_codes_remaining' => count($recoveryCodes)],
                tenantId: $user->tenant_id
            );

            return [
                'success' => true,
                'message' => 'Recovery code verified successfully',
                'remaining_codes' => count($recoveryCodes)
            ];

        } catch (\Exception $e) {
            Log::error('MFA recovery code verification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Recovery code verification failed'
            ];
        }
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(User $user, string $code): array
    {
        try {
            $secret = decrypt($user->mfa_secret);
            
            // Verify the code
            if (!$this->google2fa->verifyKey($secret, $code)) {
                return [
                    'success' => false,
                    'message' => 'Invalid verification code'
                ];
            }

            // Generate new recovery codes
            $recoveryCodes = $this->generateRecoveryCodes();

            // Update user
            $user->update([
                'mfa_recovery_codes' => json_encode(array_map('hash', $recoveryCodes)),
                'mfa_backup_codes_used' => 0
            ]);

            // Log action
            $this->auditService->logAction(
                userId: $user->id,
                action: 'mfa_recovery_codes_regenerated',
                entityType: 'User',
                entityId: $user->id,
                tenantId: $user->tenant_id
            );

            return [
                'success' => true,
                'message' => 'Recovery codes regenerated successfully',
                'recovery_codes' => $recoveryCodes
            ];

        } catch (\Exception $e) {
            Log::error('MFA recovery codes regeneration failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to regenerate recovery codes'
            ];
        }
    }

    /**
     * Check if user has MFA enabled
     */
    public function isMFAEnabled(User $user): bool
    {
        return $user->mfa_enabled && !empty($user->mfa_secret);
    }

    /**
     * Get MFA status for user
     */
    public function getMFAStatus(User $user): array
    {
        $recoveryCodes = $user->mfa_recovery_codes ? 
            json_decode($user->mfa_recovery_codes, true) : [];

        return [
            'mfa_enabled' => $user->mfa_enabled,
            'mfa_enabled_at' => $user->mfa_enabled_at,
            'recovery_codes_count' => count($recoveryCodes),
            'backup_codes_used' => $user->mfa_backup_codes_used
        ];
    }

    /**
     * Generate recovery codes
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < $this->recoveryCodesCount; $i++) {
            $codes[] = strtoupper(Str::random(8));
        }
        return $codes;
    }
}
