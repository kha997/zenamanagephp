<?php declare(strict_types=1);

namespace Src\Foundation\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Src\Foundation\Services\EnhancedMimeValidationService;

/**
 * Service quản lý file storage với multiple drivers
 * Hỗ trợ local, S3, Google Drive
 */
class FileStorageService
{
    private string $defaultDisk;
    private array $allowedMimes;
    private int $maxFileSize;
    
    public function __construct()
    {
        $this->defaultDisk = config('filesystems.default', 'local');
        $this->allowedMimes = config('app.allowed_file_types', [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'jpg', 'jpeg', 'png', 'gif', 'svg',
            'zip', 'rar', '7z'
        ]);
        $this->maxFileSize = config('app.max_file_size', 10485760); // 10MB
    }
    
    /**
     * Upload file với validation
     */
    public function uploadFile(
        UploadedFile $file,
        ?string $disk = null,
        ?string $directory = null,
        ?string $filename = null
    ): array {
        try {
            // Validate file
            $this->validateFile($file);
            
            $disk = $disk ?? $this->defaultDisk;
            $directory = $directory ?? 'uploads';
            $filename = $filename ?? $this->generateUniqueFilename($file);
            $path = $directory . '/' . $filename;
            
            // Store file
            $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $filename);
            
            if (!$storedPath) {
                throw new \Exception('Failed to store file');
            }
            
            // Get file info
            $fileInfo = [
                'original_name' => $file->getClientOriginalName(),
                'filename' => $filename,
                'path' => $storedPath,
                'disk' => $disk,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'url' => $this->getFileUrl($storedPath, $disk),
                'uploaded_at' => now()->toISOString()
            ];
            
            Log::info('File uploaded successfully', $fileInfo);
            
            return [
                'success' => true,
                'file' => $fileInfo
            ];
        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'original_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Upload multiple files
     */
    public function uploadMultipleFiles(
        array $files,
        string $directory = 'uploads',
        ?string $disk = null
    ): array {
        $results = [];
        $successCount = 0;
        $failCount = 0;
        
        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                $result = $this->uploadFile($file, $directory, $disk);
                $results[$index] = $result;
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } else {
                $results[$index] = [
                    'success' => false,
                    'error' => 'Invalid file object'
                ];
                $failCount++;
            }
        }
        
        return [
            'results' => $results,
            'summary' => [
                'total' => count($files),
                'success' => $successCount,
                'failed' => $failCount
            ]
        ];
    }
    
    /**
     * Xóa file
     */
    public function deleteFile(string $path, ?string $disk = null): bool
    {
        try {
            $disk = $disk ?? $this->defaultDisk;
            
            if (Storage::disk($disk)->exists($path)) {
                $deleted = Storage::disk($disk)->delete($path);
                
                if ($deleted) {
                    Log::info('File deleted successfully', [
                        'path' => $path,
                        'disk' => $disk
                    ]);
                }
                
                return $deleted;
            }
            
            return true; // File doesn't exist, consider as deleted
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'path' => $path,
                'disk' => $disk,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Lấy URL của file
     */
    public function getFileUrl(string $path, ?string $disk = null): string
    {
        try {
            $disk = $disk ?? $this->defaultDisk;
            
            if ($disk === 'local') {
                return Storage::disk($disk)->url($path);
            }
            
            // For cloud storage, generate temporary URL
            return Storage::disk($disk)->temporaryUrl($path, now()->addHours(24));
        } catch (\Exception $e) {
            Log::error('Get file URL failed', [
                'path' => $path,
                'disk' => $disk,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }
    
    /**
     * Kiểm tra file có tồn tại không
     */
    public function fileExists(string $path, ?string $disk = null): bool
    {
        try {
            $disk = $disk ?? $this->defaultDisk;
            return Storage::disk($disk)->exists($path);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Lấy thông tin file
     */
    public function getFileInfo(string $path, ?string $disk = null): ?array
    {
        try {
            $disk = $disk ?? $this->defaultDisk;
            
            if (!Storage::disk($disk)->exists($path)) {
                return null;
            }
            
            return [
                'path' => $path,
                'size' => Storage::disk($disk)->size($path),
                'last_modified' => Storage::disk($disk)->lastModified($path),
                'mime_type' => Storage::disk($disk)->mimeType($path),
                'url' => $this->getFileUrl($path, $disk)
            ];
        } catch (\Exception $e) {
            Log::error('Get file info failed', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Validate uploaded file with enhanced MIME validation
     */
    private function validateFile(UploadedFile $file): void
    {
        // Check if file is valid
        if (!$file->isValid()) {
            throw new \Exception('Invalid file upload');
        }

        // Use enhanced MIME validation service
        $mimeValidator = new EnhancedMimeValidationService();
        $validationResult = $mimeValidator->validateFile($file);

        if (!$validationResult['is_valid']) {
            $errors = implode(', ', $validationResult['errors']);
            throw new \Exception('File validation failed: ' . $errors);
        }

        // Log successful validation
        Log::info('File validation successful', [
            'original_name' => $file->getClientOriginalName(),
            'extension' => $validationResult['file_info']['extension'],
            'reported_mime' => $validationResult['file_info']['reported_mime'],
            'detected_mime' => $validationResult['file_info']['detected_mime'],
            'signature_match' => $validationResult['file_info']['signature_match']
        ]);
    }
    
    /**
     * Tạo filename unique
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $basename = Str::slug($basename); // Remove special characters
        
        return $basename . '_' . time() . '_' . Str::random(8) . '.' . $extension;
    }
}