<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\Unified\ProjectManagementRequest;
use App\Models\Project;
use App\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Projects API Controller (V1)
 * 
 * Pure API controller for project operations.
 * Only returns JSON responses - no view rendering.
 * 
 * This replaces the unified ProjectManagementController for API routes.
 */
class ProjectsController extends BaseApiV1Controller
{
    public function __construct(
        private ProjectManagementService $projectService
    ) {}

    /**
     * Get projects list (API)
     * 
     * Supports both offset pagination (default) and cursor pagination (use cursor parameter)
     * 
     * @param ProjectManagementRequest $request
     * @return JsonResponse
     */
    public function index(ProjectManagementRequest $request): JsonResponse
    {
        try {
            // Check authorization via policy (viewAny permission)
            $this->authorize('viewAny', \App\Models\Project::class);
            
            $filters = $request->only([
                'search', 
                'status', 
                'priority', 
                'owner_id', 
                'start_date_from', 
                'start_date_to', 
                'end_date_from', 
                'end_date_to'
            ]);
            $sortBy = $request->get('sort_by', 'updated_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $tenantId = $this->getTenantId();
            
            // Check if cursor pagination is requested
            $cursor = $request->get('cursor');
            if ($cursor) {
                $limit = (int) $request->get('limit', 15);
                
                $result = $this->projectService->getProjectsCursor(
                    $filters,
                    $limit,
                    $cursor,
                    $sortBy,
                    $sortDirection,
                    $tenantId
                );
                
                return $this->successResponse([
                    'data' => $result['data'],
                    'pagination' => [
                        'next_cursor' => $result['next_cursor'],
                        'has_more' => $result['has_more'],
                    ]
                ], 'Projects retrieved successfully');
            }
            
            // Default: offset pagination
            $perPage = (int) $request->get('per_page', 15);
            
            $projects = $this->projectService->getProjects(
                $filters,
                $perPage,
                $sortBy,
                $sortDirection,
                $tenantId
            );

            if (method_exists($projects, 'items')) {
                return $this->paginatedResponse(
                    $projects->items(),
                    [
                        'current_page' => $projects->currentPage(),
                        'per_page' => $projects->perPage(),
                        'total' => $projects->total(),
                        'last_page' => $projects->lastPage(),
                        'from' => $projects->firstItem(),
                        'to' => $projects->lastItem(),
                    ],
                    'Projects retrieved successfully',
                    [
                        'first' => $projects->url(1),
                        'last' => $projects->url($projects->lastPage()),
                        'prev' => $projects->previousPageUrl(),
                        'next' => $projects->nextPageUrl(),
                    ]
                );
            }

            return $this->successResponse($projects, 'Projects retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index']);
            return $this->errorResponse('Failed to retrieve projects', 500);
        }
    }

    /**
     * Get project by ID (API)
     * 
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $project = $this->projectService->getProjectById($id, $tenantId);
            
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('view', $project);

            return $this->successResponse($project, 'Project retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'show', 'project_id' => $id]);
            return $this->errorResponse('Failed to retrieve project', 500);
        }
    }

    /**
     * Create project (API)
     * 
     * @param ProjectManagementRequest $request
     * @return JsonResponse
     */
    public function store(ProjectManagementRequest $request): JsonResponse
    {
        try {
            // Check authorization via policy (create permission)
            $this->authorize('create', \App\Models\Project::class);
            
            $tenantId = $this->getTenantId();
            $project = $this->projectService->createProject($request->all(), $tenantId);
            
            return $this->successResponse(
                $project,
                'Project created successfully',
                201
            );
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'store']);
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Update project (API)
     * 
     * @param ProjectManagementRequest $request
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function update(ProjectManagementRequest $request, string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $project = $this->projectService->getProjectById($id, $tenantId);
            
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('update', $project);
            
            $project = $this->projectService->updateProject($id, $request->all(), $tenantId);
            
            $message = 'Project updated successfully';
            $tasksUpdatedCount = $project->getAttribute('_tasks_updated_count');
            
            if ($tasksUpdatedCount && $tasksUpdatedCount > 0) {
                $message .= sprintf(' (%d task%s updated)', $tasksUpdatedCount, $tasksUpdatedCount > 1 ? 's' : '');
            }
            
            $responseData = $project->toArray();
            
            if ($tasksUpdatedCount && $tasksUpdatedCount > 0) {
                $tasksUpdatedDetails = $project->getAttribute('_tasks_updated_details');
                $responseData['tasks_synced'] = [
                    'count' => $tasksUpdatedCount,
                    'details' => $tasksUpdatedDetails ?? [],
                ];
            }
            
            return $this->successResponse($responseData, $message);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $content = $response->getContent();
            $message = 'Resource not found';
            
            if ($content) {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['message'])) {
                    $message = $decoded['message'];
                } elseif (is_string($content) && !str_contains($content, '<html')) {
                    $message = strip_tags($content);
                }
            }
            
            return $this->errorResponse($message, $statusCode);
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'update', 'project_id' => $id]);
            return $this->errorResponse(
                $e->getMessage() ?: 'An error occurred while updating the project',
                $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Delete project (API)
     * 
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $project = $this->projectService->getProjectById($id, $tenantId);
            
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('delete', $project);
            
            $deleted = $this->projectService->deleteProject($id);
            
            if (!$deleted) {
                return $this->errorResponse('Failed to delete project', 500);
            }
            
            return $this->successResponse(null, 'Project deleted successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'destroy', 'project_id' => $id]);
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Get project KPIs
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getKpis(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $period = $request->get('period', 'week');
            
            $kpis = $this->projectService->getProjectKpis($tenantId, $period);

            return $this->successResponse($kpis, 'Project KPIs retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getKpis']);
            return $this->errorResponse('Failed to load project KPIs', 500);
        }
    }

    /**
     * Get project alerts
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAlerts(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Use the same logic from Unified controller
            $today = now()->startOfDay();
            $overdueProjects = \App\Models\Project::where('tenant_id', $tenantId)
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<', $today)
                ->whereIn('status', ['active', 'on_hold'])
                ->get();

            $alerts = [];
            foreach ($overdueProjects as $project) {
                $endDate = $project->end_date ? $project->end_date->toISOString() : now()->toISOString();
                
                $alerts[] = [
                    'id' => 'overdue-' . $project->id,
                    'title' => 'Project Overdue',
                    'message' => "Project '{$project->name}' is overdue",
                    'severity' => 'high',
                    'status' => 'unread',
                    'type' => 'overdue',
                    'source' => 'project',
                    'createdAt' => $endDate,
                    'metadata' => ['project_id' => $project->id]
                ];
            }

            return $this->successResponse($alerts, 'Project alerts retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getAlerts']);
            return $this->errorResponse('Failed to load project alerts', 500);
        }
    }

    /**
     * Get project activity
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getActivity(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $limit = (int) $request->get('limit', 10);

            // Use the same logic from Unified controller
            $activity = [];
            
            $recentProjects = \App\Models\Project::where('tenant_id', $tenantId)
                ->orderBy('updated_at', 'desc')
                ->limit((int) ceil($limit / 2))
                ->get();
                
            foreach ($recentProjects as $project) {
                $activity[] = [
                    'id' => 'project-' . $project->id,
                    'type' => 'project',
                    'action' => 'updated',
                    'description' => "Project '{$project->name}' was updated",
                    'timestamp' => $project->updated_at->toISOString(),
                    'user' => [
                        'id' => auth()->id(),
                        'name' => auth()->user()->name
                    ]
                ];
            }

            $recentComments = \App\Models\TaskComment::whereHas('task', function($q) use ($tenantId) {
                $q->whereHas('project', function($q2) use ($tenantId) {
                    $q2->where('tenant_id', $tenantId);
                });
            })
                ->orderBy('created_at', 'desc')
                ->limit((int) ceil($limit / 2))
                ->with(['task.project', 'user'])
                ->get();
                
            foreach ($recentComments as $comment) {
                $projectName = $comment->task->project->name ?? 'Unknown Project';
                $activity[] = [
                    'id' => 'comment-' . $comment->id,
                    'type' => 'comment',
                    'action' => 'commented',
                    'description' => "Commented on task in '{$projectName}'",
                    'timestamp' => $comment->created_at->toISOString(),
                    'user' => [
                        'id' => $comment->user->id ?? auth()->id(),
                        'name' => $comment->user->name ?? auth()->user()->name
                    ]
                ];
            }

            usort($activity, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            $activity = array_slice($activity, 0, $limit);

            return $this->successResponse($activity, 'Project activity retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getActivity']);
            return $this->errorResponse('Failed to load project activity', 500);
        }
    }

    /**
     * Get KPIs for a specific project
     * 
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function getProjectKpis(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $project = $this->projectService->getProjectById($id, $tenantId);
            
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('view', $project);
            
            $kpis = $this->projectService->getProjectKpisById($id, $tenantId);
            
            return $this->successResponse($kpis, 'Project KPIs retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getProjectKpis', 'project_id' => $id]);
            return $this->errorResponse('Failed to retrieve project KPIs', 500);
        }
    }

    /**
     * Get alerts for a specific project
     * 
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function getProjectAlerts(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $project = $this->projectService->getProjectById($id, $tenantId);
            
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('view', $project);
            
            $alerts = $this->projectService->getProjectAlertsById($id, $tenantId);
            
            return $this->successResponse($alerts, 'Project alerts retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getProjectAlerts', 'project_id' => $id]);
            return $this->errorResponse('Failed to retrieve project alerts', 500);
        }
    }

    /**
     * Get documents for a specific project
     * 
     * @param Request $request
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function getProjectDocuments(Request $request, string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Verify project exists
            $project = $this->projectService->getProjectById($id, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }
            
            $filters = [
                'search' => $request->get('search'),
                'category' => $request->get('category'),
                'status' => $request->get('status'),
            ];
            
            $perPage = $request->get('per_page', 50);
            
            $documents = \App\Models\Document::where('project_id', $id)
                ->where('tenant_id', $tenantId)
                ->when($filters['search'], function($q) use ($filters) {
                    $q->where(function($query) use ($filters) {
                        $query->where('name', 'like', "%{$filters['search']}%")
                              ->orWhere('original_name', 'like', "%{$filters['search']}%")
                              ->orWhere('description', 'like', "%{$filters['search']}%");
                    });
                })
                ->when($filters['category'], fn($q) => $q->where('category', $filters['category']))
                ->when($filters['status'], fn($q) => $q->where('status', $filters['status']))
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
            
            return $this->paginatedResponse(
                $documents->items(),
                [
                    'current_page' => $documents->currentPage(),
                    'last_page' => $documents->lastPage(),
                    'per_page' => $documents->perPage(),
                    'total' => $documents->total(),
                    'from' => $documents->firstItem(),
                    'to' => $documents->lastItem(),
                ],
                'Project documents retrieved successfully',
                [
                    'first' => $documents->url(1),
                    'last' => $documents->url($documents->lastPage()),
                    'prev' => $documents->previousPageUrl(),
                    'next' => $documents->nextPageUrl(),
                ]
            );
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getProjectDocuments', 'project_id' => $id]);
            return $this->errorResponse('Failed to retrieve project documents', 500);
        }
    }

    /**
     * Get team members for a project
     * 
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function getTeamMembers(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $members = $this->projectService->getTeamMembers($id, $tenantId);

            return $this->successResponse($members, 'Team members retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getTeamMembers', 'project_id' => $id]);
            return $this->errorResponse('Failed to retrieve team members', 500);
        }
    }

    /**
     * Add team member to project
     * 
     * @param Request $request
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function addTeamMember(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => ['required', 'string', 'ulid', 'exists:users,id'],
                'role_id' => ['sometimes', 'nullable', 'string', 'ulid']
            ]);

            $tenantId = $this->getTenantId();
            $project = $this->projectService->addTeamMember(
                $id,
                $request->input('user_id'),
                $request->input('role_id'),
                $tenantId
            );

            return $this->successResponse($project, 'Team member added successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'addTeamMember', 'project_id' => $id]);
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Remove team member from project
     * 
     * @param Request $request
     * @param string $id Project ID
     * @param string $userId User ID
     * @return JsonResponse
     */
    public function removeTeamMember(Request $request, string $id, string $userId): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $project = $this->projectService->removeTeamMember($id, $userId, $tenantId);

            return $this->successResponse($project, 'Team member removed successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'removeTeamMember', 'project_id' => $id, 'user_id' => $userId]);
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Get project history
     * 
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function history(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Get project audit logs or activity history
            // For now, return empty array - can be implemented later with audit log service
            $history = [];

            return $this->successResponse($history, 'Project history retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'history', 'project_id' => $id]);
            return $this->errorResponse('Failed to retrieve project history', 500);
        }
    }

    /**
     * Get project overview (execution + financials)
     * 
     * Round 67: Project Overview Cockpit
     * 
     * @param string $project Project ID
     * @return JsonResponse
     */
    public function overview(string $project): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $projectModel = $this->projectService->getProjectById($project, $tenantId);
            if (!$projectModel) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('view', $projectModel);

            $overviewService = app(\App\Services\Projects\ProjectOverviewService::class);
            $overview = $overviewService->buildOverview($tenantId, $project);

            return $this->successResponse([
                'project' => $overview['project'],
                'financials' => $overview['financials'],
                'tasks' => $overview['tasks'],
                'health' => $overview['health'],
            ], 'Project overview retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'overview', 'project_id' => $project]);
            return $this->errorResponse('Failed to retrieve project overview', 500);
        }
    }

    /**
     * Get project statistics
     * 
     * @return JsonResponse
     */
    public function getProjectStats(): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $stats = $this->projectService->getProjectStats($tenantId);
            
            return $this->successResponse($stats, 'Project statistics retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getProjectStats']);
            return $this->errorResponse('Failed to retrieve project statistics', 500);
        }
    }

    /**
     * Get project timeline
     * 
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function getProjectTimeline(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $project = $this->projectService->getProjectById($id, $tenantId);
            
            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }

            // Get timeline events (tasks, milestones, etc.)
            $timeline = [
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'start_date' => $project->start_date?->toISOString(),
                    'end_date' => $project->end_date?->toISOString(),
                ],
                'events' => [], // Can be enhanced with actual timeline events
            ];

            return $this->successResponse($timeline, 'Project timeline retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getProjectTimeline', 'project_id' => $id]);
            return $this->errorResponse('Failed to retrieve project timeline', 500);
        }
    }

    /**
     * Search projects
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function searchProjects(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');
            $tenantId = $this->getTenantId();
            
            if (empty($query)) {
                return $this->errorResponse('Search query is required', 400);
            }

            $filters = ['search' => $query];
            $projects = $this->projectService->getProjects($filters, 20, 'updated_at', 'desc', $tenantId);

            return $this->paginatedResponse(
                method_exists($projects, 'items') ? $projects->items() : $projects,
                method_exists($projects, 'currentPage') ? [
                    'current_page' => $projects->currentPage(),
                    'per_page' => $projects->perPage(),
                    'total' => $projects->total(),
                    'last_page' => $projects->lastPage(),
                ] : [],
                'Projects search completed successfully'
            );
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'searchProjects']);
            return $this->errorResponse('Failed to search projects', 500);
        }
    }

    /**
     * Get recent projects
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getRecentProjects(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->get('limit', 10);
            $tenantId = $this->getTenantId();
            
            $projects = $this->projectService->getProjects([], $limit, 'updated_at', 'desc', $tenantId);

            return $this->successResponse(
                method_exists($projects, 'items') ? $projects->items() : $projects,
                'Recent projects retrieved successfully'
            );
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getRecentProjects']);
            return $this->errorResponse('Failed to retrieve recent projects', 500);
        }
    }

    /**
     * Get project dashboard data
     * 
     * @return JsonResponse
     */
    public function getProjectDashboardData(): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $stats = $this->projectService->getProjectStats($tenantId);
            $kpis = $this->projectService->getProjectKpis($tenantId, 'week');
            
            $dashboardData = [
                'stats' => $stats,
                'kpis' => $kpis,
                'recent_projects' => [],
            ];

            return $this->successResponse($dashboardData, 'Project dashboard data retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getProjectDashboardData']);
            return $this->errorResponse('Failed to retrieve project dashboard data', 500);
        }
    }

    /**
     * Update project status
     * 
     * @param Request $request
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function updateProjectStatus(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:' . implode(',', \App\Models\Project::VALID_STATUSES),
            ]);

            $tenantId = $this->getTenantId();
            $project = $this->projectService->getProjectById($id, $tenantId);
            
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('update', $project);
            
            $status = $request->input('status');
            $project = $this->projectService->updateProject($id, ['status' => $status], $tenantId);

            return $this->successResponse($project, 'Project status updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'updateProjectStatus', 'project_id' => $id]);
            return $this->errorResponse('Failed to update project status', 500);
        }
    }

    /**
     * Update project progress
     * 
     * @param Request $request
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function updateProjectProgress(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'progress' => 'required|numeric|min:0|max:100',
            ]);

            $tenantId = $this->getTenantId();
            $project = $this->projectService->getProjectById($id, $tenantId);
            
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('update', $project);
            
            $progress = $request->input('progress');
            $project = $this->projectService->updateProject($id, ['progress' => $progress], $tenantId);

            return $this->successResponse($project, 'Project progress updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'updateProjectProgress', 'project_id' => $id]);
            return $this->errorResponse('Failed to update project progress', 500);
        }
    }

    /**
     * Assign project to users/teams
     * 
     * @param Request $request
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function assignProject(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'user_ids' => 'nullable|array',
                'user_ids.*' => 'string|ulid',
                'team_ids' => 'nullable|array',
                'team_ids.*' => 'string|ulid',
            ]);

            $tenantId = $this->getTenantId();
            $project = $this->projectService->getProjectById($id, $tenantId);
            
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('assignUsers', $project);
            
            if ($request->has('user_ids')) {
                $this->projectService->addTeamMembers($id, $request->input('user_ids'), $tenantId);
            }

            if ($request->has('team_ids')) {
                // Handle team assignment if service supports it
            }

            $project = $this->projectService->getProjectById($id, $tenantId);

            return $this->successResponse($project, 'Project assigned successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'assignProject', 'project_id' => $id]);
            return $this->errorResponse('Failed to assign project', 500);
        }
    }

    /**
     * Restore project (from soft delete)
     * 
     * @param string $id Project ID
     * @return JsonResponse
     */
    public function restoreProject(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $project = $this->projectService->getProjectById($id, $tenantId);
            
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check authorization via policy (restore is same as update)
            $this->authorize('update', $project);
            
            $project = $this->projectService->restoreProject($id, $tenantId);

            return $this->successResponse($project, 'Project restored successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'restoreProject', 'project_id' => $id]);
            return $this->errorResponse('Failed to restore project', 500);
        }
    }

    /**
     * Bulk delete projects
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkDeleteProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'string|ulid',
            ]);

            $tenantId = $this->getTenantId();
            $ids = $request->input('ids');
            $count = 0;

            foreach ($ids as $id) {
                try {
                    $this->projectService->deleteProject($id);
                    $count++;
                } catch (\Exception $e) {
                    Log::warning('Failed to delete project in bulk operation', [
                        'project_id' => $id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $this->successResponse([
                'deleted_count' => $count,
                'total_requested' => count($ids),
            ], "{$count} project(s) deleted successfully");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'bulkDeleteProjects']);
            return $this->errorResponse('Failed to bulk delete projects', 500);
        }
    }

    /**
     * Bulk archive projects
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkArchiveProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'string|ulid',
            ]);

            $tenantId = $this->getTenantId();
            $ids = $request->input('ids');
            $count = 0;

            foreach ($ids as $id) {
                try {
                    $this->projectService->updateProject($id, ['status' => 'archived'], $tenantId);
                    $count++;
                } catch (\Exception $e) {
                    Log::warning('Failed to archive project in bulk operation', [
                        'project_id' => $id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $this->successResponse([
                'archived_count' => $count,
                'total_requested' => count($ids),
            ], "{$count} project(s) archived successfully");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'bulkArchiveProjects']);
            return $this->errorResponse('Failed to bulk archive projects', 500);
        }
    }

    /**
     * Bulk export projects
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkExportProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'string|ulid',
                'format' => 'nullable|string|in:json,csv,excel',
            ]);

            $tenantId = $this->getTenantId();
            $ids = $request->input('ids');
            $format = $request->input('format', 'json');

            // Get projects
            $projects = [];
            foreach ($ids as $id) {
                $project = $this->projectService->getProjectById($id, $tenantId);
                if ($project) {
                    $projects[] = $project;
                }
            }

            // For now, return JSON. Can be enhanced to generate CSV/Excel files
            return $this->successResponse($projects, 'Projects exported successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'bulkExportProjects']);
            return $this->errorResponse('Failed to bulk export projects', 500);
        }
    }

    /**
     * Get health history for a project
     * 
     * Round 86: Project Health History (snapshots + history API, backend-only)
     * 
     * @param Request $request
     * @param Project $project Project model (resolved via route model binding)
     * @return JsonResponse
     */
    public function healthHistory(Request $request, Project $project): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Ensure project belongs to tenant
            if ($project->tenant_id !== $tenantId) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('view', $project);

            // Get limit from query param (default: 30, max: 100)
            $limit = min((int) $request->get('limit', 30), 100);

            $snapshotService = app(\App\Services\Reports\ProjectHealthSnapshotService::class);
            $snapshots = $snapshotService->getHealthHistoryForProject($tenantId, $project, $limit);

            return $this->successResponse(
                \App\Http\Resources\ProjectHealthSnapshotResource::collection($snapshots),
                'Health history retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'healthHistory', 'project_id' => $project->id]);
            return $this->errorResponse('Failed to retrieve health history', 500);
        }
    }

    /**
     * Create a health snapshot for a project
     * 
     * Round 86: Project Health History (snapshots + history API, backend-only)
     * 
     * @param Request $request
     * @param Project $project Project model (resolved via route model binding)
     * @return JsonResponse
     */
    public function snapshotHealth(Request $request, Project $project): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Ensure project belongs to tenant
            if ($project->tenant_id !== $tenantId) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('view', $project);

            $snapshotService = app(\App\Services\Reports\ProjectHealthSnapshotService::class);
            $snapshot = $snapshotService->snapshotProjectHealthForProject($tenantId, $project);

            return $this->successResponse(
                new \App\Http\Resources\ProjectHealthSnapshotResource($snapshot),
                'Health snapshot created successfully',
                201
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'snapshotHealth', 'project_id' => $project->id]);
            return $this->errorResponse('Failed to create health snapshot', 500);
        }
    }
}

