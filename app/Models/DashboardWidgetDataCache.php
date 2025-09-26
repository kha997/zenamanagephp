<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model DashboardWidgetDataCache - Cache dữ liệu widget
 * 
 * @property string $id
 * @property string $widget_id ID của widget
 * @property string $user_id ID của user
 * @property string|null $project_id ID của project
 * @property string $tenant_id ID của tenant
 * @property string $cache_key Key để cache dữ liệu
 * @property array $data Dữ liệu đã cache
 * @property \Carbon\Carbon $expires_at Thời gian hết hạn cache
 */
class DashboardWidgetDataCache extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'dashboard_widget_data_cache';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'widget_id',
        'user_id',
        'project_id',
        'tenant_id',
        'cache_key',
        'data',
        'expires_at'
    ];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Relationship: Cache thuộc về widget
     */
    public function widget(): BelongsTo
    {
        return $this->belongsTo(DashboardWidget::class, 'widget_id');
    }

    /**
     * Relationship: Cache thuộc về user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Cache thuộc về project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relationship: Cache thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope: Lọc theo widget
     */
    public function scopeForWidget($query, string $widgetId)
    {
        return $query->where('widget_id', $widgetId);
    }

    /**
     * Scope: Lọc theo user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
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
     * Scope: Lọc theo cache key
     */
    public function scopeByKey($query, string $cacheKey)
    {
        return $query->where('cache_key', $cacheKey);
    }

    /**
     * Scope: Chỉ lấy cache chưa hết hạn
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope: Chỉ lấy cache đã hết hạn
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Kiểm tra cache có hết hạn không
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Kiểm tra cache có hợp lệ không (chưa hết hạn)
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Tạo cache key
     */
    public static function generateCacheKey(
        string $widgetId,
        string $userId,
        ?string $projectId = null,
        array $params = []
    ): string {
        $key = "widget:{$widgetId}:user:{$userId}";
        
        if ($projectId) {
            $key .= ":project:{$projectId}";
        }
        
        if (!empty($params)) {
            $key .= ':' . md5(serialize($params));
        }
        
        return $key;
    }

    /**
     * Lấy cache data
     */
    public static function getCacheData(
        string $widgetId,
        string $userId,
        ?string $projectId = null,
        array $params = []
    ): ?array {
        $cacheKey = self::generateCacheKey($widgetId, $userId, $projectId, $params);
        
        $cache = self::byKey($cacheKey)
            ->notExpired()
            ->first();
        
        return $cache ? $cache->data : null;
    }

    /**
     * Lưu cache data
     */
    public static function setCacheData(
        string $widgetId,
        string $userId,
        string $tenantId,
        array $data,
        int $ttlMinutes = 60,
        ?string $projectId = null,
        array $params = []
    ): self {
        $cacheKey = self::generateCacheKey($widgetId, $userId, $projectId, $params);
        
        // Xóa cache cũ nếu có
        self::byKey($cacheKey)->delete();
        
        return self::create([
            'widget_id' => $widgetId,
            'user_id' => $userId,
            'project_id' => $projectId,
            'tenant_id' => $tenantId,
            'cache_key' => $cacheKey,
            'data' => $data,
            'expires_at' => now()->addMinutes($ttlMinutes)
        ]);
    }

    /**
     * Xóa cache data
     */
    public static function clearCacheData(
        string $widgetId,
        string $userId,
        ?string $projectId = null,
        array $params = []
    ): void {
        $cacheKey = self::generateCacheKey($widgetId, $userId, $projectId, $params);
        
        self::byKey($cacheKey)->delete();
    }

    /**
     * Xóa tất cả cache của user
     */
    public static function clearUserCache(string $userId): void
    {
        self::forUser($userId)->delete();
    }

    /**
     * Xóa tất cả cache của project
     */
    public static function clearProjectCache(string $projectId): void
    {
        self::forProject($projectId)->delete();
    }

    /**
     * Xóa tất cả cache đã hết hạn
     */
    public static function clearExpiredCache(): void
    {
        self::expired()->delete();
    }
}