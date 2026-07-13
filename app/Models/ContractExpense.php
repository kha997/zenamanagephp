<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manual expense line on a contract ("chi"). Material cost is NOT entered
 * here — it is computed automatically from MaterialReceiptLine via
 * Api\ContractController::costSummary(); a manual materials category
 * would double-count it.
 */
class ContractExpense extends Model
{
    use HasUlids;
    use TenantScope;

    public const CATEGORY_LABOR = 'labor';
    public const CATEGORY_SUBCONTRACTOR = 'subcontractor';
    public const CATEGORY_DESIGN_OUTSOURCE = 'design_outsource';
    public const CATEGORY_MISC = 'misc';

    public const VALID_CATEGORIES = [
        self::CATEGORY_LABOR,
        self::CATEGORY_SUBCONTRACTOR,
        self::CATEGORY_DESIGN_OUTSOURCE,
        self::CATEGORY_MISC,
    ];

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'expense_date',
        'amount',
        'category',
        'description',
        'recorded_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'float',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_LABOR => 'Nhân công',
            self::CATEGORY_SUBCONTRACTOR => 'Thầu phụ',
            self::CATEGORY_DESIGN_OUTSOURCE => 'Thuê ngoài thiết kế',
            default => 'Khác',
        };
    }
}
