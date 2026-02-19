<?php declare(strict_types=1);

namespace App\Models;

use App\Models\User;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Project;

/**
 * Model Document để quản lý tài liệu
 * 
 * @property string $id
 * @property string $project_id
 * @property string $title
 * @property string|null $description
 * @property string|null $linked_entity_type
 * @property string|null $linked_entity_id
 * @property string|null $current_version_id
 * @property array|null $tags
 * @property string $visibility
 * @property bool $client_approved
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Document extends Model
{
    use HasUlids, HasFactory, TenantScope, SoftDeletes;

    protected $table = 'documents';

    /**
     * Kiểu dữ liệu của khóa chính
     */
    protected $keyType = 'string';

    /**
     * Tắt auto increment cho khóa chính
     */
    public $incrementing = false;

    /**
     * Các loại entity có thể liên kết
     */
    public const ENTITY_TYPE_TASK = 'task';
    public const ENTITY_TYPE_DIARY = 'diary';
    public const ENTITY_TYPE_CR = 'cr';

    /**
     * Danh sách các loại entity hợp lệ
     */
    public const VALID_ENTITY_TYPES = [
        self::ENTITY_TYPE_TASK,
        self::ENTITY_TYPE_DIARY,
        self::ENTITY_TYPE_CR,
    ];

    /**
     * Các loại visibility
     */
    public const VISIBILITY_INTERNAL = 'internal';
    public const VISIBILITY_CLIENT = 'client';

    protected $fillable = [
        'project_id',
        'tenant_id',
        'uploaded_by',
        'created_by',
        'updated_by',
        'name',
        'title',
        'original_name',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'file_hash',
        'category',
        'visibility',
        'client_approved',
        'linked_entity_type',
        'linked_entity_id',
        'description',
        'metadata',
        'status',
        'version',
        'is_current_version',
        'current_version_id',
        'parent_document_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
        'version' => 'integer',
        'is_current_version' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'title',
        'document_type',
        'file_name',
        'change_notes',
        'tags',
    ];

    /**
     * Quan hệ với Project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Quan hệ với User (người upload)
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Quan hệ với User (người tạo)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Quan hệ với User (người cập nhật gần nhất)
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Quan hệ với Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Quan hệ với DocumentVersion (tất cả versions)
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderBy('version_number', 'desc');
    }

    /**
     * Quan hệ với DocumentVersion (version hiện tại)
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class, 'id', 'current_version_id');
    }

    /**
     * Quan hệ polymorphic với entity được liên kết
     */
    public function linkedEntity(): MorphTo
    {
        return $this->morphTo('linked_entity', 'linked_entity_type', 'linked_entity_id');
    }

    /**
     * Scope để lọc theo dự án
     */
    public function scopeForProject(Builder $query, string $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope để lọc theo loại entity
     */
    public function scopeForEntityType(Builder $query, string $entityType): Builder
    {
        return $query->where('linked_entity_type', $entityType);
    }

    /**
     * Scope để lọc theo entity cụ thể
     */
    public function scopeForEntity(Builder $query, string $entityType, string $entityId): Builder
    {
        return $query->where('linked_entity_type', $entityType)
                    ->where('linked_entity_id', $entityId);
    }

    /**
     * Scope để lọc documents đã được phê duyệt cho client
     */
    public function scopeClientApproved(Builder $query): Builder
    {
        return $query->where('visibility', self::VISIBILITY_CLIENT)
                    ->where('client_approved', true);
    }

    /**
     * Scope để lọc theo visibility
     */
    public function scopeWithVisibility(Builder $query, string $visibility): Builder
    {
        return $query->where('visibility', $visibility);
    }

    /**
     * Lấy số version tiếp theo
     */
    public function getNextVersionNumber(): int
    {
        $latestVersion = $this->versions()->max('version_number') ?? 0;
        return $latestVersion + 1;
    }

    /**
     * Tạo version mới cho document
     */
    public function createNewVersion(array $versionData): DocumentVersion
    {
        $versionData['document_id'] = $this->id;
        $versionData['version_number'] = $this->getNextVersionNumber();
        
        $newVersion = DocumentVersion::create($versionData);
        
        // Cập nhật current_version_id
        $this->update(['current_version_id' => $newVersion->id]);
        
        return $newVersion;
    }

    /**
     * Revert về version cũ
     */
    public function revertToVersion(int $versionNumber, string $createdBy, ?string $comment = null): ?DocumentVersion
    {
        $targetVersion = $this->versions()->where('version_number', $versionNumber)->first();
        
        if (!$targetVersion) {
            return null;
        }
        
        // Tạo version mới từ version cũ
        $newVersionData = [
            'file_path' => $targetVersion->file_path,
            'storage_driver' => $targetVersion->storage_driver,
            'comment' => $comment ?? "Reverted to version {$versionNumber}",
            'metadata' => $targetVersion->metadata,
            'created_by' => $createdBy,
            'reverted_from_version_number' => $versionNumber,
        ];
        
        return $this->createNewVersion($newVersionData);
    }

    /**
     * Lấy version hiện tại
     */
    public function getCurrentVersionNumber(): int
    {
        return $this->currentVersion?->version_number ?? 0;
    }

    /**
     * Kiểm tra xem document có versions không
     */
    public function hasVersions(): bool
    {
        return $this->versions()->exists();
    }

    /**
     * Kiểm tra xem document có thể được client xem không
     */
    public function isVisibleToClient(): bool
    {
        return $this->visibility === self::VISIBILITY_CLIENT && $this->client_approved;
    }

    /**
     * Lấy danh sách tags dưới dạng string
     */
    public function getTagsAsString(): string
    {
        return $this->tags ? implode(', ', $this->tags) : '';
    }

    /**
     * Title attribute (fallback to name)
     */
    public function getTitleAttribute(): ?string
    {
        return $this->attributes['title'] ?? $this->attributes['name'] ?? null;
    }

    /**
     * Document type attribute (metadata preference)
     */
    public function getDocumentTypeAttribute(): ?string
    {
        if (!empty($this->attributes['document_type'])) {
            return $this->attributes['document_type'];
        }

        if (!empty($this->metadata['document_type'])) {
            return $this->metadata['document_type'];
        }

        return $this->attributes['category'] ?? null;
    }

    /**
     * File name attribute for API consumers
     */
    public function getFileNameAttribute(): ?string
    {
        if (!empty($this->attributes['file_name'])) {
            return $this->attributes['file_name'];
        }

        if (!empty($this->attributes['file_path'])) {
            return basename($this->attributes['file_path']);
        }

        return $this->attributes['original_name'] ?? null;
    }

    /**
     * Change notes accessor (stored in metadata)
     */
    public function getChangeNotesAttribute(): ?string
    {
        return $this->metadata['change_notes'] ?? null;
    }

    /**
     * Tags attribute derived from metadata
     */
    public function getTagsAttribute(): array
    {
        return $this->metadata['tags'] ?? [];
    }
}
