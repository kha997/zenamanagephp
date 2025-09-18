<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InteractionLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'project_id',
        'task_id',
        'component_id',
        'type', // 'comment', 'status_change', 'assignment', 'file_upload', 'system'
        'content',
        'metadata', // JSON data for additional info
        'is_internal', // true for system logs, false for user interactions
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_internal' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    // Scopes
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeForTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeUserInteractions($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeSystemLogs($query)
    {
        return $query->where('is_internal', true);
    }

    // Helper methods
    public function isComment(): bool
    {
        return $this->type === 'comment';
    }

    public function isStatusChange(): bool
    {
        return $this->type === 'status_change';
    }

    public function isAssignment(): bool
    {
        return $this->type === 'assignment';
    }

    public function isFileUpload(): bool
    {
        return $this->type === 'file_upload';
    }

    public function isSystemLog(): bool
    {
        return $this->is_internal;
    }
}
