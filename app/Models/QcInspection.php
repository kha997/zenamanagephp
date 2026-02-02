<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TenantScope;

/**
 * Model QcInspection để quản lý các cuộc kiểm định chất lượng
 */
class QcInspection extends Model
{
    use HasUlids, HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'qc_plan_id',
        'tenant_id',
        'title',
        'description',
        'status',
        'inspection_date',
        'inspector_id',
        'findings',
        'recommendations',
        'checklist_results',
        'photos',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'checklist_results' => 'array',
        'photos' => 'array',
    ];

    /**
     * Quan hệ với QcPlan
     */
    public function qcPlan(): BelongsTo
    {
        return $this->belongsTo(QcPlan::class);
    }

    /**
     * Quan hệ với Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Quan hệ với User (người kiểm định)
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
    /**
     * Provide a pseudo project_id attribute derived from the plan.
     */
    public function getProjectIdAttribute(): ?string
    {
        return $this->qcPlan?->project_id;
    }

    /**
     * Project attribute derived from the QC plan.
     */
    public function getProjectAttribute(): ?Project
    {
        return $this->qcPlan?->project;
    }

    /**
     * Quan hệ với NCRs
     */
    public function ncrs(): HasMany
    {
        return $this->hasMany(Ncr::class, 'inspection_id');
    }

    /**
     * Get inspection status badge color
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
     * Check if inspection is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->inspection_date && $this->inspection_date->isPast() && $this->status === 'scheduled';
    }

    /**
     * Scope để lọc theo status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope để lọc các inspection quá hạn
     */
    public function scopeOverdue($query)
    {
        return $query->where('inspection_date', '<', now())
            ->where('status', 'scheduled');
    }

    /**
     * Scope để lọc theo inspector
     */
    public function scopeInspectedBy($query, string $userId)
    {
        return $query->where('inspector_id', $userId);
    }
}
