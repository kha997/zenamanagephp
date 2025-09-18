<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZenaQcInspection extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'qc_plan_id',
        'title',
        'description',
        'status',
        'inspection_date',
        'inspector_id',
        'findings',
        'recommendations',
    ];

    protected $casts = [
        'inspection_date' => 'date',
    ];

    /**
     * Get the QC plan that owns the inspection.
     */
    public function qcPlan(): BelongsTo
    {
        return $this->belongsTo(ZenaQcPlan::class, 'qc_plan_id');
    }

    /**
     * Get the user who performed the inspection.
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    /**
     * Get inspection status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'bg-blue-100 text-blue-800',
            'in_progress' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Check if inspection is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->inspection_date && $this->inspection_date->isPast() && $this->status === 'scheduled';
    }

    /**
     * Scope for inspections by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for overdue inspections.
     */
    public function scopeOverdue($query)
    {
        return $query->where('inspection_date', '<', now())
            ->where('status', 'scheduled');
    }

    /**
     * Scope for inspections performed by user.
     */
    public function scopeInspectedBy($query, $userId)
    {
        return $query->where('inspector_id', $userId);
    }
}
