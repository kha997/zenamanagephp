<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $wallet_id
 * @property string $reconciliation_type
 * @property string|null $external_reference
 * @property \Illuminate\Support\Carbon $reconciled_at
 * @property string $reconciled_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryReconciliation extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'treasury_reconciliations';

    protected $fillable = [
        'tenant_id', 'wallet_id', 'reconciliation_type', 'external_reference',
        'reconciled_at', 'reconciled_by',
    ];

    protected $casts = [
        'reconciled_at' => 'datetime',
    ];

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'wallet_id');
    }
}
