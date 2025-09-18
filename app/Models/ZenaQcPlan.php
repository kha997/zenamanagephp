<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZenaQcPlan extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'status',
        'planned_date',
        'created_by',
    ];

    protected $casts = [
        'planned_date' => 'date',
    ];

    /**
     * Get the project that owns the QC plan.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ZenaProject::class, 'project_id');
    }

    /**
     * Get the user who created the QC plan.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the inspections for the QC plan.
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(ZenaQcInspection::class, 'qc_plan_id');
    }

    /**
     * Get QC plan status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'active' => 'bg-green-100 text-green-800',
            'completed' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get completion percentage.
     */
    public function getCompletionPercentageAttribute(): int
    {
        $totalInspections = $this->inspections()->count();
        if ($totalInspections === 0) {
            return 0;
        }

        $completedInspections = $this->inspections()->where('status', 'completed')->count();
        return round(($completedInspections / $totalInspections) * 100);
    }

    /**
     * Scope for QC plans by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for QC plans created by user.
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }
}
