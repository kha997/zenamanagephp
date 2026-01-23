<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected $table = 'user_dashboards';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'layout_config',
        'layout',
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

    protected $appends = [
        'layout',
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
    public function addWidget(string $widgetId, array $position = [], array $config = []): void
    {
        $widgets = $this->widgets ?? [];
        
        $widgets[] = [
            'id' => $widgetId,
            'position' => $position,
            'config' => $config,
            'added_at' => now()->toISOString()
        ];

        $this->update(['widgets' => $widgets]);
    }

    /**
     * Xóa widget khỏi dashboard
     */
    public function removeWidget(string $widgetId): void
    {
        $widgets = $this->widgets ?? [];
        
        $widgets = array_filter($widgets, function ($widget) use ($widgetId) {
            return $widget['id'] !== $widgetId;
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
            if ($widget['id'] === $widgetId) {
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
            if ($widget['id'] === $widgetId) {
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
        return array_column($widgets, 'id');
    }

    /**
     * Lấy cấu hình layout
     */
    public function getLayoutConfig(): array
    {
        return $this->layout_config ?? [];
    }

    /**
     * Accessor shortcut for layout config
     */
    public function getLayoutAttribute(): array
    {
        return $this->widgets ?? [];
    }

    /**
     * Mutator for layout data to keep widgets in sync.
     */
    public function setLayoutAttribute($value): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $this->attributes['widgets'] = json_encode($decoded ?? []);
            return;
        }

        $this->attributes['widgets'] = json_encode($value ?? []);
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
