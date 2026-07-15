<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $opportunity_id
 * @property string $quote_number
 * @property int $revision_no
 * @property string $status
 * @property float $subtotal
 * @property float $discount_percent
 * @property float $vat_percent
 * @property float $discount_amount
 * @property float $vat_amount
 * @property float $total
 * @property string|null $payment_terms
 * @property string|null $valid_until
 * @property string|null $notes
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon|null $decided_at
 * @property string|null $created_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, QuoteLineItem> $lines
 * @property-read Opportunity|null $opportunity
 */
class Quote extends Model
{
    use HasUlids;
    /** @use HasFactory<\Database\Factories\QuoteFactory> */
    use HasFactory;
    use TenantScope;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUPERSEDED = 'superseded';

    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_SUPERSEDED,
    ];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SENT, self::STATUS_SUPERSEDED],
        self::STATUS_SENT => [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_SUPERSEDED],
        self::STATUS_REJECTED => [self::STATUS_SUPERSEDED],
        self::STATUS_ACCEPTED => [],
        self::STATUS_SUPERSEDED => [],
    ];

    protected $table = 'quotes';

    protected $fillable = [
        'tenant_id',
        'opportunity_id',
        'quote_number',
        'revision_no',
        'status',
        'subtotal',
        'discount_percent',
        'vat_percent',
        'discount_amount',
        'vat_amount',
        'total',
        'payment_terms',
        'valid_until',
        'notes',
        'sent_at',
        'decided_at',
        'created_by',
    ];

    /** @var array{revision_no: string, subtotal: string, discount_percent: string, vat_percent: string, discount_amount: string, vat_amount: string, total: string, valid_until: string|null, sent_at: string|null, decided_at: string|null} */
    protected $casts = [
        'revision_no' => 'integer',
        'subtotal' => 'float',
        'discount_percent' => 'float',
        'vat_percent' => 'float',
        'discount_amount' => 'float',
        'vat_amount' => 'float',
        'total' => 'float',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<Opportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /** @return HasMany<QuoteLineItem, $this> */
    public function lines(): HasMany
    {
        /** @phpstan-ignore return.type */
        return $this->hasMany(QuoteLineItem::class)->orderBy('sort_order');
    }

    /** @return array{discount_amount: float, vat_amount: float, total: float} */
    public static function computeTotals(float $subtotal, float $discountPercent, float $vatPercent): array
    {
        $discountAmount = round($subtotal * $discountPercent / 100, 2);
        $taxable = $subtotal - $discountAmount;
        $vatAmount = round($taxable * $vatPercent / 100, 2);

        return [
            'discount_amount' => $discountAmount,
            'vat_amount' => $vatAmount,
            'total' => round($taxable + $vatAmount, 2),
        ];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function nextNumber(string $tenantId): string
    {
        $year = date('Y');
        $prefix = "BG-{$year}-";

        $last = self::query()
            ->where('tenant_id', $tenantId)
            ->where('quote_number', 'like', "{$prefix}%")
            ->orderByDesc('quote_number')
            ->value('quote_number');

        if ($last && preg_match('/BG-\d{4}-(\d{4})$/', (string) $last, $m)) {
            $seq = (int) $m[1] + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public static function nextRevision(string $opportunityId): int
    {
        $max = self::query()
            ->where('opportunity_id', $opportunityId)
            ->max('revision_no');

        return ((int) $max) + 1;
    }
}
