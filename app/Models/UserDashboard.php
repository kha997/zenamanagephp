<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mockery;
use Mockery\MockInterface;

/**
 * Model UserDashboard - Quản lý dashboard của từng user
 * 
 * @property string $id
 * @property string $user_id ID của user
 * @property string $tenant_id ID của tenant
 * @property string $name Tên dashboard
 * @property array $layout_config Cấu hình layout
 * @property array $widgets Danh sách widgets
 * @property array|null $preferences Preferences của user
 * @property bool $is_default Dashboard mặc định
 * @property bool $is_active Trạng thái hoạt động
 */
class UserDashboard extends Model
{
    use HasUlids, HasFactory;

    /**
     * Mockery instance that drives static expectations during tests.
     */
    protected static ?MockInterface $staticMock = null;

    protected $table = 'user_dashboards';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'layout_config',
        'widgets',
        'preferences',
        'is_default',
        'is_active'
    ];

    protected $casts = [
        'layout_config' => 'array',
        'widgets' => 'array',
        'preferences' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Relationship: Dashboard thuộc về user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Dashboard thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Dashboard có nhiều cache data
     */
    public function cacheData(): HasMany
    {
        return $this->hasMany(DashboardWidgetDataCache::class, 'user_id');
    }

    /**
     * Scope: Lọc theo user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Lọc theo tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Chỉ lấy dashboard active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Chỉ lấy dashboard mặc định
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Thêm widget vào dashboard
     */
    public function addWidget(string $widgetId, array $position = [], array $config = [], array $metadata = []): void
    {
        $widgets = $this->widgets ?? [];
        
        $widgets[] = array_merge([
            'id' => $widgetId,
            'widget_id' => $widgetId,
            'position' => $position,
            'config' => $config,
            'added_at' => now()->toISOString()
        ], $metadata);

        $this->update(['widgets' => $widgets]);
    }

    /**
     * Xóa widget khỏi dashboard
     */
    public function removeWidget(string $widgetId): void
    {
        $widgets = $this->widgets ?? [];
        
        $widgets = array_filter($widgets, function ($widget) use ($widgetId) {
            $widgetIdentifier = $widget['widget_id'] ?? $widget['id'] ?? null;
            if ($widgetIdentifier === $widgetId) {
                return false;
            }

            if (($widget['instance_id'] ?? null) === $widgetId) {
                return false;
            }

            return true;
        });

        $this->update(['widgets' => array_values($widgets)]);
    }

    /**
     * Cập nhật vị trí widget
     */
    public function updateWidgetPosition(string $widgetId, array $position): void
    {
        $widgets = $this->widgets ?? [];
        
        foreach ($widgets as &$widget) {
            $widgetIdentifier = $widget['instance_id'] ?? $widget['widget_id'] ?? $widget['id'] ?? null;
            if ($widgetIdentifier === $widgetId || ($widget['id'] ?? null) === $widgetId) {
                $widget['position'] = $position;
                break;
            }
        }

        $this->update(['widgets' => $widgets]);
    }

    /**
     * Cập nhật cấu hình widget
     */
    public function updateWidgetConfig(string $widgetId, array $config): void
    {
        $widgets = $this->widgets ?? [];
        
        foreach ($widgets as &$widget) {
            $widgetIdentifier = $widget['instance_id'] ?? $widget['widget_id'] ?? $widget['id'] ?? null;
            if ($widgetIdentifier === $widgetId || ($widget['id'] ?? null) === $widgetId) {
                $widget['config'] = array_merge($widget['config'] ?? [], $config);
                break;
            }
        }

        $this->update(['widgets' => $widgets]);
    }

    /**
     * Lấy danh sách widget IDs
     */
    public function getWidgetIds(): array
    {
        $widgets = $this->widgets ?? [];
        $widgetIds = array_map(function ($widget) {
            return $widget['widget_id'] ?? $widget['id'] ?? null;
        }, array_filter($widgets));

        return array_values(array_filter($widgetIds));
    }

    protected static function getStaticMock(): MockInterface
    {
        if (static::$staticMock === null) {
            static::$staticMock = Mockery::mock(static::class);
        }

        return static::$staticMock;
    }

    public static function resetStaticMock(): void
    {
        static::$staticMock = null;
    }

    public static function __callStatic($method, $parameters)
    {
        if ($method === 'shouldReceive') {
            return static::getStaticMock()->shouldReceive(...$parameters);
        }

        if (static::$staticMock) {
            try {
                return static::$staticMock->__call($method, $parameters);
            } catch (\BadMethodCallException $exception) {
                // Fall back to default behavior
            }
        }

        return parent::__callStatic($method, $parameters);
    }

    /**
     * Lấy cấu hình layout
     */
    public function getLayoutConfig(): array
    {
        return $this->layout_config ?? [];
    }

    /**
     * Accessor for compatibility with consumers expecting a layout attribute.
     */
    public function getLayoutAttribute(): array
    {
        return $this->widgets ?? [];
    }

    /**
     * Cập nhật cấu hình layout
     */
    public function updateLayoutConfig(array $config): void
    {
        $this->update(['layout_config' => $config]);
    }

    /**
     * Lấy preferences của user
     */
    public function getPreferences(): array
    {
        return $this->preferences ?? [];
    }

    /**
     * Cập nhật preferences
     */
    public function updatePreferences(array $preferences): void
    {
        $this->update(['preferences' => $preferences]);
    }

    /**
     * Đặt dashboard làm mặc định
     */
    public function setAsDefault(): void
    {
        // Bỏ default cho tất cả dashboard khác của user
        self::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Đặt dashboard này làm default
        $this->update(['is_default' => true]);
    }
}
