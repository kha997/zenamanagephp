<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Models\Treasury\Concerns\EnforcesRowInvariants;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $advance_id
 * @property string $settlement_type
 * @property string $direction
 * @property string $amount
 * @property string|null $financial_document_id
 * @property string|null $reverses_settlement_id
 * @property \Illuminate\Support\Carbon $created_at
 */
class TreasuryAdvanceSettlement extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    public const UPDATED_AT = null;

    protected $table = 'treasury_advance_settlements';

    public const SETTLEMENT_TYPE_APPROVED_EXPENSE = 'approved_expense';
    public const SETTLEMENT_TYPE_CASH_RETURN = 'cash_return';

    public const DIRECTION_APPLY = 'apply';
    public const DIRECTION_REVERSE = 'reverse';

    protected $fillable = [
        'tenant_id', 'advance_id', 'settlement_type', 'direction', 'amount',
        'financial_document_id', 'reverses_settlement_id',
    ];

    protected static array $positiveAmountColumns = ['amount'];

    protected static array $allowedValues = [
        'settlement_type' => [self::SETTLEMENT_TYPE_APPROVED_EXPENSE, self::SETTLEMENT_TYPE_CASH_RETURN],
        'direction' => [self::DIRECTION_APPLY, self::DIRECTION_REVERSE],
    ];

    /** @return BelongsTo<TreasuryAdvance, $this> */
    public function advance(): BelongsTo
    {
        return $this->belongsTo(TreasuryAdvance::class, 'advance_id');
    }
}
