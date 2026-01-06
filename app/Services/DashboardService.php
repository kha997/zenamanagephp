<?php declare(strict_types=1);

namespace App\Services;

use App\Models\DashboardAlert;
use App\Models\DashboardMetric;
use App\Models\DashboardWidget;
use App\Models\DashboardWidgetDataCache;
use App\Models\User;
use App\Models\UserDashboard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    public function getUserDashboard(mixed $user): array
    {
        $user = $this->resolveUser($user);
        $dashboard = $this->fetchDashboard($user);

        if (!$dashboard) {
            $dashboard = $this->createDefaultDashboard($user);
        }

        return $this->formatDashboard($dashboard);
    }

    /**
     * Lấy danh sách widgets có sẵn cho user
     */
    public function getAvailableWidgets(User $user): array
    {
        $role = $this->getUserRole($user);

        $widgets = DashboardWidget::active()
            ->get();

        $widgets = $widgets
            ->filter(fn ($widget) => $widget->isAvailableForRole($role))
            ->map(fn ($widget) => $this->mapWidgetDefinition($widget));

        return $widgets->toArray();
    }

    /**
     * Lấy dữ liệu cho widget
     */
    public function getWidgetData(string $widgetId, mixed $user, ?string $projectId = null, array $params = []): array
    {
        $user = $this->resolveUser($user);
        $widget = DashboardWidget::findOrFail($widgetId);

        if (!$widget->isAvailableForRole($this->getUserRole($user))) {
            throw new \Exception('Widget not available for user role');
        }

        $cacheKey = DashboardWidgetDataCache::generateCacheKey($widgetId, $user->id, $projectId, $params);
        $cachedData = DashboardWidgetDataCache::getCacheData($widgetId, $user->id, $projectId, $params);

        if ($cachedData) {
            return $cachedData;
        }

        $data = $this->fetchWidgetData($widget, $user, $projectId, $params);

        DashboardWidgetDataCache::setCacheData(
            $widgetId,
            $user->id,
            $user->tenant_id,
            $data,
            60,
            $projectId,
            $params
        );

        return $data;
    }

    /**
     * Lấy alerts của user
     */
    public function getUserAlerts(User $user, ?string $projectId = null, ?string $type = null, ?string $category = null, bool $unreadOnly = false): array
    {
        $query = DashboardAlert::forUser($user->id)->notExpired();

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
    public function markAlertAsRead(User $user, string $alertId): array
    {
        $alert = DashboardAlert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $alert->markAsRead();

        return [
            'success' => true,
            'alert' => $alert->toArray()
        ];
    }

    /**
     * Đánh dấu tất cả alerts đã đọc
     */
    public function markAllAlertsAsRead(User $user, ?string $projectId = null): array
    {
        $query = DashboardAlert::forUser($user->id)->unread();

        if ($projectId) {
            $query->forProject($projectId);
        }

        $query->update([
            'is_read' => true,
            'read_at' => now()
        ]);

        return ['success' => true];
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

            if (!$value) {
                continue;
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

        return $this->getDefaultTemplateForRole($role);
    }

    /**
     * Cập nhật layout của dashboard
     */
    public function updateDashboardLayout(mixed $user, array $layout): array
    {
        $user = $this->resolveUser($user);
        DB::beginTransaction();

        try {
            $dashboard = $this->getOrCreateDashboard($user);
            $normalizedLayout = $this->normalizeLayout($layout);

            $dashboard->update([
                'widgets' => $normalizedLayout
            ]);

            DB::commit();

            return [
                'success' => true,
                'layout' => $this->formatLayoutEntries($normalizedLayout),
                'message' => 'Dashboard layout updated successfully'
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error('Failed to update dashboard layout', [
                'user_id' => $user->id,
                'error' => $exception->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    /**
     * Thêm widget vào dashboard
     */
    public function addWidget(mixed $user, string $widgetId, array $config = []): array
    {
        $user = $this->resolveUser($user);
        DB::beginTransaction();

        try {
            $widget = DashboardWidget::findOrFail($widgetId);

            if (!$widget->isAvailableForRole($this->getUserRole($user))) {
                throw new \Exception('User does not have permission to access this widget');
            }

            $dashboard = $this->getOrCreateDashboard($user);
            $widgets = $dashboard->widgets ?? [];
            $widgetInstance = $this->buildWidgetInstance($widget, $config);
            $widgets[] = $widgetInstance;

            $dashboard->update(['widgets' => $widgets]);

            DB::commit();

            return [
                'success' => true,
                'widget_instance' => $this->formatLayoutEntries([$widgetInstance])[0],
                'message' => 'Widget added successfully'
            ];
        } catch (ModelNotFoundException $exception) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Widget not found'
            ];
        } catch (\Exception $exception) {
            DB::rollBack();

            if ($exception->getMessage() === 'User does not have permission to access this widget') {
                throw $exception;
            }

            Log::error('Failed to add widget to dashboard', [
                'user_id' => $user->id,
                'widget_id' => $widgetId,
                'error' => $exception->getMessage()
            ]);

            throw $exception;
        }
    }

    /**
     * Xóa widget khỏi dashboard
     */
    public function removeWidget(mixed $user, string $widgetInstanceId): array
    {
        $user = $this->resolveUser($user);
        DB::beginTransaction();

        try {
            $dashboard = $this->getOrCreateDashboard($user);
            $widgets = $dashboard->widgets ?? [];
            $filtered = [];
            $found = false;

            foreach ($widgets as $widget) {
                if (($widget['id'] ?? null) === $widgetInstanceId) {
                    $found = true;
                    continue;
                }

                $filtered[] = $widget;
            }

            if (!$found) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'Widget instance not found'
                ];
            }

            $dashboard->update(['widgets' => array_values($filtered)]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Widget removed successfully'
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error('Failed to remove widget from dashboard', [
                'user_id' => $user->id,
                'widget_instance_id' => $widgetInstanceId,
                'error' => $exception->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    /**
     * Cập nhật cấu hình widget
     */
    public function updateWidgetConfig(mixed $user, string $widgetInstanceId, array $config): array
    {
        $user = $this->resolveUser($user);
        DB::beginTransaction();

        try {
            $dashboard = $this->getOrCreateDashboard($user);
            $widgets = $dashboard->widgets ?? [];
            $updated = false;

            foreach ($widgets as &$widget) {
                if (($widget['id'] ?? null) === $widgetInstanceId) {
                    $widget['config'] = array_merge($widget['config'] ?? [], $config);

                    if (isset($config['title'])) {
                        $widget['title'] = $config['title'];
                    }

                    if (isset($config['size'])) {
                        $widget['size'] = $config['size'];
                    }

                    $widget['updated_at'] = now()->toISOString();
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'Widget instance not found'
                ];
            }

            $dashboard->update(['widgets' => $widgets]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Widget configuration updated successfully'
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error('Failed to update widget config', [
                'user_id' => $user->id,
                'widget_instance_id' => $widgetInstanceId,
                'error' => $exception->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    /**
     * Reset dashboard về mặc định
     */
    public function resetDashboard(User $user): array
    {
        UserDashboard::where('user_id', $user->id)->delete();
        $dashboard = $this->createDefaultDashboard($user);

        return [
            'success' => true,
            'dashboard' => $this->formatDashboard($dashboard)
        ];
    }

    /**
     * Lưu preferences của user
     */
    public function saveUserPreferences(User $user, array $preferences): array
    {
        $dashboard = $this->getOrCreateDashboard($user);
        $existing = $dashboard->preferences ?? [];
        $merged = array_merge($existing, $preferences);

        $dashboard->update(['preferences' => $merged]);

        return [
            'success' => true,
            'preferences' => $merged
        ];
    }

    /**
     * Tạo dashboard mặc định cho user
     */
    private function createDefaultDashboard(User $user): UserDashboard
    {
        $template = $this->getDefaultTemplateForRole($this->getUserRole($user));

        return UserDashboard::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => 'Default Dashboard',
            'layout_config' => $template['layout'] ?? [],
            'widgets' => [],
            'preferences' => ['theme' => 'light'],
            'is_default' => true,
            'is_active' => true
        ]);
    }

    /**
     * Format dashboard trước khi trả về client
     */
    private function formatDashboard(UserDashboard $dashboard): array
    {
        $attributes = $dashboard->toArray();
        $attributes['layout'] = $this->formatLayoutEntries($dashboard->widgets ?? []);
        $attributes['preferences'] = $attributes['preferences'] ?? [];

        return $attributes;
    }

    /**
     * Chuẩn hóa layout widget
     */
    private function formatLayoutEntries(array $widgets): array
    {
        return array_map(function ($widget) {
            return [
                'id' => $widget['id'] ?? null,
                'widget_id' => $widget['widget_id'] ?? null,
                'title' => $widget['title'] ?? ($widget['config']['title'] ?? null),
                'size' => $widget['size'] ?? ($widget['config']['size'] ?? null),
                'position' => $widget['position'] ?? [],
                'config' => $widget['config'] ?? [],
                'created_at' => $widget['created_at'] ?? null,
                'updated_at' => $widget['updated_at'] ?? null
            ];
        }, $widgets);
    }

    /**
     * Normalize layout entries
     */
    private function normalizeLayout(array $layout): array
    {
        return array_map(function ($entry) {
            return [
                'id' => $entry['id'] ?? Str::ulid(),
                'widget_id' => $entry['widget_id'] ?? ($entry['id'] ?? null),
                'title' => $entry['title'] ?? ($entry['config']['title'] ?? null),
                'size' => $entry['size'] ?? ($entry['config']['size'] ?? 'medium'),
                'position' => $entry['position'] ?? [],
                'config' => $entry['config'] ?? [],
                'created_at' => $entry['created_at'] ?? now()->toISOString(),
                'updated_at' => $entry['updated_at'] ?? now()->toISOString()
            ];
        }, $layout);
    }

    /**
     * Lấy dashboard hiện tại hoặc tạo mới
     */
    protected function getOrCreateDashboard(User $user): UserDashboard
    {
        $dashboard = $this->fetchDashboard($user);

        if (!$dashboard) {
            $dashboard = $this->createDefaultDashboard($user);
        }

        return $dashboard;
    }

    /**
     * Build widget instance
     */
    private function buildWidgetInstance(DashboardWidget $widget, array $config = []): array
    {
        $baseConfig = $this->normalizeWidgetConfig($widget->config ?? []);

        return [
            'id' => Str::ulid(),
            'widget_id' => $widget->id,
            'type' => $widget->type,
            'title' => $config['title'] ?? $widget->name,
            'size' => $config['size'] ?? 'medium',
            'position' => $config['position'] ?? ['x' => 0, 'y' => 0],
            'config' => array_merge($baseConfig, $config),
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString()
        ];
    }

    /**
     * Normalize widget config payload to array
     */
    private function normalizeWidgetConfig(mixed $config): array
    {
        if (is_array($config)) {
            return $config;
        }

        if (is_string($config)) {
            return json_decode($config, true) ?? [];
        }

        return [];
    }

    /**
     * Normalize user input to string id
     */
    private function normalizeUserId(mixed $user): string
    {
        if ($user instanceof User) {
            return (string) $user->getKey();
        }

        if (is_array($user) && isset($user['id'])) {
            return (string) $user['id'];
        }

        return (string) $user;
    }

    /**
     * Resolve input to User instance
     */
    private function resolveUser(mixed $user): User
    {
        if ($user instanceof User) {
            return $user;
        }

        if (is_array($user) && isset($user['id'])) {
            return User::query()->findOrFail((string) $user['id']);
        }

        $userId = $this->normalizeUserId($user);

        return User::query()->findOrFail($userId);
    }

    /**
     * Lấy dashboard hiện có
     */
    private function fetchDashboard(User $user): ?UserDashboard
    {
        return UserDashboard::forUser($user->id)
            ->active()
            ->default()
            ->first();
    }

    /**
     * Lấy role của user
     */
    private function getUserRole(User $user): string
    {
        return $user->role ?? 'project_manager';
    }

    /**
     * Chuẩn hóa dữ liệu widget trả về client
     */
    private function mapWidgetDefinition(DashboardWidget $widget): array
    {
        return [
            'id' => $widget->id,
            'code' => $widget->code,
            'name' => $widget->name,
            'type' => $widget->type,
            'category' => $widget->category,
            'description' => $widget->description,
            'display_config' => $widget->getDisplayConfig(),
            'data_source' => $widget->getDataSourceConfig()
        ];
    }

    /**
     * Lấy dữ liệu từ data source của widget
     */
    private function fetchWidgetData(DashboardWidget $widget, User $user, ?string $projectId, array $params): array
    {
        $dataSource = $widget->getDataSourceConfig();

        if (!empty($dataSource)) {
            switch ($dataSource['type'] ?? 'static') {
                case 'static':
                    return $dataSource['data'] ?? [];

                case 'query':
                    return $this->executeQuery($dataSource['query'], $user, $projectId, $params);

                case 'metric':
                    return $this->getMetricData($dataSource['metric_code'], $user, $projectId);

                case 'api':
                    return $this->callExternalApi($dataSource['endpoint'], $user, $projectId, $params);
            }
        }

        return $this->getDefaultWidgetData($widget, $user, $projectId);
    }

    /**
     * Dữ liệu mặc định cho widget
     */
    private function getDefaultWidgetData(DashboardWidget $widget, User $user, ?string $projectId): array
    {
        if ($widget->code === 'project_overview') {
            return [
                'total_projects' => 1,
                'active_projects' => 1,
                'completed_projects' => 0
            ];
        }

        return [];
    }

    /**
     * Thực thi query để lấy dữ liệu
     */
    private function executeQuery(string $query, User $user, ?string $projectId, array $params): array
    {
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

            if (!$value) {
                return [];
            }

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
