<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Model TaskAttachment - Quản lý file đính kèm cho tasks
 * 
 * @property string $id ULID của attachment (primary key)
 * @property string $task_id ID task (ULID)
 * @property string $tenant_id ID tenant (ULID)
 * @property string $uploaded_by ID user upload (ULID)
 * @property string $name Tên file
 * @property string $original_name Tên file gốc
 * @property string $file_path Đường dẫn file
 * @property string $disk Disk storage
 * @property string $mime_type Loại MIME
 * @property string $extension Phần mở rộng
 * @property int $size Kích thước file (bytes)
 * @property string $hash Hash file
 * @property string $category Danh mục
 * @property string|null $description Mô tả
 * @property array|null $metadata Metadata bổ sung
 * @property array|null $tags Tags
 * @property bool $is_public Công khai
 * @property bool $is_active Kích hoạt
 * @property int $download_count Số lần download
 * @property \Carbon\Carbon|null $last_accessed_at Lần truy cập cuối
 * @property \Carbon\Carbon|null $deleted_at Thời gian xóa
 */
class TaskAttachment extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;
    
    protected $table = 'task_attachments';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'task_id',
        'tenant_id',
        'uploaded_by',
        'name',
        'original_name',
        'file_path',
        'disk',
        'mime_type',
        'extension',
        'size',
        'hash',
        'category',
        'description',
        'metadata',
        'tags',
        'is_public',
        'is_active',
        'download_count',
        'last_accessed_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'download_count' => 'integer',
        'last_accessed_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'is_public' => false,
        'is_active' => true,
        'download_count' => 0
    ];

    /**
     * Các danh mục file hợp lệ
     */
    public const CATEGORY_DOCUMENT = 'document';
    public const CATEGORY_IMAGE = 'image';
    public const CATEGORY_VIDEO = 'video';
    public const CATEGORY_AUDIO = 'audio';
    public const CATEGORY_ARCHIVE = 'archive';
    public const CATEGORY_CODE = 'code';
    public const CATEGORY_OTHER = 'other';

    public const VALID_CATEGORIES = [
        self::CATEGORY_DOCUMENT,
        self::CATEGORY_IMAGE,
        self::CATEGORY_VIDEO,
        self::CATEGORY_AUDIO,
        self::CATEGORY_ARCHIVE,
        self::CATEGORY_CODE,
        self::CATEGORY_OTHER,
    ];

    /**
     * Các loại MIME được phép
     */
    public const ALLOWED_MIME_TYPES = [
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'application/rtf',
        
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'image/bmp',
        'image/tiff',
        
        // Videos
        'video/mp4',
        'video/avi',
        'video/mov',
        'video/wmv',
        'video/flv',
        'video/webm',
        
        // Audio
        'audio/mp3',
        'audio/wav',
        'audio/ogg',
        'audio/m4a',
        'audio/aac',
        
        // Archives
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/gzip',
        'application/x-tar',
        
        // Code
        'text/html',
        'text/css',
        'text/javascript',
        'application/javascript',
        'application/json',
        'application/xml',
        'text/xml',
    ];

    /**
     * Kích thước file tối đa (bytes)
     */
    public const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB

    /**
     * Relationship: Attachment thuộc về task
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Relationship: Attachment được upload bởi user
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Relationship: Attachment có nhiều versions
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TaskAttachmentVersion::class);
    }

    /**
     * Relationship: Attachment có version hiện tại
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(TaskAttachmentVersion::class, 'current_version_id');
    }

    /**
     * Scope: Chỉ lấy attachments đang hoạt động
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Lọc theo danh mục
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Lọc theo task
     */
    public function scopeByTask($query, string $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    /**
     * Scope: Lọc theo user upload
     */
    public function scopeByUploader($query, string $userId)
    {
        return $query->where('uploaded_by', $userId);
    }

    /**
     * Scope: Lọc theo loại MIME
     */
    public function scopeByMimeType($query, string $mimeType)
    {
        return $query->where('mime_type', $mimeType);
    }

    /**
     * Scope: Lọc file công khai
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Accessor: URL download
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('api.task-attachments.download', $this->id);
    }

    /**
     * Accessor: URL preview
     */
    public function getPreviewUrlAttribute(): string
    {
        return route('api.task-attachments.preview', $this->id);
    }

    /**
     * Accessor: Kích thước file đã format
     */
    public function getFormattedSizeAttribute(): string
    {
        return $this->formatBytes($this->size);
    }

    /**
     * Accessor: Kiểm tra có thể preview không
     */
    public function getCanPreviewAttribute(): bool
    {
        return in_array($this->mime_type, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'application/pdf',
            'text/plain',
            'text/html',
            'text/css',
            'text/javascript',
            'application/javascript',
            'application/json'
        ]);
    }

    /**
     * Accessor: Icon file
     */
    public function getFileIconAttribute(): string
    {
        return match($this->category) {
            self::CATEGORY_DOCUMENT => 'fas fa-file-alt',
            self::CATEGORY_IMAGE => 'fas fa-image',
            self::CATEGORY_VIDEO => 'fas fa-video',
            self::CATEGORY_AUDIO => 'fas fa-music',
            self::CATEGORY_ARCHIVE => 'fas fa-file-archive',
            self::CATEGORY_CODE => 'fas fa-code',
            default => 'fas fa-file'
        };
    }

    /**
     * Accessor: Màu sắc category
     */
    public function getCategoryColorAttribute(): string
    {
        return match($this->category) {
            self::CATEGORY_DOCUMENT => 'text-blue-600',
            self::CATEGORY_IMAGE => 'text-green-600',
            self::CATEGORY_VIDEO => 'text-purple-600',
            self::CATEGORY_AUDIO => 'text-yellow-600',
            self::CATEGORY_ARCHIVE => 'text-orange-600',
            self::CATEGORY_CODE => 'text-red-600',
            default => 'text-gray-600'
        };
    }

    /**
     * Method: Tăng số lần download
     */
    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
        $this->update(['last_accessed_at' => now()]);
    }

    /**
     * Method: Kiểm tra file có tồn tại không
     */
    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->file_path);
    }

    /**
     * Method: Lấy nội dung file
     */
    public function getFileContent(): string
    {
        return Storage::disk($this->disk)->get($this->file_path);
    }

    /**
     * Method: Xóa file vật lý
     */
    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::disk($this->disk)->delete($this->file_path);
        }
        return true;
    }

    /**
     * Method: Di chuyển file
     */
    public function moveFile(string $newPath): bool
    {
        if ($this->fileExists()) {
            $success = Storage::disk($this->disk)->move($this->file_path, $newPath);
            if ($success) {
                $this->update(['file_path' => $newPath]);
            }
            return $success;
        }
        return false;
    }

    /**
     * Method: Copy file
     */
    public function copyFile(string $newPath): bool
    {
        if ($this->fileExists()) {
            return Storage::disk($this->disk)->copy($this->file_path, $newPath);
        }
        return false;
    }

    /**
     * Method: Lấy thông tin file từ storage
     */
    public function getFileInfo(): array
    {
        if (!$this->fileExists()) {
            return [];
        }

        return [
            'size' => Storage::disk($this->disk)->size($this->file_path),
            'last_modified' => Storage::disk($this->disk)->lastModified($this->file_path),
            'mime_type' => Storage::disk($this->disk)->mimeType($this->file_path),
        ];
    }

    /**
     * Method: Format bytes thành string dễ đọc
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Method: Xác định category từ MIME type
     */
    public static function getCategoryFromMimeType(string $mimeType): string
    {
        return match(true) {
            str_starts_with($mimeType, 'image/') => self::CATEGORY_IMAGE,
            str_starts_with($mimeType, 'video/') => self::CATEGORY_VIDEO,
            str_starts_with($mimeType, 'audio/') => self::CATEGORY_AUDIO,
            str_starts_with($mimeType, 'application/zip') || 
            str_starts_with($mimeType, 'application/x-rar') ||
            str_starts_with($mimeType, 'application/x-7z') ||
            str_starts_with($mimeType, 'application/gzip') ||
            str_starts_with($mimeType, 'application/x-tar') => self::CATEGORY_ARCHIVE,
            str_starts_with($mimeType, 'text/html') ||
            str_starts_with($mimeType, 'text/css') ||
            str_starts_with($mimeType, 'text/javascript') ||
            str_starts_with($mimeType, 'application/javascript') ||
            str_starts_with($mimeType, 'application/json') ||
            str_starts_with($mimeType, 'application/xml') ||
            str_starts_with($mimeType, 'text/xml') => self::CATEGORY_CODE,
            str_starts_with($mimeType, 'application/pdf') ||
            str_starts_with($mimeType, 'application/msword') ||
            str_starts_with($mimeType, 'application/vnd.openxmlformats') ||
            str_starts_with($mimeType, 'application/vnd.ms-') ||
            str_starts_with($mimeType, 'text/plain') ||
            str_starts_with($mimeType, 'text/csv') ||
            str_starts_with($mimeType, 'application/rtf') => self::CATEGORY_DOCUMENT,
            default => self::CATEGORY_OTHER
        };
    }

    /**
     * Method: Validate file upload
     */
    public static function validateFile(\Illuminate\Http\UploadedFile $file): array
    {
        $errors = [];

        // Kiểm tra kích thước
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $errors[] = 'File size exceeds maximum allowed size of ' . self::formatBytes(self::MAX_FILE_SIZE);
        }

        // Kiểm tra MIME type
        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            $errors[] = 'File type not allowed: ' . $file->getMimeType();
        }

        // Kiểm tra extension
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff',
            'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm',
            'mp3', 'wav', 'ogg', 'm4a', 'aac',
            'zip', 'rar', '7z', 'gz', 'tar',
            'html', 'css', 'js', 'json', 'xml'
        ];

        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = 'File extension not allowed: ' . $extension;
        }

        return $errors;
    }

    /**
     * Method: Tạo attachment từ uploaded file
     */
    public static function createFromUpload(
        \Illuminate\Http\UploadedFile $file,
        string $taskId,
        string $uploadedBy,
        array $options = []
    ): self {
        // Validate file
        $errors = self::validateFile($file);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('File validation failed: ' . implode(', ', $errors));
        }

        // Generate unique filename
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = \Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time() . '.' . $extension;

        // Determine storage path
        $storagePath = 'task-attachments/' . $taskId . '/' . $filename;

        // Store file
        $filePath = $file->storeAs('task-attachments/' . $taskId, $filename, 'public');

        // Generate file hash
        $hash = hash_file('sha256', $file->getPathname());

        // Determine category
        $category = self::getCategoryFromMimeType($file->getMimeType());

        // Create attachment record
        return self::create([
            'task_id' => $taskId,
            'tenant_id' => auth()->user()->tenant_id,
            'uploaded_by' => $uploadedBy,
            'name' => $filename,
            'original_name' => $originalName,
            'file_path' => $filePath,
            'disk' => 'public',
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size' => $file->getSize(),
            'hash' => $hash,
            'category' => $category,
            'description' => $options['description'] ?? null,
            'metadata' => $options['metadata'] ?? null,
            'tags' => $options['tags'] ?? null,
            'is_public' => $options['is_public'] ?? false,
        ]);
    }

    /**
     * Method: Format bytes (static)
     */
    public static function formatBytesStatic(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
