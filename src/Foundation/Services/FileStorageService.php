<?php declare(strict_types=1);

namespace Src\Foundation\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

/**
 * Service quản lý file storage cho documents và uploads
 * Hỗ trợ multiple storage drivers (local, s3, google cloud)
 */
class FileStorageService
{
    /**
     * Upload file và trả về thông tin file
     *
     * @param UploadedFile $file File được upload
     * @param string $disk Storage disk (documents, uploads, s3, google)
     * @param string|null $directory Thư mục lưu trữ
     * @param string|null $filename Tên file tùy chỉnh
     * @return array Thông tin file đã upload
     * @throws Exception
     */
    public function uploadFile(
        UploadedFile $file,
        string $disk = 'documents',
        ?string $directory = null,
        ?string $filename = null
    ): array {
        try {
            // Validate file trước khi upload
            $this->validateFile($file);

            // Generate filename nếu không được cung cấp
            if (!$filename) {
                $filename = $this->generateUniqueFilename($file);
            }

            // Build đường dẫn đầy đủ
            $path = $directory ? $directory . '/' . $filename : $filename;

            // Lưu trữ file
            $storedPath = Storage::disk($disk)->putFileAs(
                $directory ?? '',
                $file,
                $filename
            );

            if (!$storedPath) {
                throw new Exception('Failed to store file');
            }

            return [
                'original_name' => $file->getClientOriginalName(),
                'filename' => $filename,
                'path' => $storedPath,
                'disk' => $disk,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'url' => $this->getFileUrl($storedPath, $disk),
                'hash' => hash_file('sha256', $file->getRealPath())
            ];
        } catch (Exception $e) {
            throw new Exception('File upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Xóa file khỏi storage
     *
     * @param string $path Đường dẫn file
     * @param string $disk Storage disk
     * @return bool
     */
    public function deleteFile(string $path, string $disk = 'documents'): bool
    {
        try {
            return Storage::disk($disk)->delete($path);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Kiểm tra file có tồn tại không
     *
     * @param string $path Đường dẫn file
     * @param string $disk Storage disk
     * @return bool
     */
    public function fileExists(string $path, string $disk = 'documents'): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    /**
     * Lấy URL của file để truy cập
     *
     * @param string $path Đường dẫn file
     * @param string $disk Storage disk
     * @return string|null
     */
    public function getFileUrl(string $path, string $disk = 'documents'): ?string
    {
        try {
            // Đối với public files, trả về URL trực tiếp
            if (in_array($disk, ['public', 'uploads'])) {
                return Storage::disk($disk)->url($path);
            }
            
            // Đối với private files, trả về download route
            return route('files.download', [
                'disk' => $disk,
                'path' => base64_encode($path)
            ]);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Download file từ storage
     *
     * @param string $path Đường dẫn file
     * @param string $disk Storage disk
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * @throws Exception
     */
    public function downloadFile(string $path, string $disk = 'documents')
    {
        if (!$this->fileExists($path, $disk)) {
            throw new Exception('File not found');
        }

        return Storage::disk($disk)->download($path);
    }

    /**
     * Lấy thông tin chi tiết của file
     *
     * @param string $path Đường dẫn file
     * @param string $disk Storage disk
     * @return array|null
     */
    public function getFileInfo(string $path, string $disk = 'documents'): ?array
    {
        try {
            if (!$this->fileExists($path, $disk)) {
                return null;
            }

            $storage = Storage::disk($disk);
            
            return [
                'path' => $path,
                'disk' => $disk,
                'size' => $storage->size($path),
                'last_modified' => $storage->lastModified($path),
                'mime_type' => $storage->mimeType($path),
                'url' => $this->getFileUrl($path, $disk)
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Validate file upload theo các quy tắc bảo mật
     *
     * @param UploadedFile $file
     * @throws Exception
     */
    private function validateFile(UploadedFile $file): void
    {
        // Kiểm tra kích thước file (tối đa 50MB)
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($file->getSize() > $maxSize) {
            throw new Exception('File size exceeds maximum allowed size (50MB)');
        }

        // Kiểm tra extension được phép
        $allowedExtensions = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg',
            'txt', 'csv', 'zip', 'rar', '7z',
            'mp4', 'avi', 'mov', 'wmv'
        ];
        
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('File type not allowed: ' . $extension);
        }

        // Kiểm tra MIME type để tăng cường bảo mật
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/bmp',
            'image/svg+xml',
            'text/plain',
            'text/csv',
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'video/mp4',
            'video/x-msvideo',
            'video/quicktime',
            'video/x-ms-wmv'
        ];
        
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new Exception('MIME type not allowed: ' . $file->getMimeType());
        }
    }

    /**
     * Tạo tên file unique để tránh trùng lặp
     *
     * @param UploadedFile $file
     * @return string
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Sanitize filename để tránh các ký tự đặc biệt
        $basename = Str::slug($basename);
        
        // Thêm timestamp và random string để đảm bảo unique
        $timestamp = now()->format('YmdHis');
        $random = Str::random(8);
        
        return $basename . '_' . $timestamp . '_' . $random . '.' . $extension;
    }
}