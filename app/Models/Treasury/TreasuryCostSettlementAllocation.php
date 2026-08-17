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
 * @property string|null $financial_document_id
 * @property string|null $advance_settlement_id
 * @property string|null $cost_source_contract_expense_id
 * @property string|null $cost_source_material_receipt_line_id
 * @property string $direction
 * @property string $allocated_amount
 * @property string|null $reverses_allocation_id
 * @property \Illuminate\Support\Carbon $created_at
 */
class TreasuryCostSettlementAllocation extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    public const UPDATED_AT = null;

    protected $table = 'treasury_cost_settlement_allocations';

    public const DIRECTION_APPLY = 'apply';
    public const DIRECTION_REVERSE = 'reverse';

    protected $fillable = [
        'tenant_id', 'financial_document_id', 'advance_settlement_id',
        'cost_source_contract_expense_id', 'cost_source_material_receipt_line_id',
        'direction', 'allocated_amount', 'reverses_allocation_id',
    ];

    protected static array $positiveAmountColumns = ['allocated_amount'];

    protected static array $exactlyOneOfGroups = [
        ['financial_document_id', 'advance_settlement_id'],
        ['cost_source_contract_expense_id', 'cost_source_material_receipt_line_id'],
    ];

    protected static array $allowedValues = [
        'direction' => [self::DIRECTION_APPLY, self::DIRECTION_REVERSE],
    ];

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function financialDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'financial_document_id');
    }

    /** @return BelongsTo<TreasuryAdvanceSettlement, $this> */
    public function advanceSettlement(): BelongsTo
    {
        return $this->belongsTo(TreasuryAdvanceSettlement::class, 'advance_settlement_id');
    }
}
