<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Models\Treasury\Concerns\EnforcesRowInvariants;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $total_allocated_amount
 * @property string $status
 * @property string|null $linked_financial_document_id
 * @property string|null $linked_contract_payment_id
 * @property string|null $expected_destination_wallet_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryPaymentRoute extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    protected $table = 'treasury_payment_routes';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'project_id', 'total_allocated_amount', 'status',
        'linked_financial_document_id', 'linked_contract_payment_id',
        'expected_destination_wallet_id',
    ];

    protected static array $positiveAmountColumns = ['total_allocated_amount'];

    protected static array $exactlyOneOfGroups = [
        ['linked_financial_document_id', 'linked_contract_payment_id'],
    ];

    protected static array $coNullablePairs = [
        ['linked_contract_payment_id', 'expected_destination_wallet_id'],
    ];

    protected static array $allowedValues = [
        'status' => [self::STATUS_PLANNED, self::STATUS_PARTIAL, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
    ];

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function linkedFinancialDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'linked_financial_document_id');
    }

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function expectedDestinationWallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'expected_destination_wallet_id');
    }

    /** @return HasMany<TreasuryPaymentRouteLeg, $this> */
    public function legs(): HasMany
    {
        return $this->hasMany(TreasuryPaymentRouteLeg::class, 'payment_route_id');
    }
}
