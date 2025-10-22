<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model TaskAttachmentVersion - Quản lý phiên bản file đính kèm
 * 
 * @property string $id ULID của version (primary key)
 * @property string $task_attachment_id ID attachment (ULID)
 * @property string $uploaded_by ID user upload (ULID)
 * @property int $version_number Số phiên bản
 * @property string $file_path Đường dẫn file
 * @property string $disk Disk storage
 * @property int $size Kích thước file (bytes)
 * @property string $hash Hash file
 * @property string|null $change_description Mô tả thay đổi
 * @property array|null $metadata Metadata bổ sung
 * @property bool $is_current Phiên bản hiện tại
 * @property \Carbon\Carbon|null $deleted_at Thời gian xóa
 */
class TaskAttachmentVersion extends Model
{
    use HasUlids, HasFactory, SoftDeletes;
    
    protected $table = 'task_attachment_versions';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'task_attachment_id',
        'uploaded_by',
        'version_number',
        'file_path',
        'disk',
        'size',
        'hash',
        'change_description',
        'metadata',
        'is_current'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_current' => 'boolean',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'is_current' => false
    ];

    /**
     * Relationship: Version thuộc về attachment
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(TaskAttachment::class, 'task_attachment_id');
    }

    /**
     * Relationship: Version được upload bởi user
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope: Chỉ lấy version hiện tại
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope: Lọc theo attachment
     */
    public function scopeByAttachment($query, string $attachmentId)
    {
        return $query->where('task_attachment_id', $attachmentId);
    }

    /**
     * Scope: Lọc theo user upload
     */
    public function scopeByUploader($query, string $userId)
    {
        return $query->where('uploaded_by', $userId);
    }

    /**
     * Accessor: URL download
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('api.task-attachment-versions.download', $this->id);
    }

    /**
     * Accessor: Kích thước file đã format
     */
    public function getFormattedSizeAttribute(): string
    {
        return $this->formatBytes($this->size);
    }

    /**
     * Method: Kiểm tra file có tồn tại không
     */
    public function fileExists(): bool
    {
        return \Illuminate\Support\Facades\Storage::disk($this->disk)->exists($this->file_path);
    }

    /**
     * Method: Lấy nội dung file
     */
    public function getFileContent(): string
    {
        return \Illuminate\Support\Facades\Storage::disk($this->disk)->get($this->file_path);
    }

    /**
     * Method: Xóa file vật lý
     */
    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return \Illuminate\Support\Facades\Storage::disk($this->disk)->delete($this->file_path);
        }
        return true;
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
     * Method: Tạo version mới từ uploaded file
     */
    public static function createFromUpload(
        \Illuminate\Http\UploadedFile $file,
        string $attachmentId,
        string $uploadedBy,
        string $changeDescription = null
    ): self {
        // Get attachment
        $attachment = TaskAttachment::findOrFail($attachmentId);

        // Generate unique filename for version
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = \Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_v' . time() . '.' . $extension;

        // Determine storage path
        $storagePath = 'task-attachments/' . $attachment->task_id . '/versions/' . $filename;

        // Store file
        $filePath = $file->storeAs('task-attachments/' . $attachment->task_id . '/versions', $filename, 'public');

        // Generate file hash
        $hash = hash_file('sha256', $file->getPathname());

        // Get next version number
        $nextVersion = $attachment->versions()->max('version_number') + 1;

        // Create version record
        return self::create([
            'task_attachment_id' => $attachmentId,
            'uploaded_by' => $uploadedBy,
            'version_number' => $nextVersion,
            'file_path' => $filePath,
            'disk' => 'public',
            'size' => $file->getSize(),
            'hash' => $hash,
            'change_description' => $changeDescription,
            'is_current' => false
        ]);
    }

    /**
     * Method: Đặt làm version hiện tại
     */
    public function setAsCurrent(): void
    {
        // Remove current flag from all versions of this attachment
        $this->attachment->versions()->update(['is_current' => false]);
        
        // Set this version as current
        $this->update(['is_current' => true]);
        
        // Update attachment's current version reference
        $this->attachment->update(['current_version_id' => $this->id]);
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
