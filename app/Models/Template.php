<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Template Model - Unified Template Management
 * 
 * Quản lý các mẫu dự án với versioning, categorization và tenant isolation
 * Hỗ trợ JSON structure cho phases, tasks và workflows
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id Tenant ID
 * @property string $name Template name
 * @property string $description Template description
 * @property string $category Template category
 * @property array $template_data Template structure (phases, tasks, etc.)
 * @property array $settings Template settings
 * @property string $status Template status
 * @property int $version Template version
 * @property boolean $is_public Public template flag
 * @property boolean $is_active Active template flag
 * @property string $created_by Creator user ID
 * @property string $updated_by Last updater user ID
 * @property int $usage_count Usage counter
 * @property array $tags Template tags
 * @property array $metadata Additional metadata
 */
class Template extends Model
{
    use HasUlids, HasFactory, SoftDeletes, TenantScope;

    protected $table = 'templates';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'category',
        'template_data',
        'settings',
        'status',
        'version',
        'is_public',
        'is_active',
        'created_by',
        'updated_by',
        'usage_count',
        'tags',
        'metadata'
    ];

    protected $casts = [
        'template_data' => 'array',
        'settings' => 'array',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'version' => 'integer',
        'usage_count' => 'integer',
        'tags' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => 'draft',
        'version' => 1,
        'is_public' => false,
        'is_active' => true,
        'usage_count' => 0,
        'tags' => '[]',
        'metadata' => '[]'
    ];

    /**
     * Template status constants
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_DEPRECATED = 'deprecated';

    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
        self::STATUS_DEPRECATED,
    ];

    /**
     * Template category constants
     */
    public const CATEGORY_PROJECT = 'project';
    public const CATEGORY_TASK = 'task';
    public const CATEGORY_WORKFLOW = 'workflow';
    public const CATEGORY_DOCUMENT = 'document';
    public const CATEGORY_REPORT = 'report';

    public const VALID_CATEGORIES = [
        self::CATEGORY_PROJECT,
        self::CATEGORY_TASK,
        self::CATEGORY_WORKFLOW,
        self::CATEGORY_DOCUMENT,
        self::CATEGORY_REPORT,
    ];

    /**
     * Relationships
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->orderBy('version', 'desc');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'template_id');
    }

    /**
     * Scopes
     */
    public function scopeByTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('created_by', $userId);
    }

    public function scopePopular($query, int $limit = 10)
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    /**
     * Template operations
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function publish(): bool
    {
        $this->status = self::STATUS_ACTIVE;
        return $this->save();
    }

    public function archive(): bool
    {
        $this->status = self::STATUS_ARCHIVED;
        return $this->save();
    }

    public function duplicate(string $newName, string $userId): self
    {
        $duplicate = $this->replicate();
        $duplicate->id = Str::ulid();
        $duplicate->name = $newName;
        $duplicate->version = 1;
        $duplicate->status = self::STATUS_DRAFT;
        $duplicate->usage_count = 0;
        $duplicate->created_by = $userId;
        $duplicate->updated_by = $userId;
        $duplicate->created_at = now();
        $duplicate->updated_at = now();
        $duplicate->save();

        return $duplicate;
    }

    /**
     * Template data helpers
     */
    public function getPhases(): array
    {
        return $this->template_data['phases'] ?? [];
    }

    public function getTasks(): array
    {
        return $this->template_data['tasks'] ?? [];
    }

    public function getMilestones(): array
    {
        return $this->template_data['milestones'] ?? [];
    }

    public function getEstimatedDuration(): int
    {
        $tasks = $this->getTasks();
        return array_sum(array_column($tasks, 'duration_days'));
    }

    public function getEstimatedCost(): float
    {
        $tasks = $this->getTasks();
        return array_sum(array_column($tasks, 'estimated_cost'));
    }

    /**
     * Validation helpers
     */
    public function isValid(): bool
    {
        return !empty($this->name) && 
               !empty($this->category) && 
               !empty($this->template_data) &&
               in_array($this->category, self::VALID_CATEGORIES) &&
               in_array($this->status, self::VALID_STATUSES);
    }

    public function canBeUsed(): bool
    {
        return $this->is_active && 
               $this->status === self::STATUS_ACTIVE &&
               $this->isValid();
    }
}