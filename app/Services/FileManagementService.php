<?php

namespace App\Services;

use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class FileManagementService
{
    protected $allowedMimeTypes = [
        'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
        'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'text/markdown'],
        'spreadsheet' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'],
        'presentation' => ['application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'video' => ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-ms-wmv', 'video/x-flv', 'video/webm'],
        'audio' => ['audio/mpeg', 'audio/wav', 'audio/flac', 'audio/aac', 'audio/ogg'],
        'archive' => ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/x-tar', 'application/gzip'],
        'code' => ['text/javascript', 'application/x-httpd-php', 'text/html', 'text/css', 'application/json', 'application/xml']
    ];

    protected $maxFileSize = 100 * 1024 * 1024; // 100MB
    protected $maxImageSize = 10 * 1024 * 1024; // 10MB

    /**
     * Upload a file
     */
    public function uploadFile(UploadedFile $uploadedFile, User $user, array $options = []): File
    {
        try {
            // Validate file
            $this->validateFile($uploadedFile);

            // Generate file hash for deduplication
            $fileHash = hash_file('sha256', $uploadedFile->getPathname());

            // Check if file already exists
            $existingFile = File::where('hash', $fileHash)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if ($existingFile) {
                Log::info('File already exists, returning existing file', [
                    'file_id' => $existingFile->id,
                    'hash' => $fileHash
                ]);
                return $existingFile;
            }

            // Determine file type and category
            $fileType = $this->getFileType($uploadedFile);
            $category = $options['category'] ?? 'general';

            // Generate file path
            $filePath = $this->generateFilePath($user, $fileType, $uploadedFile->getClientOriginalExtension());

            // Store file
            $storedPath = $uploadedFile->storeAs(
                dirname($filePath),
                basename($filePath),
                $options['disk'] ?? 'local'
            );

            // Create file record
            $file = File::create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $this->generateFileName($uploadedFile->getClientOriginalName()),
                'original_name' => $uploadedFile->getClientOriginalName(),
                'path' => $storedPath,
                'disk' => $options['disk'] ?? 'local',
                'mime_type' => $uploadedFile->getMimeType(),
                'extension' => $uploadedFile->getClientOriginalExtension(),
                'size' => $uploadedFile->getSize(),
                'hash' => $fileHash,
                'type' => $fileType,
                'category' => $category,
                'project_id' => $options['project_id'] ?? null,
                'task_id' => $options['task_id'] ?? null,
                'metadata' => $this->extractMetadata($uploadedFile),
                'tags' => $options['tags'] ?? [],
                'is_public' => $options['is_public'] ?? false
            ]);

            // Create initial version
            $this->createFileVersion($file, $user, 'Initial upload');

            // Generate thumbnails for images
            if ($file->isImage()) {
                $this->generateThumbnails($file);
            }

            Log::info('File uploaded successfully', [
                'file_id' => $file->id,
                'user_id' => $user->id,
                'size' => $file->size,
                'type' => $file->type
            ]);

            return $file;
        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'file_name' => $uploadedFile->getClientOriginalName()
            ]);
            throw $e;
        }
    }

    /**
     * Update file with new version
     */
    public function updateFile(File $file, UploadedFile $uploadedFile, User $user, string $changeDescription = null): FileVersion
    {
        try {
            // Validate file
            $this->validateFile($uploadedFile);

            // Generate new file path
            $newFilePath = $this->generateFilePath($user, $file->type, $uploadedFile->getClientOriginalExtension());

            // Store new file
            $storedPath = $uploadedFile->storeAs(
                dirname($newFilePath),
                basename($newFilePath),
                $file->disk
            );

            // Generate new version number
            $versionNumber = $this->generateVersionNumber($file);

            // Create new version
            $version = FileVersion::create([
                'file_id' => $file->id,
                'user_id' => $user->id,
                'version_number' => $versionNumber,
                'path' => $storedPath,
                'disk' => $file->disk,
                'size' => $uploadedFile->getSize(),
                'hash' => hash_file('sha256', $uploadedFile->getPathname()),
                'change_description' => $changeDescription,
                'metadata' => $this->extractMetadata($uploadedFile),
                'is_current' => true
            ]);

            // Mark previous versions as not current
            FileVersion::where('file_id', $file->id)
                ->where('id', '!=', $version->id)
                ->update(['is_current' => false]);

            // Update file record
            $file->update([
                'path' => $storedPath,
                'size' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getMimeType(),
                'extension' => $uploadedFile->getClientOriginalExtension(),
                'metadata' => $this->extractMetadata($uploadedFile)
            ]);

            // Generate thumbnails for images
            if ($file->isImage()) {
                $this->generateThumbnails($file);
            }

            Log::info('File updated successfully', [
                'file_id' => $file->id,
                'version_id' => $version->id,
                'user_id' => $user->id
            ]);

            return $version;
        } catch (\Exception $e) {
            Log::error('File update failed', [
                'file_id' => $file->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete a file
     */
    public function deleteFile(File $file): bool
    {
        try {
            // Delete physical file
            if (Storage::disk($file->disk)->exists($file->path)) {
                Storage::disk($file->disk)->delete($file->path);
            }

            // Delete thumbnails
            $this->deleteThumbnails($file);

            // Delete all versions
            foreach ($file->versions as $version) {
                if (Storage::disk($version->disk)->exists($version->path)) {
                    Storage::disk($version->disk)->delete($version->path);
                }
            }

            // Delete file record (cascades to versions)
            $file->delete();

            Log::info('File deleted successfully', [
                'file_id' => $file->id,
                'path' => $file->path
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get file download URL
     */
    public function getDownloadUrl(File $file): string
    {
        $file->incrementDownloadCount();
        
        return Storage::disk($file->disk)->url($file->path);
    }

    /**
     * Get file preview URL
     */
    public function getPreviewUrl(File $file): string
    {
        if ($file->isImage()) {
            return Storage::disk($file->disk)->url($file->path);
        }

        if ($file->isDocument() && $file->extension === 'pdf') {
            return Storage::disk($file->disk)->url($file->path);
        }

        return $this->getDownloadUrl($file);
    }

    /**
     * Search files
     */
    public function searchFiles(User $user, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = File::where('tenant_id', $user->tenant_id)
            ->active()
            ->with(['user', 'project', 'task']);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (isset($filters['task_id'])) {
            $query->where('task_id', $filters['task_id']);
        }

        if (isset($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('original_name', 'like', "%{$searchTerm}%")
                  ->orWhereJsonContains('tags', $searchTerm);
            });
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get file statistics
     */
    public function getFileStats(User $user): array
    {
        $files = File::where('tenant_id', $user->tenant_id)->active();

        return [
            'total_files' => $files->count(),
            'total_size' => $files->sum('size'),
            'by_type' => $files->groupBy('type')->map->count(),
            'by_category' => $files->groupBy('category')->map->count(),
            'recent_uploads' => $files->where('created_at', '>=', now()->subDays(7))->count(),
            'most_downloaded' => $files->orderBy('download_count', 'desc')->take(5)->get()
        ];
    }

    /**
     * Validate uploaded file
     */
    protected function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            throw new \Exception('File size exceeds maximum allowed size of ' . $this->formatBytes($this->maxFileSize));
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        $allowedTypes = array_merge(...array_values($this->allowedMimeTypes));
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new \Exception('File type not allowed: ' . $mimeType);
        }

        // Additional validation for images
        if (in_array($mimeType, $this->allowedMimeTypes['image'])) {
            if ($file->getSize() > $this->maxImageSize) {
                throw new \Exception('Image size exceeds maximum allowed size of ' . $this->formatBytes($this->maxImageSize));
            }
        }
    }

    /**
     * Get file type from uploaded file
     */
    protected function getFileType(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType();

        foreach ($this->allowedMimeTypes as $type => $mimeTypes) {
            if (in_array($mimeType, $mimeTypes)) {
                return $type;
            }
        }

        return 'other';
    }

    /**
     * Generate file path
     */
    protected function generateFilePath(User $user, string $type, string $extension): string
    {
        $date = now()->format('Y/m/d');
        $fileName = Str::uuid() . '.' . $extension;
        
        return "files/{$user->tenant_id}/{$type}/{$date}/{$fileName}";
    }

    /**
     * Generate file name
     */
    protected function generateFileName(string $originalName): string
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        
        return Str::slug($name) . '.' . $extension;
    }

    /**
     * Generate version number
     */
    protected function generateVersionNumber(File $file): string
    {
        $latestVersion = $file->versions()->orderBy('version_number', 'desc')->first();
        
        if (!$latestVersion) {
            return '1.0';
        }

        $versionParts = explode('.', $latestVersion->version_number);
        $major = (int) $versionParts[0];
        $minor = (int) ($versionParts[1] ?? 0);
        
        return ($major + 1) . '.0';
    }

    /**
     * Create file version
     */
    protected function createFileVersion(File $file, User $user, string $description = null): FileVersion
    {
        return FileVersion::create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'version_number' => '1.0',
            'path' => $file->path,
            'disk' => $file->disk,
            'size' => $file->size,
            'hash' => $file->hash,
            'change_description' => $description,
            'metadata' => $file->metadata,
            'is_current' => true
        ]);
    }

    /**
     * Extract file metadata
     */
    protected function extractMetadata(UploadedFile $file): array
    {
        $metadata = [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_at' => now()->toISOString()
        ];

        // Extract image metadata
        if (in_array($file->getMimeType(), $this->allowedMimeTypes['image'])) {
            try {
                $image = Image::make($file->getPathname());
                $metadata['image'] = [
                    'width' => $image->width(),
                    'height' => $image->height(),
                    'format' => $image->mime()
                ];
            } catch (\Exception $e) {
                Log::warning('Failed to extract image metadata', ['error' => $e->getMessage()]);
            }
        }

        return $metadata;
    }

    /**
     * Generate thumbnails for images
     */
    protected function generateThumbnails(File $file): void
    {
        if (!$file->isImage()) {
            return;
        }

        try {
            $image = Image::make(Storage::disk($file->disk)->path($file->path));
            
            // Generate different thumbnail sizes
            $sizes = [
                'thumb' => [150, 150],
                'medium' => [300, 300],
                'large' => [600, 600]
            ];

            foreach ($sizes as $sizeName => $dimensions) {
                $thumbnail = $image->resize($dimensions[0], $dimensions[1], function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                $thumbnailPath = str_replace(
                    basename($file->path),
                    $sizeName . '_' . basename($file->path),
                    $file->path
                );

                $thumbnail->save(Storage::disk($file->disk)->path($thumbnailPath));
            }
        } catch (\Exception $e) {
            Log::warning('Failed to generate thumbnails', [
                'file_id' => $file->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete thumbnails
     */
    protected function deleteThumbnails(File $file): void
    {
        if (!$file->isImage()) {
            return;
        }

        $sizes = ['thumb', 'medium', 'large'];
        
        foreach ($sizes as $size) {
            $thumbnailPath = str_replace(
                basename($file->path),
                $size . '_' . basename($file->path),
                $file->path
            );

            if (Storage::disk($file->disk)->exists($thumbnailPath)) {
                Storage::disk($file->disk)->delete($thumbnailPath);
            }
        }
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
