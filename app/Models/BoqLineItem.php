<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id ULID primary key
 * @property string $tenant_id Tenant ULID
 * @property string $boq_id BOQ ULID
 * @property string|null $component_id Component ULID
 * @property string $code Line item code
 * @property string $name Line item name
 * @property string|null $description Description
 * @property float $quantity Quantity
 * @property string $unit Unit of measurement
 * @property float|null $unit_price Unit price
 */
class BoqLineItem extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'boq_line_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'boq_id',
        'component_id',
        'code',
        'name',
        'description',
        'quantity',
        'unit',
        'unit_price',
    ];

    protected $casts = [
        'tenant_id' => 'string',
        'boq_id' => 'string',
        'component_id' => 'string',
        'code' => 'string',
        'name' => 'string',
        'description' => 'string',
        'quantity' => 'float',
        'unit' => 'string',
        'unit_price' => 'float',
    ];

    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
