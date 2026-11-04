<?php

declare(strict_types=1);

namespace App\Models\Payroll;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * GAP-109 L-1 — the payroll sheet of one month: draft while the accountant enters it, closed by the owner.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $period
 * @property int $working_days
 * @property string $hours_per_day
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property string|null $closed_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class PayrollPeriod extends Model
{
    use HasUlids;
    use TenantScope;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'payroll_periods';

    protected $fillable = ['tenant_id', 'period', 'working_days', 'hours_per_day', 'status', 'closed_at', 'closed_by'];

    /** @var array<string, string> */
    protected $casts = [
        'working_days' => 'integer',
        'hours_per_day' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    /** @return HasMany<PayrollLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class, 'payroll_period_id');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
