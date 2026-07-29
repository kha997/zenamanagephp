<?php

namespace App\Models;

use App\Exceptions\RfiEscalationIntegrityException;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScope;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $title
 * @property string $subject
 * @property string $description
 * @property string $question
 * @property string $rfi_number
 * @property string $priority
 * @property string|null $location
 * @property string|null $drawing_reference
 * @property string $asked_by
 * @property string $created_by
 * @property string|null $assigned_to
 * @property \Carbon\Carbon|null $due_date
 * @property string $status
 * @property string|null $answer
 * @property string|null $response
 * @property string|null $answered_by
 * @property string|null $responded_by
 * @property \Carbon\Carbon|null $answered_at
 * @property \Carbon\Carbon|null $responded_at
 * @property \Carbon\Carbon|null $assigned_at
 * @property string|null $assignment_notes
 * @property string|null $escalated_to
 * @property string|null $escalation_reason
 * @property string|null $escalated_by
 * @property \Carbon\Carbon|null $escalated_at
 * @property string|null $current_escalation_id
 * @property string|null $closed_by
 * @property \Carbon\Carbon|null $closed_at
 * @property array<int, mixed>|null $attachments
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Rfi extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $fillable = [
        'tenant_id',
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
        'current_escalation_id',
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
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function askedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asked_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Alias to keep backwards compatibility.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->createdBy();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    /**
     * @return BelongsTo<RfiEscalation, $this>
     */
    public function currentEscalation(): BelongsTo
    {
        return $this->belongsTo(RfiEscalation::class, 'current_escalation_id');
    }

    /**
     * Guard against a corrupted current_escalation_id pointer: it must be null, or point to an
     * escalation belonging to THIS rfi, THIS tenant, and still unresolved.
     */
    public function assertEscalationPointerIntegrity(): void
    {
        if ($this->current_escalation_id === null) {
            return;
        }

        $escalation = RfiEscalation::query()->find($this->current_escalation_id);

        if (!$escalation
            || $escalation->rfi_id !== $this->id
            || $escalation->tenant_id !== $this->tenant_id
            || $escalation->resolved_at !== null
        ) {
            throw new RfiEscalationIntegrityException(
                "RFI {$this->id} current_escalation_id points to an invalid escalation (missing, cross-RFI, cross-tenant, or already resolved)."
            );
        }
    }

    /**
     * @return BelongsTo<User, $this>
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
