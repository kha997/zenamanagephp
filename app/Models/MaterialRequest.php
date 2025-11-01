<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialRequest extends Model
{

    protected $fillable = [
        'project_id',
        'request_number',
        'description',
        'status',
        'estimated_cost',
        'required_date',
        'requested_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'required_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the project that owns the material request.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the user who requested the material.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved the material request.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get material request status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'submitted' => 'bg-blue-100 text-blue-800',
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            'fulfilled' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Check if material request is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->required_date && $this->required_date->isPast() && $this->status !== 'fulfilled';
    }

    /**
     * Scope for material requests by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for overdue material requests.
     */
    public function scopeOverdue($query)
    {
        return $query->where('required_date', '<', now())
            ->where('status', '!=', 'fulfilled');
    }

    /**
     * Scope for material requests requested by user.
     */
    public function scopeRequestedBy($query, $userId)
    {
        return $query->where('requested_by', $userId);
    }
}