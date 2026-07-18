<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $work_item_code
 * @property string $work_item_name
 * @property string $unit
 * @property float $unit_price
 * @property string $benchmark_type
 * @property string|null $evidence_note
 * @property \Illuminate\Support\Carbon $evidenced_at
 * @property string|null $created_by
 */
class PriceReferenceEntry extends Model
{
    use HasUlids;
    /** @use HasFactory<\Database\Factories\PriceReferenceEntryFactory> */
    use HasFactory;
    use TenantScope;

    public const UPDATED_AT = null;

    public const BENCHMARK_VENDOR_QUOTE = 'vendor_quote';
    public const BENCHMARK_PREVIOUS_PROJECT = 'previous_project';
    public const BENCHMARK_APPROVED_RATE = 'approved_rate';
    public const BENCHMARK_EXPERT_ESTIMATE = 'expert_estimate';

    /** @var list<string> */
    public const VALID_BENCHMARK_TYPES = [
        self::BENCHMARK_VENDOR_QUOTE,
        self::BENCHMARK_PREVIOUS_PROJECT,
        self::BENCHMARK_APPROVED_RATE,
        self::BENCHMARK_EXPERT_ESTIMATE,
    ];

    /** @var array<string, string> */
    public const BENCHMARK_TYPE_LABELS = [
        self::BENCHMARK_VENDOR_QUOTE => 'Báo giá nhà cung cấp',
        self::BENCHMARK_PREVIOUS_PROJECT => 'Giá dự án trước',
        self::BENCHMARK_APPROVED_RATE => 'Bảng giá nội bộ đã duyệt',
        self::BENCHMARK_EXPERT_ESTIMATE => 'Ước tính chuyên gia',
    ];

    protected $table = 'price_reference_entries';

    protected $fillable = [
        'tenant_id',
        'work_item_code',
        'work_item_name',
        'unit',
        'unit_price',
        'benchmark_type',
        'evidence_note',
        'evidenced_at',
        'created_by',
    ];

    /** @var array{unit_price: string, evidenced_at: string} */
    protected $casts = [
        'unit_price' => 'float',
        'evidenced_at' => 'date',
    ];

    public static function latestFor(string $tenantId, string $code, string $unit): ?self
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('work_item_code', $code)
            ->where('unit', $unit)
            ->orderByDesc('evidenced_at')
            ->orderByDesc('created_at')
            ->first();
    }
}
