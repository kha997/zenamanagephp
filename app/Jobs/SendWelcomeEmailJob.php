<?php

namespace App\Jobs;

use App\Mail\WelcomeEmail;
use App\Models\EmailTracking;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable;

    public $user;
    public $tries = 3;
    public $timeout = 60;
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }
    
    /**
     * Get the queue the job should be sent to.
     */
    public function onQueue($queue)
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if user is still active
            if ($this->user->status !== 'active') {
                Log::info('Welcome email job skipped - user not active', [
                    'user_id' => $this->user->id,
                    'status' => $this->user->status,
                ]);
                return;
            }

            // Check rate limits
            if (!$this->checkRateLimits()) {
                Log::warning('Welcome email job delayed - rate limit exceeded', [
                    'user_id' => $this->user->id,
                ]);
                
                $this->release(60);
                return;
            }

            // Create email tracking record
            $tracking = $this->createEmailTracking();

            // Get cached email template
            $emailTemplate = $this->getCachedEmailTemplate();

            // Send email
            Mail::to($this->user->email)->send($emailTemplate);

            // Mark as sent
            $tracking->markAsSent();

            // Log success
            Log::info('Welcome email sent successfully via queue', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'tracking_id' => $tracking->tracking_id,
            ]);

        } catch (\Exception $e) {
            // Mark as failed
            if (isset($tracking)) {
                $tracking->markAsFailed($e->getMessage());
            }

            // Log error
            Log::error('Failed to send welcome email via queue', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Welcome email job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
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
        $organizationId = $this->user->organization_id;

        // Check per minute limit
        $minuteKey = "email_rate_limit_minute_{$organizationId}_{$now->format('Y-m-d-H-i')}";
        $minuteCount = Cache::get($minuteKey, 0);
        if ($minuteCount >= ($rateLimits['max_per_minute'] ?? 60)) {
            return false;
        }

        // Increment counter
        Cache::increment($minuteKey, 1);
        Cache::put($minuteKey, $minuteCount + 1, 60);

        return true;
    }

    /**
     * Create email tracking record
     */
    private function createEmailTracking(): EmailTracking
    {
        return EmailTracking::create([
            'email_type' => 'welcome',
            'recipient_email' => $this->user->email,
            'recipient_name' => $this->user->name,
            'user_id' => $this->user->id,
            'organization_id' => $this->user->organization_id,
            'subject' => "Welcome to " . ($this->user->organization->name ?? 'our platform') . "!",
            'content_hash' => $this->generateContentHash(),
            'metadata' => [
                'role' => $this->user->role,
                'joined_at' => $this->user->joined_at?->toISOString(),
                'invitation_id' => $this->user->invitation_id,
                'queue' => 'emails-welcome',
                'job_id' => $this->job->getJobId(),
            ],
            'status' => 'pending',
        ]);
    }

    /**
     * Get cached email template
     */
    private function getCachedEmailTemplate(): WelcomeEmail
    {
        $cacheKey = "email_template_welcome_{$this->user->id}";
        $cacheEnabled = config('mail.template_cache.enabled', true);
        
        if ($cacheEnabled) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        // Create new email template
        $emailTemplate = new WelcomeEmail($this->user);
        
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
            'email' => $this->user->email,
            'name' => $this->user->name,
            'role' => $this->user->role,
            'organization_id' => $this->user->organization_id,
            'joined_at' => $this->user->joined_at?->toISOString(),
        ];

        return hash('sha256', json_encode($content));
    }
}