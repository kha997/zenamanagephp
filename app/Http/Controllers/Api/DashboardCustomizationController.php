<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardCustomizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DashboardCustomizationController extends Controller
{
    protected $customizationService;

    public function __construct(DashboardCustomizationService $customizationService)
    {
        $this->customizationService = $customizationService;
    }

    /**
     * Get customizable dashboard with all options
     */
    public function getCustomizableDashboard(): JsonResponse
    {
        try {
            $user = Auth::user();
            $dashboard = $this->customizationService->getUserCustomizableDashboard($user);

            return response()->json([
                'success' => true,
                'data' => $dashboard
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get customizable dashboard', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load customizable dashboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Add widget to dashboard
     */
    public function addWidget(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'widget_id' => 'required|string|exists:dashboard_widgets,id',
                'config' => 'sometimes|array',
                'config.title' => 'sometimes|string|max:255',
                'config.size' => 'sometimes|string|in:small,medium,large,extra-large',
                'config.position' => 'sometimes|array',
                'config.position.x' => 'sometimes|integer|min:0',
                'config.position.y' => 'sometimes|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $result = $this->customizationService->addWidgetToDashboard(
                $user,
                $request->widget_id,
                $request->config ?? []
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to add widget to dashboard', [
                'user_id' => Auth::id(),
                'widget_id' => $request->widget_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add widget to dashboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove widget from dashboard
     */
    public function removeWidget(Request $request, string $widgetInstanceId): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->customizationService->removeWidgetFromDashboard($user, $widgetInstanceId);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to remove widget from dashboard', [
                'user_id' => Auth::id(),
                'widget_instance_id' => $widgetInstanceId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove widget from dashboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update widget configuration
     */
    public function updateWidgetConfig(Request $request, string $widgetInstanceId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'config' => 'required|array',
                'config.title' => 'sometimes|string|max:255',
                'config.size' => 'sometimes|string|in:small,medium,large,extra-large',
                'config.position' => 'sometimes|array',
                'config.position.x' => 'sometimes|integer|min:0',
                'config.position.y' => 'sometimes|integer|min:0',
                'config.refresh_interval' => 'sometimes|integer|min:30|max:3600',
                'config.show_title' => 'sometimes|boolean',
                'config.show_borders' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $result = $this->customizationService->updateWidgetConfig(
                $user,
                $widgetInstanceId,
                $request->config
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to update widget config', [
                'user_id' => Auth::id(),
                'widget_instance_id' => $widgetInstanceId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update widget configuration',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update dashboard layout (drag & drop)
     */
    public function updateLayout(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'layout' => 'array',
                'layout.*.id' => 'sometimes|string',
                'layout.*.position' => 'sometimes|array',
                'layout.*.position.x' => 'sometimes|integer|min:0',
                'layout.*.position.y' => 'sometimes|integer|min:0',
                'layout.*.size' => 'sometimes|string|in:small,medium,large,extra-large'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $result = $this->customizationService->updateDashboardLayout($user, $request->layout);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to update dashboard layout', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update dashboard layout',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Apply layout template
     */
    public function applyTemplate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'template_id' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $result = $this->customizationService->applyLayoutTemplate($user, $request->template_id);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to apply layout template', [
                'user_id' => Auth::id(),
                'template_id' => $request->template_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to apply layout template',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Save user preferences
     */
    public function savePreferences(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'preferences' => 'required|array',
                'preferences.theme' => 'sometimes|string|in:light,dark,auto',
                'preferences.refresh_interval' => 'sometimes|integer|min:30|max:3600',
                'preferences.compact_mode' => 'sometimes|boolean',
                'preferences.show_widget_borders' => 'sometimes|boolean',
                'preferences.enable_animations' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $result = $this->customizationService->saveUserPreferences($user, $request->preferences);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to save user preferences', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save user preferences',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Reset dashboard to default
     */
    public function resetDashboard(): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->customizationService->resetDashboardToDefault($user);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to reset dashboard', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset dashboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available widgets for user
     */
    public function getAvailableWidgets(): JsonResponse
    {
        try {
            $user = Auth::user();
            $widgets = $this->customizationService->getAvailableWidgetsForUser($user);
            $categories = $this->customizationService->getWidgetCategories();

            return response()->json([
                'success' => true,
                'data' => [
                    'widgets' => $widgets,
                    'categories' => $categories
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get available widgets', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load available widgets',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get layout templates
     */
    public function getLayoutTemplates(): JsonResponse
    {
        try {
            $user = Auth::user();
            $templates = $this->customizationService->getLayoutTemplates();
            $permissions = $this->customizationService->getCustomizationPermissions($user);

            // Filter templates based on user permissions
            $availableTemplates = array_filter($templates, function ($template) use ($permissions) {
                return in_array($template['permission'], $permissions);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'templates' => array_values($availableTemplates),
                    'permissions' => $permissions
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get layout templates', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load layout templates',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get customization options
     */
    public function getCustomizationOptions(): JsonResponse
    {
        try {
            $user = Auth::user();
            $options = $this->customizationService->getCustomizationOptions($user);

            return response()->json([
                'success' => true,
                'data' => $options
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get customization options', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load customization options',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Duplicate widget
     */
    public function duplicateWidget(Request $request, string $widgetInstanceId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'config' => 'sometimes|array',
                'config.title' => 'sometimes|string|max:255',
                'config.position' => 'sometimes|array',
                'config.position.x' => 'sometimes|integer|min:0',
                'config.position.y' => 'sometimes|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            
            // Get current dashboard to find widget
            $dashboard = $this->customizationService->getUserCustomizableDashboard($user);
            $currentWidget = null;
            
            foreach ($dashboard['dashboard']['layout'] as $widget) {
                if ($widget['id'] === $widgetInstanceId) {
                    $currentWidget = $widget;
                    break;
                }
            }

            if (!$currentWidget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Widget instance not found'
                ], 404);
            }

            // Add duplicated widget
            $duplicateConfig = array_merge($currentWidget['config'], $request->config ?? []);
            if (isset($duplicateConfig['title'])) {
                $duplicateConfig['title'] = $duplicateConfig['title'] . ' (Copy)';
            }

            $result = $this->customizationService->addWidgetToDashboard(
                $user,
                $currentWidget['widget_id'],
                $duplicateConfig
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to duplicate widget', [
                'user_id' => Auth::id(),
                'widget_instance_id' => $widgetInstanceId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate widget',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Export dashboard configuration
     */
    public function exportDashboard(): JsonResponse
    {
        try {
            $user = Auth::user();
            $dashboard = $this->customizationService->getUserCustomizableDashboard($user);
            $dashboardModel = $dashboard['dashboard'];
            $dashboardData = $dashboardModel->toArray();

            $dashboardLayout = $dashboardData['layout'] ?? [];
            if (empty($dashboardLayout)) {
                $dashboardLayout = $this->customizationService->getLayoutTemplateForRole($user->role);
            }

            $exportData = [
                'version' => '1.0',
                'exported_at' => now()->toISOString(),
                'user_role' => $user->role,
                'dashboard' => [
                    'name' => $dashboardData['name'],
                    'layout' => $dashboardLayout,
                    'preferences' => $dashboardData['preferences'] ?? []
                ],
                'widgets' => $dashboard['available_widgets']
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to export dashboard', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export dashboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Import dashboard configuration
     */
    public function importDashboard(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'dashboard_config' => 'required|array',
                'dashboard_config.version' => 'required|string',
                'dashboard_config.dashboard' => 'required|array',
                'dashboard_config.dashboard.layout' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $config = $request->dashboard_config;

            // Validate version compatibility
            if ($config['version'] !== '1.0') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported dashboard configuration version'
                ], 400);
            }

            // Apply imported layout
            $result = $this->customizationService->updateDashboardLayout($user, $config['dashboard']['layout']);

            // Apply imported preferences if available
            if (isset($config['dashboard']['preferences'])) {
                $this->customizationService->saveUserPreferences($user, $config['dashboard']['preferences']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Dashboard configuration imported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to import dashboard', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import dashboard configuration',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
