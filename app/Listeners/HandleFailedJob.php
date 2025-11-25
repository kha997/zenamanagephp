<?php declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use App\Services\JobRetryPolicyService;
use Illuminate\Support\Facades\Log;

/**
 * Handle Failed Job Listener
 * 
 * PR: Job idempotency
 * 
 * Moves failed jobs to dead letter queue after max retries.
 */
class HandleFailedJob
{
    protected JobRetryPolicyService $retryPolicy;

    public function __construct(JobRetryPolicyService $retryPolicy)
    {
        $this->retryPolicy = $retryPolicy;
    }

    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        $job = $event->job;
        $exception = $event->exception;

        // Get job details
        $jobId = $job->getJobId() ?? uniqid('job_', true);
        $jobClass = $job->getName();
        $payload = $job->payload();

        // Extract tenant_id from payload if available
        $tenantId = null;
        if (isset($payload['data']['command'])) {
            $command = unserialize($payload['data']['command']);
            if (is_object($command)) {
                $tenantId = $command->tenantId ?? $command->tenant_id ?? null;
            }
        }

        // Move to dead letter queue
        $this->retryPolicy->moveToDeadLetterQueue(
            $jobId,
            $jobClass,
            $payload,
            $exception
        );

        Log::critical('Job moved to dead letter queue', [
            'job_id' => $jobId,
            'job_class' => $jobClass,
            'tenant_id' => $tenantId,
            'exception' => $exception->getMessage(),
            'attempts' => $job->attempts(),
        ]);
    }
}

