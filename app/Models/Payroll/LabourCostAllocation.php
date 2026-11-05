<?php

declare(strict_types=1);

namespace App\Models\Payroll;

use App\Models\Project;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GAP-113 S-1 — one person's labour cost in a closed payroll month charged to one project, or (project_id null) not
 * charged to any project. Written by LabourCostService at close, removed on reopen.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $payroll_period_id
 * @property string $staff_member_id
 * @property string|null $project_id
 * @property int $minutes
 * @property string $amount
 * @property-read StaffMember $staffMember
 * @property-read Project|null $project
 */
class LabourCostAllocation extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'labour_cost_allocations';

    protected $fillable = ['tenant_id', 'payroll_period_id', 'staff_member_id', 'project_id', 'minutes', 'amount'];

    /** @var array<string, string> */
    protected $casts = [
        'minutes' => 'integer',
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'staff_member_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
