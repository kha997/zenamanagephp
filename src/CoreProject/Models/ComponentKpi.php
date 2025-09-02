<?php declare(strict_types=1);

namespace Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Model ComponentKpi
 * Quản lý các KPI của component
 * 
 * @property string $id
 * @property string $component_id
 * @property string $kpi_code
 * @property float $value
 * @property string|null $unit
 * @property string|null $description
 * @property \Carbon\Carbon|null $measured_date
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ComponentKpi extends Model
{
    use HasUlids;

    protected $table = 'component_kpis';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'component_id',
        'kpi_code',
        'value',
        'unit',
        'description',
        'measured_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'measured_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship với Component
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}