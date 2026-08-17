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
 * @property string $project_id
 * @property string $document_type
 * @property string $status
 * @property string|null $posting_path
 * @property string $amount
 * @property string|null $source_wallet_id
 * @property string|null $destination_wallet_id
 * @property string|null $source_party_id
 * @property string|null $destination_party_id
 * @property string|null $description
 * @property string $created_by
 * @property string|null $approved_by
 * @property \Illuminate\Support\Carbon|null $posted_at
 * @property string|null $reversed_document_id
 * @property string|null $replacement_document_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryFinancialDocument extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    protected $table = 'treasury_financial_documents';

    public const TYPE_FUNDING = 'funding';
    public const TYPE_INTERNAL_TRANSFER = 'internal_transfer';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_OWNER_CONTRIBUTION = 'owner_contribution';
    public const TYPE_ADVANCE = 'advance';
    public const TYPE_ADVANCE_RETURN = 'advance_return';
    public const TYPE_REVERSAL = 'reversal';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_POSTED_UNRECONCILED = 'posted_unreconciled';
    public const STATUS_POSTED_RECONCILED = 'posted_reconciled';
    public const STATUS_REVERSED = 'reversed';

    public const POSTING_PATH_DIRECT = 'direct';
    public const POSTING_PATH_VIA_ROUTE = 'via_route';

    protected $fillable = [
        'tenant_id', 'project_id', 'document_type', 'status', 'posting_path',
        'amount', 'source_wallet_id', 'destination_wallet_id',
        'source_party_id', 'destination_party_id', 'description',
        'created_by', 'approved_by', 'posted_at',
        'reversed_document_id', 'replacement_document_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    protected static array $positiveAmountColumns = ['amount'];

    protected static array $mutuallyExclusivePairs = [
        ['source_wallet_id', 'source_party_id'],
        ['destination_wallet_id', 'destination_party_id'],
    ];

    protected static array $allowedValues = [
        'document_type' => [
            self::TYPE_FUNDING, self::TYPE_INTERNAL_TRANSFER, self::TYPE_EXPENSE,
            self::TYPE_OWNER_CONTRIBUTION, self::TYPE_ADVANCE, self::TYPE_ADVANCE_RETURN,
            self::TYPE_REVERSAL, self::TYPE_ADJUSTMENT,
        ],
        'status' => [
            self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_APPROVED,
            self::STATUS_REJECTED, self::STATUS_POSTED_UNRECONCILED,
            self::STATUS_POSTED_RECONCILED, self::STATUS_REVERSED,
        ],
        'posting_path' => [self::POSTING_PATH_DIRECT, self::POSTING_PATH_VIA_ROUTE],
    ];

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'source_wallet_id');
    }

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'destination_wallet_id');
    }

    /** @return BelongsTo<TreasuryFinancialParty, $this> */
    public function sourceParty(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialParty::class, 'source_party_id');
    }

    /** @return BelongsTo<TreasuryFinancialParty, $this> */
    public function destinationParty(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialParty::class, 'destination_party_id');
    }

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function reversedDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'reversed_document_id');
    }

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function replacementDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'replacement_document_id');
    }
}
