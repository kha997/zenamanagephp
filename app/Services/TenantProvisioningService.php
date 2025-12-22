<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Tenant Provisioning Service
 * 
 * Handles tenant creation and owner user setup
 * with email verification workflow.
 */
class TenantProvisioningService
{
    public function __construct(
        private EmailVerificationService $emailVerificationService
    ) {}

    /**
     * Provision new tenant with owner user
     */
    public function provisionTenant(array $data): array
    {
        try {
            DB::beginTransaction();

            // Create tenant
            $tenant = $this->createTenant([
                'name' => $data['tenant_name'],
            ]);

            // Create owner user
            $user = $this->createOwnerUser([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => $data['owner_password'],
                'tenant_id' => $tenant->id,
            ]);

            // Queue email verification (don't fail if email service is down)
            try {
                $this->emailVerificationService->queueVerificationEmail($user);
            } catch (\Exception $e) {
                Log::error('Failed to queue email verification', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
                // Continue with registration even if email fails
            }

            DB::commit();

            Log::info('Tenant provisioned successfully', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return [
                'success' => true,
                'tenant' => $tenant,
                'user' => $user,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Tenant provisioning failed', [
                'error' => $e->getMessage(),
                'email' => $data['owner_email'] ?? null,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create account. Please try again.',
                'code' => 'PROVISIONING_FAILED'
            ];
        }
    }

    /**
     * Create new tenant
     */
    private function createTenant(array $data): Tenant
    {
        $slug = $this->generateUniqueSlug($data['name']);

        return Tenant::create([
            'name' => $data['name'],
            'slug' => $slug,
            'status' => 'active',
            'plan' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'settings' => [
                'timezone' => 'UTC',
                'locale' => 'en',
                'features' => [
                    'projects' => true,
                    'tasks' => true,
                    'documents' => true,
                    'notifications' => true,
                ]
            ],
        ]);
    }

    /**
     * Create owner user for tenant
     */
    private function createOwnerUser(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'tenant_id' => $data['tenant_id'],
            'role' => 'admin', // First user is admin
            'is_active' => true,
            'email_verified_at' => null, // Will be verified via email
            'preferences' => [
                'theme' => 'light',
                'notifications' => [
                    'email' => true,
                    'in_app' => true,
                ],
                'dashboard' => [
                    'layout' => 'default',
                    'widgets' => ['recent_projects', 'recent_tasks', 'kpis'],
                ]
            ],
        ]);
    }

    /**
     * Generate unique slug for tenant
     */
    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Verify email and activate user
     */
    public function verifyEmail(string $token): array
    {
        try {
            $user = $this->emailVerificationService->verifyToken($token);

            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'Invalid or expired verification token',
                    'code' => 'INVALID_TOKEN'
                ];
            }

            // Mark email as verified
            $user->update([
                'email_verified_at' => now(),
            ]);

            Log::info('Email verified successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return [
                'success' => true,
                'user' => $user,
                'message' => 'Email verified successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Email verification failed', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10) . '...',
            ]);

            return [
                'success' => false,
                'error' => 'Email verification failed',
                'code' => 'VERIFICATION_FAILED'
            ];
        }
    }
}
