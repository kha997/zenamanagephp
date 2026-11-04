<?php

declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * GAP-107 I-1 — one uploaded CSV bank statement of a bank wallet.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $wallet_id
 * @property string $statement_reference
 * @property string $original_name
 * @property string $file_hash
 * @property array<string, mixed> $column_map
 * @property int $line_count
 * @property string $created_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryBankStatementImport extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'treasury_bank_statement_imports';

    protected $fillable = [
        'tenant_id', 'project_id', 'wallet_id', 'statement_reference', 'original_name', 'file_hash', 'column_map',
        'line_count', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = ['column_map' => 'array', 'line_count' => 'integer'];
}
