<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends Model
{
    use HasFactory;

    protected $table = 'task_dependencies';
    
    protected $fillable = [
        'task_id',
        'dependency_id',
        'tenant_id',
        'dependency_type',
        'notes'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relationship: Dependency belongs to a task
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * Relationship: Dependency depends on another task
     */
    public function dependsOnTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'dependency_id');
    }

    /**
     * Relationship: Dependency belongs to a tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope: Filter by tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Filter by task
     */
    public function scopeForTask($query, string $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    /**
     * Scope: Filter by depends on task
     */
    public function scopeDependsOnTask($query, string $taskId)
    {
        return $query->where('dependency_id', $taskId);
    }
}