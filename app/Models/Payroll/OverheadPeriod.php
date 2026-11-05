<?php

declare(strict_types=1);

namespace App\Models\Payroll;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * GAP-114 C-1 — the company overhead sheet of one month: draft while lines are entered, closed (split stored) after the
 * payroll month is closed.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $period
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property string|null $closed_by
 */
class OverheadPeriod extends Model
{
    use HasUlids;
    use TenantScope;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'overhead_periods';

    protected $fillable = ['tenant_id', 'period', 'status', 'closed_at', 'closed_by'];

    /** @var array<string, string> */
    protected $casts = ['closed_at' => 'datetime'];

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
