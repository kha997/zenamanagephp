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
 * @property string|null $source_financial_document_id
 * @property string|null $source_payment_route_leg_id
 * @property string $wallet_id
 * @property string $direction
 * @property string $amount
 * @property string $entry_type
 * @property \Illuminate\Support\Carbon $posted_at
 * @property string|null $reversal_of_entry_id
 * @property string $original_posting_key
 * @property \Illuminate\Support\Carbon $created_at
 */
class TreasuryLedgerEntry extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    public const UPDATED_AT = null;

    protected $table = 'treasury_ledger_entries';

    public const DIRECTION_DEBIT = 'debit';
    public const DIRECTION_CREDIT = 'credit';

    protected $fillable = [
        'tenant_id', 'source_financial_document_id', 'source_payment_route_leg_id',
        'wallet_id', 'direction', 'amount', 'entry_type', 'posted_at',
        'reversal_of_entry_id', 'original_posting_key',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'posted_at' => 'datetime',
    ];

    /** @var list<string> */
    protected static array $positiveAmountColumns = ['amount'];

    /** @var list<list<string>> */
    protected static array $exactlyOneOfGroups = [
        ['source_financial_document_id', 'source_payment_route_leg_id'],
    ];

    /** @var array<string,list<string>> */
    protected static array $allowedValues = [
        'direction' => [self::DIRECTION_DEBIT, self::DIRECTION_CREDIT],
    ];

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'wallet_id');
    }
}
