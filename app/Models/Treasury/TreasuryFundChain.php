<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $chain_reference
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryFundChain extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'treasury_fund_chains';

    protected $fillable = ['tenant_id', 'project_id', 'chain_reference', 'description'];
}
