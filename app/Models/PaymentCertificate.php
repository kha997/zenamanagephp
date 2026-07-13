<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentCertificate extends Model
{
    use HasUlids;
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
