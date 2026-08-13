<?php declare(strict_types=1);

namespace App\Models;

use App\Models\User;
use App\Models\Support\DocumentStatusResolver;
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
 * @property int $version
 * @property array|null $tags
 * @property array<string, mixed>|null $metadata
 * @property string $status
 * @property string|null $lifecycle_status
 * @property string|null $approval_status
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
    public const ENTITY_TYPE_COMPONENT = 'component';
    public const ENTITY_TYPE_DIARY = 'diary';
    public const ENTITY_TYPE_CR = 'cr';
    public const ENTITY_TYPE_SUBMITTAL = 'submittal';
    public const ENTITY_TYPE_DESIGN_ITEM = 'design_item';

    /**
     * Danh sách các loại entity hợp lệ
     */
    public const VALID_ENTITY_TYPES = [
        self::ENTITY_TYPE_TASK,
        self::ENTITY_TYPE_COMPONENT,
        self::ENTITY_TYPE_DIARY,
        self::ENTITY_TYPE_CR,
        self::ENTITY_TYPE_SUBMITTAL,
        self::ENTITY_TYPE_DESIGN_ITEM,
    ];

    /**
     * Các loại visibility
     */
    public const VISIBILITY_INTERNAL = 'internal';
    public const VISIBILITY_CLIENT = 'client';

    public const DOCUMENT_TYPE_DRAWING = 'drawing';
    public const DOCUMENT_TYPE_SPECIFICATION = 'specification';
    public const DOCUMENT_TYPE_CONTRACT = 'contract';
    public const DOCUMENT_TYPE_REPORT = 'report';
    public const DOCUMENT_TYPE_PHOTO = 'photo';
    public const DOCUMENT_TYPE_OTHER = 'other';

    public const VALID_DOCUMENT_TYPES = [
        self::DOCUMENT_TYPE_DRAWING,
        self::DOCUMENT_TYPE_SPECIFICATION,
        self::DOCUMENT_TYPE_CONTRACT,
        self::DOCUMENT_TYPE_REPORT,
        self::DOCUMENT_TYPE_PHOTO,
        self::DOCUMENT_TYPE_OTHER,
    ];

    protected $fillable = [
        'project_id',
        'tenant_id',
        'uploaded_by',
        'created_by',
        'updated_by',
        'name',
        'title',
        'document_type',
        'discipline',
        'package',
        'revision',
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
        'approver_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'lifecycle_status' => 'string',
        'approval_status' => 'string',
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

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Resolution order (GAP-033, Owner Gate 2 §6.1): explicit per-document
     * assignment, else the document's project manager, else no specific
     * approver. Pure read — never writes, never authorizes.
     */
    public function effectiveApprover(): ?User
    {
        if ($this->approver_id !== null) {
            return $this->approver;
        }

        return $this->project?->pm_id !== null ? $this->project?->manager : null;
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
        return $this->hasMany(DocumentVersion::class, 'document_id')->orderBy('version_number', 'desc');
    }

    /**
     * Quan hệ với DocumentVersion (version hiện tại)
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class, 'id', 'current_version_id');
    }

    public function getDecisionByIdAttribute(): ?string
    {
        return is_array($this->metadata) ? ($this->metadata['decision_by'] ?? null) : null;
    }

    public function getDecisionAtAttribute(): ?\Carbon\Carbon
    {
        $value = is_array($this->metadata) ? ($this->metadata['decision_at'] ?? null) : null;

        return $value ? \Carbon\Carbon::parse($value) : null;
    }

    public function getDecisionNoteAttribute(): ?string
    {
        return is_array($this->metadata) ? ($this->metadata['decision_note'] ?? null) : null;
    }

    public function getLifecycleStatusAttribute(mixed $value): ?string
    {
        $rawLifecycleStatus = $this->getRawOriginal('lifecycle_status');

        return (new DocumentStatusResolver())->lifecycle(
            is_string($rawLifecycleStatus) ? $rawLifecycleStatus : null,
            (string) $this->getRawOriginal('status')
        )?->value;
    }

    public function getApprovalStatusAttribute(mixed $value): ?string
    {
        $rawApprovalStatus = $this->getRawOriginal('approval_status');

        return (new DocumentStatusResolver())->approval(
            is_string($rawApprovalStatus) ? $rawApprovalStatus : null,
            (string) $this->getRawOriginal('status')
        )?->value;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $array = parent::toArray();
        $resolver = new DocumentStatusResolver();
        $rawLifecycleStatus = $this->getRawOriginal('lifecycle_status');
        $rawApprovalStatus = $this->getRawOriginal('approval_status');
        $legacyStatus = (string) $this->getRawOriginal('status');
        $lifecycle = $resolver->lifecycle(
            is_string($rawLifecycleStatus) ? $rawLifecycleStatus : null,
            $legacyStatus
        );
        $approval = $resolver->approval(
            is_string($rawApprovalStatus) ? $rawApprovalStatus : null,
            $legacyStatus
        );
        $compatibilityStatus = $resolver->compatibilityStatus($lifecycle, $approval, $legacyStatus);
        $array['lifecycle_status'] = $lifecycle?->value;
        $array['approval_status'] = $approval?->value;
        $array['status'] = $compatibilityStatus;

        if ($lifecycle !== null || $approval !== null) {
            $metadata = is_array($array['metadata'] ?? null) ? $array['metadata'] : [];
            $metadata['status'] = $compatibilityStatus;
            $array['metadata'] = $metadata;
        }

        return $array;
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
     * GAP-032: version creation and `current_version_id` moves are owned by
     * App\Services\DocumentVersionService, which allocates the number and writes the
     * canonical snapshot under the governed documents row lock. The former
     * createNewVersion()/revertToVersion() model mutators were removed because they
     * bypassed that lock and the Approval eligibility rule.
     */

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

    public function getDisciplineAttribute(): ?string
    {
        if (!empty($this->attributes['discipline'])) {
            return $this->attributes['discipline'];
        }

        return $this->metadata['discipline'] ?? null;
    }

    public function getPackageAttribute(): ?string
    {
        if (!empty($this->attributes['package'])) {
            return $this->attributes['package'];
        }

        return $this->metadata['package'] ?? null;
    }

    public function getRevisionAttribute(): ?string
    {
        if (!empty($this->attributes['revision'])) {
            return $this->attributes['revision'];
        }

        return $this->metadata['revision'] ?? null;
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
