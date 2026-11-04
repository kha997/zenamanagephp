<?php

declare(strict_types=1);

namespace App\Models\Payroll;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * GAP-109 — one person the company pays: an employee or a collaborator. is_overhead marks office staff whose cost goes
 * to company overhead (part 4); user_id links the login used for time logging (part 2).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property string $staff_type
 * @property bool $is_overhead
 * @property string|null $user_id
 * @property string|null $financial_party_id
 * @property string|null $standard_hours_override
 * @property string $annual_bonus_estimate
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class StaffMember extends Model
{
    use HasUlids;
    use TenantScope;

    public const TYPE_EMPLOYEE = 'employee';

    public const TYPE_COLLABORATOR = 'collaborator';

    public const TYPES = [self::TYPE_EMPLOYEE => 'Nhân viên', self::TYPE_COLLABORATOR => 'Cộng tác viên'];

    protected $table = 'staff_members';

    protected $fillable = [
        'tenant_id', 'code', 'name', 'staff_type', 'is_overhead', 'user_id', 'financial_party_id',
        'standard_hours_override', 'annual_bonus_estimate', 'start_date', 'end_date', 'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_overhead' => 'boolean',
        'is_active' => 'boolean',
        'standard_hours_override' => 'decimal:2',
        'annual_bonus_estimate' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
