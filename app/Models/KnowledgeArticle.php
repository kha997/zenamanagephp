<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $type
 * @property string $title
 * @property string|null $category
 * @property string|null $body
 * @property array<int, array{text: string, done: bool}>|null $checklist_items
 * @property array<int, string>|null $tags
 * @property string|null $project_id
 * @property string $status
 * @property \Carbon\Carbon|null $published_at
 * @property string|null $created_by
 * @property string|null $updated_by
 */
class KnowledgeArticle extends Model
{
    use HasUlids, TenantScope;

    /** @use HasFactory<\Database\Factories\KnowledgeArticleFactory> */
    use HasFactory;

    protected $table = 'knowledge_articles';
    protected $keyType = 'string';
    public $incrementing = false;

    public const TYPE_SOP = 'sop';
    public const TYPE_CHECKLIST = 'checklist';
    public const TYPE_LESSON_LEARNED = 'lesson_learned';

    public const VALID_TYPES = [
        self::TYPE_SOP,
        self::TYPE_CHECKLIST,
        self::TYPE_LESSON_LEARNED,
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PUBLISHED],
        self::STATUS_PUBLISHED => [self::STATUS_DRAFT],
    ];

    protected $fillable = [
        'tenant_id',
        'type',
        'title',
        'category',
        'body',
        'checklist_items',
        'tags',
        'project_id',
        'status',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'checklist_items' => 'array',
        'tags' => 'array',
        'published_at' => 'datetime',
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_SOP => 'Quy trình chuẩn (SOP)',
            self::TYPE_CHECKLIST => 'Checklist',
            self::TYPE_LESSON_LEARNED => 'Bài học công trình',
            default => $this->type,
        };
    }
}
