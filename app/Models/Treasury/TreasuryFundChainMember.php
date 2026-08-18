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
 * @property string $fund_chain_id
 * @property string|null $member_financial_document_id
 * @property string|null $member_payment_route_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryFundChainMember extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    protected $table = 'treasury_fund_chain_members';

    protected $fillable = [
        'tenant_id', 'fund_chain_id', 'member_financial_document_id', 'member_payment_route_id',
    ];

    /** @var list<list<string>> */
    protected static array $exactlyOneOfGroups = [
        ['member_financial_document_id', 'member_payment_route_id'],
    ];

    /** @return BelongsTo<TreasuryFundChain, $this> */
    public function fundChain(): BelongsTo
    {
        return $this->belongsTo(TreasuryFundChain::class, 'fund_chain_id');
    }
}
