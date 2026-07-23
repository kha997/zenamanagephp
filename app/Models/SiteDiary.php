<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScope;

class SiteDiary extends Model
{
    use HasUlids, TenantScope;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';

    protected $table = 'site_diaries';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'diary_number',
        'diary_date',
        'weather',
        'temperature',
        'manpower_count',
        'work_performed',
        'equipment_used',
        'materials_delivered',
        'safety_notes',
        'visitors',
        'delays_issues',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'diary_date' => 'date',
        'manpower_count' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
