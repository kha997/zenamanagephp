<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Secure File Upload Service
 * 
 * Enhanced file upload security with comprehensive validation
 */
class SecureFileUploadService
{
    private array $allowedMimeTypes;
    private array $allowedExtensions;
    private int $maxFileSize;
    private string $uploadPath;
    private bool $scanForViruses;

    public function __construct()
    {
        $this->allowedMimeTypes = config('upload.allowed_mime_types', [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv', 'application/zip', 'application/x-rar-compressed'
        ]);
        
        $this->allowedExtensions = config('upload.allowed_extensions', [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip', 'rar'
        ]);
        
        $this->maxFileSize = config('upload.max_file_size', 10 * 1024 * 1024); // 10MB
        $this->uploadPath = config('upload.path', 'uploads');
        $this->scanForViruses = config('upload.scan_viruses', false);
    }

    /**
     * Upload file with security validation
     */
    public function uploadFile(UploadedFile $file, string $subPath = '', array $options = []): array
    {
        try {
            // Basic validation
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'File validation failed',
                    'errors' => $validation['errors']
                ];
            }

            // Generate secure filename
            $filename = $this->generateSecureFilename($file);
            $path = $this->buildPath($subPath, $filename);

            // Additional security checks
            if (!$this->performSecurityChecks($file)) {
                return [
                    'success' => false,
                    'message' => 'File failed security checks'
                ];
            }

            // Store file
            $storedPath = $file->storeAs($path, $filename, 'public');

            // Generate file metadata
            $metadata = $this->generateFileMetadata($file, $storedPath);

            // Log upload
            Log::info('File uploaded successfully', [
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ]);

            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $metadata
            ];

        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName()
            ]);
            
            return [
                'success' => false,
                'message' => 'File upload failed'
            ];
        }
    }

    /**
     * Validate file
     */
    private function validateFile(UploadedFile $file): array
    {
        $errors = [];

        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            $errors[] = 'File size exceeds maximum allowed size';
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedExtensions)) {
            $errors[] = 'File extension not allowed';
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            $errors[] = 'File type not allowed';
        }

        // Check for double extensions
        $filename = $file->getClientOriginalName();
        if (preg_match('/\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)\.[^.]+$/i', $filename)) {
            $errors[] = 'Suspicious file extension detected';
        }

        // Check filename for suspicious patterns
        if (preg_match('/[<>:"|?*\x00-\x1f]/', $filename)) {
            $errors[] = 'Invalid characters in filename';
        }

        // Check file content (basic)
        if (!$this->validateFileContent($file)) {
            $errors[] = 'File content validation failed';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate file content
     */
    private function validateFileContent(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        // Read first few bytes to check file signature
        $handle = fopen($file->getPathname(), 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 1024);
        fclose($handle);

        // Check file signatures
        $signatures = [
            'image/jpeg' => ["\xFF\xD8\xFF"],
            'image/png' => ["\x89\x50\x4E\x47"],
            'image/gif' => ["\x47\x49\x46\x38"],
            'application/pdf' => ["%PDF"],
            'application/zip' => ["\x50\x4B\x03\x04", "\x50\x4B\x05\x06", "\x50\x4B\x07\x08"],
        ];

        if (isset($signatures[$mimeType])) {
            foreach ($signatures[$mimeType] as $signature) {
                if (str_starts_with($header, $signature)) {
                    return true;
                }
            }
            return false;
        }

        // For other file types, just check they're not executable
        $executableSignatures = [
            "\x7F\x45\x4C\x46", // ELF
            "\x4D\x5A", // PE/DOS
            "\xFE\xED\xFA", // Mach-O
            "#!/", // Shell script
            "<?php", // PHP
            "<%", // ASP
        ];

        foreach ($executableSignatures as $signature) {
            if (str_starts_with($header, $signature)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Perform additional security checks
     */
    private function performSecurityChecks(UploadedFile $file): bool
    {
        // Check for embedded scripts in images
        if (str_starts_with($file->getMimeType(), 'image/')) {
            return $this->checkImageSecurity($file);
        }

        // Check for macros in documents
        if (in_array($file->getMimeType(), ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
            return $this->checkDocumentSecurity($file);
        }

        return true;
    }

    /**
     * Check image security
     */
    private function checkImageSecurity(UploadedFile $file): bool
    {
        // Check for embedded PHP code in images
        $content = file_get_contents($file->getPathname());
        
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            return false;
        }

        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/gzinflate/i',
            '/str_rot13/i'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check document security
     */
    private function checkDocumentSecurity(UploadedFile $file): bool
    {
        // For now, just check file size and basic validation
        // In production, you might want to use a document analysis library
        
        $content = file_get_contents($file->getPathname());
        
        // Check for embedded scripts
        if (preg_match('/<script[^>]*>.*?<\/script>/i', $content)) {
            return false;
        }

        return true;
    }

    /**
     * Generate secure filename
     */
    private function generateSecureFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $random = Str::random(8);
        
        return "{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Build upload path
     */
    private function buildPath(string $subPath, string $filename): string
    {
        $path = $this->uploadPath;
        
        if ($subPath) {
            $path .= '/' . trim($subPath, '/');
        }
        
        // Add year/month for organization
        $path .= '/' . Carbon::now()->format('Y/m');
        
        return $path;
    }

    /**
     * Generate file metadata
     */
    private function generateFileMetadata(UploadedFile $file, string $storedPath): array
    {
        return [
            'id' => Str::ulid(),
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($storedPath),
            'stored_path' => $storedPath,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'uploaded_at' => Carbon::now()->toIso8601String(),
            'checksum' => hash_file('sha256', $file->getPathname())
        ];
    }

    /**
     * Delete file securely
     */
    public function deleteFile(string $path): bool
    {
        try {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                
                Log::info('File deleted', ['path' => $path]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get file info
     */
    public function getFileInfo(string $path): ?array
    {
        try {
            if (!Storage::disk('public')->exists($path)) {
                return null;
            }

            $fullPath = Storage::disk('public')->path($path);
            
            return [
                'path' => $path,
                'size' => filesize($fullPath),
                'modified_at' => Carbon::createFromTimestamp(filemtime($fullPath)),
                'checksum' => hash_file('sha256', $fullPath)
            ];
        } catch (\Exception $e) {
            Log::error('Get file info failed', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
