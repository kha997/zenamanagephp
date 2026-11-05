<?php

declare(strict_types=1);

namespace App\Models\Payroll;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * GAP-114 N-1 — one company cost of a month (rent, utilities, …).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $overhead_period_id
 * @property string $category
 * @property string $description
 * @property string $amount
 * @property string|null $created_by
 */
class OverheadCostLine extends Model
{
    use HasUlids;
    use TenantScope;

    public const CATEGORIES = [
        'rent' => 'Thuê văn phòng',
        'utilities' => 'Điện, nước',
        'internet' => 'Internet, điện thoại',
        'software' => 'Phần mềm',
        'supplies' => 'Văn phòng phẩm',
        'depreciation' => 'Khấu hao',
        'other' => 'Khác',
    ];

    protected $table = 'overhead_cost_lines';

    protected $fillable = ['tenant_id', 'overhead_period_id', 'category', 'description', 'amount', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['amount' => 'decimal:2'];
}
