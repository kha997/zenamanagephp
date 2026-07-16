<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $quote_id
 * @property int $sort_order
 * @property string|null $code
 * @property string $name
 * @property string $unit
 * @property float $quantity
 * @property float $unit_price
 * @property float $amount
 * @property string|null $price_note
 */
class QuoteLineItem extends Model
{
    use HasUlids;
    /** @use HasFactory<\Database\Factories\QuoteLineItemFactory> */
    use HasFactory;
    use TenantScope;

    protected $table = 'quote_line_items';

    protected $fillable = [
        'tenant_id',
        'quote_id',
        'sort_order',
        'code',
        'name',
        'unit',
        'quantity',
        'unit_price',
        'amount',
        'price_note',
    ];

    /** @var array{sort_order: string, quantity: string, unit_price: string, amount: string} */
    protected $casts = [
        'sort_order' => 'integer',
        'quantity' => 'float',
        'unit_price' => 'float',
        'amount' => 'float',
    ];

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
