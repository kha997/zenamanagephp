<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseApiController;
use App\Models\DashboardWidget;
use App\Models\UserDashboard;
use App\Models\DashboardAlert;
use App\Models\DashboardMetric;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard API Controller
 * 
 * Quản lý API endpoints cho dashboard system
 */
class DashboardController extends BaseApiController
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Lấy dashboard của user hiện tại
     */
    public function getUserDashboard(): JsonResponse
    {
        try {
            $user = Auth::user();
            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            
            return $this->successResponse($dashboard, 'Dashboard retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dashboard: ' . $e->getMessage());
        }
    }

    /**
     * Lấy danh sách widgets có sẵn cho role của user
     */
    public function getAvailableWidgets(): JsonResponse
    {
        try {
            $user = Auth::user();
            $widgets = $this->dashboardService->getAvailableWidgetsForUser($user);
            
            return $this->successResponse($widgets, 'Available widgets retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve widgets: ' . $e->getMessage());
        }
    }

    /**
     * Lấy dữ liệu cho widget cụ thể
     */
    public function getWidgetData(Request $request, string $widgetId): JsonResponse
    {
        try {
            $user = Auth::user();
            $projectId = $request->get('project_id');
            $params = $request->except(['project_id']);
            
            $data = $this->dashboardService->getWidgetData($widgetId, $user, $projectId, $params);
            
            return $this->successResponse($data, 'Widget data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve widget data: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật layout của dashboard
     */
    public function updateDashboardLayout(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $layoutConfig = $request->get('layout_config', []);
            $widgets = $request->get('widgets', []);
            
            $dashboard = $this->dashboardService->updateDashboardLayout($user->id, $layoutConfig, $widgets);
            
            return $this->successResponse($dashboard, 'Dashboard layout updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update dashboard layout: ' . $e->getMessage());
        }
    }

    /**
     * Thêm widget vào dashboard
     */
    public function addWidget(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $widgetId = $request->get('widget_id');
            $position = $request->get('position', []);
            $config = $request->get('config', []);
            
            $dashboard = $this->dashboardService->addWidgetToDashboard($user->id, $widgetId, $position, $config);
            
            return $this->successResponse($dashboard, 'Widget added to dashboard successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to add widget: ' . $e->getMessage());
        }
    }

    /**
     * Xóa widget khỏi dashboard
     */
    public function removeWidget(Request $request, string $widgetId): JsonResponse
    {
        try {
            $user = Auth::user();
            $dashboard = $this->dashboardService->removeWidgetFromDashboard($user->id, $widgetId);
            
            return $this->successResponse($dashboard, 'Widget removed from dashboard successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to remove widget: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật cấu hình widget
     */
    public function updateWidgetConfig(Request $request, string $widgetId): JsonResponse
    {
        try {
            $user = Auth::user();
            $config = $request->get('config', []);
            
            $dashboard = $this->dashboardService->updateWidgetConfig($user->id, $widgetId, $config);
            
            return $this->successResponse($dashboard, 'Widget config updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update widget config: ' . $e->getMessage());
        }
    }

    /**
     * Lấy alerts của user
     */
    public function getUserAlerts(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $projectId = $request->get('project_id');
            $type = $request->get('type');
            $category = $request->get('category');
            $unreadOnly = $request->get('unread_only', false);
            
            $alerts = $this->dashboardService->getUserAlerts($user->id, $projectId, $type, $category, $unreadOnly);
            
            return $this->successResponse($alerts, 'Alerts retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve alerts: ' . $e->getMessage());
        }
    }

    /**
     * Đánh dấu alert đã đọc
     */
    public function markAlertAsRead(string $alertId): JsonResponse
    {
        try {
            $user = Auth::user();
            $alert = $this->dashboardService->markAlertAsRead($alertId, $user->id);
            
            return $this->successResponse($alert, 'Alert marked as read successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to mark alert as read: ' . $e->getMessage());
        }
    }

    /**
     * Đánh dấu tất cả alerts đã đọc
     */
    public function markAllAlertsAsRead(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $projectId = $request->get('project_id');
            
            $count = $this->dashboardService->markAllAlertsAsRead($user->id, $projectId);
            
            return $this->successResponse(['count' => $count], 'All alerts marked as read successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to mark all alerts as read: ' . $e->getMessage());
        }
    }

    /**
     * Lấy metrics cho dashboard
     */
    public function getDashboardMetrics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $projectId = $request->get('project_id');
            $category = $request->get('category');
            $timeRange = $request->get('time_range', '7d');
            
            $metrics = $this->dashboardService->getDashboardMetrics($user, $projectId, $category, $timeRange);
            
            return $this->successResponse($metrics, 'Dashboard metrics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dashboard metrics: ' . $e->getMessage());
        }
    }

    /**
     * Lấy dashboard template cho role
     */
    public function getDashboardTemplate(): JsonResponse
    {
        try {
            $user = Auth::user();
            $template = $this->dashboardService->getDashboardTemplateForRole($user);
            
            return $this->successResponse($template, 'Dashboard template retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dashboard template: ' . $e->getMessage());
        }
    }

    /**
     * Reset dashboard về template mặc định
     */
    public function resetDashboard(): JsonResponse
    {
        try {
            $user = Auth::user();
            $dashboard = $this->dashboardService->resetDashboardToDefault($user->id);
            
            return $this->successResponse($dashboard, 'Dashboard reset to default successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reset dashboard: ' . $e->getMessage());
        }
    }

    /**
     * Lưu preferences của user
     */
    public function saveUserPreferences(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $preferences = $request->get('preferences', []);
            
            $dashboard = $this->dashboardService->saveUserPreferences($user->id, $preferences);
            
            return $this->successResponse($dashboard, 'User preferences saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to save user preferences: ' . $e->getMessage());
        }
    }

    /**
     * Lấy thống kê dashboard cho user hiện tại
     */
    public function getStats(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;
            
            // Import các model cần thiết
            $projectModel = app(\App\Models\Project::class);
            $taskModel = app(\App\Models\Task::class);
            $userModel = app(\App\Models\User::class);
            
            // Lấy thống kê projects
            $projects = $projectModel->where('tenant_id', $tenantId);
            $projectsTotal = $projects->count();
            $projectsActive = $projects->where('status', 'active')->count();
            $projectsCompleted = $projects->where('status', 'completed')->count();
            $projectsOverdue = $projects->where('end_date', '<', now())
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();
            
            // Lấy thống kê tasks
            $tasks = $taskModel->where('tenant_id', $tenantId);
            $tasksTotal = $tasks->count();
            $tasksPending = $tasks->where('status', 'pending')->count();
            $tasksInProgress = $tasks->where('status', 'in_progress')->count();
            $tasksCompleted = $tasks->where('status', 'completed')->count();
            $tasksOverdue = $tasks->where('due_date', '<', now())
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();
            
            // Lấy thống kê users
            $users = $userModel->where('tenant_id', $tenantId);
            $usersTotal = $users->count();
            $usersActive = $users->where('is_active', true)->count();
            $usersInactive = $users->where('is_active', false)->count();
            
            $stats = [
                'projects' => [
                    'total' => $projectsTotal,
                    'active' => $projectsActive,
                    'completed' => $projectsCompleted,
                    'overdue' => $projectsOverdue,
                ],
                'tasks' => [
                    'total' => $tasksTotal,
                    'pending' => $tasksPending,
                    'in_progress' => $tasksInProgress,
                    'completed' => $tasksCompleted,
                    'overdue' => $tasksOverdue,
                ],
                'users' => [
                    'total' => $usersTotal,
                    'active' => $usersActive,
                    'inactive' => $usersInactive,
                ],
            ];
            
            return $this->successResponse($stats, 'Dashboard statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dashboard statistics: ' . $e->getMessage());
        }
    }
}
