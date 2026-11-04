<?php

declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * GAP-107 L-1 / U-1 — one row per lock or unlock of a project's Treasury period; the latest row is the current state
 * (null locked_through = unlocked).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property \Illuminate\Support\Carbon|null $locked_through
 * @property string|null $reason
 * @property string $created_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryPeriodLock extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'treasury_period_locks';

    protected $fillable = ['tenant_id', 'project_id', 'locked_through', 'reason', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['locked_through' => 'date'];
}
