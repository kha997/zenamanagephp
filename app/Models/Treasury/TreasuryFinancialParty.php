<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $party_type
 * @property string $name
 * @property string|null $linked_account_id
 * @property string|null $linked_user_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryFinancialParty extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'treasury_financial_parties';

    protected $fillable = [
        'tenant_id', 'party_type', 'name', 'linked_account_id', 'linked_user_id',
    ];
}
