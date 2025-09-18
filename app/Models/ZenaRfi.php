<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZenaRfi extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id',
        'title',
        'subject',
        'description',
        'question',
        'rfi_number',
        'priority',
        'location',
        'drawing_reference',
        'asked_by',
        'created_by',
        'assigned_to',
        'due_date',
        'status',
        'answer',
        'response',
        'answered_by',
        'responded_by',
        'answered_at',
        'responded_at',
        'assigned_at',
        'assignment_notes',
        'escalated_to',
        'escalation_reason',
        'escalated_by',
        'escalated_at',
        'closed_by',
        'closed_at',
        'attachments',
    ];

    protected $casts = [
        'due_date' => 'date',
        'answered_at' => 'datetime',
        'responded_at' => 'datetime',
        'assigned_at' => 'datetime',
        'escalated_at' => 'datetime',
        'closed_at' => 'datetime',
        'attachments' => 'array',
    ];

    /**
     * Get the project that owns the RFI.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ZenaProject::class, 'project_id');
    }

    /**
     * Get the user who asked the RFI.
     */
    public function askedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asked_by');
    }

    /**
     * Get the user who created the RFI.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user assigned to answer the RFI.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who answered the RFI.
     */
    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /**
     * Get the user who responded to the RFI.
     */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Get the user who escalated the RFI.
     */
    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    /**
     * Get the user who closed the RFI.
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get RFI status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'bg-red-100 text-red-800',
            'in_progress' => 'bg-yellow-100 text-yellow-800',
            'answered' => 'bg-blue-100 text-blue-800',
            'closed' => 'bg-green-100 text-green-800',
            'escalated' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Check if RFI is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast() && in_array($this->status, ['pending', 'in_progress']);
    }

    /**
     * Get days until due.
     */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Scope for RFIs by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for overdue RFIs.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereIn('status', ['pending', 'in_progress']);
    }

    /**
     * Scope for RFIs assigned to user.
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope for RFIs asked by user.
     */
    public function scopeAskedBy($query, $userId)
    {
        return $query->where('asked_by', $userId);
    }
}
