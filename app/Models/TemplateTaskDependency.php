<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TemplateTaskDependency Model - Dependency between Template Tasks
 * 
 * Represents a dependency relationship between two template tasks.
 * 
 * @property string $id ULID primary key
 * @property string $set_id Template set ID
 * @property string $task_id Task ID (the task that has the dependency)
 * @property string $depends_on_task_id Task ID (the task that must be completed first)
 */
class TemplateTaskDependency extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'template_task_dependencies';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'set_id',
        'task_id',
        'depends_on_task_id',
    ];

    /**
     * Get the template set this dependency belongs to
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(TemplateSet::class, 'set_id');
    }

    /**
     * Get the task that has this dependency
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(TemplateTask::class, 'task_id');
    }

    /**
     * Get the task that must be completed first
     */
    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(TemplateTask::class, 'depends_on_task_id');
    }
}

