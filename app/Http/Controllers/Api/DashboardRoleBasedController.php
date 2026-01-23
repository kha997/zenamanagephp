<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\DashboardRoleBasedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DashboardRoleBasedController extends Controller
{
    protected $roleBasedService;

    public function __construct(DashboardRoleBasedService $roleBasedService)
    {
        $this->roleBasedService = $roleBasedService;
    }

    /**
     * Get role-based dashboard
     */
    public function getRoleBasedDashboard(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'sometimes|string|exists:projects,id',
                'refresh_cache' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $projectId = $request->get('project_id');
            $refreshCache = $request->get('refresh_cache', false);

            $dashboard = $this->roleBasedService->getRoleBasedDashboard($user, $projectId);

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'meta' => [
                    'user_role' => $user->role,
                    'project_context' => $projectId ? 'project_specific' : 'all_projects',
                    'cache_refreshed' => $refreshCache,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get role-based dashboard', [
                'user_id' => Auth::id(),
                'project_id' => $request->get('project_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load role-based dashboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get role-specific widgets
     */
    public function getRoleWidgets(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'sometimes|string|exists:projects,id',
                'category' => 'sometimes|string',
                'include_data' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $projectId = $request->get('project_id');
            $category = $request->get('category');
            $includeData = $request->get('include_data', false);

            $roleConfig = $this->roleBasedService->getRoleConfiguration($user->role);
            $widgets = $this->roleBasedService->getRoleBasedWidgets($user, $roleConfig, $projectId);

            // Filter by category if specified
            if ($category) {
                $widgets = array_filter($widgets, function ($widget) use ($category) {
                    return $widget['category'] === $category;
                });
            }

            // Remove data if not requested
            if (!$includeData) {
                $widgets = array_map(function ($widget) {
                    unset($widget['data']);
                    return $widget;
                }, $widgets);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'widgets' => array_values($widgets),
                    'role_config' => $roleConfig,
                    'total_count' => count($widgets)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get role widgets', [
                'user_id' => Auth::id(),
                'project_id' => $request->get('project_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load role widgets',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get role-specific metrics
     */
    public function getRoleMetrics(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'sometimes|string|exists:projects,id',
                'time_range' => 'sometimes|string|in:1d,7d,30d,90d,1y',
                'include_trends' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $projectId = $request->get('project_id');
            $timeRange = $request->get('time_range', '7d');
            $includeTrends = $request->get('include_trends', true);

            $metrics = $this->roleBasedService->getRoleBasedMetrics($user, $projectId);

            // Add time range context
            $metrics = array_map(function ($metric) use ($includeTrends) {
                if (!$includeTrends) {
                    unset($metric['trend']);
                }
                return $metric;
            }, $metrics);

            return response()->json([
                'success' => true,
                'data' => [
                    'metrics' => $metrics,
                    'time_range' => $timeRange,
                    'total_count' => count($metrics)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get role metrics', [
                'user_id' => Auth::id(),
                'project_id' => $request->get('project_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load role metrics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get role-specific alerts
     */
    public function getRoleAlerts(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'sometimes|string|exists:projects,id',
                'severity' => 'sometimes|string|in:low,medium,high,critical',
                'unread_only' => 'sometimes|boolean',
                'limit' => 'sometimes|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $projectId = $request->get('project_id');
            $severity = $request->get('severity');
            $unreadOnly = $request->get('unread_only', true);
            $limit = $request->get('limit', 20);

            $alerts = $this->roleBasedService->getRoleBasedAlerts($user, $projectId);

            // Filter by severity if specified
            if ($severity) {
                $alerts = array_filter($alerts, function ($alert) use ($severity) {
                    return $alert['severity'] === $severity;
                });
            }

            // Filter unread only if specified
            if ($unreadOnly) {
                $alerts = array_filter($alerts, function ($alert) {
                    return !$alert['is_read'];
                });
            }

            // Apply limit
            $alerts = array_slice($alerts, 0, $limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'alerts' => array_values($alerts),
                    'total_count' => count($alerts),
                    'filters' => [
                        'severity' => $severity,
                        'unread_only' => $unreadOnly,
                        'project_id' => $projectId
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get role alerts', [
                'user_id' => Auth::id(),
                'project_id' => $request->get('project_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load role alerts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get role permissions
     */
    public function getRolePermissions(): JsonResponse
    {
        try {
            $user = Auth::user();
            $permissions = $this->roleBasedService->getRolePermissions($user->role);

            return response()->json([
                'success' => true,
                'data' => [
                    'permissions' => $permissions,
                    'user_role' => $user->role,
                    'role_name' => $this->roleBasedService->getRoleConfiguration($user->role)['name']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get role permissions', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load role permissions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get role configuration
     */
    public function getRoleConfiguration(): JsonResponse
    {
        try {
            $user = Auth::user();
            $roleConfig = $this->roleBasedService->getRoleConfiguration($user->role);

            return response()->json([
                'success' => true,
                'data' => [
                    'role_config' => $roleConfig,
                    'user_role' => $user->role,
                    'tenant_id' => $user->tenant_id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get role configuration', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load role configuration',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get project context for user
     */
    public function getProjectContext(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'required|string|exists:projects,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $projectId = $request->get('project_id');

            $projectContext = $this->roleBasedService->getProjectContext($user, $projectId);

            return response()->json([
                'success' => true,
                'data' => $projectContext
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get project context', [
                'user_id' => Auth::id(),
                'project_id' => $request->get('project_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load project context',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available projects for user role
     */
    public function getAvailableProjects(): JsonResponse
    {
        try {
            $user = Auth::user();
            $roleConfig = $this->roleBasedService->getRoleConfiguration($user->role);

            $query = \App\Models\Project::where('tenant_id', $user->tenant_id);

            // Role-based project access
            switch ($user->role) {
                case 'system_admin':
                    // System admin sees all projects
                    break;
                
                case 'project_manager':
                case 'design_lead':
                case 'site_engineer':
                case 'qc_inspector':
                case 'client_rep':
                case 'subcontractor_lead':
                    // Other roles see only assigned projects
                    $query->whereHas('projectUsers', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
                    break;
            }

            $projects = $query->select(['id', 'name', 'status', 'progress_percentage', 'budget', 'start_date', 'end_date'])
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'projects' => $projects,
                    'total_count' => $projects->count(),
                    'role_access' => $roleConfig['project_access']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get available projects', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load available projects',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get role-based dashboard summary
     */
    public function getDashboardSummary(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'sometimes|string|exists:projects,id',
                'include_widgets' => 'sometimes|boolean',
                'include_metrics' => 'sometimes|boolean',
                'include_alerts' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $projectId = $request->get('project_id');
            $includeWidgets = $request->get('include_widgets', true);
            $includeMetrics = $request->get('include_metrics', true);
            $includeAlerts = $request->get('include_alerts', true);

            $summary = [
                'user_info' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'tenant_id' => $user->tenant_id
                ],
                'project_context' => $projectId ? 'project_specific' : 'all_projects',
                'generated_at' => now()->toISOString()
            ];

            if ($includeWidgets) {
                $roleConfig = $this->roleBasedService->getRoleConfiguration($user->role);
                $summary['widgets'] = [
                    'total_widgets' => count($roleConfig['default_widgets']),
                    'categories' => $roleConfig['widget_categories'],
                    'customization_level' => $roleConfig['customization_level']
                ];
            }

            if ($includeMetrics) {
                $metrics = $this->roleBasedService->getRoleBasedMetrics($user, $projectId);
                $summary['metrics'] = [
                    'total_metrics' => count($metrics),
                    'priority_metrics' => array_column($metrics, 'metric')
                ];
            }

            if ($includeAlerts) {
                $alerts = $this->roleBasedService->getRoleBasedAlerts($user, $projectId);
                $summary['alerts'] = [
                    'total_alerts' => count($alerts),
                    'unread_alerts' => count(array_filter($alerts, function ($alert) {
                        return !$alert['is_read'];
                    })),
                    'severity_breakdown' => $this->getSeverityBreakdown($alerts)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get dashboard summary', [
                'user_id' => Auth::id(),
                'project_id' => $request->get('project_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard summary',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get severity breakdown for alerts
     */
    protected function getSeverityBreakdown(array $alerts): array
    {
        $breakdown = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'critical' => 0
        ];

        foreach ($alerts as $alert) {
            $severity = $alert['severity'] ?? 'low';
            if (isset($breakdown[$severity])) {
                $breakdown[$severity]++;
            }
        }

        return $breakdown;
    }

    /**
     * Switch project context
     */
    public function switchProjectContext(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'required|string|exists:projects,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $projectId = $request->get('project_id');

            // Verify user has access to this project
            $hasAccess = Project::where('id', $projectId)
                ->where('tenant_id', $user->tenant_id)
                ->where(function ($query) use ($user) {
                    $query->where('pm_id', $user->id)
                          ->orWhereHas('projectUsers', function ($q) use ($user) {
                              $q->where('user_id', $user->id);
                          });
                })
                ->exists();

            if (!$hasAccess && $user->role === 'project_manager') {
                $hasAccess = Project::where('id', $projectId)
                    ->where('tenant_id', $user->tenant_id)
                    ->exists();
            }

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this project'
                ], 403);
            }

            // Get updated dashboard for new project context
            $dashboard = $this->roleBasedService->getRoleBasedDashboard($user, $projectId);

            return response()->json([
                'success' => true,
                'data' => [
                    'dashboard' => $dashboard,
                    'project_context' => $projectId,
                    'switched_at' => now()->toISOString()
                ],
                'message' => 'Project context switched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to switch project context', [
                'user_id' => Auth::id(),
                'project_id' => $request->get('project_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to switch project context',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
