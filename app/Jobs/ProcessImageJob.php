<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\MediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessImageJob
 * 
 * Processes uploaded images:
 * - Generates thumbnails and variants
 * - Optimizes images
 * - Stores variants in storage
 */
class ProcessImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(
        public string $fileId
    ) {}

    public function handle(MediaService $mediaService): void
    {
        $file = File::find($this->fileId);
        
        if (!$file) {
            Log::warning('File not found for image processing', [
                'file_id' => $this->fileId,
            ]);
            return;
        }

        if (!$file->isImage()) {
            Log::debug('File is not an image, skipping processing', [
                'file_id' => $this->fileId,
            ]);
            return;
        }

        try {
            // Generate image variants
            $variants = $mediaService->generateImageVariants($file);
            
            // Update file metadata with variant paths
            $metadata = $file->metadata ?? [];
            $metadata['variants'] = $variants;
            $file->update(['metadata' => $metadata]);

            Log::info('Image processing completed', [
                'file_id' => $file->id,
                'variants' => array_keys($variants),
            ]);
        } catch (\Exception $e) {
            Log::error('Image processing failed', [
                'file_id' => $this->fileId,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }
}

