<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'query_hash',
        'sql',
        'bindings',
        'execution_time',
        'connection',
        'user_id',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'memory_usage',
        'rows_affected',
        'rows_returned',
        'query_type',
        'is_slow',
        'is_error',
        'error_message',
        'executed_at'
    ];

    protected $casts = [
        'bindings' => 'array',
        'execution_time' => 'decimal:3',
        'memory_usage' => 'integer',
        'rows_affected' => 'integer',
        'rows_returned' => 'integer',
        'is_slow' => 'boolean',
        'is_error' => 'boolean',
        'executed_at' => 'datetime'
    ];

    /**
     * Get the user that executed the query
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for slow queries
     */
    public function scopeSlow($query)
    {
        return $query->where('is_slow', true);
    }

    /**
     * Scope for error queries
     */
    public function scopeErrors($query)
    {
        return $query->where('is_error', true);
    }

    /**
     * Scope for queries by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('query_type', $type);
    }

    /**
     * Scope for queries by user
     */
    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for queries within time range
     */
    public function scopeWithinTimeRange($query, $start, $end)
    {
        return $query->whereBetween('executed_at', [$start, $end]);
    }

    /**
     * Scope for queries with high execution time
     */
    public function scopeHighExecutionTime($query, float $threshold = 1000)
    {
        return $query->where('execution_time', '>', $threshold);
    }

    /**
     * Get query performance statistics
     */
    public static function getPerformanceStats($startDate = null, $endDate = null)
    {
        $query = static::query();

        if ($startDate && $endDate) {
            $query->withinTimeRange($startDate, $endDate);
        }

        return [
            'total_queries' => $query->count(),
            'slow_queries' => $query->clone()->slow()->count(),
            'error_queries' => $query->clone()->errors()->count(),
            'avg_execution_time' => $query->clone()->avg('execution_time'),
            'max_execution_time' => $query->clone()->max('execution_time'),
            'min_execution_time' => $query->clone()->min('execution_time'),
            'total_execution_time' => $query->clone()->sum('execution_time'),
            'queries_by_type' => $query->clone()
                ->selectRaw('query_type, COUNT(*) as count')
                ->groupBy('query_type')
                ->pluck('count', 'query_type'),
            'slowest_queries' => $query->clone()
                ->orderBy('execution_time', 'desc')
                ->limit(10)
                ->get(['sql', 'execution_time', 'executed_at'])
        ];
    }

    /**
     * Get top slow queries
     */
    public static function getTopSlowQueries(int $limit = 10)
    {
        return static::slow()
            ->orderBy('execution_time', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get query frequency analysis
     */
    public static function getQueryFrequencyAnalysis(int $limit = 20)
    {
        return static::selectRaw('
                query_hash,
                sql,
                COUNT(*) as frequency,
                AVG(execution_time) as avg_execution_time,
                MAX(execution_time) as max_execution_time,
                SUM(execution_time) as total_execution_time
            ')
            ->groupBy('query_hash', 'sql')
            ->orderBy('frequency', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old query logs
     */
    public static function cleanupOldLogs(int $days = 30)
    {
        return static::where('executed_at', '<', now()->subDays($days))->delete();
    }
}