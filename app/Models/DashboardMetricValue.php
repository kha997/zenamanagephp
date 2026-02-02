<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JsonException;

/**
 * Model DashboardMetricValue - Lưu trữ giá trị metrics theo thời gian
 * 
 * @property string $id
 * @property string $metric_id ID của metric
 * @property string|null $project_id ID của project
 * @property string $tenant_id ID của tenant
 * @property float $value Giá trị metric
 * @property array|null $metadata Dữ liệu bổ sung
 * @property \Carbon\Carbon $recorded_at Thời gian ghi nhận
 */
class DashboardMetricValue extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'dashboard_metric_values';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'metric_id',
        'project_id',
        'tenant_id',
        'value',
        'metadata',
        'recorded_at',
        'timestamp',
        'context'
    ];

    protected $casts = [
        'metadata' => 'array',
        'value' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function setTimestampAttribute($value): void
    {
        $this->attributes['recorded_at'] = $value;
    }

    public function setContextAttribute($value): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            try {
                $value = json_encode($value, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $value = json_encode($value);
            }
        }

        $this->attributes['metadata'] = $value;
    }

    public function getContextAttribute()
    {
        return $this->metadata;
    }

    /**
     * Relationship: Giá trị thuộc về metric
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(DashboardMetric::class, 'metric_id');
    }

    /**
     * Relationship: Giá trị thuộc về project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Relationship: Giá trị thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Scope: Lọc theo metric
     */
    public function scopeForMetric($query, string $metricId)
    {
        return $query->where('metric_id', $metricId);
    }

    /**
     * Scope: Lọc theo project
     */
    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope: Lọc theo tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Lọc theo khoảng thời gian
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Lấy giá trị mới nhất
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }

    /**
     * Scope: Lấy giá trị cũ nhất
     */
    public function scopeOldest($query)
    {
        return $query->orderBy('recorded_at', 'asc');
    }
}
