<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Job Retry Policy Service
 * 
 * PR: Job idempotency
 * 
 * Manages retry policies with exponential backoff and dead letter queue.
 */
class JobRetryPolicyService
{
    /**
     * Default retry configuration
     */
    private const DEFAULT_MAX_TRIES = 3;
    private const DEFAULT_INITIAL_BACKOFF = 60; // 1 minute
    private const DEFAULT_MAX_BACKOFF = 3600; // 1 hour
    private const DEFAULT_MULTIPLIER = 2;

    /**
     * Calculate exponential backoff delays
     * 
     * @param int $attempt Current attempt number (1-based)
     * @param int $initialBackoff Initial backoff in seconds
     * @param int $maxBackoff Maximum backoff in seconds
     * @param float $multiplier Exponential multiplier
     * @return int Backoff delay in seconds
     */
    public function calculateBackoff(
        int $attempt,
        int $initialBackoff = self::DEFAULT_INITIAL_BACKOFF,
        int $maxBackoff = self::DEFAULT_MAX_BACKOFF,
        float $multiplier = self::DEFAULT_MULTIPLIER
    ): int {
        // Exponential backoff: initial * (multiplier ^ (attempt - 1))
        $backoff = (int) ($initialBackoff * pow($multiplier, $attempt - 1));
        
        // Cap at max backoff
        return min($backoff, $maxBackoff);
    }

    /**
     * Get backoff array for Laravel job
     * 
     * @param int $maxTries Maximum number of tries
     * @param int $initialBackoff Initial backoff in seconds
     * @return array Backoff delays in seconds
     */
    public function getBackoffArray(
        int $maxTries = self::DEFAULT_MAX_TRIES,
        int $initialBackoff = self::DEFAULT_INITIAL_BACKOFF
    ): array {
        $backoffs = [];
        
        for ($attempt = 1; $attempt < $maxTries; $attempt++) {
            $backoffs[] = $this->calculateBackoff($attempt, $initialBackoff);
        }

        return $backoffs;
    }

    /**
     * Check if job should be retried
     * 
     * @param int $attempts Current number of attempts
     * @param int $maxTries Maximum number of tries
     * @param \Throwable|null $exception Exception that occurred
     * @return bool True if should retry
     */
    public function shouldRetry(int $attempts, int $maxTries, ?\Throwable $exception = null): bool
    {
        if ($attempts >= $maxTries) {
            return false;
        }

        // Don't retry on certain exceptions (e.g., validation errors)
        if ($exception && $this->isNonRetryableException($exception)) {
            return false;
        }

        return true;
    }

    /**
     * Check if exception is non-retryable
     */
    protected function isNonRetryableException(\Throwable $exception): bool
    {
        $nonRetryableExceptions = [
            \Illuminate\Validation\ValidationException::class,
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Auth\Access\AuthorizationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        ];

        foreach ($nonRetryableExceptions as $nonRetryableException) {
            if ($exception instanceof $nonRetryableException) {
                return true;
            }
        }

        return false;
    }

    /**
     * Move job to dead letter queue
     */
    public function moveToDeadLetterQueue(string $jobId, string $jobClass, array $payload, \Throwable $exception): void
    {
        try {
            DB::table('dead_letter_queue')->insert([
                'job_id' => $jobId,
                'job_class' => $jobClass,
                'payload' => json_encode($payload),
                'exception' => $exception->getMessage(),
                'exception_trace' => $exception->getTraceAsString(),
                'attempts' => $this->getJobAttempts($jobId),
                'failed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::critical('Job moved to dead letter queue', [
                'job_id' => $jobId,
                'job_class' => $jobClass,
                'exception' => $exception->getMessage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to move job to dead letter queue', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get job attempts from cache or database
     */
    protected function getJobAttempts(string $jobId): int
    {
        return Cache::get("job_attempts:{$jobId}", 0);
    }
}

