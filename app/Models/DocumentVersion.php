<?php declare(strict_types=1);

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Model DocumentVersion để quản lý các phiên bản tài liệu
 * 
 * @property string $document_id
 * @property int $version_number
 * @property string $file_path
 * @property string $storage_driver
 * @property string|null $comment
 * @property array|null $metadata
 * @property string $created_by
 * @property int|null $reverted_from_version_number
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DocumentVersion extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'document_versions';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Các storage driver hỗ trợ
     */
    public const STORAGE_LOCAL = 'local';
    public const STORAGE_S3 = 's3';
    public const STORAGE_GDRIVE = 'gdrive';
    public const STORAGE_GCS = 'gcs';

    /**
     * Danh sách các storage driver hợp lệ
     */
    public const VALID_STORAGE_DRIVERS = [
        self::STORAGE_LOCAL,
        self::STORAGE_S3,
        self::STORAGE_GDRIVE,
        self::STORAGE_GCS,
    ];

    protected $fillable = [
        'document_id',
        'version_number',
        'file_path',
        'storage_driver',
        'comment',
        'metadata',
        'created_by',
        'reverted_from_version_number',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'metadata' => 'array',
        'reverted_from_version_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Quan hệ với Document
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Quan hệ với User (người tạo)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope để lọc theo document
     */
    public function scopeForDocument(Builder $query, string $documentId): Builder
    {
        return $query->where('document_id', $documentId);
    }

    /**
     * Scope để lọc theo storage driver
     */
    public function scopeWithStorageDriver(Builder $query, string $driver): Builder
    {
        return $query->where('storage_driver', $driver);
    }

    /**
     * Scope để lấy các version được revert
     */
    public function scopeReverted(Builder $query): Builder
    {
        return $query->whereNotNull('reverted_from_version_number');
    }

    /**
     * Scope để sắp xếp theo version number
     */
    public function scopeOrderByVersion(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('version_number', $direction);
    }

    /**
     * Kiểm tra xem version này có phải là revert không
     */
    public function isReverted(): bool
    {
        return !is_null($this->reverted_from_version_number);
    }

    /**
     * Kiểm tra storage driver có hợp lệ không
     */
    public function isValidStorageDriver(): bool
    {
        return in_array($this->storage_driver, self::VALID_STORAGE_DRIVERS);
    }

    /**
     * Lấy URL để download file
     */
    public function getDownloadUrl(): ?string
    {
        try {
            switch ($this->storage_driver) {
                case self::STORAGE_LOCAL:
                    return Storage::disk('local')->url($this->file_path);
                case self::STORAGE_S3:
                    return Storage::disk('s3')->temporaryUrl($this->file_path, now()->addHours(1));
                case self::STORAGE_GDRIVE:
                case self::STORAGE_GCS:
                    // Google-backed storage behavior to be implemented once a provider is available.
                    return null;
                default:
                    return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Kiểm tra xem file có tồn tại không
     */
    public function fileExists(): bool
    {
        try {
            return Storage::disk($this->storage_driver)->exists($this->file_path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Lấy kích thước file
     */
    public function getFileSize(): ?int
    {
        try {
            return Storage::disk($this->storage_driver)->size($this->file_path);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Lấy tên file từ path
     */
    public function getFileName(): string
    {
        return basename($this->file_path);
    }

    /**
     * Lấy extension của file
     */
    public function getFileExtension(): string
    {
        return pathinfo($this->file_path, PATHINFO_EXTENSION);
    }

    /**
     * Lấy tên file gốc từ metadata
     */
    public function getOriginalFileName(): ?string
    {
        return $this->metadata['original_filename'] ?? null;
    }

    /**
     * Lấy MIME type từ metadata
     */
    public function getMimeType(): ?string
    {
        return $this->metadata['mime_type'] ?? null;
    }

    /**
     * Lấy kích thước file từ metadata
     */
    public function getFileSizeFromMetadata(): ?int
    {
        return $this->metadata['size'] ?? null;
    }

    /**
     * Lấy formatted file size
     */
    public function getFormattedFileSize(): string
    {
        $size = $this->getFileSizeFromMetadata() ?? $this->getFileSize();
        
        if (!$size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        
        return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }
}

