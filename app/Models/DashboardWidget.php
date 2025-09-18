<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Src\Foundation\Traits\HasTimestamps;

/**
 * Model DashboardWidget - Quản lý các widget có sẵn trong hệ thống
 * 
 * @property string $id
 * @property string $name Tên widget
 * @property string $type Loại widget (chart, table, card, metric, alert)
 * @property string $category Danh mục widget
 * @property array|null $config Cấu hình widget
 * @property array|null $data_source Nguồn dữ liệu
 * @property array|null $permissions Quyền truy cập
 * @property bool $is_active Trạng thái hoạt động
 * @property string|null $description Mô tả widget
 */
class DashboardWidget extends Model
{
    use HasFactory, HasUlids, HasTimestamps;

    protected $table = 'dashboard_widgets';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'name',
        'type',
        'category',
        'config',
        'data_source',
        'permissions',
        'is_active',
        'description'
    ];

    protected $casts = [
        'config' => 'array',
        'data_source' => 'array',
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Các loại widget hợp lệ
     */
    public const TYPE_CHART = 'chart';
    public const TYPE_TABLE = 'table';
    public const TYPE_CARD = 'card';
    public const TYPE_METRIC = 'metric';
    public const TYPE_ALERT = 'alert';

    public const VALID_TYPES = [
        self::TYPE_CHART,
        self::TYPE_TABLE,
        self::TYPE_CARD,
        self::TYPE_METRIC,
        self::TYPE_ALERT,
    ];

    /**
     * Các danh mục widget hợp lệ
     */
    public const CATEGORY_OVERVIEW = 'overview';
    public const CATEGORY_PROGRESS = 'progress';
    public const CATEGORY_ANALYTICS = 'analytics';
    public const CATEGORY_ALERTS = 'alerts';
    public const CATEGORY_QUALITY = 'quality';
    public const CATEGORY_BUDGET = 'budget';
    public const CATEGORY_SAFETY = 'safety';

    public const VALID_CATEGORIES = [
        self::CATEGORY_OVERVIEW,
        self::CATEGORY_PROGRESS,
        self::CATEGORY_ANALYTICS,
        self::CATEGORY_ALERTS,
        self::CATEGORY_QUALITY,
        self::CATEGORY_BUDGET,
        self::CATEGORY_SAFETY,
    ];

    /**
     * Relationship: Widget có nhiều cache data
     */
    public function cacheData(): HasMany
    {
        return $this->hasMany(DashboardWidgetDataCache::class, 'widget_id');
    }

    /**
     * Scope: Lọc theo type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Lọc theo category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Chỉ lấy widget active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Lọc theo role permissions
     */
    public function scopeForRole($query, string $role)
    {
        return $query->whereJsonContains('permissions->roles', $role);
    }

    /**
     * Kiểm tra widget có phù hợp với role không
     */
    public function isAvailableForRole(string $role): bool
    {
        if (!$this->permissions || !isset($this->permissions['roles'])) {
            return true; // Nếu không có permission config, cho phép tất cả
        }

        return in_array($role, $this->permissions['roles']);
    }

    /**
     * Lấy cấu hình hiển thị cho widget
     */
    public function getDisplayConfig(): array
    {
        return $this->config['display'] ?? [];
    }

    /**
     * Lấy cấu hình dữ liệu cho widget
     */
    public function getDataSourceConfig(): array
    {
        return $this->data_source ?? [];
    }
}
