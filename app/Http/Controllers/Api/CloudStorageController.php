<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Cloud Storage Controller
 * 
 * Handles file uploads and management for cloud storage providers
 */
class CloudStorageController extends BaseApiController
{
    /**
     * Upload file to AWS S3
     */
    public function uploadToS3(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB max
                'folder' => 'nullable|string|max:255',
                'visibility' => 'nullable|in:private,public'
            ]);

            $file = $request->file('file');
            $folder = $request->input('folder', 'uploads');
            $visibility = $request->input('visibility', 'private');
            
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $folder . '/' . $filename;

            $uploadedPath = $file->storeAs($folder, $filename, 's3');

            return $this->successResponse([
                'path' => $uploadedPath,
                'url' => Storage::disk('s3')->url($uploadedPath),
                'filename' => $filename,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ], 'File uploaded to S3 successfully');

        } catch (\Exception $e) {
            Log::error('S3 upload failed: ' . $e->getMessage());
            return $this->errorResponse('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload file to Google Cloud Storage
     */
    public function uploadToGCS(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB max
                'folder' => 'nullable|string|max:255',
                'visibility' => 'nullable|in:private,public'
            ]);

            $file = $request->file('file');
            $folder = $request->input('folder', 'uploads');
            $visibility = $request->input('visibility', 'private');
            
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $folder . '/' . $filename;

            $uploadedPath = $file->storeAs($folder, $filename, 'gcs');

            return $this->successResponse([
                'path' => $uploadedPath,
                'url' => Storage::disk('gcs')->url($uploadedPath),
                'filename' => $filename,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ], 'File uploaded to GCS successfully');

        } catch (\Exception $e) {
            Log::error('GCS upload failed: ' . $e->getMessage());
            return $this->errorResponse('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload multiple files to multiple providers
     */
    public function uploadToMultiple(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'files' => 'required|array|max:10',
                'files.*' => 'required|file|max:10240',
                'providers' => 'required|array',
                'providers.*' => 'in:s3,gcs',
                'folder' => 'nullable|string|max:255'
            ]);

            $files = $request->file('files');
            $providers = $request->input('providers');
            $folder = $request->input('folder', 'uploads');
            
            $results = [];

            foreach ($files as $index => $file) {
                $filename = time() . '_' . $index . '_' . $file->getClientOriginalName();
                
                foreach ($providers as $provider) {
                    try {
                        $path = $folder . '/' . $filename;
                        $uploadedPath = $file->storeAs($folder, $filename, $provider);
                        
                        $results[] = [
                            'provider' => $provider,
                            'path' => $uploadedPath,
                            'url' => Storage::disk($provider)->url($uploadedPath),
                            'filename' => $filename,
                            'size' => $file->getSize(),
                            'mime_type' => $file->getMimeType()
                        ];
                    } catch (\Exception $e) {
                        $results[] = [
                            'provider' => $provider,
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }

            return $this->successResponse($results, 'Multiple files uploaded successfully');

        } catch (\Exception $e) {
            Log::error('Multiple upload failed: ' . $e->getMessage());
            return $this->errorResponse('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete file from S3
     */
    public function deleteFromS3(Request $request, string $path): JsonResponse
    {
        try {
            $fullPath = urldecode($path);
            
            if (Storage::disk('s3')->exists($fullPath)) {
                Storage::disk('s3')->delete($fullPath);
                return $this->successResponse(null, 'File deleted from S3 successfully');
            }

            return $this->errorResponse('File not found', 404);

        } catch (\Exception $e) {
            Log::error('S3 delete failed: ' . $e->getMessage());
            return $this->errorResponse('Delete failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete file from GCS
     */
    public function deleteFromGCS(Request $request, string $path): JsonResponse
    {
        try {
            $fullPath = urldecode($path);
            
            if (Storage::disk('gcs')->exists($fullPath)) {
                Storage::disk('gcs')->delete($fullPath);
                return $this->successResponse(null, 'File deleted from GCS successfully');
            }

            return $this->errorResponse('File not found', 404);

        } catch (\Exception $e) {
            Log::error('GCS delete failed: ' . $e->getMessage());
            return $this->errorResponse('Delete failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get signed URL for S3 file
     */
    public function getS3Url(Request $request, string $path): JsonResponse
    {
        try {
            $fullPath = urldecode($path);
            $expiration = $request->input('expiration', 60); // minutes
            
            if (Storage::disk('s3')->exists($fullPath)) {
                $url = Storage::disk('s3')->temporaryUrl($fullPath, now()->addMinutes($expiration));
                return $this->successResponse(['url' => $url], 'Signed URL generated successfully');
            }

            return $this->errorResponse('File not found', 404);

        } catch (\Exception $e) {
            Log::error('S3 URL generation failed: ' . $e->getMessage());
            return $this->errorResponse('URL generation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get signed URL for GCS file
     */
    public function getGCSUrl(Request $request, string $path): JsonResponse
    {
        try {
            $fullPath = urldecode($path);
            $expiration = $request->input('expiration', 60); // minutes
            
            if (Storage::disk('gcs')->exists($fullPath)) {
                $url = Storage::disk('gcs')->temporaryUrl($fullPath, now()->addMinutes($expiration));
                return $this->successResponse(['url' => $url], 'Signed URL generated successfully');
            }

            return $this->errorResponse('File not found', 404);

        } catch (\Exception $e) {
            Log::error('GCS URL generation failed: ' . $e->getMessage());
            return $this->errorResponse('URL generation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get storage statistics for a provider
     */
    public function getStats(Request $request, string $provider): JsonResponse
    {
        try {
            if (!in_array($provider, ['s3', 'gcs'])) {
                return $this->errorResponse('Invalid provider', 400);
            }

            // This would typically connect to the cloud provider's API
            // For now, return mock data
            $stats = [
                'provider' => $provider,
                'total_files' => 0,
                'total_size' => 0,
                'storage_used' => '0 MB',
                'storage_limit' => 'Unlimited',
                'last_updated' => now()->toISOString()
            ];

            return $this->successResponse($stats, 'Storage statistics retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Storage stats failed: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }
}
