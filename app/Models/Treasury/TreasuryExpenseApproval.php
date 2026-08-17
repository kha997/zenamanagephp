<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $financial_document_id
 * @property string $event
 * @property string|null $from_status
 * @property string $to_status
 * @property string $actor_id
 * @property string|null $note
 * @property array<string,mixed>|null $context
 * @property \Illuminate\Support\Carbon $created_at
 */
class TreasuryExpenseApproval extends Model
{
    use HasUlids;
    use TenantScope;

    public const UPDATED_AT = null;

    protected $table = 'treasury_expense_approvals';

    protected $fillable = [
        'tenant_id', 'financial_document_id', 'event', 'from_status',
        'to_status', 'actor_id', 'note', 'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function financialDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'financial_document_id');
    }
}
