<?php
declare(strict_types=1);

namespace Src\Foundation\Utils;

use Src\Foundation\Foundation;

/**
 * Utility class để xử lý upload file
 */
class FileUploadHandler {
    /**
     * Các loại file được phép upload
     * 
     * @var array
     */
    private static array $allowedTypes = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        'archive' => ['zip', 'rar', '7z'],
        'text' => ['txt', 'csv']
    ];
    
    /**
     * Kích thước file tối đa (bytes)
     * 
     * @var int
     */
    private static int $maxFileSize = 10 * 1024 * 1024; // 10MB
    
    /**
     * Upload file
     * 
     * @param array $file $_FILES array
     * @param string $destination Thư mục đích
     * @param array $allowedTypes Các loại file được phép
     * @return array
     */
    public static function upload(array $file, string $destination, array $allowedTypes = []): array {
        try {
            // Kiểm tra lỗi upload
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception(self::getUploadErrorMessage($file['error']));
            }
            
            // Kiểm tra kích thước file
            if ($file['size'] > self::$maxFileSize) {
                throw new \Exception('File quá lớn. Kích thước tối đa: ' . self::formatFileSize(self::$maxFileSize));
            }
            
            // Lấy extension
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Kiểm tra loại file
            if (!self::isAllowedType($extension, $allowedTypes)) {
                throw new \Exception('Loại file không được phép: ' . $extension);
            }
            
            // Tạo tên file mới
            $fileName = self::generateFileName($file['name']);
            $filePath = rtrim($destination, '/') . '/' . $fileName;
            
            // Tạo thư mục nếu chưa tồn tại
            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            // Di chuyển file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new \Exception('Không thể lưu file');
            }
            
            return [
                'success' => true,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'original_name' => $file['name'],
                'size' => $file['size'],
                'extension' => $extension,
                'mime_type' => $file['type']
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Tạo tên file mới với ULID
     * 
     * @param string $originalName
     * @return string
     */
    private static function generateFileName(string $originalName): string {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $ulid = Foundation::generateULID();
        return $ulid . '.' . $extension;
    }
    
    /**
     * Kiểm tra loại file có được phép không
     * 
     * @param string $extension
     * @param array $allowedTypes
     * @return bool
     */
    private static function isAllowedType(string $extension, array $allowedTypes = []): bool {
        if (empty($allowedTypes)) {
            // Nếu không chỉ định, cho phép tất cả loại file đã định nghĩa
            $allAllowed = [];
            foreach (self::$allowedTypes as $types) {
                $allAllowed = array_merge($allAllowed, $types);
            }
            return in_array($extension, $allAllowed);
        }
        
        return in_array($extension, $allowedTypes);
    }
    
    /**
     * Lấy thông báo lỗi upload
     * 
     * @param int $errorCode
     * @return string
     */
    private static function getUploadErrorMessage(int $errorCode): string {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File quá lớn (vượt quá upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File quá lớn (vượt quá MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File chỉ được upload một phần',
            UPLOAD_ERR_NO_FILE => 'Không có file nào được upload',
            UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm',
            UPLOAD_ERR_CANT_WRITE => 'Không thể ghi file vào disk',
            UPLOAD_ERR_EXTENSION => 'Upload bị dừng bởi extension'
        ];
        
        return $errors[$errorCode] ?? 'Lỗi upload không xác định';
    }
    
    /**
     * Format kích thước file
     * 
     * @param int $bytes
     * @return string
     */
    private static function formatFileSize(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
    
    /**
     * Xóa file
     * 
     * @param string $filePath
     * @return bool
     */
    public static function deleteFile(string $filePath): bool {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
    
    /**
     * Lấy thông tin file
     * 
     * @param string $filePath
     * @return array|null
     */
    public static function getFileInfo(string $filePath): ?array {
        if (!file_exists($filePath)) {
            return null;
        }
        
        return [
            'path' => $filePath,
            'name' => basename($filePath),
            'size' => filesize($filePath),
            'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
            'mime_type' => mime_content_type($filePath),
            'created_at' => date('Y-m-d H:i:s', filectime($filePath)),
            'modified_at' => date('Y-m-d H:i:s', filemtime($filePath))
        ];
    }
}