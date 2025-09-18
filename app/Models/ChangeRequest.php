<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'tenant_id',
        'project_id',
        'task_id',
        'component_id',
        'requested_by',
        'approved_by',
        'title',
        'description',
        'type', // 'scope', 'budget', 'timeline', 'resource', 'quality'
        'priority', // 'low', 'medium', 'high', 'critical'
        'status', // 'pending', 'approved', 'rejected', 'implemented', 'cancelled'
        'impact_analysis', // JSON data
        'cost_impact',
        'time_impact', // in days
        'risk_assessment', // JSON data
        'implementation_plan', // JSON data
        'approval_notes',
        'rejection_reason',
        'requested_at',
        'approved_at',
        'implemented_at',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'impact_analysis' => 'array',
        'risk_assessment' => 'array',
        'implementation_plan' => 'array',
        'cost_impact' => 'decimal:2',
        'time_impact' => 'integer',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'implemented_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isImplemented(): bool
    {
        return $this->status === 'implemented';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isHighPriority(): bool
    {
        return in_array($this->priority, ['high', 'critical']);
    }

    public function hasCostImpact(): bool
    {
        return $this->cost_impact > 0;
    }

    public function hasTimeImpact(): bool
    {
        return $this->time_impact > 0;
    }

    public function getDaysSinceRequested(): int
    {
        return $this->requested_at ? $this->requested_at->diffInDays(now()) : 0;
    }

    public function getApprovalTime(): ?int
    {
        if ($this->requested_at && $this->approved_at) {
            return $this->requested_at->diffInDays($this->approved_at);
        }
        return null;
    }
}
