<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Component extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'tenant_id',
        'project_id',
        'parent_id',
        'created_by',
        'name',
        'description',
        'type',
        'status',
        'progress',
        'dependencies',
        'actual_cost',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'progress' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'dependencies' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the component.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the project that owns the component.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the parent component.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'parent_id');
    }

    /**
     * Get the child components.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Component::class, 'parent_id');
    }

    /**
     * Get the user who created the component.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the tasks associated with this component.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the documents associated with this component.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}