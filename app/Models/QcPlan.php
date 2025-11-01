<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model QcPlan để quản lý kế hoạch kiểm định chất lượng
 */
class QcPlan extends Model
{
    use HasUlids, HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'tenant_id',
        'title',
        'description',
        'status',
        'start_date',
        'end_date',
        'created_by',
        'checklist_items',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'checklist_items' => 'array',
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
     * Quan hệ với User (người tạo)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Quan hệ với QcInspections
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(QcInspection::class);
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
}