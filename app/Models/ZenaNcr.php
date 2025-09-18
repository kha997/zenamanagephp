<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZenaNcr extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id',
        'ncr_number',
        'title',
        'description',
        'status',
        'severity',
        'created_by',
        'assigned_to',
        'resolution',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the project that owns the NCR.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ZenaProject::class, 'project_id');
    }

    /**
     * Get the user who created the NCR.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user assigned to resolve the NCR.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get NCR status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'open' => 'bg-red-100 text-red-800',
            'under_review' => 'bg-yellow-100 text-yellow-800',
            'closed' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get NCR severity badge color.
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
     * Check if NCR is overdue (open for more than 7 days).
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'open' && $this->created_at->diffInDays(now()) > 7;
    }

    /**
     * Get days open.
     */
    public function getDaysOpenAttribute(): int
    {
        if ($this->status === 'closed' && $this->resolved_at) {
            return $this->created_at->diffInDays($this->resolved_at);
        }

        return $this->created_at->diffInDays(now());
    }

    /**
     * Scope for NCRs by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for NCRs by severity.
     */
    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope for overdue NCRs.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'open')
            ->where('created_at', '<', now()->subDays(7));
    }

    /**
     * Scope for NCRs assigned to user.
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope for NCRs created by user.
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }
}
