<?php

namespace App\Services;

use App\Models\DashboardWidget;
use App\Models\User;
use App\Models\UserDashboard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DashboardCustomizationService
{
    protected $dashboardService;
    protected $realTimeService;

    public function __construct(
        DashboardService $dashboardService,
        DashboardRealTimeService $realTimeService
    ) {
        $this->dashboardService = $dashboardService;
        $this->realTimeService = $realTimeService;
    }

    /**
     * Get user's customizable dashboard
     */
    public function getUserCustomizableDashboard(User $user): array
    {
        try {
            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            $currentLayout = $dashboard->layout;
            $needsCustomLayout = is_null($currentLayout) || (!empty($currentLayout) && !isset($currentLayout[0]['widget_id']));
            if ($needsCustomLayout) {
                $defaultLayout = $this->getDefaultLayoutForRole($user->role);
                $dashboard->update(['layout' => $defaultLayout]);
                $dashboard->refresh();
            }
            $availableWidgets = $this->getAvailableWidgetsForUser($user);
            $widgetCategories = $this->getWidgetCategories();
            $layoutTemplates = $this->getLayoutTemplates();

            return [
                'dashboard' => $dashboard,
                'available_widgets' => $availableWidgets,
                'widget_categories' => $widgetCategories,
                'layout_templates' => $layoutTemplates,
                'customization_options' => $this->getCustomizationOptions($user),
                'permissions' => $this->getCustomizationPermissions($user)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get customizable dashboard', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Add widget to user dashboard
     */
    public function addWidgetToDashboard(User $user, string $widgetId, array $config = []): array
    {
        try {
            DB::beginTransaction();

            // Validate widget exists and user has permission
            $widget = DashboardWidget::findOrFail($widgetId);
            $widgetConfig = $this->ensureArray($widget->config ?? []);
            $this->validateWidgetPermission($user, $widget);

            // Get user dashboard
            $dashboard = UserDashboard::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$dashboard) {
                $dashboard = $this->createUserDashboard($user);
            }

            // Parse current layout
            $layout = $this->decodeLayout($dashboard->layout ?? []);
            
            // Generate widget instance ID
            $widgetInstanceId = Str::ulid();
            
            // Create widget instance
            $mergedConfig = array_merge($widgetConfig, $config);

            $widgetInstance = [
                'id' => $widgetInstanceId,
                'widget_id' => $widgetId,
                'type' => $widget->type,
                'title' => $config['title'] ?? $widget->name,
                'size' => $config['size'] ?? 'medium',
                'position' => $this->calculateNextPosition($layout),
                'config' => $mergedConfig,
                'is_customizable' => $config['is_customizable'] ?? true,
                'created_at' => now()->toISOString()
            ];

            // Add to layout
            $layout[] = $widgetInstance;
            
            // Update dashboard
            $dashboard->update([
                'layout' => json_encode($layout),
                'updated_at' => now()
            ]);

            DB::commit();

            // Broadcast real-time update
            $this->realTimeService->broadcastDashboardUpdate(
                $user->id,
                'widget_added',
                [
                    'widget_instance' => $widgetInstance,
                    'widget_definition' => $widget->toArray()
                ]
            );

            return [
                'success' => true,
                'widget_instance' => $widgetInstance,
                'message' => 'Widget added successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add widget to dashboard', [
                'user_id' => $user->id,
                'widget_id' => $widgetId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Remove widget from user dashboard
     */
    public function removeWidgetFromDashboard(User $user, string $widgetInstanceId): array
    {
        try {
            DB::beginTransaction();

            $dashboard = UserDashboard::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$dashboard) {
                throw new \Exception('Dashboard not found');
            }

            $layout = $this->decodeLayout($dashboard->layout ?? []);
            
            // Find and remove widget instance
            $widgetInstance = null;
            $layout = array_filter($layout, function ($widget) use ($widgetInstanceId, &$widgetInstance) {
                $instanceId = $widget['id'] ?? $widget['instance_id'] ?? null;
                if ($instanceId === $widgetInstanceId) {
                    $widgetInstance = $widget;
                    return false;
                }

                return true;
            });

            if (!$widgetInstance) {
                throw new \Exception('Widget instance not found');
            }

            // Update dashboard
            $dashboard->update([
                'layout' => json_encode(array_values($layout)),
                'updated_at' => now()
            ]);

            DB::commit();

            // Broadcast real-time update
            $this->realTimeService->broadcastDashboardUpdate(
                $user->id,
                'widget_removed',
                ['widget_instance_id' => $widgetInstanceId]
            );

            return [
                'success' => true,
                'message' => 'Widget removed successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove widget from dashboard', [
                'user_id' => $user->id,
                'widget_instance_id' => $widgetInstanceId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update widget configuration
     */
    public function updateWidgetConfig(User $user, string $widgetInstanceId, array $config): array
    {
        try {
            DB::beginTransaction();

            $dashboard = UserDashboard::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$dashboard) {
                throw new \Exception('Dashboard not found');
            }

            $layout = $this->decodeLayout($dashboard->layout ?? []);
            
            // Find and update widget instance
            $updated = false;
            foreach ($layout as &$widget) {
                if ($widget['id'] === $widgetInstanceId) {
                    // Validate config changes
                    $this->validateWidgetConfig($widget, $config);
                    
                    // Update config
                    $widget['config'] = array_merge($widget['config'] ?? [], $config);
                    $widget['updated_at'] = now()->toISOString();
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                throw new \Exception('Widget instance not found');
            }

            // Update dashboard
            $dashboard->update([
                'layout' => json_encode($layout),
                'updated_at' => now()
            ]);

            DB::commit();

            // Broadcast real-time update
            $this->realTimeService->broadcastDashboardUpdate(
                $user->id,
                'widget_config_updated',
                [
                    'widget_instance_id' => $widgetInstanceId,
                    'config' => $config
                ]
            );

            return [
                'success' => true,
                'message' => 'Widget configuration updated successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update widget config', [
                'user_id' => $user->id,
                'widget_instance_id' => $widgetInstanceId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update dashboard layout (drag & drop)
     */
    public function updateDashboardLayout(User $user, array $layout): array
    {
        try {
            DB::beginTransaction();

            // Check permissions
            $permissions = $this->getCustomizationPermissions($user);
            if (empty($permissions['can_configure_widgets'])) {
                throw new \Exception('User does not have permission to update layout');
            }

            // Validate layout structure
            $this->validateLayoutStructure($layout);

            $dashboard = UserDashboard::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$dashboard) {
                $dashboard = $this->createUserDashboard($user);
            }

            // Update positions and layout
            $updatedLayout = $this->normalizeLayoutPositions($layout);
            
            $dashboard->update([
                'layout' => json_encode($updatedLayout),
                'updated_at' => now()
            ]);

            DB::commit();

            // Broadcast real-time update
            $this->realTimeService->broadcastDashboardUpdate(
                $user->id,
                'layout_updated',
                ['layout' => $updatedLayout]
            );

            return [
                'success' => true,
                'layout' => $updatedLayout,
                'message' => 'Dashboard layout updated successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update dashboard layout', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Apply layout template
     */
    public function applyLayoutTemplate(User $user, string $templateId): array
    {
        try {
            $template = $this->getLayoutTemplate($templateId);
            if (!$template) {
                throw new \Exception('Layout template not found');
            }

            // Validate user can 

            DB::beginTransaction();

            $dashboard = UserDashboard::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$dashboard) {
                $dashboard = $this->createUserDashboard($user);
            }

            // Apply template layout
            $templateLayout = $this->adaptTemplateToUser($template, $user);
            
            $dashboard->update([
                'layout' => json_encode($templateLayout),
                'updated_at' => now()
            ]);

            DB::commit();

            // Broadcast real-time update
            $this->realTimeService->broadcastDashboardUpdate(
                $user->id,
                'template_applied',
                [
                    'template_id' => $templateId,
                    'layout' => $templateLayout
                ]
            );

            return [
                'success' => true,
                'layout' => $templateLayout,
                'message' => 'Layout template applied successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to apply layout template', [
                'user_id' => $user->id,
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Save user preferences
     */
    public function saveUserPreferences(User $user, $preferences): array
    {
        try {
            DB::beginTransaction();

            $permissions = $this->getCustomizationPermissions($user);
            if (empty($permissions['can_configure_widgets'])) {
                throw new \Exception('User does not have permission to save preferences');
            }

            $dashboard = UserDashboard::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$dashboard) {
                $dashboard = $this->createUserDashboard($user);
            }

            if (is_string($preferences)) {
                $preferences = json_decode($preferences, true) ?? [];
            }

            // Validate preferences
            $validatedPreferences = $this->validatePreferences($preferences);

            // Merge with existing preferences
            $existingPreferences = json_decode($dashboard->preferences, true) ?? [];
            $mergedPreferences = array_merge($existingPreferences, $validatedPreferences);

            $dashboard->update([
                'preferences' => json_encode($mergedPreferences),
                'updated_at' => now()
            ]);

            DB::commit();

            return [
                'success' => true,
                'preferences' => $mergedPreferences,
                'message' => 'Preferences saved successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save user preferences', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Reset dashboard to default
     */
    public function resetDashboardToDefault(User $user): array
    {
        try {
            DB::beginTransaction();

            $dashboard = UserDashboard::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$dashboard) {
                $dashboard = $this->createUserDashboard($user);
            }

            $emptyLayout = [];
            $dashboard->update([
                'layout' => json_encode($emptyLayout),
                'preferences' => json_encode($this->getDefaultPreferences()),
                'updated_at' => now()
            ]);

            DB::commit();

            // Broadcast real-time update
            $this->realTimeService->broadcastDashboardUpdate(
                $user->id,
                'dashboard_reset',
                ['layout' => $emptyLayout]
            );

            return [
                'success' => true,
                'layout' => $emptyLayout,
                'message' => 'Dashboard reset to default successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reset dashboard', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get available widgets for user
     */
    public function getAvailableWidgetsForUser(User $user): array
    {
        $widgets = DashboardWidget::where('is_active', true)
            ->where('tenant_id', $user->tenant_id)
            ->get();

        return $widgets->filter(function ($widget) {
            return true;
        })->map(function ($widget) {
            return [
                'code' => $widget->code,
                'id' => $widget->id,
                'name' => $widget->name,
                'type' => $widget->type,
                'category' => $widget->category,
                'description' => $widget->description,
                'icon' => $widget->config['icon'] ?? 'widget',
                'default_size' => $widget->config['default_size'] ?? 'medium',
                'is_customizable' => $widget->config['is_customizable'] ?? true,
                'permissions' => $widget->permissions
            ];
        })->values()->toArray();
    }

    /**
     * Get widget categories
     */
    public function getWidgetCategories(): array
    {
        return [
            'overview' => [
                'name' => 'Overview',
                'description' => 'Project overview and summary widgets',
                'icon' => 'chart-bar'
            ],
            'tasks' => [
                'name' => 'Tasks',
                'description' => 'Task management and progress widgets',
                'icon' => 'check-circle'
            ],
            'communication' => [
                'name' => 'Communication',
                'description' => 'RFI, submittals, and communication widgets',
                'icon' => 'chat'
            ],
            'quality' => [
                'name' => 'Quality',
                'description' => 'Quality control and inspection widgets',
                'icon' => 'shield-check'
            ],
            'financial' => [
                'name' => 'Financial',
                'description' => 'Budget and cost tracking widgets',
                'icon' => 'currency-dollar'
            ],
            'safety' => [
                'name' => 'Safety',
                'description' => 'Safety incidents and compliance widgets',
                'icon' => 'exclamation-triangle'
            ],
            'system' => [
                'name' => 'System',
                'description' => 'System health and performance widgets',
                'icon' => 'cog'
            ]
        ];
    }

    /**
     * Get layout templates
     */
    public function getLayoutTemplates(): array
    {
        return [
            'project_manager' => [
                'id' => 'project_manager',
                'name' => 'Project Manager',
                'description' => 'Comprehensive project management layout',
                'role' => 'project_manager',
                'permission' => 'can_apply_templates',
                'widgets' => [
                    'project_overview',
                    'task_progress',
                    'rfi_status',
                    'budget_tracking',
                    'schedule_timeline',
                    'team_performance'
                ]
            ],
            'site_engineer' => [
                'id' => 'site_engineer',
                'name' => 'Site Engineer',
                'description' => 'Field-focused layout for site engineers',
                'role' => 'site_engineer',
                'permission' => 'can_apply_templates',
                'widgets' => [
                    'daily_tasks',
                    'site_diary',
                    'inspection_checklist',
                    'weather_forecast',
                    'equipment_status',
                    'safety_alerts'
                ]
            ],
            'qc_inspector' => [
                'id' => 'qc_inspector',
                'name' => 'QC Inspector',
                'description' => 'Quality control focused layout',
                'role' => 'qc_inspector',
                'permission' => 'can_apply_templates',
                'widgets' => [
                    'inspection_schedule',
                    'ncr_tracking',
                    'quality_metrics',
                    'defect_analysis',
                    'corrective_actions',
                    'compliance_status'
                ]
            ],
            'client_rep' => [
                'id' => 'client_rep',
                'name' => 'Client Representative',
                'description' => 'Client-focused reporting layout',
                'role' => 'client_rep',
                'permission' => 'can_apply_templates',
                'widgets' => [
                    'project_summary',
                    'progress_report',
                    'milestone_status',
                    'budget_summary',
                    'quality_summary',
                    'schedule_status'
                ]
            ]
        ];
    }

    /**
     * Get customization options
     */
    public function getCustomizationOptions(User $user): array
    {
        return [
            'widget_sizes' => ['small', 'medium', 'large', 'extra-large'],
            'layout_grid' => [
                'columns' => 12,
                'row_height' => 60,
                'margin' => [10, 10]
            ],
            'themes' => [
                'light' => 'Light Theme',
                'dark' => 'Dark Theme',
                'auto' => 'Auto (System)'
            ],
            'refresh_intervals' => [
                30 => '30 seconds',
                60 => '1 minute',
                300 => '5 minutes',
                900 => '15 minutes',
                1800 => '30 minutes'
            ],
            'permissions' => $this->getCustomizationPermissions($user)
        ];
    }

    /**
     * Get customization permissions
     */
    public function getCustomizationPermissions(User $user): array
    {
        $permissions = [
            'can_add_widgets' => false,
            'can_remove_widgets' => false,
            'can_resize_widgets' => false,
            'can_move_widgets' => false,
            'can_configure_widgets' => false,
            'can_apply_templates' => false,
            'can_reset_dashboard' => false
        ];

        switch ($user->role) {
            case 'system_admin':
            case 'project_manager':
                $permissions = array_fill_keys(array_keys($permissions), true);
                break;
            case 'design_lead':
            case 'site_engineer':
                $permissions['can_add_widgets'] = true;
                $permissions['can_remove_widgets'] = true;
                $permissions['can_resize_widgets'] = true;
                $permissions['can_move_widgets'] = true;
                $permissions['can_configure_widgets'] = true;
                $permissions['can_apply_templates'] = true;
                break;
            case 'qc_inspector':
            case 'client_rep':
                $permissions['can_resize_widgets'] = true;
                $permissions['can_move_widgets'] = true;
                break;
        }

        return $permissions;
    }

    /**
     * Validate widget permission
     */
    protected function validateWidgetPermission(User $user, DashboardWidget $widget): void
    {
        if (!$this->userCanAccessWidget($user, $widget)) {
            throw new \Exception('User does not have permission to access this widget');
        }
    }

    /**
     * Check if user can access widget
     */
    protected function userCanAccessWidget($user, DashboardWidget $widget): bool
    {
        $permissions = $widget->getResolvedPermissions();
        
        if (empty($permissions)) {
            return true; // No restrictions
        }

        return in_array($user->role, $permissions);
    }

    /**
     * Validate widget configuration
     */
    protected function validateWidgetConfig(array $widget, array $config): void
    {
        // Validate size
        if (isset($config['size'])) {
            $validSizes = ['small', 'medium', 'large', 'extra-large'];
            if (!in_array($config['size'], $validSizes)) {
                throw new \Exception('Invalid widget size');
            }
        }

        // Validate position
        if (isset($config['position'])) {
            if (!isset($config['position']['x']) || !isset($config['position']['y'])) {
                throw new \Exception('Invalid position format');
            }
        }
    }

    /**
     * Validate layout structure
     */
    protected function validateLayoutStructure(array $layout): void
    {
        foreach ($layout as $widget) {
            if (!isset($widget['id']) || !isset($widget['position'])) {
                throw new \Exception('Invalid layout structure');
            }
        }
    }

    /**
     * Calculate next position for new widget
     */
    protected function calculateNextPosition(array $layout): array
    {
        $maxY = 0;
        $maxX = 0;

        foreach ($layout as $widget) {
            $pos = $widget['position'] ?? ['x' => 0, 'y' => 0];
            $size = $widget['size'] ?? 'medium';
            $sizeMap = [
                'small' => ['w' => 3, 'h' => 2],
                'medium' => ['w' => 6, 'h' => 4],
                'large' => ['w' => 9, 'h' => 6],
                'extra-large' => ['w' => 12, 'h' => 8]
            ];
            
            $widgetSize = $sizeMap[$size] ?? $sizeMap['medium'];
            $maxX = max($maxX, $pos['x'] + $widgetSize['w']);
            $maxY = max($maxY, $pos['y'] + $widgetSize['h']);
        }

        return ['x' => 0, 'y' => $maxY];
    }

    /**
     * Normalize layout positions
     */
    protected function normalizeLayoutPositions(array $layout): array
    {
        $normalized = [];
        $y = 0;

        foreach ($layout as $widget) {
            $widget['position'] = ['x' => 0, 'y' => $y];
            $normalized[] = $widget;
            
            $size = $widget['size'] ?? 'medium';
            $sizeMap = [
                'small' => 2,
                'medium' => 4,
                'large' => 6,
                'extra-large' => 8
            ];
            
            $y += $sizeMap[$size] ?? 4;
        }

        return $normalized;
    }

    private function decodeLayout(mixed $layout): array
    {
        if (is_array($layout)) {
            return $layout;
        }

        if (is_string($layout)) {
            return json_decode($layout, true) ?? [];
        }

        return [];
    }

    private function ensureArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        return [];
    }

    /**
     * Create user dashboard
     */
    protected function createUserDashboard(User $user): UserDashboard
    {
        $defaultLayout = $this->getDefaultLayoutForRole($user->role);
        
        return UserDashboard::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => 'My Dashboard',
            'layout' => json_encode($defaultLayout),
            'is_default' => true,
            'preferences' => json_encode($this->getDefaultPreferences())
        ]);
    }

    /**
     * Get default layout for role
     */
    protected function getDefaultLayoutForRole(string $role): array
    {
        $templates = $this->getLayoutTemplates();
        $template = $templates[$role] ?? $templates['project_manager'];
        
        return $this->adaptTemplateToUser($template, (object)['role' => $role]);
    }

    public function getLayoutTemplateForRole(string $role): array
    {
        return $this->getDefaultLayoutForRole($role);
    }

    /**
     * Adapt template to user
     */
    protected function adaptTemplateToUser(array $template, $user): array
    {
        $layout = [];
        $y = 0;

        foreach ($template['widgets'] as $widgetId) {
            $widget = DashboardWidget::where('code', $widgetId)->first();
            if ($widget && $this->userCanAccessWidget($user, $widget)) {
                $layout[] = [
                    'id' => Str::ulid(),
                    'widget_id' => $widget->id,
                    'type' => $widget->type,
                    'title' => $widget->name,
                    'size' => $widget->config['default_size'] ?? 'medium',
                    'position' => ['x' => 0, 'y' => $y],
                    'config' => $widget->config ?? [],
                    'is_customizable' => $widget->config['is_customizable'] ?? true,
                    'created_at' => now()->toISOString()
                ];
                
                $size = $widget->config['default_size'] ?? 'medium';
                $sizeMap = [
                    'small' => 2,
                    'medium' => 4,
                    'large' => 6,
                    'extra-large' => 8
                ];
                
                $y += $sizeMap[$size] ?? 4;
            }
        }

        return $layout;
    }

    /**
     * Get layout template
     */
    protected function getLayoutTemplate(string $templateId): ?array
    {
        $templates = $this->getLayoutTemplates();
        return $templates[$templateId] ?? null;
    }

    /**
     * Validate template permission
     */
    protected function validateTemplatePermission(User $user, array $template): void
    {
        if (isset($template['role']) && $template['role'] !== $user->role) {
            throw new \Exception('User does not have permission to use this template');
        }
    }

    /**
     * Validate preferences
     */
    protected function validatePreferences(array $preferences): array
    {
        $validated = [];

        if (isset($preferences['theme'])) {
            $validThemes = ['light', 'dark', 'auto'];
            if (in_array($preferences['theme'], $validThemes)) {
                $validated['theme'] = $preferences['theme'];
            }
        }

        if (isset($preferences['refresh_interval'])) {
            $validIntervals = [30, 60, 300, 900, 1800];
            if (in_array($preferences['refresh_interval'], $validIntervals)) {
                $validated['refresh_interval'] = $preferences['refresh_interval'];
            }
        }

        if (isset($preferences['compact_mode'])) {
            $validated['compact_mode'] = (bool) $preferences['compact_mode'];
        }

        return $validated;
    }

    /**
     * Get default preferences
     */
    protected function getDefaultPreferences(): array
    {
        return [
            'theme' => 'light',
            'refresh_interval' => 300,
            'compact_mode' => false,
            'show_widget_borders' => true,
            'enable_animations' => true
        ];
    }
}
