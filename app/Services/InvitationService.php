<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Tenant;
use App\Mail\InvitationEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

/**
 * InvitationService - Handles invitation creation, sending, and acceptance
 */
class InvitationService
{
    /**
     * Default invitation expiry days
     */
    private const DEFAULT_EXPIRY_DAYS = 7;

    /**
     * Create a single invitation with idempotent logic
     *
     * @param array $data
     * @return array ['status' => 'created'|'already_member'|'pending_invitation', 'invitation' => Invitation|null, 'user' => User|null]
     * @throws \Exception
     */
    public function createInvitation(array $data): array
    {
        // Validate required fields
        if (empty($data['email'])) {
            throw new \InvalidArgumentException('Email is required');
        }
        if (empty($data['tenant_id'])) {
            throw new \InvalidArgumentException('Tenant ID is required');
        }
        if (empty($data['invited_by'])) {
            throw new \InvalidArgumentException('Invited by (user ID) is required');
        }

        $email = $data['email'];
        $tenantId = $data['tenant_id'];

        // Idempotent check: Check for existing user in same tenant
        $existingUser = $this->checkExistingUser($email, $tenantId);
        if ($existingUser) {
            return [
                'status' => 'already_member',
                'invitation' => null,
                'user' => $existingUser,
                'message' => "User with email {$email} is already a member of this tenant",
            ];
        }

        // Check for existing pending invitation
        $existingInvitation = Invitation::where('email', $email)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->first();

        if ($existingInvitation) {
            return [
                'status' => 'pending_invitation',
                'invitation' => $existingInvitation,
                'user' => null,
                'message' => "Pending invitation already exists for {$email}",
            ];
        }

        // Check if user exists in different tenant - create join request
        $userInOtherTenant = User::where('email', $email)
            ->where('tenant_id', '!=', $tenantId)
            ->first();

        if ($userInOtherTenant) {
            // User exists but in different tenant - create invitation to join
            // This allows cross-tenant membership (if supported)
            // For now, we'll create the invitation normally
        }

        // Create invitation
        $invitation = Invitation::create([
            'email' => $email,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'role' => $data['role'] ?? 'member',
            'message' => $data['message'] ?? null,
            'note' => $data['note'] ?? null,
            'tenant_id' => $tenantId,
            'project_id' => $data['project_id'] ?? null,
            'invited_by' => $data['invited_by'],
            'expires_at' => $data['expires_at'] ?? Carbon::now()->addDays(self::DEFAULT_EXPIRY_DAYS),
            'status' => 'pending',
        ]);

        // Send email if requested and SMTP is configured
        if (!empty($data['send_email']) && $this->isEmailConfigured()) {
            $this->sendInvitationEmail($invitation);
        }

        return [
            'status' => 'created',
            'invitation' => $invitation,
            'user' => null,
            'message' => "Invitation created successfully for {$email}",
        ];
    }

    /**
     * Create bulk invitations
     *
     * @param array $emails Array of email addresses
     * @param array $defaults Default values for all invitations
     * @return array ['created' => [], 'already_member' => [], 'pending' => [], 'errors' => []]
     */
    public function createBulkInvitations(array $emails, array $defaults): array
    {
        $created = [];
        $alreadyMember = [];
        $pending = [];
        $errors = [];

        foreach ($emails as $index => $email) {
            try {
                $email = trim($email);
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = [
                        'row' => $index + 1,
                        'email' => $email,
                        'message' => 'Invalid email format',
                    ];
                    continue;
                }

                $result = $this->createInvitation(array_merge($defaults, [
                    'email' => $email,
                    'send_email' => false, // Don't send immediately, batch later
                ]));

                if ($result['status'] === 'created') {
                    $created[] = $result['invitation'];
                } elseif ($result['status'] === 'already_member') {
                    $alreadyMember[] = [
                        'email' => $email,
                        'user' => $result['user'],
                    ];
                } elseif ($result['status'] === 'pending_invitation') {
                    $pending[] = $result['invitation'];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'email' => $email,
                    'message' => $e->getMessage(),
                ];
            }
        }

        // Send emails in batch if configured
        if (!empty($created) && !empty($defaults['send_email']) && $this->isEmailConfigured()) {
            foreach ($created as $invitation) {
                try {
                    $this->sendInvitationEmail($invitation);
                } catch (\Exception $e) {
                    Log::warning("Failed to send invitation email", [
                        'invitation_id' => $invitation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $total = count($emails);
        $createdCount = count($created);
        $alreadyMemberCount = count($alreadyMember);
        $pendingCount = count($pending);
        $errorsCount = count($errors);

        return [
            'created' => $created,
            'already_member' => $alreadyMember,
            'pending' => $pending,
            'errors' => $errors,
            'summary' => [
                'total' => $total,
                'created' => $createdCount,
                'already_member' => $alreadyMemberCount,
                'pending' => $pendingCount,
                'errors' => $errorsCount,
            ],
        ];
    }

    /**
     * Send invitation email
     *
     * @param Invitation $invitation
     * @return bool
     */
    public function sendInvitationEmail(Invitation $invitation): bool
    {
        if (!$this->isEmailConfigured()) {
            Log::warning("Email not configured, skipping invitation email", [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
            ]);
            return false;
        }

        try {
            $mailable = new InvitationEmail($invitation);
            
            // Queue if mail driver supports it, otherwise send immediately
            if ($mailable->shouldQueue()) {
                Mail::to($invitation->email)->queue($mailable);
                Log::info("Invitation email queued", [
                    'invitation_id' => $invitation->id,
                    'email' => $invitation->email,
                ]);
            } else {
                Mail::to($invitation->email)->send($mailable);
                Log::info("Invitation email sent", [
                    'invitation_id' => $invitation->id,
                    'email' => $invitation->email,
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send invitation email", [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Accept invitation and create user
     *
     * @param string $token
     * @param array $userData
     * @return User
     * @throws \Exception
     */
    public function acceptInvitation(string $token, array $userData): User
    {
        $invitation = Invitation::findByToken($token);

        if (!$invitation) {
            throw new \Exception('Invalid invitation token');
        }

        if (!$invitation->canBeAccepted()) {
            if ($invitation->is_expired) {
                throw new \Exception('Invitation has expired');
            }
            if ($invitation->is_used) {
                throw new \Exception('Invitation has already been used');
            }
            throw new \Exception('Invitation cannot be accepted');
        }

        // Check if user already exists
        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser) {
            // User exists - add to tenant if not already a member
            if ($existingUser->tenant_id !== $invitation->tenant_id) {
                // Update tenant_id to join the new tenant
                $existingUser->update([
                    'tenant_id' => $invitation->tenant_id,
                ]);
            }

            // Mark invitation as accepted
            $invitation->markAsAccepted($existingUser->id);

            return $existingUser;
        }

        // Create new user
        $user = User::create([
            'name' => $userData['name'] ?? ($invitation->full_name ?: $invitation->email),
            'email' => $invitation->email,
            'password' => bcrypt($userData['password']),
            'tenant_id' => $invitation->tenant_id,
            'first_name' => $invitation->first_name ?? $userData['first_name'] ?? null,
            'last_name' => $invitation->last_name ?? $userData['last_name'] ?? null,
            'phone' => $userData['phone'] ?? null,
            'job_title' => $userData['job_title'] ?? null,
            'role' => $invitation->role,
            'status' => 'active',
            'is_active' => true,
            'email_verified_at' => now(), // Auto-verify email from invitation
        ]);

        // Mark invitation as accepted
        $invitation->markAsAccepted($user->id);

        Log::info("Invitation accepted and user created", [
            'invitation_id' => $invitation->id,
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $user;
    }

    /**
     * Resend invitation (generate new token and send email)
     *
     * @param int $id
     * @return Invitation
     * @throws \Exception
     */
    public function resendInvitation(int $id): Invitation
    {
        $invitation = Invitation::findOrFail($id);

        // Reset invitation
        $invitation->resend();

        // Send email if configured
        if ($this->isEmailConfigured()) {
            $this->sendInvitationEmail($invitation);
        }

        return $invitation;
    }

    /**
     * Check if user exists with email and tenant_id
     *
     * @param string $email
     * @param string|object $tenantId Can be string or Ulid object
     * @return User|null
     */
    public function checkExistingUser(string $email, $tenantId): ?User
    {
        // Convert Ulid to string if needed
        $tenantIdString = is_object($tenantId) && method_exists($tenantId, 'toString')
            ? $tenantId->toString()
            : (string) $tenantId;
            
        return User::where('email', $email)
            ->where('tenant_id', $tenantIdString)
            ->first();
    }

    /**
     * Check if email service is configured
     *
     * @return bool
     */
    public function isEmailConfigured(): bool
    {
        $mailer = config('mail.default');
        return !empty($mailer) && $mailer !== 'array';
    }
}

