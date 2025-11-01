<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Ncr để quản lý Non-Conformance Reports
 */
class Ncr extends Model
{
    use HasUlids, HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'tenant_id',
        'inspection_id',
        'ncr_number',
        'title',
        'description',
        'status',
        'severity',
        'created_by',
        'assigned_to',
        'root_cause',
        'corrective_action',
        'preventive_action',
        'resolution',
        'resolved_at',
        'closed_at',
        'attachments',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'attachments' => 'array',
    ];

    /**
     * Quan hệ với Project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Quan hệ với Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Quan hệ với QcInspection
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(QcInspection::class);
    }

    /**
     * Quan hệ với User (người tạo)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Quan hệ với User (người được giao)
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get NCR status badge color
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'open' => 'bg-red-100 text-red-800',
            'under_review' => 'bg-yellow-100 text-yellow-800',
            'in_progress' => 'bg-blue-100 text-blue-800',
            'resolved' => 'bg-green-100 text-green-800',
            'closed' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get NCR severity badge color
     */
    public function getSeverityBadgeColorAttribute(): string
    {
        return match ($this->severity) {
            'low' => 'bg-green-100 text-green-800',
            'medium' => 'bg-yellow-100 text-yellow-800',
            'high' => 'bg-orange-100 text-orange-800',
            'critical' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Check if NCR is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'open' && $this->created_at->diffInDays(now()) > 7;
    }

    /**
     * Scope để lọc theo project
     */
    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope để lọc theo status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope để lọc theo severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope để lọc các NCR quá hạn
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'open')
            ->where('created_at', '<', now()->subDays(7));
    }

    /**
     * Scope để lọc theo assignee
     */
    public function scopeAssignedTo($query, string $userId)
    {
        return $query->where('assigned_to', $userId);
    }
}
