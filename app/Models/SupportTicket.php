<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{

    protected $fillable = [
        'ticket_number',
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'user_id',
        'assigned_to',
        'due_date',
        'closed_at',
        'closed_by',
        'attachments',
        'metadata'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'closed_at' => 'datetime',
        'attachments' => 'array',
        'metadata' => 'array'
    ];

    /**
     * Get the user who created the ticket
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user assigned to the ticket
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who closed the ticket
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get the messages for the ticket
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    /**
     * Scope for open tickets
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope for in progress tickets
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope for resolved tickets
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Scope for closed tickets
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * Scope for urgent tickets
     */
    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent');
    }

    /**
     * Scope for high priority tickets
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }

    /**
     * Scope for overdue tickets
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('status', '!=', 'closed');
    }

    /**
     * Scope for assigned tickets
     */
    public function scopeAssigned($query)
    {
        return $query->whereNotNull('assigned_to');
    }

    /**
     * Scope for unassigned tickets
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Scope for tickets assigned to user
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope for tickets created by user
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get ticket age in days
     */
    public function getAgeAttribute()
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Get resolution time in hours
     */
    public function getResolutionTimeAttribute()
    {
        if ($this->closed_at) {
            return $this->created_at->diffInHours($this->closed_at);
        }
        
        return null;
    }

    /**
     * Check if ticket is overdue
     */
    public function getIsOverdueAttribute()
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'closed';
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        return match($this->status) {
            'open' => 'badge-warning',
            'in_progress' => 'badge-info',
            'pending_customer' => 'badge-secondary',
            'resolved' => 'badge-success',
            'closed' => 'badge-dark',
            default => 'badge-secondary'
        };
    }

    /**
     * Get priority badge class
     */
    public function getPriorityBadgeClassAttribute()
    {
        return match($this->priority) {
            'urgent' => 'badge-danger',
            'high' => 'badge-warning',
            'medium' => 'badge-info',
            'low' => 'badge-success',
            default => 'badge-secondary'
        };
    }

    /**
     * Get category badge class
     */
    public function getCategoryBadgeClassAttribute()
    {
        return match($this->category) {
            'technical' => 'badge-danger',
            'billing' => 'badge-warning',
            'feature_request' => 'badge-info',
            'bug_report' => 'badge-warning',
            'general' => 'badge-success',
            default => 'badge-secondary'
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'pending_customer' => 'Pending Customer',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => 'Unknown'
        };
    }

    /**
     * Get priority label
     */
    public function getPriorityLabelAttribute()
    {
        return match($this->priority) {
            'urgent' => 'Urgent',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            default => 'Unknown'
        };
    }

    /**
     * Get category label
     */
    public function getCategoryLabelAttribute()
    {
        return match($this->category) {
            'technical' => 'Technical Issue',
            'billing' => 'Billing Question',
            'feature_request' => 'Feature Request',
            'bug_report' => 'Bug Report',
            'general' => 'General Inquiry',
            default => 'Unknown'
        };
    }

    /**
     * Mark ticket as read
     */
    public function markAsRead()
    {
        $this->update(['last_read_at' => now()]);
    }

    /**
     * Check if ticket has unread messages
     */
    public function hasUnreadMessages()
    {
        if (!$this->last_read_at) {
            return $this->messages()->exists();
        }

        return $this->messages()
            ->where('created_at', '>', $this->last_read_at)
            ->exists();
    }

    /**
     * Get unread message count
     */
    public function getUnreadMessageCountAttribute()
    {
        if (!$this->last_read_at) {
            return $this->messages()->count();
        }

        return $this->messages()
            ->where('created_at', '>', $this->last_read_at)
            ->count();
    }

    /**
     * Get latest message
     */
    public function getLatestMessageAttribute()
    {
        return $this->messages()->latest()->first();
    }

    /**
     * Get ticket activity summary
     */
    public function getActivitySummaryAttribute()
    {
        $messages = $this->messages()->count();
        $age = $this->age;
        $status = $this->status_label;
        $priority = $this->priority_label;

        return "{$status} ticket, {$priority} priority, {$age} days old, {$messages} messages";
    }

    /**
     * Get SLA status
     */
    public function getSlaStatusAttribute()
    {
        if ($this->status === 'closed') {
            return 'completed';
        }

        if ($this->is_overdue) {
            return 'breach';
        }

        $hoursUntilDue = $this->due_date ? now()->diffInHours($this->due_date, false) : null;
        
        if ($hoursUntilDue !== null) {
            if ($hoursUntilDue < 0) {
                return 'breach';
            } elseif ($hoursUntilDue < 24) {
                return 'warning';
            }
        }

        return 'on_track';
    }

    /**
     * Get SLA status badge class
     */
    public function getSlaStatusBadgeClassAttribute()
    {
        return match($this->sla_status) {
            'completed' => 'badge-success',
            'on_track' => 'badge-success',
            'warning' => 'badge-warning',
            'breach' => 'badge-danger',
            default => 'badge-secondary'
        };
    }

    /**
     * Get SLA status label
     */
    public function getSlaStatusLabelAttribute()
    {
        return match($this->sla_status) {
            'completed' => 'Completed',
            'on_track' => 'On Track',
            'warning' => 'Warning',
            'breach' => 'Breach',
            default => 'Unknown'
        };
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (!$ticket->ticket_number) {
                $ticket->ticket_number = static::generateTicketNumber();
            }
        });
    }

    /**
     * Generate unique ticket number
     */
    public static function generateTicketNumber()
    {
        do {
            $number = 'TKT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::where('ticket_number', $number)->exists());

        return $number;
    }
}