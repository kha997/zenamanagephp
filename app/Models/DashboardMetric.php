<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model DashboardMetric - Quản lý metrics và KPIs
 * 
 * @property string $id
 * @property string $metric_code Mã metric
 * @property string $category Danh mục metric
 * @property string $name Tên metric
 * @property string|null $unit Đơn vị đo
 * @property array|null $calculation_config Cấu hình tính toán
 * @property array|null $display_config Cấu hình hiển thị
 * @property bool $is_active Trạng thái hoạt động
 * @property string|null $description Mô tả metric
 */
class DashboardMetric extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'dashboard_metrics';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'code',
        'metric_code',
        'type',
        'category',
        'name',
        'unit',
        'permissions',
        'calculation_config',
        'display_config',
        'is_active',
        'description'
    ];

    protected $casts = [
        'calculation_config' => 'array',
        'display_config' => 'array',
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Các danh mục metric hợp lệ
     */
    public const CATEGORY_PROJECT = 'project';
    public const CATEGORY_BUDGET = 'budget';
    public const CATEGORY_QUALITY = 'quality';
    public const CATEGORY_SAFETY = 'safety';
    public const CATEGORY_SCHEDULE = 'schedule';
    public const CATEGORY_RESOURCE = 'resource';
    public const CATEGORY_PERFORMANCE = 'performance';

    public const VALID_CATEGORIES = [
        self::CATEGORY_PROJECT,
        self::CATEGORY_BUDGET,
        self::CATEGORY_QUALITY,
        self::CATEGORY_SAFETY,
        self::CATEGORY_SCHEDULE,
        self::CATEGORY_RESOURCE,
        self::CATEGORY_PERFORMANCE,
    ];

    /**
     * Relationship: Metric có nhiều giá trị theo thời gian
     */
    public function values(): HasMany
    {
        return $this->hasMany(DashboardMetricValue::class, 'metric_id');
    }

    /**
     * Scope: Lọc theo category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Chỉ lấy metric active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Lọc theo metric code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('metric_code', $code);
    }

    /**
     * Lấy giá trị metric mới nhất cho project
     */
    public function getLatestValueForProject(string $projectId): ?DashboardMetricValue
    {
        return $this->values()
            ->where('project_id', $projectId)
            ->orderBy('recorded_at', 'desc')
            ->first();
    }

    /**
     * Lấy giá trị metric mới nhất cho tenant
     */
    public function getLatestValueForTenant(string $tenantId): ?DashboardMetricValue
    {
        return $this->values()
            ->where('tenant_id', $tenantId)
            ->whereNull('project_id')
            ->orderBy('recorded_at', 'desc')
            ->first();
    }

    /**
     * Lấy giá trị metric trong khoảng thời gian
     */
    public function getValuesInRange(string $tenantId, ?string $projectId, $startDate, $endDate)
    {
        return $this->values()
            ->where('tenant_id', $tenantId)
            ->when($projectId, function ($query) use ($projectId) {
                return $query->where('project_id', $projectId);
            })
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at', 'asc')
            ->get();
    }

    /**
     * Tính toán giá trị metric
     */
    public function calculateValue(string $tenantId, ?string $projectId = null, array $context = []): float
    {
        $config = $this->calculation_config ?? [];
        
        if (empty($config)) {
            return 0.0;
        }

        // Thực hiện tính toán dựa trên config
        switch ($config['type'] ?? 'simple') {
            case 'simple':
                return $this->calculateSimpleValue($config, $tenantId, $projectId, $context);
            case 'aggregate':
                return $this->calculateAggregateValue($config, $tenantId, $projectId, $context);
            case 'formula':
                return $this->calculateFormulaValue($config, $tenantId, $projectId, $context);
            default:
                return 0.0;
        }
    }

    /**
     * Tính toán giá trị đơn giản
     */
    private function calculateSimpleValue(array $config, string $tenantId, ?string $projectId, array $context): float
    {
        // Implementation cho simple calculation
        return 0.0;
    }

    /**
     * Tính toán giá trị tổng hợp
     */
    private function calculateAggregateValue(array $config, string $tenantId, ?string $projectId, array $context): float
    {
        // Implementation cho aggregate calculation
        return 0.0;
    }

    /**
     * Tính toán giá trị theo công thức
     */
    private function calculateFormulaValue(array $config, string $tenantId, ?string $projectId, array $context): float
    {
        // Implementation cho formula calculation
        return 0.0;
    }

    /**
     * Lưu giá trị metric
     */
    public function saveValue(string $tenantId, ?string $projectId, float $value, array $metadata = []): DashboardMetricValue
    {
        return $this->values()->create([
            'project_id' => $projectId,
            'tenant_id' => $tenantId,
            'value' => $value,
            'metadata' => $metadata,
            'recorded_at' => now()
        ]);
    }

    /**
     * Lấy cấu hình hiển thị
     */
    public function getDisplayConfig(): array
    {
        return $this->display_config ?? [];
    }

    /**
     * Lấy cấu hình tính toán
     */
    public function getCalculationConfig(): array
    {
        return $this->calculation_config ?? [];
    }
}
