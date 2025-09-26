<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Models\Task;
use Src\DocumentManagement\Models\Document;

/**
 * Model CrLink để quản lý liên kết giữa Change Request và các entity khác
 * 
 * @property string $id
 * @property string $change_request_id
 * @property string $linked_type
 * @property string $linked_id
 * @property string|null $link_description
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class CrLink extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'cr_links';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Các loại entity có thể liên kết
     */
    public const LINKED_TYPE_TASK = 'task';
    public const LINKED_TYPE_DOCUMENT = 'document';
    public const LINKED_TYPE_COMPONENT = 'component';

    /**
     * Danh sách các loại liên kết hợp lệ
     */
    public const VALID_LINKED_TYPES = [
        self::LINKED_TYPE_TASK,
        self::LINKED_TYPE_DOCUMENT,
        self::LINKED_TYPE_COMPONENT,
    ];

    protected $fillable = [
        'change_request_id',
        'linked_type',
        'linked_id',
        'link_description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Quan hệ với ChangeRequest
     */
    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    /**
     * Quan hệ polymorphic với entity được liên kết
     * Sử dụng custom logic vì không thể dùng morphTo với ULID
     */
    public function getLinkedEntityAttribute()
    {
        return match($this->linked_type) {
            self::LINKED_TYPE_TASK => Task::find($this->linked_id),
            self::LINKED_TYPE_DOCUMENT => Document::find($this->linked_id),
            self::LINKED_TYPE_COMPONENT => Component::find($this->linked_id),
            default => null,
        };
    }

    /**
     * Quan hệ với Task (khi linked_type = 'task')
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'linked_id')
                    ->where('linked_type', self::LINKED_TYPE_TASK);
    }

    /**
     * Quan hệ với Document (khi linked_type = 'document')
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'linked_id')
                    ->where('linked_type', self::LINKED_TYPE_DOCUMENT);
    }

    /**
     * Quan hệ với Component (khi linked_type = 'component')
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'linked_id')
                    ->where('linked_type', self::LINKED_TYPE_COMPONENT);
    }

    /**
     * Scope để lọc theo Change Request
     */
    public function scopeForChangeRequest($query, string $changeRequestId)
    {
        return $query->where('change_request_id', $changeRequestId);
    }

    /**
     * Scope để lọc theo loại entity
     */
    public function scopeForLinkedType($query, string $linkedType)
    {
        return $query->where('linked_type', $linkedType);
    }

    /**
     * Scope để lọc theo entity cụ thể
     */
    public function scopeForLinkedEntity($query, string $linkedType, string $linkedId)
    {
        return $query->where('linked_type', $linkedType)
                    ->where('linked_id', $linkedId);
    }

    /**
     * Kiểm tra xem loại liên kết có hợp lệ không
     */
    public static function isValidLinkedType(string $type): bool
    {
        return in_array($type, self::VALID_LINKED_TYPES);
    }

    /**
     * Tạo liên kết mới
     */
    public static function createLink(
        string $changeRequestId,
        string $linkedType,
        string $linkedId,
        ?string $description = null
    ): ?self {
        if (!self::isValidLinkedType($linkedType)) {
            return null;
        }

        // Kiểm tra xem liên kết đã tồn tại chưa
        $existing = self::forChangeRequest($changeRequestId)
                       ->forLinkedEntity($linkedType, $linkedId)
                       ->first();

        if ($existing) {
            return $existing;
        }

        return self::create([
            'change_request_id' => $changeRequestId,
            'linked_type' => $linkedType,
            'linked_id' => $linkedId,
            'link_description' => $description,
        ]);
    }

    /**
     * Xóa liên kết
     */
    public static function removeLink(
        string $changeRequestId,
        string $linkedType,
        string $linkedId
    ): bool {
        return self::forChangeRequest($changeRequestId)
                  ->forLinkedEntity($linkedType, $linkedId)
                  ->delete() > 0;
    }

    /**
     * Lấy tất cả entity được liên kết với CR
     */
    public static function getLinkedEntities(string $changeRequestId): array
    {
        $links = self::forChangeRequest($changeRequestId)->get();
        $entities = [];

        foreach ($links as $link) {
            $entity = $link->linked_entity;
            if ($entity) {
                $entities[] = [
                    'type' => $link->linked_type,
                    'id' => $link->linked_id,
                    'entity' => $entity,
                    'description' => $link->link_description,
                ];
            }
        }

        return $entities;
    }
}