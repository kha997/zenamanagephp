<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskWatcher extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'task_watchers';
    
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'task_id',
        'user_id',
    ];

    /**
     * RELATIONSHIPS
     */

    /**
     * Get the task being watched.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user watching the task.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * SCOPES
     */

    /**
     * Scope: Filter by task
     */
    public function scopeForTask($query, string $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
