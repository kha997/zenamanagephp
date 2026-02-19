<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceMetric extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'metric_name',
        'metric_value',
        'metric_unit',
        'category',
        'metadata'
    ];

    protected $casts = [
        'metric_value' => 'float',
        'metadata' => 'array',
        'deleted_at' => 'datetime'
    ];

    /**
     * Scope for specific metric
     */
    public function scopeMetric($query, $name)
    {
        return $query->where('metric_name', $name);
    }

    /**
     * Scope for specific category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for recent metrics
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Get average value for a metric
     */
    public static function getAverage($metricName, $hours = 24)
    {
        return static::where('metric_name', $metricName)
            ->where('created_at', '>=', now()->subHours($hours))
            ->avg('metric_value');
    }

    /**
     * Get maximum value for a metric
     */
    public static function getMax($metricName, $hours = 24)
    {
        return static::where('metric_name', $metricName)
            ->where('created_at', '>=', now()->subHours($hours))
            ->max('metric_value');
    }

    /**
     * Get minimum value for a metric
     */
    public static function getMin($metricName, $hours = 24)
    {
        return static::where('metric_name', $metricName)
            ->where('created_at', '>=', now()->subHours($hours))
            ->min('metric_value');
    }

    /**
     * Record a performance metric
     */
    public static function record($name, $value, $unit = null, $category = 'system', $metadata = [])
    {
        return static::create([
            'metric_name' => $name,
            'metric_value' => $value,
            'metric_unit' => $unit,
            'category' => $category,
            'metadata' => $metadata
        ]);
    }

    /**
     * Get formatted value
     */
    public function getFormattedValueAttribute()
    {
        $value = $this->metric_value;
        $unit = $this->metric_unit;

        if ($unit === 'bytes') {
            return $this->formatBytes($value);
        }

        if ($unit === 'percentage') {
            return round($value, 2) . '%';
        }

        if ($unit === 'milliseconds') {
            return round($value, 2) . 'ms';
        }

        return round($value, 2) . ($unit ? ' ' . $unit : '');
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Get metric trend (increasing, decreasing, stable)
     */
    public function getTrendAttribute()
    {
        $recent = static::where('metric_name', $this->metric_name)
            ->where('created_at', '>=', now()->subHours(1))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->pluck('metric_value')
            ->toArray();

        if (count($recent) < 2) {
            return 'stable';
        }

        $first = $recent[count($recent) - 1];
        $last = $recent[0];

        if ($last > $first * 1.1) {
            return 'increasing';
        } elseif ($last < $first * 0.9) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Get trend icon
     */
    public function getTrendIconAttribute()
    {
        return match($this->trend) {
            'increasing' => 'fa-arrow-up text-danger',
            'decreasing' => 'fa-arrow-down text-success',
            'stable' => 'fa-minus text-info',
            default => 'fa-question text-secondary'
        };
    }
}
