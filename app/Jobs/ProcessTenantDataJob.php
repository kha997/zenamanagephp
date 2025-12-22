<?php

namespace App\Jobs;

use App\Services\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessTenantDataJob extends TenantScopedJob
{
    protected string $data;
    protected string $filename;

    /**
     * Create a new job instance.
     */
    public function __construct(string $data, string $filename, ?string $tenantId = null, ?string $userId = null)
    {
        parent::__construct($tenantId, $userId);
        $this->data = $data;
        $this->filename = $filename;
    }

    /**
     * Execute the job logic.
     */
    protected function execute(): void
    {
        $tenantId = TenantContext::getTenantId();
        
        if (!$tenantId) {
            throw new \RuntimeException('No tenant context available for data processing');
        }

        // Process the data with tenant context
        $processedData = $this->processData($this->data);
        
        // Store with tenant-scoped S3 key
        $s3Key = TenantContext::getS3Key("processed/{$this->filename}");
        Storage::disk('s3')->put($s3Key, $processedData);
        
        // Log the processing with tenant context
        Log::info('Tenant data processed and stored', [
            'tenant_id' => $tenantId,
            'filename' => $this->filename,
            's3_key' => $s3Key,
            'data_size' => strlen($processedData),
            'tenant_context' => TenantContext::getTenantId()
        ]);
    }

    /**
     * Process the data
     */
    private function processData(string $data): string
    {
        // Example processing logic
        $processed = json_encode([
            'tenant_id' => TenantContext::getTenantId(),
            'processed_at' => now()->toISOString(),
            'original_data' => $data,
            'processed_by' => TenantContext::getUserId()
        ]);
        
        return $processed;
    }
}
