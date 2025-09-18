<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'task',
        'level',
        'priority',
        'status',
        'user_id',
        'started_at',
        'completed_at',
        'error_message',
        'metadata'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Get the user who performed the task
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for completed tasks
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed tasks
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for pending tasks
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for high priority tasks
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }

    /**
     * Scope for recent tasks
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get task duration
     */
    public function getDurationAttribute()
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInSeconds($this->completed_at);
        }
        
        return null;
    }

    /**
     * Mark task as started
     */
    public function markAsStarted()
    {
        $this->update([
            'status' => 'running',
            'started_at' => now()
        ]);
    }

    /**
     * Mark task as completed
     */
    public function markAsCompleted($metadata = null)
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'metadata' => $metadata
        ]);
    }

    /**
     * Mark task as failed
     */
    public function markAsFailed($errorMessage = null)
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Get task status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        return match($this->status) {
            'completed' => 'badge-success',
            'failed' => 'badge-danger',
            'running' => 'badge-warning',
            'pending' => 'badge-info',
            default => 'badge-secondary'
        };
    }

    /**
     * Get priority badge class
     */
    public function getPriorityBadgeClassAttribute()
    {
        return match($this->priority) {
            'high' => 'badge-danger',
            'medium' => 'badge-warning',
            'low' => 'badge-info',
            default => 'badge-secondary'
        };
    }

    /**
     * Get level badge class
     */
    public function getLevelBadgeClassAttribute()
    {
        return match($this->level) {
            'error' => 'badge-danger',
            'warning' => 'badge-warning',
            'info' => 'badge-info',
            'success' => 'badge-success',
            default => 'badge-secondary'
        };
    }
}
