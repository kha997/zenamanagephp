<?php declare(strict_types=1);

namespace App\Jobs;

use App\Mail\TenantInvitationMail;
use App\Models\TenantInvitation;
use App\Services\TenancyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * SendTenantInvitationMail Job
 * 
 * Queued job for sending tenant invitation emails.
 */
class SendTenantInvitationMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes
    public $tries = 3;
    public $backoff = [30, 60, 120]; // Retry delays in seconds

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $invitationId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Load invitation with relationships
            $invitation = TenantInvitation::with(['tenant', 'inviter'])
                ->findOrFail($this->invitationId);

            // Get tenant name
            $tenantName = $invitation->tenant?->name ?? 'the workspace';

            // Get role label
            $roleLabel = $this->getRoleLabel($invitation->role);

            // Get inviter name
            $inviterName = $invitation->inviter?->name ?? 'Administrator';

            // Send email
            Mail::to($invitation->email)->send(
                new TenantInvitationMail(
                    $invitation,
                    $tenantName,
                    $roleLabel,
                    $inviterName
                )
            );

            Log::info('Tenant invitation email sent successfully', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'tenant_id' => $invitation->tenant_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send tenant invitation email', [
                'invitation_id' => $this->invitationId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Get display label for role
     */
    private function getRoleLabel(string $role): string
    {
        $roleMap = [
            'owner' => 'Owner',
            'admin' => 'Administrator',
            'member' => 'Member',
            'viewer' => 'Viewer',
        ];

        return $roleMap[$role] ?? ucfirst($role);
    }
}

