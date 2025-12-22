<?php declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public $documentId;
    public $userId;
    public $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $documentId, string $userId, string $tenantId)
    {
        $this->documentId = $documentId;
        $this->userId = $userId;
        $this->tenantId = $tenantId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing document', [
                'document_id' => $this->documentId,
                'user_id' => $this->userId,
                'tenant_id' => $this->tenantId,
                'attempt' => $this->attempts()
            ]);

            // Simulate document processing
            $this->processDocument();
            
            Log::info('Document processed successfully', [
                'document_id' => $this->documentId
            ]);

        } catch (\Exception $e) {
            Log::error('Document processing failed', [
                'document_id' => $this->documentId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            throw $e;
        }
    }

    /**
     * Process the document
     */
    private function processDocument(): void
    {
        // Simulate processing time
        sleep(2);
        
        // Update document status
        \DB::table('documents')
            ->where('id', $this->documentId)
            ->where('tenant_id', $this->tenantId)
            ->update([
                'status' => 'processed',
                'processed_at' => now(),
                'updated_at' => now()
            ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Document processing job failed permanently', [
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Update document status to failed
        \DB::table('documents')
            ->where('id', $this->documentId)
            ->where('tenant_id', $this->tenantId)
            ->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'updated_at' => now()
            ]);
    }
}
