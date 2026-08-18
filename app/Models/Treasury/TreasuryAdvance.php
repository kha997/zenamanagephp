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
 * @property string $financial_party_id
 * @property string $originating_financial_document_id
 * @property string $amount
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryAdvance extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    protected $table = 'treasury_advances';

    protected $fillable = [
        'tenant_id', 'project_id', 'financial_party_id',
        'originating_financial_document_id', 'amount',
    ];

    /** @var list<string> */
    protected static array $positiveAmountColumns = ['amount'];

    /** @return BelongsTo<TreasuryFinancialParty, $this> */
    public function financialParty(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialParty::class, 'financial_party_id');
    }

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function originatingFinancialDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'originating_financial_document_id');
    }

    /** @return HasMany<TreasuryAdvanceSettlement, $this> */
    public function settlements(): HasMany
    {
        return $this->hasMany(TreasuryAdvanceSettlement::class, 'advance_id');
    }
}
