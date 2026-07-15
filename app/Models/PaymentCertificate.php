<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id ULID primary key
 * @property string $tenant_id Tenant ULID
 * @property string $contract_id Contract ULID
 * @property int $period_no Period number
 * @property \Carbon\Carbon|null $period_from Period start date
 * @property \Carbon\Carbon|null $period_to Period end date
 * @property string $status Certificate status (draft|submitted|approved)
 * @property float $total_this_period Total for this period
 * @property float $retention_amount Retention amount deducted this period
 * @property float $advance_deduction Advance recovery deducted this period
 * @property float $net_payable Net payable after deductions
 * @property string|null $submitted_by User ULID who submitted
 * @property \Carbon\Carbon|null $submitted_at Submission timestamp
 * @property string|null $approved_by User ULID who approved
 * @property \Carbon\Carbon|null $approved_at Approval timestamp
 * @property \App\Models\Contract $contract Related contract
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentCertificateLine> $lines Certificate lines
 */
class PaymentCertificate extends Model
{
    use HasUlids;
    /** @use HasFactory<\Database\Factories\PaymentCertificateFactory> */
    use HasFactory;
    use TenantScope;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SUBMITTED],
        self::STATUS_SUBMITTED => [self::STATUS_APPROVED, self::STATUS_DRAFT],
        self::STATUS_APPROVED => [],
    ];

    protected $table = 'payment_certificates';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'period_no',
        'period_from',
        'period_to',
        'status',
        'total_this_period',
        'retention_amount',
        'advance_deduction',
        'net_payable',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tenant_id' => 'string',
        'contract_id' => 'string',
        'period_no' => 'integer',
        'period_from' => 'date',
        'period_to' => 'date',
        'total_this_period' => 'float',
        'retention_amount' => 'float',
        'advance_deduction' => 'float',
        'net_payable' => 'float',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return HasMany<PaymentCertificateLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PaymentCertificateLine::class, 'payment_certificate_id');
    }
}
