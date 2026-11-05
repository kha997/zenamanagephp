<?php

declare(strict_types=1);

namespace App\Models\Payroll;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * GAP-114 A-1 — overhead of a closed month charged to one project, or (project_id null) not allocated.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $overhead_period_id
 * @property string|null $project_id
 * @property int $minutes
 * @property string $amount
 */
class OverheadAllocation extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'overhead_allocations';

    protected $fillable = ['tenant_id', 'overhead_period_id', 'project_id', 'minutes', 'amount'];

    /** @var array<string, string> */
    protected $casts = ['minutes' => 'integer', 'amount' => 'decimal:2'];
}
