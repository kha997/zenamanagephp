<?php declare(strict_types=1);

namespace App\Services;

use App\Jobs\ScanFileVirusJob;
use App\Jobs\ProcessImageJob;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

/**
 * Media Service
 * 
 * Complete media pipeline with:
 * - Virus scanning
 * - EXIF stripping
 * - Image resizing/variants
 * - Signed URL generation
 * - CDN integration (when configured)
 */
class MediaService
{
    protected FileManagementService $fileService;
    protected SecureUploadService $secureUploadService;

    public function __construct(
        FileManagementService $fileService,
        SecureUploadService $secureUploadService
    ) {
        $this->fileService = $fileService;
        $this->secureUploadService = $secureUploadService;
    }

    /**
     * Process uploaded file through complete pipeline
     * 
     * @param UploadedFile $uploadedFile
     * @param User $user
     * @param array $options
     * @return File
     */
    public function processUpload(UploadedFile $uploadedFile, User $user, array $options = []): File
    {
        // Step 1: Check quota
        $quotaService = app(\App\Services\MediaQuotaService::class);
        $fileSize = $uploadedFile->getSize();
        $quotaCheck = $quotaService->canUpload($user->tenant_id, $fileSize);
        
        if (!$quotaCheck['allowed']) {
            throw new \Exception($quotaCheck['message'] ?? 'Storage quota exceeded');
        }

        // Step 2: Validate file
        $this->validateFile($uploadedFile);

        // Step 3: Strip EXIF data if image (synchronous for privacy)
        if ($this->isImage($uploadedFile) && config('media.strip_exif', true)) {
            $uploadedFile = $this->stripExifData($uploadedFile);
        }

        // Step 4: Upload file (basic upload)
        $file = $this->fileService->uploadFile($uploadedFile, $user, $options);

        // Step 5: Record quota usage
        $quotaService->recordUpload($user->tenant_id, $fileSize);

        // Step 6: Queue virus scan (async)
        if (config('media.virus_scan_enabled', true)) {
            ScanFileVirusJob::dispatch($file->id, $file->path)
                ->onQueue('media');
        }

        // Step 7: Queue image processing (resizing, variants) - async
        if ($file->isImage() && config('media.image_processing_enabled', true)) {
            ProcessImageJob::dispatch($file->id)
                ->onQueue('media');
        }

        Log::info('Media file processed', [
            'file_id' => $file->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'file_size' => $fileSize,
        ]);

        return $file;
    }

    /**
     * Strip EXIF data from image
     * 
     * @param UploadedFile $file
     * @return UploadedFile Modified file without EXIF
     */
    protected function stripExifData(UploadedFile $file): UploadedFile
    {
        try {
            $image = Image::make($file->getPathname());
            
            // Strip EXIF data
            $image->strip();
            
            // Save to temporary file
            $tempPath = sys_get_temp_dir() . '/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $image->save($tempPath);
            
            // Create new UploadedFile without EXIF
            $strippedFile = new UploadedFile(
                $tempPath,
                $file->getClientOriginalName(),
                $file->getMimeType(),
                null,
                true // test mode
            );
            
            Log::debug('EXIF data stripped from image', [
                'original_name' => $file->getClientOriginalName(),
            ]);
            
            return $strippedFile;
        } catch (\Exception $e) {
            Log::warning('Failed to strip EXIF data, using original file', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);
            return $file; // Return original if stripping fails
        }
    }

    /**
     * Generate signed URL for file access
     * 
     * @param File $file
     * @param int $ttlSeconds Time to live in seconds (default: 1 hour)
     * @return string Signed URL
     */
    public function generateSignedUrl(File $file, int $ttlSeconds = 3600): string
    {
        // Use CDN if enabled
        if (config('media.cdn_enabled', false)) {
            return $this->generateCdnSignedUrl($file, $ttlSeconds);
        }
        
        // Use S3 signed URL if S3 is configured
        if (config('filesystems.disks.s3.key')) {
            return $this->generateS3SignedUrl($file, $ttlSeconds);
        }
        
        // Fallback to local signed URL
        return $this->secureUploadService->createSignedUrl(
            $file->path,
            $file->user_id ?? auth()->id(),
            $file->tenant_id ?? auth()->user()?->tenant_id
        );
    }

    /**
     * Generate CDN signed URL
     */
    private function generateCdnSignedUrl(File $file, int $ttlSeconds): string
    {
        $cdnUrl = config('media.cdn_url', '');
        $cdnDomain = config('media.cdn_domain', '');
        
        if (!$cdnUrl && !$cdnDomain) {
            // Fallback to regular signed URL
            return $this->generateS3SignedUrl($file, $ttlSeconds);
        }
        
        $baseUrl = $cdnUrl ?: "https://{$cdnDomain}";
        $fileUrl = "{$baseUrl}/{$file->path}";
        
        // Generate signature for CDN
        $expires = time() + $ttlSeconds;
        $signature = hash_hmac('sha256', "{$file->path}|{$expires}", config('app.key'));
        
        return "{$fileUrl}?expires={$expires}&signature={$signature}";
    }

    /**
     * Generate S3 signed URL
     */
    private function generateS3SignedUrl(File $file, int $ttlSeconds): string
    {
        try {
            $disk = Storage::disk('s3');
            return $disk->temporaryUrl($file->path, now()->addSeconds($ttlSeconds));
        } catch (\Exception $e) {
            Log::warning('Failed to generate S3 signed URL', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback to local signed URL
            return $this->secureUploadService->createSignedUrl(
                $file->path,
                $file->user_id ?? auth()->id(),
                $file->tenant_id ?? auth()->user()?->tenant_id
            );
        }
    }

    /**
     * Generate image variants (thumbnails, different sizes, WebP/PNG)
     * 
     * Part of Gói 11: Media Pipeline An Toàn & Nhẹ.
     * Generates variants in both original format and WebP for optimization.
     * 
     * @param File $file
     * @param array $sizes Array of sizes ['thumbnail' => [150, 150], 'medium' => [800, 600]]
     * @return array Generated variant paths
     */
    public function generateImageVariants(File $file, array $sizes = null): array
    {
        if (!$file->isImage()) {
            return [];
        }

        $sizes = $sizes ?? config('media.image_variants', [
            'thumbnail' => [150, 150],
            'small' => [400, 300],
            'medium' => [800, 600],
            'large' => [1200, 900],
        ]);

        $variants = [];
        $originalPath = Storage::disk($file->disk)->path($file->path);
        $image = Image::make($originalPath);
        $quality = config('media.image_quality', 85);

        foreach ($sizes as $name => $dimensions) {
            [$width, $height] = $dimensions;
            
            // Resize image
            $variant = clone $image;
            $variant->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Generate original format variant
            $variantPath = $this->getVariantPath($file, $name);
            $variant->save(Storage::disk($file->disk)->path($variantPath), $quality);
            $variants[$name] = $variantPath;

            // Generate WebP variant (if supported)
            if (function_exists('imagewebp') || class_exists('Imagick')) {
                try {
                    $webpPath = $this->getVariantPath($file, $name, 'webp');
                    $variant->encode('webp', $quality);
                    $variant->save(Storage::disk($file->disk)->path($webpPath));
                    $variants[$name . '_webp'] = $webpPath;

                    Log::debug('WebP variant generated', [
                        'file_id' => $file->id,
                        'variant' => $name . '_webp',
                    ]);
                } catch (\Exception $e) {
                    Log::debug('WebP variant generation failed', [
                        'file_id' => $file->id,
                        'variant' => $name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::debug('Image variant generated', [
                'file_id' => $file->id,
                'variant' => $name,
                'size' => "{$width}x{$height}",
            ]);
        }

        return $variants;
    }

    /**
     * Get variant path for file
     * 
     * @param File $file
     * @param string $variantName
     * @param string|null $format Optional format override (e.g., 'webp')
     * @return string
     */
    protected function getVariantPath(File $file, string $variantName, ?string $format = null): string
    {
        $pathInfo = pathinfo($file->path);
        $extension = $format ?? $pathInfo['extension'];
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_' . $variantName . '.' . $extension;
    }


    /**
     * Check if file is an image
     */
    protected function isImage(UploadedFile $file): bool
    {
        return str_starts_with($file->getMimeType(), 'image/');
    }

    /**
     * Validate file
     */
    protected function validateFile(UploadedFile $file): void
    {
        $this->fileService->validateFile($file);
    }
}

