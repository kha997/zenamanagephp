<?php

namespace App\Jobs;

use App\Services\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

abstract class TenantScopedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $tenantId;
    protected ?string $userId;
    protected array $metadata;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $tenantId = null, ?string $userId = null)
    {
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->metadata = TenantContext::getJobMetadata();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Set tenant context for the job
        if ($this->tenantId) {
            TenantContext::set($this->tenantId, $this->userId);
        }

        // Log job execution with tenant context
        Log::info('Tenant-scoped job executed', [
            'job_class' => static::class,
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'metadata' => $this->metadata,
            'tenant_context' => TenantContext::getTenantId()
        ]);

        try {
            // Execute the actual job logic
            $this->execute();
        } finally {
            // Clear tenant context after job execution
            TenantContext::clear();
        }
    }

    /**
     * Execute the job logic - to be implemented by child classes
     */
    abstract protected function execute(): void;

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'tenant:' . ($this->tenantId ?? 'unknown'),
            'user:' . ($this->userId ?? 'unknown'),
            'job:' . class_basename(static::class)
        ];
    }
}
