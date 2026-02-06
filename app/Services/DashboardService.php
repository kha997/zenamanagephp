<?php declare(strict_types=1);

namespace App\Services;

use App\Models\DashboardAlert;
use App\Models\DashboardMetric;
use App\Models\DashboardWidget;
use App\Models\DashboardWidgetDataCache;
use App\Models\Project;
use App\Models\User;
use App\Models\UserDashboard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\DashboardMetricValue;

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
    public function getUserDashboard(User|string|int|array $user): ?UserDashboard
    {
        $user = $this->resolveUser($user);

        $dashboard = UserDashboard::forUser($user->id)
            ->active()
            ->default()
            ->first();

        if (!$dashboard) {
            // Tạo dashboard mặc định nếu chưa có
            $dashboard = $this->createDefaultDashboard($user);
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

        if (empty($data) && $widget->code === 'project_overview') {
            $data = $this->buildProjectOverviewData($user, $projectId);
        }

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
    public function updateDashboardLayout(User|string|int|array $user, array $layoutConfig, array $widgets = [])
    {
        if ($user instanceof User && func_num_args() === 2) {
            return $this->updateDashboardLayoutEntries($user, $layoutConfig);
        }

        $resolvedUser = $this->resolveUser($user);
        $dashboard = $this->getUserDashboard($resolvedUser);

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
    public function updateWidgetConfig(User|string|int|array $user, string $widgetId, array $config)
    {
        if ($user instanceof User) {
            return $this->updateWidgetInstanceConfig($user, $widgetId, $config);
        }

        $resolvedUser = $this->resolveUser($user);
        $dashboard = $this->getUserDashboard($resolvedUser);
        $dashboard->updateWidgetConfig($widgetId, $config);

        return $dashboard->fresh();
    }

    /**
     * Lấy alerts của user
     */
    public function getUserAlerts(User|string|int|array $user, ?string $projectId = null, ?string $type = null, ?string $category = null, bool $unreadOnly = false): array
    {
        $user = $this->resolveUser($user);

        $query = DashboardAlert::forUser($user->id)
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
    public function markAlertAsRead(User|string|int|array $user, string $alertId): array
    {
        $user = $this->resolveUser($user);

        $alert = DashboardAlert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $alert->update(['is_read' => true]);

        return ['success' => true];
    }

    /**
     * Đánh dấu tất cả alerts đã đọc
     */
    public function markAllAlertsAsRead(User|string|int|array $user, ?string $projectId = null): array
    {
        $user = $this->resolveUser($user);

        $query = DashboardAlert::forUser($user->id)->unread();

        if ($projectId) {
            $query->forProject($projectId);
        }

        $query->update([
            'is_read' => true
        ]);

        return ['success' => true];
    }

    /**
     * Lấy metrics cho dashboard
     */
    public function getDashboardMetrics(User $user, ?string $projectId = null, ?string $category = null, string $timeRange = '7d'): array
    {
        $metricBuilder = DashboardMetric::active();

        if ($category) {
            $metricBuilder->byCategory($category);
        }

        $this->ensureNoArrayBindings($metricBuilder, 'metric list');
        $metrics = $metricBuilder->get();

        $valueQuery = DashboardMetricValue::where('tenant_id', $user->tenant_id)
            ->when($projectId, function (Builder $query) use ($projectId) {
                return $query->where('project_id', $projectId);
            })
            ->orderBy('recorded_at', 'desc');

        $this->ensureNoArrayBindings($valueQuery, 'metric values');
        $metricValues = $valueQuery->get()->groupBy('metric_id');

        $metricsData = [];
        foreach ($metrics as $metric) {
            $value = $metricValues->get($metric->id)?->first();

            if (!$value) {
                continue;
            }

            $metricsData[] = [
                'id' => $metric->id,
                'code' => $metric->metric_code,
                'name' => $metric->name,
                'category' => $metric->category,
                'unit' => $metric->unit,
                'value' => $value->value,
                'display_config' => $metric->getDisplayConfig(),
                'recorded_at' => $value->recorded_at
            ];
        }

        return $metricsData;
    }

    private function ensureNoArrayBindings(Builder $builder, string $context): void
    {
        foreach ($builder->getBindings() as $binding) {
            if (is_array($binding)) {
                throw new \RuntimeException(sprintf(
                    'Array binding detected (%s) %s | SQL: %s | bindings: %s',
                    $context,
                    $builder->toSql(),
                    $builder->toSql(),
                    json_encode($builder->getBindings(), JSON_THROW_ON_ERROR)
                ));
            }
        }
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
    public function resetDashboardToDefault(User|string|int|array $user): UserDashboard
    {
        $user = $this->resolveUser($user);
        $role = $this->getUserRole($user);
        
        // Xóa dashboard hiện tại
        UserDashboard::forUser($user->id)->delete();
        
        // Tạo dashboard mới từ template
        return $this->createDefaultDashboard($user);
    }

    /**
     * Lưu preferences của user
     */
    public function saveUserPreferences(User|string|int|array $user, array $preferences): array
    {
        $user = $this->resolveUser($user);
        $dashboard = $this->persistUserPreferences($user, $preferences);

        return [
            'success' => true,
            'dashboard' => $dashboard
        ];
    }

    /**
     * Apply preferences to a user dashboard and return the refreshed model.
     */
    private function persistUserPreferences(User $user, array $preferences): UserDashboard
    {
        $dashboard = $this->getUserDashboard($user);
        $dashboard->updatePreferences($preferences);

        return $dashboard->fresh();
    }

    /**
     * Compatibility helper: available widgets for a user
     */
    public function getAvailableWidgets(User $user): array
    {
        $role = $this->getUserRole($user);

        $widgets = DashboardWidget::active()
            ->where(function ($query) use ($user) {
                if ($user->tenant_id) {
                    $query->whereNull('tenant_id')
                        ->orWhere('tenant_id', $user->tenant_id);
                } else {
                    $query->whereNull('tenant_id');
                }
            })
            ->get()
            ->filter(fn (DashboardWidget $widget) => $widget->isAvailableForRole($role))
            ->values();

        return $widgets->map(function (DashboardWidget $widget) {
            return [
                'id' => $widget->id,
                'code' => $widget->code ?? $widget->id,
                'name' => $widget->name,
                'type' => $widget->type,
                'category' => $widget->category,
                'description' => $widget->description,
                'config' => $widget->config,
                'data_source' => $widget->data_source,
                'permissions' => $widget->permissions,
                'default_size' => $widget->config['default_size'] ?? 'medium',
                'is_active' => $widget->is_active,
                'tenant_id' => $widget->tenant_id,
            ];
        })->toArray();
    }

    /**
     * Compatibility helper: add widget via legacy signature
     */
    public function addWidget(User|string|int|array $user, string $widgetId, array $config = []): array
    {
        $user = $this->resolveUser($user);

        try {
            $widget = DashboardWidget::findOrFail($widgetId);
        } catch (ModelNotFoundException $exception) {
            return [
                'success' => false,
                'message' => 'Widget not found'
            ];
        }

        if (!$widget->isAvailableForRole($this->getUserRole($user))) {
            throw new \Exception('User does not have permission to access this widget');
        }

        DB::beginTransaction();
        try {
            $dashboard = $this->getUserDashboard($user);
            $layout = $this->normalizeWidgetLayout($dashboard);

            $position = $config['position'] ?? $this->calculateDefaultPosition($layout, $dashboard);
            $widgetConfig = $config;
            unset($widgetConfig['position']);

            $instanceId = (string) Str::ulid();
            $widgetInstance = [
                'id' => $instanceId,
                'instance_id' => $instanceId,
                'widget_id' => $widget->id,
                'code' => $widget->code ?? $widget->id,
                'type' => $widget->type,
                'title' => $widgetConfig['title'] ?? $widget->name,
                'size' => $widgetConfig['size'] ?? $widget->config['default_size'] ?? 'medium',
                'position' => $position,
                'config' => $widgetConfig,
                'is_customizable' => $widgetConfig['is_customizable'] ?? true,
                'added_at' => now()->toISOString(),
                'created_at' => now()->toISOString()
            ];

            $layout[] = $widgetInstance;
            $this->persistWidgetLayout($dashboard, $layout);

            DB::commit();

            return [
                'success' => true,
                'widget_instance' => $widgetInstance
            ];
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * Compatibility helper: remove widget instance
     */
    public function removeWidget(User|string|int|array $user, string $widgetInstanceId): array
    {
        $user = $this->resolveUser($user);
        $dashboard = $this->getUserDashboard($user);
        $layout = $this->normalizeWidgetLayout($dashboard);

        $foundIndex = null;
        foreach ($layout as $index => $entry) {
            $entryId = $entry['id'] ?? $entry['instance_id'] ?? null;
            if ($entryId === $widgetInstanceId) {
                $foundIndex = $index;
                break;
            }
        }

        if ($foundIndex === null) {
            return [
                'success' => false,
                'message' => 'Widget instance not found'
            ];
        }

        array_splice($layout, $foundIndex, 1);
        $this->persistWidgetLayout($dashboard, $layout);

        return ['success' => true];
    }

    /**
     * Compatibility helper: reset dashboard to default and clear custom widgets
     */
    public function resetDashboard(User|string|int|array $user): array
    {
        $user = $this->resolveUser($user);

        $dashboard = $this->resetDashboardToDefault($user);
        $dashboard->update([
            'widgets' => [],
            'preferences' => array_merge($dashboard->preferences ?? [], ['theme' => 'light'])
        ]);

        return ['success' => true];
    }

    /**
     * Normalize layout array for compatibility helpers
     */
    private function normalizeWidgetLayout(UserDashboard $dashboard): array
    {
        return $dashboard->widgets ?? [];
    }

    /**
     * Persist layout array to dashboard
     */
    private function persistWidgetLayout(UserDashboard $dashboard, array $layout): void
    {
        $dashboard->update(['widgets' => array_values($layout)]);
    }

    /**
     * Calculate default position for new widget instance
     */
    private function calculateDefaultPosition(array $layout, UserDashboard $dashboard): array
    {
        $columns = $dashboard->layout_config['columns'] ?? 4;
        $columns = max((int) $columns, 1);
        $index = count($layout);

        return [
            'x' => $index % $columns,
            'y' => (int) floor($index / $columns),
            'w' => 1,
            'h' => 1
        ];
    }

    /**
     * Update widget entry by instance id (compatibility helper)
     */
    private function updateWidgetInstanceConfig(User $user, string $widgetInstanceId, array $config): array
    {
        $dashboard = $this->getUserDashboard($user);
        $layout = $this->normalizeWidgetLayout($dashboard);

        $updated = false;
        foreach ($layout as &$entry) {
            $entryId = $entry['id'] ?? $entry['instance_id'] ?? null;
            if ($entryId === $widgetInstanceId) {
                if (isset($config['position'])) {
                    $entry['position'] = $config['position'];
                    unset($config['position']);
                }

                $entry['config'] = array_merge($entry['config'] ?? [], $config);

                if (isset($config['title'])) {
                    $entry['title'] = $config['title'];
                }

                if (isset($config['size'])) {
                    $entry['size'] = $config['size'];
                }

                $updated = true;
                break;
            }
        }

        if (!$updated) {
            return [
                'success' => false,
                'message' => 'Widget instance not found'
            ];
        }

        $this->persistWidgetLayout($dashboard, $layout);

        return ['success' => true];
    }

    /**
     * Update layout entries (compatibility helper)
     */
    private function updateDashboardLayoutEntries(User $user, array $layout): array
    {
        $dashboard = $this->getUserDashboard($user);
        $this->persistWidgetLayout($dashboard, $layout);

        return [
            'success' => true,
            'layout' => array_values($layout)
        ];
    }

    /**
     * Fallback data builder for project overview widgets
     */
    private function buildProjectOverviewData(User $user, ?string $projectId): array
    {
        $baseQuery = Project::where('tenant_id', $user->tenant_id);
        if ($projectId) {
            $baseQuery->where('id', $projectId);
        }

        $totalProjects = (clone $baseQuery)->count();
        $activeProjects = (clone $baseQuery)->where('status', 'active')->count();

        return [
            'total_projects' => $totalProjects,
            'active_projects' => $activeProjects
        ];
    }

    /**
     * Tạo dashboard mặc định cho user
     */
    private function createDefaultDashboard(User $user): UserDashboard
    {
        $role = $this->getUserRole($user);
        $template = $this->getDefaultTemplateForRole($role);

        return UserDashboard::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => 'Default Dashboard',
            'layout_config' => $template['layout'],
            'widgets' => [],
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
        return $user->role ?? 'project_manager';
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
     * Resolve incoming user identifier into a User instance.
     */
    private function resolveUser(User|string|int|array $user): User
    {
        if ($user instanceof User) {
            return $user;
        }

        if (is_string($user) || is_int($user)) {
            return User::query()->findOrFail($user);
        }

        if (is_array($user)) {
            $id = $user['id'] ?? $user['user_id'] ?? null;

            if (!$id) {
                throw new InvalidArgumentException('Unable to resolve user id from provided data.');
            }

            return User::query()->findOrFail($id);
        }

        throw new InvalidArgumentException('Unsupported user identifier provided to DashboardService.');
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
