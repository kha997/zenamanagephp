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
 * @property string $reconciliation_id
 * @property string $ledger_entry_id
 * @property string $direction
 * @property string|null $reverses_reconciliation_entry_id
 * @property string $actor_id
 * @property \Illuminate\Support\Carbon $created_at
 */
class TreasuryReconciliationEntry extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    public const UPDATED_AT = null;

    protected $table = 'treasury_reconciliation_entries';

    public const DIRECTION_APPLY = 'apply';
    public const DIRECTION_REVERSE = 'reverse';

    protected $fillable = [
        'tenant_id', 'reconciliation_id', 'ledger_entry_id', 'direction',
        'reverses_reconciliation_entry_id', 'actor_id',
    ];

    protected static array $allowedValues = [
        'direction' => [self::DIRECTION_APPLY, self::DIRECTION_REVERSE],
    ];

    /** @return BelongsTo<TreasuryReconciliation, $this> */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(TreasuryReconciliation::class, 'reconciliation_id');
    }

    /** @return BelongsTo<TreasuryLedgerEntry, $this> */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(TreasuryLedgerEntry::class, 'ledger_entry_id');
    }
}
