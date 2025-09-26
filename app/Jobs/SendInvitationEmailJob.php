<?php

namespace App\Jobs;

use App\Mail\InvitationEmail;
use App\Models\EmailTracking;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInvitationEmailJob implements ShouldQueue
{

    public $invitation;
    public $tries = 3;
    public $timeout = 60;
    public $backoff = [30, 60, 120]; // Retry delays in seconds

    /**
     * Create a new job instance.
     */
    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
        
        // Set queue name based on priority
        $this->onQueue($this->getQueueName());
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if invitation is still valid
            if ($this->invitation->status !== 'pending' || $this->invitation->isExpired()) {
                Log::info('Invitation email job skipped - invitation no longer valid', [
                    'invitation_id' => $this->invitation->id,
                    'status' => $this->invitation->status,
                ]);
                return;
            }

            // Check rate limits
            if (!$this->checkRateLimits()) {
                Log::warning('Invitation email job delayed - rate limit exceeded', [
                    'invitation_id' => $this->invitation->id,
                ]);
                
                // Delay the job for 1 minute
                $this->release(60);
                return;
            }

            // Create email tracking record
            $tracking = $this->createEmailTracking();

            // Get cached email template
            $emailTemplate = $this->getCachedEmailTemplate();

            // Send email
            Mail::to($this->invitation->email)->send($emailTemplate);

            // Mark as sent
            $tracking->markAsSent();

            // Update invitation
            $this->invitation->update([
                'sent_at' => Carbon::now(),
            ]);

            // Log success
            Log::info('Invitation email sent successfully via queue', [
                'invitation_id' => $this->invitation->id,
                'email' => $this->invitation->email,
                'tracking_id' => $tracking->tracking_id,
                'queue' => $this->queue,
            ]);

        } catch (\Exception $e) {
            // Mark as failed
            if (isset($tracking)) {
                $tracking->markAsFailed($e->getMessage());
            }

            // Log error
            Log::error('Failed to send invitation email via queue', [
                'invitation_id' => $this->invitation->id,
                'email' => $this->invitation->email,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Invitation email job failed permanently', [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Update invitation status
        $this->invitation->update([
            'status' => 'failed',
            'failed_at' => Carbon::now(),
        ]);
    }

    /**
     * Get queue name based on invitation priority
     */
    private function getQueueName(): string
    {
        $role = $this->invitation->role;
        
        // High priority for admin roles
        if (in_array($role, ['super_admin', 'admin', 'project_manager'])) {
            return 'emails-high';
        }
        
        // Medium priority for regular roles
        if (in_array($role, ['designer', 'site_engineer', 'qc_engineer'])) {
            return 'emails-medium';
        }
        
        // Low priority for other roles
        return 'emails-low';
    }

    /**
     * Check rate limits
     */
    private function checkRateLimits(): bool
    {
        $rateLimits = config('mail.rate_limits', []);
        
        if (!$rateLimits['enabled'] ?? true) {
            return true;
        }

        $now = now();
        $organizationId = $this->invitation->organization_id;

        // Check per minute limit
        $minuteKey = "email_rate_limit_minute_{$organizationId}_{$now->format('Y-m-d-H-i')}";
        $minuteCount = Cache::get($minuteKey, 0);
        if ($minuteCount >= ($rateLimits['max_per_minute'] ?? 60)) {
            return false;
        }

        // Check per hour limit
        $hourKey = "email_rate_limit_hour_{$organizationId}_{$now->format('Y-m-d-H')}";
        $hourCount = Cache::get($hourKey, 0);
        if ($hourCount >= ($rateLimits['max_per_hour'] ?? 1000)) {
            return false;
        }

        // Check per day limit
        $dayKey = "email_rate_limit_day_{$organizationId}_{$now->format('Y-m-d')}";
        $dayCount = Cache::get($dayKey, 0);
        if ($dayCount >= ($rateLimits['max_per_day'] ?? 10000)) {
            return false;
        }

        // Increment counters
        Cache::increment($minuteKey, 1);
        Cache::increment($hourKey, 1);
        Cache::increment($dayKey, 1);

        // Set expiration
        Cache::put($minuteKey, $minuteCount + 1, 60);
        Cache::put($hourKey, $hourCount + 1, 3600);
        Cache::put($dayKey, $dayCount + 1, 86400);

        return true;
    }

    /**
     * Create email tracking record
     */
    private function createEmailTracking(): EmailTracking
    {
        return EmailTracking::create([
            'email_type' => 'invitation',
            'recipient_email' => $this->invitation->email,
            'recipient_name' => $this->invitation->first_name . ' ' . $this->invitation->last_name,
            'invitation_id' => $this->invitation->id,
            'organization_id' => $this->invitation->organization_id,
            'subject' => "You're invited to join {$this->invitation->organization->name}",
            'content_hash' => $this->generateContentHash(),
            'metadata' => [
                'role' => $this->invitation->role,
                'project_id' => $this->invitation->project_id,
                'invited_by' => $this->invitation->invited_by,
                'expires_at' => $this->invitation->expires_at->toISOString(),
                'queue' => $this->queue,
                'job_id' => $this->job->getJobId(),
            ],
            'status' => 'pending',
        ]);
    }

    /**
     * Get cached email template
     */
    private function getCachedEmailTemplate(): InvitationEmail
    {
        $cacheKey = "email_template_invitation_{$this->invitation->id}";
        $cacheEnabled = config('mail.template_cache.enabled', true);
        
        if ($cacheEnabled) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        // Create new email template
        $emailTemplate = new InvitationEmail($this->invitation);
        
        // Cache the template
        if ($cacheEnabled) {
            $ttl = config('mail.template_cache.ttl', 3600);
            Cache::put($cacheKey, $emailTemplate, $ttl);
        }

        return $emailTemplate;
    }

    /**
     * Generate content hash for tracking
     */
    private function generateContentHash(): string
    {
        $content = [
            'email' => $this->invitation->email,
            'role' => $this->invitation->role,
            'project_id' => $this->invitation->project_id,
            'organization_id' => $this->invitation->organization_id,
            'expires_at' => $this->invitation->expires_at->toISOString(),
        ];

        return hash('sha256', json_encode($content));
    }
}