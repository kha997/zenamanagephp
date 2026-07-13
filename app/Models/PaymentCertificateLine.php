<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCertificateLine extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'payment_certificate_lines';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'payment_certificate_id',
        'boq_line_item_id',
        'qty_this_period',
        'unit_price_snapshot',
        'amount_this_period',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tenant_id' => 'string',
        'payment_certificate_id' => 'string',
        'boq_line_item_id' => 'string',
        'qty_this_period' => 'float',
        'unit_price_snapshot' => 'float',
        'amount_this_period' => 'float',
    ];

    /** @return BelongsTo<PaymentCertificate, $this> */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(PaymentCertificate::class, 'payment_certificate_id');
    }

    /** @return BelongsTo<BoqLineItem, $this> */
    public function boqLineItem(): BelongsTo
    {
        return $this->belongsTo(BoqLineItem::class);
    }
}
