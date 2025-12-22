<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Models\Dashboard;
use App\Models\DashboardAlert;
use App\Models\DashboardWidget;
use App\Models\UserDashboard;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard Controller
 * 
 * Handles dashboard-related API endpoints
 */
class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }
    /**
     * Display a listing of dashboards
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $dashboards = Dashboard::where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $dashboards
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch dashboards']
            ], 500);
        }
    }

    /**
     * Store a new dashboard
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'layout' => 'nullable|string',
                'is_public' => 'boolean'
            ]);

            $dashboard = Dashboard::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'layout' => $validated['layout'] ?? 'grid',
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'is_default' => false
            ]);

            return response()->json([
                'id' => $dashboard->id,
                'name' => $dashboard->name,
                'description' => $dashboard->description,
                'layout' => $dashboard->layout,
                'is_public' => false, // Default value
                'created_at' => $dashboard->created_at,
                'updated_at' => $dashboard->updated_at
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Validation failed'],
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Dashboard creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to create dashboard']
            ], 500);
        }
    }

    /**
     * Display the specified dashboard
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $dashboard = Dashboard::find($id);

            if (!$dashboard) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Dashboard not found']
                ], 404);
            }

            // Check if user owns this dashboard
            if ($dashboard->user_id !== (string)$user->id || $dashboard->tenant_id !== (string)$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Access denied']
                ], 403);
            }

            Log::info('Dashboard show request', [
                'dashboard_id' => $dashboard->id,
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'dashboard_user_id' => $dashboard->user_id,
                'dashboard_tenant_id' => $dashboard->tenant_id,
                'user_tenant_match' => $dashboard->tenant_id === $user->tenant_id,
                'user_id_match' => $dashboard->user_id === $user->id
            ]);

            return response()->json([
                'id' => $dashboard->id,
                'name' => $dashboard->name,
                'description' => $dashboard->description,
                'layout' => $dashboard->layout,
                'is_public' => false,
                'created_at' => $dashboard->created_at,
                'updated_at' => $dashboard->updated_at
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch dashboard']
            ], 500);
        }
    }

    /**
     * Update the specified dashboard
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $dashboard = Dashboard::find($id);

            if (!$dashboard) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Dashboard not found']
                ], 404);
            }

            // Check if user owns this dashboard
            if ($dashboard->user_id !== (string)$user->id || $dashboard->tenant_id !== (string)$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Access denied']
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'layout' => 'nullable|string',
                'is_public' => 'boolean'
            ]);

            $dashboard->update($validated);

            return response()->json([
                'id' => $dashboard->id,
                'name' => $dashboard->name,
                'description' => $dashboard->description,
                'layout' => $dashboard->layout,
                'is_public' => false,
                'created_at' => $dashboard->created_at,
                'updated_at' => $dashboard->updated_at
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Validation failed'],
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Dashboard update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update dashboard']
            ], 500);
        }
    }

    /**
     * Remove the specified dashboard
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $dashboard = Dashboard::find($id);

            if (!$dashboard) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Dashboard not found']
                ], 404);
            }

            // Check if user owns this dashboard
            if ($dashboard->user_id !== (string)$user->id || $dashboard->tenant_id !== (string)$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Access denied']
                ], 403);
            }

            $dashboard->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            Log::error('Dashboard delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to delete dashboard']
            ], 500);
        }
    }

    /**
     * Get dashboard data
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $data = $this->dashboardService->getDashboardData($user->id, $user->tenant_id);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard Data Error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->check() ? auth()->id() : null,
                'tenant_id' => auth()->check() ? auth()->user()->tenant_id : null
            ]);

            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to load dashboard data']
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $stats = $this->dashboardService->getStats($user->tenant_id);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch dashboard statistics']
            ], 500);
        }
    }

    /**
     * Get recent projects
     */
    public function getRecentProjects(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $limit = $request->get('limit', 5);
            $projects = $this->dashboardService->getRecentProjects($user->tenant_id, $limit);

            return response()->json([
                'success' => true,
                'data' => $projects
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard recent projects error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch recent projects']
            ], 500);
        }
    }

    /**
     * Get recent tasks
     */
    public function getRecentTasks(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $limit = $request->get('limit', 10);
            $tasks = $this->dashboardService->getRecentTasks($user->tenant_id, $limit);

            return response()->json([
                'success' => true,
                'data' => $tasks
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard recent tasks error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch recent tasks']
            ], 500);
        }
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $limit = $request->get('limit', 20);
            $activities = $this->dashboardService->getRecentActivities($user->tenant_id, $limit);

            return response()->json([
                'success' => true,
                'data' => $activities
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard recent activity error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch recent activity']
            ], 500);
        }
    }

    /**
     * Get dashboard metrics
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $metrics = $this->dashboardService->getMetrics($user->tenant_id);

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard metrics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch dashboard metrics']
            ], 500);
        }
    }

    /**
     * Get user dashboard
     */
    public function getUserDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            
            if (!$dashboard) {
                // Create default dashboard if none exists
                $dashboard = \App\Models\UserDashboard::create([
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'name' => 'My Dashboard',
                    'layout_config' => ['columns' => 3],
                    'widgets' => [],
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $dashboard->id,
                    'name' => $dashboard->name,
                    'layout' => $dashboard->layout_config, // Changed from layout_config to layout
                    'widgets' => $dashboard->widgets,
                    'preferences' => $dashboard->preferences ?? [],
                    'is_default' => $dashboard->is_default,
                    'created_at' => $dashboard->created_at->toISOString(),
                    'updated_at' => $dashboard->updated_at->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get user dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch user dashboard']
            ], 500);
        }
    }

    /**
     * Get available widgets
     */
    public function getAvailableWidgets(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $widgets = \App\Models\DashboardWidget::where('is_active', true)->get();

            return response()->json([
                'success' => true,
                'data' => $widgets->map(function ($widget) {
                    return [
                        'id' => $widget->id,
                        'name' => $widget->name,
                        'type' => $widget->type,
                        'category' => $widget->category,
                        'description' => $widget->description,
                        'config' => $widget->config,
                        'permissions' => $widget->permissions,
                        'is_active' => $widget->is_active
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Get available widgets error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch available widgets']
            ], 500);
        }
    }

    /**
     * Get available widgets for customization
     */
    public function getAvailableWidgetsForCustomization(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $widgets = \App\Models\DashboardWidget::where('is_active', true)->get();
            $categories = $widgets->pluck('category')->unique()->values()->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'widgets' => $widgets->map(function ($widget) {
                        return [
                            'id' => $widget->id,
                            'name' => $widget->name,
                            'type' => $widget->type,
                            'category' => $widget->category,
                            'description' => $widget->description,
                            'config' => $widget->config,
                            'permissions' => $widget->permissions,
                            'is_active' => $widget->is_active
                        ];
                    }),
                    'categories' => $categories
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get available widgets for customization error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch available widgets for customization']
            ], 500);
        }
    }

    /**
     * Get widget data
     */
    public function getWidgetData(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $widget = \App\Models\DashboardWidget::find($id);
            if (!$widget) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Widget not found']
                ], 404);
            }

            // Mock widget data based on type
            $data = match ($widget->type) {
                'chart' => [
                    'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
                    'datasets' => [
                        [
                            'label' => 'Projects',
                            'data' => [12, 19, 3, 5, 2],
                            'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                            'borderColor' => 'rgba(54, 162, 235, 1)',
                            'borderWidth' => 1
                        ]
                    ]
                ],
                'metric' => [
                    'value' => 42,
                    'unit' => 'projects',
                    'trend' => '+12%',
                    'trend_direction' => 'up'
                ],
                'table' => [
                    'headers' => ['Name', 'Status', 'Progress'],
                    'rows' => [
                        ['Project A', 'Active', '75%'],
                        ['Project B', 'Completed', '100%'],
                        ['Project C', 'Planning', '25%']
                    ]
                ],
                default => ['message' => 'No data available']
            };

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Get widget data error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch widget data']
            ], 500);
        }
    }

    /**
     * Add widget to dashboard
     */
    public function addWidget(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $request->validate([
                'widget_id' => 'required|exists:dashboard_widgets,id',
                'position' => 'nullable|array',
                'size' => 'nullable'
            ]);

            // Check widget permissions
            $widget = \App\Models\DashboardWidget::find($request->widget_id);
            if (!$widget || !$widget->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Widget not found or inactive'
                ], 500);
            }

            // Check user permissions for widget
            $userPermissions = $this->getUserPermissions($user);
            if ($user->role === 'qc_inspector' && !in_array('view-projects-overview', $userPermissions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to add this widget'
                ], 500);
            }

            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            if (!$dashboard) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Dashboard not found']
                ], 404);
            }

            $widgets = $dashboard->widgets ?? [];
            $widgets[] = [
                'id' => $request->widget_id,
                'position' => $request->position,
                'size' => $request->size,
                'config' => $request->config ?? []
            ];

            $dashboard->update(['widgets' => $widgets]);

            // Get widget details
            $widget = \App\Models\DashboardWidget::find($request->widget_id);

            return response()->json([
                'success' => true,
                'widget_instance' => [
                    'id' => $request->widget_id,
                    'widget_id' => $request->widget_id,
                    'type' => $widget->type ?? 'metric',
                    'title' => $request->config['title'] ?? $widget->name ?? 'Widget',
                    'size' => $request->size,
                    'position' => $request->position,
                    'config' => $request->config ?? [],
                    'is_customizable' => true,
                    'created_at' => now()->toISOString()
                ],
                'message' => 'Widget added successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Add widget error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add widget'
            ], 500);
        }
    }

    /**
     * Remove widget from dashboard
     */
    public function removeWidget(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            if (!$dashboard) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Dashboard not found']
                ], 404);
            }

            $widgets = collect($dashboard->widgets ?? [])->filter(function ($widget) use ($id) {
                return $widget['id'] != $id;
            })->values()->toArray();

            $dashboard->update(['widgets' => $widgets]);

            return response()->json([
                'success' => true,
                'message' => 'Widget removed successfully',
                'data' => [
                    'widgets' => $widgets
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Remove widget error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to remove widget']
            ], 500);
        }
    }

    /**
     * Update widget configuration
     */
    public function updateWidgetConfig(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            if (!$dashboard) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Dashboard not found']
                ], 404);
            }

            $widgets = collect($dashboard->widgets ?? [])->map(function ($widget) use ($id, $request) {
                if ($widget['id'] == $id) {
                    $widget['config'] = array_merge($widget['config'] ?? [], $request->config ?? []);
                    if ($request->has('position')) {
                        $widget['position'] = $request->position;
                    }
                    if ($request->has('size')) {
                        $widget['size'] = $request->size;
                    }
                }
                return $widget;
            })->toArray();

            $dashboard->update(['widgets' => $widgets]);

            return response()->json([
                'success' => true,
                'message' => 'Widget configuration updated successfully',
                'data' => [
                    'widgets' => $widgets
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Update widget config error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update widget configuration']
            ], 500);
        }
    }

    /**
     * Update dashboard layout
     */
    public function updateLayout(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $request->validate([
                'layout' => 'nullable|array',
                'layout_config' => 'nullable|array'
            ]);

            $layoutData = $request->layout ?? $request->layout_config;
            if (!$layoutData) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Layout data is required']
                ], 422);
            }

            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            if (!$dashboard) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Dashboard not found']
                ], 404);
            }

            $dashboard->update(['layout_config' => $layoutData]);

            return response()->json([
                'success' => true,
                'layout' => $layoutData,
                'message' => 'Layout updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Update layout error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update layout']
            ], 500);
        }
    }

    /**
     * Get user alerts
     */
    public function getUserAlerts(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $alerts = \App\Models\DashboardAlert::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $alerts->map(function ($alert) {
                    return [
                        'id' => $alert->id,
                        'type' => $alert->type,
                        'severity' => $alert->type === 'error' ? 'high' : ($alert->type === 'warning' ? 'medium' : 'low'),
                        'message' => $alert->message,
                        'is_read' => $alert->is_read,
                        'triggered_at' => $alert->created_at->toISOString(),
                        'context' => $alert->metadata ?? []
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Get user alerts error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch user alerts']
            ], 500);
        }
    }

    /**
     * Mark alert as read
     */
    public function markAlertAsRead(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $alert = \App\Models\DashboardAlert::where('id', $id)
                ->where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$alert) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Alert not found']
                ], 404);
            }

            $alert->update([
                'is_read' => true,
                'read_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Alert marked as read',
                'data' => [
                    'alert' => [
                        'id' => $alert->id,
                        'is_read' => $alert->is_read,
                        'read_at' => $alert->read_at->toISOString()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Mark alert as read error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to mark alert as read']
            ], 500);
        }
    }

    /**
     * Mark all alerts as read
     */
    public function markAllAlertsAsRead(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $updated = \App\Models\DashboardAlert::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => "Marked {$updated} alerts as read",
                'data' => [
                    'updated_count' => $updated
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Mark all alerts as read error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to mark all alerts as read']
            ], 500);
        }
    }

    /**
     * Save user preferences
     */
    public function saveUserPreferences(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $request->validate([
                'preferences' => 'required|array'
            ]);

            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            if (!$dashboard) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Dashboard not found']
                ], 404);
            }

            $dashboard->update(['preferences' => $request->preferences]);

            return response()->json([
                'success' => true,
                'preferences' => $request->preferences,
                'message' => 'Preferences saved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Save user preferences error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to save user preferences']
            ], 500);
        }
    }

    /**
     * Reset dashboard to default
     */
    public function resetToDefault(Request $request): JsonResponse
    {
        Log::info('resetToDefault called');
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            if (!$dashboard) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Dashboard not found']
                ], 404);
            }

            $defaultLayout = ['columns' => 3];
            $dashboard->update([
                'layout_config' => $defaultLayout,
                'widgets' => [],
                'preferences' => []
            ]);

            return response()->json([
                'success' => true,
                'layout' => $defaultLayout,
                'message' => 'Dashboard reset to default successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Reset dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to reset dashboard']
            ], 500);
        }
    }

    /**
     * Apply layout template
     */
    public function applyLayoutTemplate(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $request->validate([
                'template_id' => 'required|string'
            ]);

            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            if (!$dashboard) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Dashboard not found']
                ], 404);
            }

            // Apply template logic here
            $templateConfig = $this->getTemplateConfig($request->template_id);
            if (!$templateConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid template ID'
                ], 500);
            }
            $dashboard->update(['layout_config' => $templateConfig]);

            return response()->json([
                'success' => true,
                'layout' => $templateConfig,
                'message' => 'Layout template applied successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Apply layout template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply layout template'
            ], 500);
        }
    }

    /**
     * Import dashboard
     */
    public function importDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $request->validate([
                'dashboard_config' => 'required|array',
                'dashboard_config.version' => 'required|string',
                'dashboard_config.dashboard' => 'required|array',
                'dashboard_config.widgets' => 'array'
            ]);

            $config = $request->dashboard_config;
            
            // Validate version compatibility
            if ($config['version'] !== '1.0') {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Unsupported dashboard version'],
                    'errors' => ['version' => ['Unsupported version']]
                ], 422);
            }

            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            if (!$dashboard) {
                $dashboard = \App\Models\UserDashboard::create([
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'name' => $config['dashboard']['name'] ?? 'Imported Dashboard',
                    'layout_config' => $config['dashboard']['layout'] ?? ['columns' => 3],
                    'widgets' => $config['widgets'] ?? [],
                    'preferences' => $config['dashboard']['preferences'] ?? [],
                    'is_default' => false,
                    'is_active' => true,
                ]);
            } else {
                $dashboard->update([
                    'name' => $config['dashboard']['name'] ?? $dashboard->name,
                    'layout_config' => $config['dashboard']['layout'] ?? $dashboard->layout_config,
                    'widgets' => $config['widgets'] ?? $dashboard->widgets,
                    'preferences' => $config['dashboard']['preferences'] ?? $dashboard->preferences,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Dashboard imported successfully',
                'data' => [
                    'dashboard' => [
                        'id' => $dashboard->id,
                        'name' => $dashboard->name,
                        'layout' => $dashboard->layout_config,
                        'widgets' => $dashboard->widgets,
                        'preferences' => $dashboard->preferences
                    ],
                    'imported_at' => now()->toISOString()
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Import dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to import dashboard'
            ], 500);
        }
    }

    /**
     * Switch project context
     */
    public function switchProject(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $request->validate([
                'project_id' => 'required|string'
            ]);

            // Validate project access
            $project = \App\Models\Project::where('id', $request->project_id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found or access denied',
                    'errors' => ['project_id' => ['Project not found or access denied']]
                ], 422);
            }

            // Check if user has permission to access this project
            if ($user->role === 'client' && $project->owner_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to project'
                ], 403);
            }

            // Check if user has permission to access this project (client_rep role)
            if ($user->role === 'client_rep' && $project->owner_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to project'
                ], 403);
            }

            // Update user's current project context
            $user->update(['current_project_id' => $request->project_id]);

            // Get user's dashboard
            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            if (!$dashboard) {
                $dashboard = \App\Models\UserDashboard::create([
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'name' => 'My Dashboard',
                    'layout_config' => ['columns' => 3],
                    'widgets' => [],
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Project context switched successfully',
                'data' => [
                    'dashboard' => [
                        'id' => $dashboard->id,
                        'name' => $dashboard->name,
                        'layout' => $dashboard->layout_config,
                        'widgets' => $dashboard->widgets
                    ],
                    'project_context' => [
                        'current_project_id' => $request->project_id,
                        'project_name' => $project->name,
                        'total_projects' => \App\Models\Project::where('tenant_id', $user->tenant_id)->count()
                    ],
                    'switched_at' => now()->toISOString()
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Switch project error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to switch project context'
            ], 500);
        }
    }

    /**
     * Export dashboard
     */
    public function exportDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            if (!$dashboard) {
                // Create default dashboard if none exists
                $dashboard = \App\Models\UserDashboard::create([
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'name' => 'My Dashboard',
                    'layout_config' => ['columns' => 3],
                    'widgets' => [],
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'version' => '1.0',
                    'exported_at' => now()->toISOString(),
                    'user_role' => $user->role ?? 'member',
                    'dashboard' => [
                        'id' => $dashboard->id,
                        'name' => $dashboard->name,
                        'layout_config' => $dashboard->layout_config,
                        'widgets' => $dashboard->widgets,
                        'preferences' => $dashboard->preferences
                    ],
                    'widgets' => $dashboard->widgets ?? []
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Export dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to export dashboard']
            ], 500);
        }
    }

    /**
     * Get layout templates
     */
    public function getLayoutTemplates(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $templates = [
                'default' => ['name' => 'Default', 'columns' => 3, 'rows' => 2],
                'wide' => ['name' => 'Wide', 'columns' => 4, 'rows' => 2],
                'compact' => ['name' => 'Compact', 'columns' => 2, 'rows' => 3],
                'executive' => ['name' => 'Executive', 'columns' => 3, 'rows' => 1]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'templates' => $templates,
                    'permissions' => $this->getUserPermissions($user)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get layout templates error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to get layout templates']
            ], 500);
        }
    }

    /**
     * Get customization options
     */
    public function getCustomizationOptions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'widget_sizes' => ['small', 'medium', 'large', 'extra-large'],
                    'layout_grid' => [
                        'columns' => [2, 3, 4, 6],
                        'rows' => [1, 2, 3, 4]
                    ],
                    'themes' => ['light', 'dark', 'auto'],
                    'refresh_intervals' => [30, 60, 300, 600],
                    'permissions' => $this->getUserPermissions($user)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get customization options error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to get customization options']
            ], 500);
        }
    }

    /**
     * Get role-based dashboard
     */
    public function getRoleBasedDashboard(Request $request): JsonResponse
    {
        Log::info('getRoleBasedDashboard called');
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            if (!$dashboard) {
                $dashboard = UserDashboard::create([
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'name' => 'My Dashboard',
                    'layout_config' => ['columns' => 3],
                    'widgets' => [],
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }

            $widgets = DashboardWidget::where('is_active', true)->get();
            $roleWidgets = $widgets->filter(function ($widget) use ($user) {
                $permissions = $widget->permissions ?? [];
                if (is_string($permissions)) {
                    $permissions = json_decode($permissions, true) ?? [];
                }
                return empty($permissions) || $this->userHasPermissions($user, $permissions);
            });

            $metrics = $this->dashboardService->getDashboardMetrics($user->tenant_id);
            $alerts = DashboardAlert::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->where('is_read', false)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'dashboard' => [
                        'id' => $dashboard->id,
                        'name' => $dashboard->name,
                        'layout' => $dashboard->layout_config,
                        'widgets' => $dashboard->widgets,
                        'preferences' => $dashboard->preferences ?? []
                    ],
                    'widgets' => $roleWidgets->map(function ($widget) {
                        return [
                            'id' => $widget->id,
                            'name' => $widget->name,
                            'type' => $widget->type,
                            'description' => $widget->description,
                            'is_active' => $widget->is_active
                        ];
                    }),
                    'metrics' => $metrics,
                    'alerts' => $alerts->map(function ($alert) {
                        return [
                            'id' => $alert->id,
                            'type' => $alert->type,
                            'message' => $alert->message,
                            'created_at' => $alert->created_at->toISOString()
                        ];
                    }),
                    'permissions' => $this->getUserPermissions($user),
                    'role_config' => [
                        'role' => $user->role ?? 'member',
                        'permissions' => $this->getUserPermissions($user),
                        'access_level' => $this->getUserPermissions($user)['access_level'] ?? 'standard'
                    ],
                    'project_context' => [
                        'current_project_id' => $user->current_project_id,
                        'total_projects' => \App\Models\Project::where('tenant_id', $user->tenant_id)->count()
                    ]
                ],
                'meta' => [
                    'user_role' => $user->role ?? 'member',
                    'project_context' => [
                        'current_project_id' => $user->current_project_id,
                        'total_projects' => \App\Models\Project::where('tenant_id', $user->tenant_id)->count()
                    ],
                    'generated_at' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get role-based dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to get role-based dashboard']
            ], 500);
        }
    }

    /**
     * Get role-based widgets
     */
    public function getRoleBasedWidgets(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $widgets = DashboardWidget::where('is_active', true)->get();
            $roleWidgets = $widgets->filter(function ($widget) use ($user) {
                $permissions = $widget->permissions ?? [];
                // Ensure permissions is an array
                if (is_string($permissions)) {
                    $permissions = json_decode($permissions, true) ?? [];
                }
                return empty($permissions) || $this->userHasPermissions($user, $permissions);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'widgets' => $roleWidgets->map(function ($widget) {
                        return [
                            'id' => $widget->id,
                            'name' => $widget->name,
                            'type' => $widget->type,
                            'description' => $widget->description,
                            'is_active' => $widget->is_active
                        ];
                    }),
                    'role_config' => [
                        'user_role' => $user->role ?? 'member',
                        'permissions' => $this->getUserPermissions($user),
                        'access_level' => $this->getUserPermissions($user)['access_level'] ?? 'standard'
                    ],
                    'total_count' => $roleWidgets->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get role-based widgets error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to get role-based widgets']
            ], 500);
        }
    }

    /**
     * Get role-based metrics
     */
    public function getRoleBasedMetrics(Request $request): JsonResponse
    {
        Log::info('getRoleBasedMetrics called');
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $metrics = $this->dashboardService->getDashboardMetrics($user->tenant_id);
            
            // Filter metrics based on user role
            $roleMetrics = $this->filterMetricsByRole($metrics, $user->role ?? 'member');

            return response()->json([
                'success' => true,
                'data' => [
                    'metrics' => $roleMetrics,
                    'time_range' => 'last_30_days',
                    'total_count' => count($roleMetrics),
                    'role' => $user->role ?? 'member'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get role-based metrics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to get role-based metrics']
            ], 500);
        }
    }

    /**
     * Get role-based alerts
     */
    public function getRoleBasedAlerts(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $alerts = \App\Models\DashboardAlert::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'alerts' => $alerts->map(function ($alert) {
                        return [
                            'id' => $alert->id,
                            'type' => $alert->type,
                            'message' => $alert->message,
                            'is_read' => $alert->is_read,
                            'created_at' => $alert->created_at->toISOString()
                        ];
                    }),
                    'total_count' => $alerts->count(),
                    'filters' => [
                        'status' => ['unread', 'read'],
                        'type' => ['info', 'warning', 'error', 'success']
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get role-based alerts error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to get role-based alerts']
            ], 500);
        }
    }

    /**
     * Get role-based permissions
     */
    public function getRoleBasedPermissions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $permissions = $this->getUserPermissions($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'permissions' => $permissions,
                    'user_role' => $user->role ?? 'member',
                    'role_name' => $user->role ?? 'member'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get role-based permissions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to get role-based permissions']
            ], 500);
        }
    }

    /**
     * Get role config
     */
    public function getRoleConfig(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $roleConfig = $this->getRoleBasedConfig($user->role ?? 'member');

            return response()->json([
                'success' => true,
                'data' => [
                    'role_config' => $roleConfig,
                    'user_role' => $user->role ?? 'member',
                    'tenant_id' => $user->tenant_id
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get role config error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to get role config']
            ], 500);
        }
    }

    /**
     * Get role-based projects
     */
    public function getRoleBasedProjects(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $projects = \App\Models\Project::where('tenant_id', $user->tenant_id)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'projects' => $projects->map(function ($project) {
                        try {
                            return [
                                'id' => $project->id,
                                'name' => $project->name,
                                'status' => $project->status,
                                'progress' => method_exists($project, 'calculateProgress') ? $project->calculateProgress() : 0.0
                            ];
                        } catch (\Exception $e) {
                            return [
                                'id' => $project->id,
                                'name' => $project->name,
                                'status' => $project->status,
                                'progress' => 0.0
                            ];
                        }
                    }),
                    'total_count' => $projects->count(),
                    'role_access' => $this->getUserPermissions($user)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get role-based projects error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to get role-based projects']
            ], 500);
        }
    }

    /**
     * Get role-based summary
     */
    public function getRoleBasedSummary(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $summary = [
                'user_info' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'member',
                    'permissions' => $this->getUserPermissions($user)
                ],
                'project_context' => [
                    'current_project_id' => $user->current_project_id,
                    'total_projects' => \App\Models\Project::where('tenant_id', $user->tenant_id)->count(),
                    'access_level' => $this->getUserPermissions($user)['access_level'] ?? 'standard'
                ],
                'generated_at' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            Log::error('Get role-based summary error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to get role-based summary']
            ], 500);
        }
    }

    /**
     * Get customizable dashboard
     */
    public function getCustomizableDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            if (!$dashboard) {
                $dashboard = \App\Models\UserDashboard::create([
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'name' => 'My Dashboard',
                    'layout_config' => ['columns' => 3],
                    'widgets' => [],
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }

            $availableWidgets = \App\Models\DashboardWidget::where('is_active', true)->get();
            $widgetCategories = $availableWidgets->pluck('category')->unique()->values()->toArray();
            $layoutTemplates = $this->getLayoutTemplates($request);
            $customizationOptions = $this->getCustomizationOptions($request);

            return response()->json([
                'success' => true,
                'data' => [
                    'dashboard' => [
                        'id' => $dashboard->id,
                        'name' => $dashboard->name,
                        'layout' => $dashboard->layout_config,
                        'widgets' => $dashboard->widgets,
                        'preferences' => $dashboard->preferences ?? []
                    ],
                    'available_widgets' => $availableWidgets->map(function ($widget) {
                        return [
                            'id' => $widget->id,
                            'name' => $widget->name,
                            'type' => $widget->type,
                            'category' => $widget->category,
                            'description' => $widget->description
                        ];
                    }),
                    'widget_categories' => $widgetCategories,
                    'layout_templates' => $layoutTemplates->getData(true)['data'] ?? [],
                    'customization_options' => $customizationOptions->getData(true)['data'] ?? [],
                    'permissions' => $this->getUserPermissions($user)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get customizable dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to get customizable dashboard']
            ], 500);
        }
    }

    /**
     * Helper methods
     */
    private function getUserPermissions($user): array
    {
        $role = $user->role ?? 'member';
        $permissions = [
            'super_admin' => ['*'],
            'admin' => ['view-all', 'edit-all', 'delete-all'],
            'pm' => ['view-projects', 'edit-projects', 'view-tasks', 'edit-tasks'],
            'member' => ['view-projects', 'view-tasks', 'edit-own-tasks'],
            'client' => ['view-projects', 'view-tasks']
        ];

        return $permissions[$role] ?? $permissions['member'];
    }

    private function getRoleBasedConfig(string $role): array
    {
        $configs = [
            'super_admin' => ['all_widgets' => true, 'all_projects' => true],
            'admin' => ['all_widgets' => true, 'all_projects' => true],
            'pm' => ['project_widgets' => true, 'assigned_projects' => true],
            'member' => ['basic_widgets' => true, 'assigned_projects' => true],
            'client' => ['readonly_widgets' => true, 'client_projects' => true]
        ];

        return $configs[$role] ?? $configs['member'];
    }

    private function filterMetricsByRole(array $metrics, string $role): array
    {
        // Filter metrics based on role permissions
        $roleFilters = [
            'super_admin' => $metrics,
            'admin' => $metrics,
            'pm' => array_intersect_key($metrics, array_flip(['project_completion_rate', 'task_completion_rate'])),
            'member' => array_intersect_key($metrics, array_flip(['task_completion_rate'])),
            'client' => array_intersect_key($metrics, array_flip(['project_completion_rate']))
        ];

        return $roleFilters[$role] ?? $roleFilters['member'];
    }

    private function userHasPermissions($user, array $requiredPermissions): bool
    {
        $userPermissions = $this->getUserPermissions($user);
        return !empty(array_intersect($userPermissions, $requiredPermissions)) || in_array('*', $userPermissions);
    }

    private function getProjectAccess($user): array
    {
        $role = $user->role ?? 'member';
        $access = [
            'super_admin' => 'all',
            'admin' => 'all',
            'pm' => 'assigned',
            'member' => 'assigned',
            'client' => 'client_only'
        ];

        return ['level' => $access[$role] ?? 'assigned'];
    }

    /**
     * Get template configuration
     */
    private function getTemplateConfig(string $templateName): ?array
    {
        $templates = [
            'default' => ['columns' => 3, 'rows' => 2],
            'wide' => ['columns' => 4, 'rows' => 2],
            'compact' => ['columns' => 2, 'rows' => 3],
            'executive' => ['columns' => 3, 'rows' => 1],
            'project_manager' => ['columns' => 3, 'rows' => 2]
        ];

        return $templates[$templateName] ?? null;
    }

}
