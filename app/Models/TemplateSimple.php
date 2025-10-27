<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

    /**
     * TemplateSimple Model - Simplified for Testing
     *
     * This model is a simplified representation of the 'templates' table
     * to facilitate testing against the current database schema.
     *
     * @property string $id ULID primary key
     * @property string $template_name Template name
     * @property string $category Template category
     * @property array $json_body Template structure (phases, tasks, etc.)
     * @property int $version Template version
     * @property boolean $is_active Active template flag
     * @property string $created_by Creator user ID
     * @property string $updated_by Last updater user ID
     */
class TemplateSimple extends Model
{
    use HasUlids, HasFactory, SoftDeletes;

    protected $table = 'templates';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $guarded = [];

    protected $casts = [
        'json_body' => 'array',
        'is_active' => 'boolean',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'version' => 1,
        'is_active' => true,
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

    /**
     * Scopes
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Accessor for template name
     */
    public function getNameAttribute()
    {
        return $this->template_name;
    }

    /**
     * Mutator for template name
     */
    public function setNameAttribute($value)
    {
        $this->template_name = $value;
    }

    /**
     * Get template phases from json_body
     */
    public function getPhases()
    {
        return $this->json_body['phases'] ?? [];
    }

    /**
     * Get template tasks from json_body
     */
    public function getTasks()
    {
        return $this->json_body['tasks'] ?? [];
    }

    /**
     * Get template milestones from json_body
     */
    public function getMilestones()
    {
        return $this->json_body['milestones'] ?? [];
    }

    /**
     * Calculate estimated duration from tasks
     */
    public function getEstimatedDuration()
    {
        $tasks = $this->getTasks();
        $totalDays = 0;
        
        foreach ($tasks as $task) {
            if (isset($task['duration_days'])) {
                $totalDays += (int)$task['duration_days'];
            }
        }
        
        return $totalDays;
    }

    /**
     * Calculate estimated cost from tasks
     */
    public function getEstimatedCost()
    {
        $tasks = $this->getTasks();
        $totalCost = 0;
        
        foreach ($tasks as $task) {
            if (isset($task['estimated_cost'])) {
                $totalCost += (float)$task['estimated_cost'];
            }
        }
        
        return $totalCost;
    }

    /**
     * Validate template data
     */
    public function isValid()
    {
        return !empty($this->template_name) && 
               !empty($this->category) && 
               in_array($this->category, self::VALID_CATEGORIES);
    }

    /**
     * Check if template can be used
     */
    public function canBeUsed()
    {
        return $this->is_active && $this->isValid();
    }

    /**
     * Increment usage count
     */
    public function incrementUsage()
    {
        // Note: usage_count column doesn't exist in current schema
        // This method is here for future compatibility
    }

    /**
     * Publish template
     */
    public function publish()
    {
        // Note: status column doesn't exist in current schema
        // This method is here for future compatibility
        return true;
    }

    /**
     * Archive template
     */
    public function archive()
    {
        // Note: status column doesn't exist in current schema
        // This method is here for future compatibility
        return true;
    }

    /**
     * Duplicate template
     */
    public function duplicate($newName, $userId)
    {
        $duplicate = $this->replicate();
        $duplicate->id = \Illuminate\Support\Str::ulid();
        $duplicate->template_name = $newName;
        $duplicate->version = 1;
        $duplicate->created_by = $userId;
        $duplicate->updated_by = $userId;
        $duplicate->save();
        
        return $duplicate;
    }
}
