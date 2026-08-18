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
 * @property string $payment_route_id
 * @property int $sequence_no
 * @property string|null $from_wallet_id
 * @property string $to_wallet_id
 * @property string $amount
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $occurred_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryPaymentRouteLeg extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    protected $table = 'treasury_payment_route_legs';

    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'tenant_id', 'payment_route_id', 'sequence_no', 'from_wallet_id',
        'to_wallet_id', 'amount', 'status', 'occurred_at',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /** @var list<string> */
    protected static array $positiveAmountColumns = ['amount'];

    /** @var array<string,list<string>> */
    protected static array $allowedValues = [
        'status' => [self::STATUS_IN_TRANSIT, self::STATUS_SETTLED, self::STATUS_REVERSED],
    ];

    /** @return BelongsTo<TreasuryPaymentRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(TreasuryPaymentRoute::class, 'payment_route_id');
    }

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function fromWallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'from_wallet_id');
    }

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'to_wallet_id');
    }
}
