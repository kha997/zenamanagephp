<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use App\Models\EmailTracking;
use App\Mail\InvitationEmail;
use App\Mail\WelcomeEmail;
use App\Jobs\SendInvitationEmailJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Services\EmailTemplateCacheService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EmailService
{
    protected $templateCacheService;

    public function __construct(EmailTemplateCacheService $templateCacheService)
    {
        $this->templateCacheService = $templateCacheService;
    }

    /**
     * Send invitation email (queued)
     */
    public function sendInvitationEmail(Invitation $invitation): bool
    {
        try {
            // Check if queuing is enabled
            $queueEnabled = config('mail.queue.enabled', true);
            
            if ($queueEnabled) {
                // Dispatch job to queue
                SendInvitationEmailJob::dispatch($invitation);
                
                Log::info('Invitation email queued successfully', [
                    'invitation_id' => $invitation->id,
                    'email' => $invitation->email,
                ]);
                
                return true;
            } else {
                // Send immediately (synchronous)
                return $this->sendInvitationEmailSync($invitation);
            }

        } catch (\Exception $e) {
            Log::error('Failed to queue invitation email', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send invitation email synchronously
     */
    public function sendInvitationEmailSync(Invitation $invitation): bool
    {
        try {
            // Create email tracking record
            $tracking = EmailTracking::create([
                'email_type' => 'invitation',
                'recipient_email' => $invitation->email,
                'recipient_name' => $invitation->first_name . ' ' . $invitation->last_name,
                'invitation_id' => $invitation->id,
                'organization_id' => $invitation->organization_id,
                'subject' => "You're invited to join {$invitation->organization->name}",
                'content_hash' => $this->generateContentHash($invitation),
                'metadata' => [
                    'role' => $invitation->role,
                    'project_id' => $invitation->project_id,
                    'invited_by' => $invitation->invited_by,
                    'expires_at' => $invitation->expires_at->toISOString(),
                ],
                'status' => 'pending',
            ]);

            // Send email
            Mail::to($invitation->email)->send(new InvitationEmail($invitation));

            // Mark as sent
            $tracking->markAsSent();

            // Log success
            Log::info('Invitation email sent successfully (sync)', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'tracking_id' => $tracking->tracking_id,
            ]);

            return true;

        } catch (\Exception $e) {
            // Mark as failed
            if (isset($tracking)) {
                $tracking->markAsFailed($e->getMessage());
            }

            // Log error
            Log::error('Failed to send invitation email (sync)', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send welcome email (queued)
     */
    public function sendWelcomeEmail(User $user): bool
    {
        try {
            // Check if queuing is enabled
            $queueEnabled = config('mail.queue.enabled', true);
            
            if ($queueEnabled) {
                // Dispatch job to queue
                SendWelcomeEmailJob::dispatch($user);
                
                Log::info('Welcome email queued successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                
                return true;
            } else {
                // Send immediately (synchronous)
                return $this->sendWelcomeEmailSync($user);
            }

        } catch (\Exception $e) {
            Log::error('Failed to queue welcome email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send welcome email synchronously
     */
    public function sendWelcomeEmailSync(User $user): bool
    {
        try {
            // Create email tracking record
            $tracking = EmailTracking::create([
                'email_type' => 'welcome',
                'recipient_email' => $user->email,
                'recipient_name' => $user->name,
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
                'subject' => "Welcome to " . ($user->organization->name ?? 'our platform') . "!",
                'content_hash' => $this->generateUserContentHash($user),
                'metadata' => [
                    'role' => $user->role,
                    'joined_at' => $user->joined_at?->toISOString(),
                    'invitation_id' => $user->invitation_id,
                ],
                'status' => 'pending',
            ]);

            // Send email
            Mail::to($user->email)->send(new WelcomeEmail($user));

            // Mark as sent
            $tracking->markAsSent();

            // Log success
            Log::info('Welcome email sent successfully (sync)', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tracking_id' => $tracking->tracking_id,
            ]);

            return true;

        } catch (\Exception $e) {
            // Mark as failed
            if (isset($tracking)) {
                $tracking->markAsFailed($e->getMessage());
            }

            // Log error
            Log::error('Failed to send welcome email (sync)', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resend invitation email
     */
    public function resendInvitationEmail(Invitation $invitation): bool
    {
        // Check if invitation is still valid
        if ($invitation->status !== 'pending' || $invitation->isExpired()) {
            return false;
        }

        // Update expiry date
        $invitation->update([
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        return $this->sendInvitationEmail($invitation);
    }

    /**
     * Track email open
     */
    public function trackEmailOpen(string $trackingId, array $details = []): bool
    {
        try {
            $tracking = EmailTracking::where('tracking_id', $trackingId)->first();
            
            if (!$tracking) {
                return false;
            }

            $tracking->markAsOpened($details);

            Log::info('Email opened tracked', [
                'tracking_id' => $trackingId,
                'details' => $details,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to track email open', [
                'tracking_id' => $trackingId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Track email click
     */
    public function trackEmailClick(string $trackingId, string $linkUrl, array $details = []): bool
    {
        try {
            $tracking = EmailTracking::where('tracking_id', $trackingId)->first();
            
            if (!$tracking) {
                return false;
            }

            $clickDetails = array_merge($details, [
                'link_url' => $linkUrl,
                'clicked_at' => Carbon::now()->toISOString(),
            ]);

            $tracking->markAsClicked($clickDetails);

            Log::info('Email click tracked', [
                'tracking_id' => $trackingId,
                'link_url' => $linkUrl,
                'details' => $details,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to track email click', [
                'tracking_id' => $trackingId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get email analytics for organization
     */
    public function getEmailAnalytics(int $organizationId, Carbon $from = null, Carbon $to = null): array
    {
        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();

        $trackings = EmailTracking::where('organization_id', $organizationId)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $totalSent = $trackings->count();
        $totalDelivered = $trackings->where('status', 'delivered')->count();
        $totalOpened = $trackings->where('status', 'opened')->count();
        $totalClicked = $trackings->where('status', 'clicked')->count();
        $totalBounced = $trackings->where('status', 'bounced')->count();
        $totalFailed = $trackings->where('status', 'failed')->count();

        return [
            'total_sent' => $totalSent,
            'total_delivered' => $totalDelivered,
            'total_opened' => $totalOpened,
            'total_clicked' => $totalClicked,
            'total_bounced' => $totalBounced,
            'total_failed' => $totalFailed,
            'delivery_rate' => $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 2) : 0,
            'open_rate' => $totalDelivered > 0 ? round(($totalOpened / $totalDelivered) * 100, 2) : 0,
            'click_rate' => $totalOpened > 0 ? round(($totalClicked / $totalOpened) * 100, 2) : 0,
            'bounce_rate' => $totalSent > 0 ? round(($totalBounced / $totalSent) * 100, 2) : 0,
            'failure_rate' => $totalSent > 0 ? round(($totalFailed / $totalSent) * 100, 2) : 0,
            'engagement_score' => $trackings->avg('engagement_score') ?? 0,
        ];
    }

    /**
     * Generate content hash for invitation
     */
    private function generateContentHash(Invitation $invitation): string
    {
        $content = [
            'email' => $invitation->email,
            'role' => $invitation->role,
            'project_id' => $invitation->project_id,
            'organization_id' => $invitation->organization_id,
            'expires_at' => $invitation->expires_at->toISOString(),
        ];

        return hash('sha256', json_encode($content));
    }

    /**
     * Generate content hash for user
     */
    private function generateUserContentHash(User $user): string
    {
        $content = [
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
            'organization_id' => $user->organization_id,
            'joined_at' => $user->joined_at?->toISOString(),
        ];

        return hash('sha256', json_encode($content));
    }
}
