<?php declare(strict_types=1);

namespace App\Services;

use App\Models\DashboardAlert;
use App\Models\DashboardMetric;
use App\Models\DashboardWidget;
use App\Models\DashboardWidgetDataCache;
use App\Models\User;
use App\Models\UserDashboard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard Service
 * 
 * Service xử lý logic business cho dashboard system
 */
class DashboardService
{
    /**
     * Lấy dashboard của user
     */
    public function getUserDashboard(string $userId): ?UserDashboard
    {
        $dashboard = UserDashboard::forUser($userId)
            ->active()
            ->default()
            ->first();

        if (!$dashboard) {
            // Tạo dashboard mặc định nếu chưa có
            $dashboard = $this->createDefaultDashboard($userId);
        }

        return $dashboard;
    }

    /**
     * Lấy danh sách widgets có sẵn cho user
     */
    public function getAvailableWidgetsForUser(User $user): array
    {
        $userRole = $this->getUserRole($user);
        
        $widgets = DashboardWidget::active()
            ->forRole($userRole)
            ->get()
            ->map(function ($widget) {
                return [
                    'id' => $widget->id,
                    'name' => $widget->name,
                    'type' => $widget->type,
                    'category' => $widget->category,
                    'description' => $widget->description,
                    'display_config' => $widget->getDisplayConfig(),
                    'data_source' => $widget->getDataSourceConfig()
                ];
            });

        return $widgets->toArray();
    }

    /**
     * Lấy dữ liệu cho widget
     */
    public function getWidgetData(string $widgetId, User $user, ?string $projectId = null, array $params = []): array
    {
        $widget = DashboardWidget::findOrFail($widgetId);
        
        // Kiểm tra quyền truy cập
        if (!$widget->isAvailableForRole($this->getUserRole($user))) {
            throw new \Exception('Widget not available for user role');
        }

        // Kiểm tra cache trước
        $cacheKey = DashboardWidgetDataCache::generateCacheKey($widgetId, $user->id, $projectId, $params);
        $cachedData = DashboardWidgetDataCache::getCacheData($widgetId, $user->id, $projectId, $params);
        
        if ($cachedData) {
            return $cachedData;
        }

        // Lấy dữ liệu từ data source
        $data = $this->fetchWidgetData($widget, $user, $projectId, $params);

        // Cache dữ liệu
        DashboardWidgetDataCache::setCacheData(
            $widgetId,
            $user->id,
            $user->tenant_id,
            $data,
            60, // 60 phút
            $projectId,
            $params
        );

        return $data;
    }

    /**
     * Cập nhật layout của dashboard
     */
    public function updateDashboardLayout(string $userId, array $layoutConfig, array $widgets): UserDashboard
    {
        $dashboard = $this->getUserDashboard($userId);
        
        $dashboard->update([
            'layout_config' => $layoutConfig,
            'widgets' => $widgets
        ]);

        return $dashboard;
    }

    /**
     * Thêm widget vào dashboard
     */
    public function addWidgetToDashboard(string $userId, string $widgetId, array $position = [], array $config = []): UserDashboard
    {
        $dashboard = $this->getUserDashboard($userId);
        
        // Kiểm tra widget có tồn tại không
        $widget = DashboardWidget::findOrFail($widgetId);
        
        // Kiểm tra widget đã có trong dashboard chưa
        if (in_array($widgetId, $dashboard->getWidgetIds())) {
            throw new \Exception('Widget already exists in dashboard');
        }

        $dashboard->addWidget($widgetId, $position, $config);

        return $dashboard->fresh();
    }

    /**
     * Xóa widget khỏi dashboard
     */
    public function removeWidgetFromDashboard(string $userId, string $widgetId): UserDashboard
    {
        $dashboard = $this->getUserDashboard($userId);
        $dashboard->removeWidget($widgetId);

        return $dashboard->fresh();
    }

    /**
     * Cập nhật cấu hình widget
     */
    public function updateWidgetConfig(string $userId, string $widgetId, array $config): UserDashboard
    {
        $dashboard = $this->getUserDashboard($userId);
        $dashboard->updateWidgetConfig($widgetId, $config);

        return $dashboard->fresh();
    }

    /**
     * Lấy alerts của user
     */
    public function getUserAlerts(string $userId, ?string $projectId = null, ?string $type = null, ?string $category = null, bool $unreadOnly = false): array
    {
        $query = DashboardAlert::forUser($userId)
            ->notExpired();

        if ($projectId) {
            $query->forProject($projectId);
        }

        if ($type) {
            $query->byType($type);
        }

        if ($category) {
            $query->byCategory($category);
        }

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->latest()->get()->toArray();
    }

    /**
     * Đánh dấu alert đã đọc
     */
    public function markAlertAsRead(string $alertId, string $userId): DashboardAlert
    {
        $alert = DashboardAlert::where('id', $alertId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $alert->markAsRead();

        return $alert;
    }

    /**
     * Đánh dấu tất cả alerts đã đọc
     */
    public function markAllAlertsAsRead(string $userId, ?string $projectId = null): int
    {
        $query = DashboardAlert::forUser($userId)->unread();

        if ($projectId) {
            $query->forProject($projectId);
        }

        return $query->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Lấy metrics cho dashboard
     */
    public function getDashboardMetrics(User $user, ?string $projectId = null, ?string $category = null, string $timeRange = '7d'): array
    {
        $metrics = DashboardMetric::active();

        if ($category) {
            $metrics->byCategory($category);
        }

        $metricsData = [];
        foreach ($metrics->get() as $metric) {
            $value = $metric->getLatestValueForProject($projectId ?? '');
            if (!$value && $projectId) {
                $value = $metric->getLatestValueForTenant($user->tenant_id);
            }

            $metricsData[] = [
                'id' => $metric->id,
                'code' => $metric->metric_code,
                'name' => $metric->name,
                'category' => $metric->category,
                'unit' => $metric->unit,
                'value' => $value ? $value->value : 0,
                'display_config' => $metric->getDisplayConfig(),
                'recorded_at' => $value ? $value->recorded_at : null
            ];
        }

        return $metricsData;
    }

    /**
     * Lấy dashboard template cho role
     */
    public function getDashboardTemplateForRole(User $user): array
    {
        $role = $this->getUserRole($user);
        
        // Trả về template mặc định cho role
        return $this->getDefaultTemplateForRole($role);
    }

    /**
     * Reset dashboard về mặc định
     */
    public function resetDashboardToDefault(string $userId): UserDashboard
    {
        $user = User::findOrFail($userId);
        $role = $this->getUserRole($user);
        
        // Xóa dashboard hiện tại
        UserDashboard::forUser($userId)->delete();
        
        // Tạo dashboard mới từ template
        return $this->createDefaultDashboard($userId);
    }

    /**
     * Lưu preferences của user
     */
    public function saveUserPreferences(string $userId, array $preferences): UserDashboard
    {
        $dashboard = $this->getUserDashboard($userId);
        $dashboard->updatePreferences($preferences);

        return $dashboard;
    }

    /**
     * Tạo dashboard mặc định cho user
     */
    private function createDefaultDashboard(string $userId): UserDashboard
    {
        $user = User::findOrFail($userId);
        $role = $this->getUserRole($user);
        $template = $this->getDefaultTemplateForRole($role);

        return UserDashboard::create([
            'user_id' => $userId,
            'tenant_id' => $user->tenant_id,
            'name' => 'Default Dashboard',
            'layout_config' => $template['layout'],
            'widgets' => $template['widgets'],
            'is_default' => true,
            'is_active' => true
        ]);
    }

    /**
     * Lấy role của user
     */
    private function getUserRole(User $user): string
    {
        // Logic để lấy role của user từ RBAC system
        // Tạm thời return role mặc định
        return 'project_manager';
    }

    /**
     * Lấy dữ liệu từ data source của widget
     */
    private function fetchWidgetData(DashboardWidget $widget, User $user, ?string $projectId, array $params): array
    {
        $dataSource = $widget->getDataSourceConfig();
        
        switch ($dataSource['type'] ?? 'static') {
            case 'static':
                return $dataSource['data'] ?? [];
                
            case 'query':
                return $this->executeQuery($dataSource['query'], $user, $projectId, $params);
                
            case 'metric':
                return $this->getMetricData($dataSource['metric_code'], $user, $projectId);
                
            case 'api':
                return $this->callExternalApi($dataSource['endpoint'], $user, $projectId, $params);
                
            default:
                return [];
        }
    }

    /**
     * Thực thi query để lấy dữ liệu
     */
    private function executeQuery(string $query, User $user, ?string $projectId, array $params): array
    {
        // Thay thế placeholders trong query
        $query = str_replace('{user_id}', $user->id, $query);
        $query = str_replace('{tenant_id}', $user->tenant_id, $query);
        $query = str_replace('{project_id}', $projectId ?? '', $query);
        
        try {
            return DB::select($query);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Lấy dữ liệu metric
     */
    private function getMetricData(string $metricCode, User $user, ?string $projectId): array
    {
        $metric = DashboardMetric::byCode($metricCode)->first();
        
        if (!$metric) {
            return [];
        }

        $value = $projectId 
            ? $metric->getLatestValueForProject($projectId)
            : $metric->getLatestValueForTenant($user->tenant_id);

        return [
            'value' => $value ? $value->value : 0,
            'unit' => $metric->unit,
            'recorded_at' => $value ? $value->recorded_at : null
        ];
    }

    /**
     * Gọi external API
     */
    private function callExternalApi(string $endpoint, User $user, ?string $projectId, array $params): array
    {
        // Implementation cho external API calls
        return [];
    }

    /**
     * Lấy template mặc định cho role
     */
    private function getDefaultTemplateForRole(string $role): array
    {
        $templates = [
            'system_admin' => [
                'layout' => [
                    'columns' => 4,
                    'rows' => 3,
                    'gap' => 16
                ],
                'widgets' => [
                    [
                        'id' => 'system_overview',
                        'position' => ['x' => 0, 'y' => 0, 'w' => 2, 'h' => 1],
                        'config' => []
                    ],
                    [
                        'id' => 'user_management',
                        'position' => ['x' => 2, 'y' => 0, 'w' => 2, 'h' => 1],
                        'config' => []
                    ]
                ]
            ],
            'project_manager' => [
                'layout' => [
                    'columns' => 4,
                    'rows' => 3,
                    'gap' => 16
                ],
                'widgets' => [
                    [
                        'id' => 'project_overview',
                        'position' => ['x' => 0, 'y' => 0, 'w' => 2, 'h' => 1],
                        'config' => []
                    ],
                    [
                        'id' => 'task_management',
                        'position' => ['x' => 2, 'y' => 0, 'w' => 2, 'h' => 1],
                        'config' => []
                    ]
                ]
            ]
        ];

        return $templates[$role] ?? $templates['project_manager'];
    }
}