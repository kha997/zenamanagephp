<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $project_id
 * @property string $wallet_type
 * @property string $name
 * @property string|null $custodian_party_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryWallet extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'treasury_wallets';

    protected $fillable = [
        'tenant_id', 'project_id', 'wallet_type', 'name', 'custodian_party_id',
    ];

    /** @return BelongsTo<TreasuryFinancialParty, $this> */
    public function custodianParty(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialParty::class, 'custodian_party_id');
    }
}
