<?php

declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * GAP-107 I-1 — one line of an imported bank statement. It never creates a Treasury document; it can only be matched to
 * an existing unreconciled ledger entry (confirmed through bank reconciliation) or ignored.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $import_id
 * @property int $line_no
 * @property \Illuminate\Support\Carbon $transaction_date
 * @property string $direction
 * @property string $amount
 * @property string|null $description
 * @property string|null $bank_reference
 * @property string $fingerprint
 * @property string $status
 * @property string|null $ledger_entry_id
 * @property string|null $reconciliation_id
 * @property string|null $note
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryBankStatementLine extends Model
{
    use HasUlids;
    use TenantScope;

    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_DUPLICATE = 'duplicate';

    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';

    protected $table = 'treasury_bank_statement_lines';

    protected $fillable = [
        'tenant_id', 'import_id', 'line_no', 'transaction_date', 'direction', 'amount', 'description', 'bank_reference',
        'fingerprint', 'status', 'ledger_entry_id', 'reconciliation_id', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = ['transaction_date' => 'date', 'line_no' => 'integer', 'amount' => 'decimal:2'];
}
