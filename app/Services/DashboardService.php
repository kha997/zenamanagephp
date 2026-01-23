<?php declare(strict_types=1);

namespace App\Services;

use App\Models\DashboardAlert;
use App\Models\DashboardMetric;
use App\Models\DashboardWidget;
use App\Models\DashboardWidgetDataCache;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserDashboard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dashboard Service
 * 
 * Service xử lý logic business cho dashboard system
 */
class DashboardService
{
    private const DEFAULT_ROLE = 'viewer';
    private const ROLE_PRIORITY = [
        'viewer' => 0,
        'team_member' => 1,
        'client' => 1,
        'client_rep' => 1,
        'subcontractor_lead' => 1,
        'qc_inspector' => 2,
        'design_lead' => 2,
        'site_engineer' => 2,
        'project_manager' => 3,
        'admin' => 4,
        'system_admin' => 5,
        'super_admin' => 6,
    ];

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
            ->get();

        $visibleWidgets = $this->filterWidgetsByRole($widgets, $userRole)
            ->values()
            ->map(function (DashboardWidget $widget) {
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

        return $visibleWidgets->toArray();
    }

    private function filterWidgetsByRole(Collection $widgets, string $role): Collection
    {
        return $widgets->filter(fn (DashboardWidget $widget) => $this->isWidgetVisibleToRole($widget, $role));
    }

    private function isWidgetVisibleToRole(DashboardWidget $widget, string $role): bool
    {
        $permissions = DashboardWidget::normalizePermissions($widget->permissions);

        $allowedRoles = $this->resolveAllowedRoles($permissions);

        if (!empty($allowedRoles)) {
            return in_array($role, $allowedRoles, true);
        }

        if (!empty($permissions['min_role'])) {
            return $this->getRoleRank($role) >= $this->getRoleRank((string) $permissions['min_role']);
        }

        if (!empty($permissions['required_role'])) {
            return $role === (string) $permissions['required_role'];
        }

        return true;
    }

    private function resolveAllowedRoles(array $permissions): array
    {
        $roles = [];

        foreach (['roles', 'allowed_roles', 'allowedRoles'] as $key) {
            if (!empty($permissions[$key])) {
                $roles = array_merge($roles, (array) $permissions[$key]);
            }
        }

        if (!empty($permissions['required_role'])) {
            $roles[] = $permissions['required_role'];
        }

        if ($this->hasSequentialKeys($permissions)) {
            $roles = array_merge($roles, $permissions);
        }

        return array_values(array_unique(array_filter($roles, fn ($value) => is_string($value) && $value !== '')));
    }

    private function hasSequentialKeys(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    private function getRoleRank(string $role): int
    {
        return self::ROLE_PRIORITY[strtolower($role)] ?? self::ROLE_PRIORITY[self::DEFAULT_ROLE];
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
    public function updateDashboardLayout(string $userId, array $layoutConfig, ?array $widgets = null): UserDashboard
    {
        $dashboard = $this->getUserDashboard($userId);
        $updateData = [
            'layout_config' => $layoutConfig,
        ];

        if (is_array($widgets)) {
            $updateData['widgets'] = $widgets;
        }

        $dashboard->update($updateData);

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

    public function addWidget(User $user, string $widgetId, array $config = []): array
    {
        DB::beginTransaction();

        try {
            $widget = DashboardWidget::findOrFail($widgetId);

            if (!$widget->isAvailableForRole($this->getUserRole($user))) {
                throw new \Exception('User does not have permission to access this widget');
            }

            $dashboard = $this->getUserDashboard($user->id);
            $layout = $dashboard['layout'] ?? [];

            $widgetInstance = [
                'id' => (string) Str::ulid(),
                'widget_id' => $widgetId,
                'type' => $widget->type,
                'title' => $config['title'] ?? $widget->name,
                'size' => $config['size'] ?? 'medium',
                'position' => $config['position'] ?? [],
                'config' => $config,
                'is_customizable' => true,
                'created_at' => now()->toISOString()
            ];

            $layout[] = $widgetInstance;

            $dashboard->update([
                'layout_config' => $layout,
                'widgets' => $layout
            ]);

            DB::commit();

            return [
                'success' => true,
                'widget_instance' => $widgetInstance
            ];
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Widget not found'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Xóa widget khỏi dashboard
     */
    public function removeWidget(User $user, string $widgetIdentifier): array
    {
        $dashboard = $this->getUserDashboard($user->id);
        $layout = $dashboard->layout_config ?? [];
        $widgets = $dashboard->widgets ?? [];
        $removed = false;

        $layout = $this->removeWidgetInstanceFromStructure($layout, $widgetIdentifier, $removed);
        $widgets = $this->removeWidgetInstanceFromStructure($widgets, $widgetIdentifier, $removed);

        if (!$removed) {
            return [
                'success' => false,
                'message' => 'Widget instance not found'
            ];
        }

        $dashboard->update([
            'layout_config' => $layout,
            'widgets' => $widgets
        ]);

        return [
            'success' => true,
            'message' => 'Widget removed successfully'
        ];
    }

    private function removeWidgetInstanceFromStructure(array $structure, string $identifier, bool &$removed): array
    {
        $result = [];

        foreach ($structure as $key => $value) {
            if (is_array($value)) {
                if ($this->matchesWidgetIdentifier($value, $identifier)) {
                    $removed = true;
                    continue;
                }

                $result[$key] = $this->removeWidgetInstanceFromStructure($value, $identifier, $removed);
                continue;
            }

            if ($this->scalarMatchesIdentifier($value, $identifier)) {
                $removed = true;
                continue;
            }

            $result[$key] = $value;
        }

        if (array_is_list($result)) {
            $result = array_values($result);
        }

        return $result;
    }

    private function matchesWidgetIdentifier(array $widget, string $identifier): bool
    {
        foreach (['id', 'instance_id', 'widget_instance_id', 'widget_key', 'widget_id'] as $key) {
            if (isset($widget[$key]) && (string) $widget[$key] === $identifier) {
                return true;
            }
        }

        return false;
    }

    private function scalarMatchesIdentifier(mixed $value, string $identifier): bool
    {
        return is_scalar($value) && (string) $value === $identifier;
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
    public function getUserAlerts(User|string $user, ?string $projectId = null, ?string $type = null, ?string $category = null, bool $unreadOnly = false): array
    {
        $userId = $this->resolveUserId($user);

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

        $alerts = $query
            ->orderBy('is_read', 'asc')
            ->orderByDesc('created_at')
            ->get()
            ->toArray();

        return array_values($alerts);
    }

    /**
     * Đánh dấu alert đã đọc
     */
    public function markAlertAsRead(User|string $user, string $alertId): array
    {
        $userId = $this->resolveUserId($user);

        $alert = DashboardAlert::where('id', $alertId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $alert->markAsRead();

        return [
            'success' => true,
            'alert' => $alert
        ];
    }

    /**
     * Đánh dấu tất cả alerts đã đọc
     */
    public function markAllAlertsAsRead(User|string $user, ?string $projectId = null): array
    {
        $userId = $this->resolveUserId($user);

        $query = DashboardAlert::forUser($userId)->unread();

        if ($projectId) {
            $query->forProject($projectId);
        }

        $updated = $query->update([
            'is_read' => true,
            'read_at' => now()
        ]);

        return [
            'success' => true,
            'updated' => $updated
        ];
    }

    /**
     * Lấy metrics cho dashboard
     */
    public function getDashboardMetrics(User $user, ?string $projectId = null, ?string $category = null, string $timeRange = '7d'): array
    {
        $tenantId = (string) $user->tenant_id;
        $metrics = DashboardMetric::active();

        if ($category) {
            $metrics->byCategory($category);
        }

        $metricsData = [];
        foreach ($metrics->get() as $metric) {
            $value = $metric->getLatestValueForProject($projectId ?? '');
            if (!$value && $projectId) {
                $value = $metric->getLatestValueForTenant($tenantId);
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
        if (empty($metricsData)) {
            $projectQuery = Project::where('tenant_id', $user->tenant_id);
            $totalTasks = Task::where('tenant_id', $user->tenant_id)->count();

            $metricsData = [
                [
                    'id' => 'projects_active',
                    'code' => 'projects_active',
                    'name' => 'Active Projects',
                    'category' => 'projects',
                    'unit' => 'count',
                    'value' => $projectQuery->where('status', 'active')->count(),
                    'display_config' => [],
                    'recorded_at' => now()->toISOString()
                ],
                [
                    'id' => 'tasks_total',
                    'code' => 'tasks_total',
                    'name' => 'Total Tasks',
                    'category' => 'tasks',
                    'unit' => 'count',
                    'value' => $totalTasks,
                    'display_config' => [],
                    'recorded_at' => now()->toISOString()
                ]
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

    public function resetDashboard(string $userId): array
    {
        $dashboard = $this->resetDashboardToDefault($userId);

        return [
            'success' => true,
            'dashboard' => $dashboard,
        ];
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
            'widgets' => [],
            'preferences' => [],
            'is_default' => true,
            'is_active' => true
        ]);
    }

    /**
     * Lấy role của user
     */
    private function getUserRole(User $user): string
    {
        $explicitRole = trim((string) ($user->role ?? ''));

        if ($explicitRole !== '') {
            return $explicitRole;
        }

        $primaryRole = $user->getPrimaryRole();

        if (!empty($primaryRole)) {
            return $primaryRole;
        }

        if ($user->isSuperAdmin()) {
            return 'super_admin';
        }

        return self::DEFAULT_ROLE;
    }

    private function resolveUserId(User|string $user): string
    {
        return $user instanceof User ? $user->id : $user;
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

        $tenantId = (string) $user->tenant_id;

        $value = $projectId 
            ? $metric->getLatestValueForProject($projectId)
            : $metric->getLatestValueForTenant($tenantId);

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
