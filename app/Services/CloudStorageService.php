<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * CloudStorageService - Service cho cloud storage integrations
 */
class CloudStorageService
{
    private array $config;

    public function __construct()
    {
        $this->config = [
            'aws' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
                'region' => config('filesystems.disks.s3.region'),
                'bucket' => config('filesystems.disks.s3.bucket'),
                'url' => config('filesystems.disks.s3.url')
            ],
            'google' => [
                'project_id' => config('filesystems.disks.gcs.project_id'),
                'key_file' => config('filesystems.disks.gcs.key_file'),
                'bucket' => config('filesystems.disks.gcs.bucket')
            ]
        ];
    }

    /**
     * Upload file to AWS S3
     */
    public function uploadToS3(UploadedFile $file, string $path, array $options = []): array
    {
        try {
            $disk = Storage::disk('s3');
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename($file, $path);
            $fullPath = $path . '/' . $filename;
            
            // Upload file
            $uploadedPath = $disk->putFileAs($path, $file, $filename, $options);
            
            // Get file URL
            $url = $disk->url($uploadedPath);
            
            // Get file metadata
            $metadata = $this->getFileMetadata($file, $uploadedPath, $url);
            
            return [
                'success' => true,
                'path' => $uploadedPath,
                'url' => $url,
                'filename' => $filename,
                'metadata' => $metadata
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to upload file to S3', [
                'error' => $e->getMessage(),
                'path' => $path,
                'filename' => $file->getClientOriginalName()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload file to Google Cloud Storage
     */
    public function uploadToGCS(UploadedFile $file, string $path, array $options = []): array
    {
        try {
            $disk = Storage::disk('gcs');
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename($file, $path);
            $fullPath = $path . '/' . $filename;
            
            // Upload file
            $uploadedPath = $disk->putFileAs($path, $file, $filename, $options);
            
            // Get file URL
            $url = $disk->url($uploadedPath);
            
            // Get file metadata
            $metadata = $this->getFileMetadata($file, $uploadedPath, $url);
            
            return [
                'success' => true,
                'path' => $uploadedPath,
                'url' => $url,
                'filename' => $filename,
                'metadata' => $metadata
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to upload file to GCS', [
                'error' => $e->getMessage(),
                'path' => $path,
                'filename' => $file->getClientOriginalName()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload file to multiple cloud providers
     */
    public function uploadToMultipleProviders(UploadedFile $file, string $path, array $providers = ['s3', 'gcs']): array
    {
        $results = [];
        
        foreach ($providers as $provider) {
            switch ($provider) {
                case 's3':
                    $results['s3'] = $this->uploadToS3($file, $path);
                    break;
                case 'gcs':
                    $results['gcs'] = $this->uploadToGCS($file, $path);
                    break;
            }
        }
        
        return $results;
    }

    /**
     * Delete file from S3
     */
    public function deleteFromS3(string $path): bool
    {
        try {
            $disk = Storage::disk('s3');
            return $disk->delete($path);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete file from S3', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            
            return false;
        }
    }

    /**
     * Delete file from GCS
     */
    public function deleteFromGCS(string $path): bool
    {
        try {
            $disk = Storage::disk('gcs');
            return $disk->delete($path);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete file from GCS', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            
            return false;
        }
    }

    /**
     * Get file from S3
     */
    public function getFromS3(string $path): ?string
    {
        try {
            $disk = Storage::disk('s3');
            return $disk->get($path);
            
        } catch (\Exception $e) {
            Log::error('Failed to get file from S3', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            
            return null;
        }
    }

    /**
     * Get file URL from S3
     */
    public function getS3Url(string $path, int $expirationMinutes = 60): string
    {
        try {
            $disk = Storage::disk('s3');
            return $disk->temporaryUrl($path, now()->addMinutes($expirationMinutes));
            
        } catch (\Exception $e) {
            Log::error('Failed to get S3 URL', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            
            return '';
        }
    }

    /**
     * Get file URL from GCS
     */
    public function getGCSUrl(string $path, int $expirationMinutes = 60): string
    {
        try {
            $disk = Storage::disk('gcs');
            return $disk->temporaryUrl($path, now()->addMinutes($expirationMinutes));
            
        } catch (\Exception $e) {
            Log::error('Failed to get GCS URL', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            
            return '';
        }
    }

    /**
     * List files in S3 directory
     */
    public function listS3Files(string $path): array
    {
        try {
            $disk = Storage::disk('s3');
            $files = $disk->files($path);
            
            $fileList = [];
            foreach ($files as $file) {
                $fileList[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'url' => $disk->url($file),
                    'size' => $disk->size($file),
                    'last_modified' => $disk->lastModified($file)
                ];
            }
            
            return $fileList;
            
        } catch (\Exception $e) {
            Log::error('Failed to list S3 files', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            
            return [];
        }
    }

    /**
     * List files in GCS directory
     */
    public function listGCSFiles(string $path): array
    {
        try {
            $disk = Storage::disk('gcs');
            $files = $disk->files($path);
            
            $fileList = [];
            foreach ($files as $file) {
                $fileList[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'url' => $disk->url($file),
                    'size' => $disk->size($file),
                    'last_modified' => $disk->lastModified($file)
                ];
            }
            
            return $fileList;
            
        } catch (\Exception $e) {
            Log::error('Failed to list GCS files', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            
            return [];
        }
    }

    /**
     * Copy file between cloud providers
     */
    public function copyBetweenProviders(string $sourcePath, string $sourceProvider, string $targetPath, string $targetProvider): bool
    {
        try {
            // Get file content from source
            $content = null;
            if ($sourceProvider === 's3') {
                $content = $this->getFromS3($sourcePath);
            } elseif ($sourceProvider === 'gcs') {
                $content = $this->getFromGCS($sourcePath);
            }
            
            if (!$content) {
                return false;
            }
            
            // Upload to target provider
            $tempFile = tmpfile();
            fwrite($tempFile, $content);
            $tempPath = stream_get_meta_data($tempFile)['uri'];
            
            $uploadedFile = new UploadedFile(
                $tempPath,
                basename($sourcePath),
                mime_content_type($tempPath),
                null,
                true
            );
            
            $result = false;
            if ($targetProvider === 's3') {
                $result = $this->uploadToS3($uploadedFile, dirname($targetPath));
            } elseif ($targetProvider === 'gcs') {
                $result = $this->uploadToGCS($uploadedFile, dirname($targetPath));
            }
            
            fclose($tempFile);
            
            return $result['success'] ?? false;
            
        } catch (\Exception $e) {
            Log::error('Failed to copy file between providers', [
                'error' => $e->getMessage(),
                'source_path' => $sourcePath,
                'source_provider' => $sourceProvider,
                'target_path' => $targetPath,
                'target_provider' => $targetProvider
            ]);
            
            return false;
        }
    }

    /**
     * Get storage usage statistics
     */
    public function getStorageStats(string $provider): array
    {
        try {
            if ($provider === 's3') {
                return $this->getS3Stats();
            } elseif ($provider === 'gcs') {
                return $this->getGCSStats();
            }
            
            return [];
            
        } catch (\Exception $e) {
            Log::error('Failed to get storage stats', [
                'error' => $e->getMessage(),
                'provider' => $provider
            ]);
            
            return [];
        }
    }

    /**
     * Get S3 storage statistics
     */
    private function getS3Stats(): array
    {
        try {
            $s3Client = new S3Client([
                'version' => 'latest',
                'region' => $this->config['aws']['region'],
                'credentials' => [
                    'key' => $this->config['aws']['key'],
                    'secret' => $this->config['aws']['secret']
                ]
            ]);
            
            $result = $s3Client->listObjectsV2([
                'Bucket' => $this->config['aws']['bucket']
            ]);
            
            $totalSize = 0;
            $fileCount = 0;
            
            foreach ($result['Contents'] ?? [] as $object) {
                $totalSize += $object['Size'];
                $fileCount++;
            }
            
            return [
                'total_files' => $fileCount,
                'total_size' => $totalSize,
                'total_size_mb' => round($totalSize / 1024 / 1024, 2),
                'bucket' => $this->config['aws']['bucket'],
                'region' => $this->config['aws']['region']
            ];
            
        } catch (AwsException $e) {
            Log::error('Failed to get S3 stats', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Get GCS storage statistics
     */
    private function getGCSStats(): array
    {
        try {
            $disk = Storage::disk('gcs');
            $files = $disk->allFiles();
            
            $totalSize = 0;
            $fileCount = count($files);
            
            foreach ($files as $file) {
                $totalSize += $disk->size($file);
            }
            
            return [
                'total_files' => $fileCount,
                'total_size' => $totalSize,
                'total_size_mb' => round($totalSize / 1024 / 1024, 2),
                'bucket' => $this->config['google']['bucket']
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get GCS stats', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Generate unique filename
     */
    private function generateUniqueFilename(UploadedFile $file, string $path): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        
        // Add timestamp and random string for uniqueness
        $uniqueId = time() . '_' . substr(md5(uniqid()), 0, 8);
        
        return $filename . '_' . $uniqueId . '.' . $extension;
    }

    /**
     * Get file metadata
     */
    private function getFileMetadata(UploadedFile $file, string $path, string $url): array
    {
        return [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'size_mb' => round($file->getSize() / 1024 / 1024, 2),
            'extension' => $file->getClientOriginalExtension(),
            'path' => $path,
            'url' => $url,
            'uploaded_at' => now()->toISOString()
        ];
    }

    /**
     * Get file from GCS
     */
    private function getFromGCS(string $path): ?string
    {
        try {
            $disk = Storage::disk('gcs');
            return $disk->get($path);
            
        } catch (\Exception $e) {
            Log::error('Failed to get file from GCS', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            
            return null;
        }
    }
}
