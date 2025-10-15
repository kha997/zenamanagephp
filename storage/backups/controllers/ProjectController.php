<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    /**
     * Get all projects for the authenticated user's tenant
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Temporarily disabled database query for debugging
            // $query = Project::query();
            
            // Apply tenant scope
            // Temporarily disabled for debugging
            // if (Auth::check() && Auth::user()->tenant_id) {
            //     $query->where('tenant_id', Auth::user()->tenant_id);
            // }
            
            // Apply filters
            // if ($request->has('status') && $request->status) {
            //     $query->where('status', $request->status);
            // }
            
            // $projects = $query->paginate($perPage);
            
            // Return mock data for debugging
            return response()->json([
                'data' => [],
                'message' => 'Debug mode - no database query',
                'status' => 'success'
            ]);
            
            // Temporarily disabled for debugging
            // if ($request->has('search') && $request->search) {
            //     $search = $request->search;
            //     $query->where(function($q) use ($search) {
            //         $q->where('name', 'like', "%{$search}%")
            //           ->orWhere('description', 'like', "%{$search}%")
            //           ->orWhere('code', 'like', "%{$search}%");
            //     });
            // }
            
            // // Pagination
            // $perPage = $request->get('per_page', 12);
            // // Temporarily disabled for debugging
            // // $projects = $query->orderBy('created_at', 'desc')->paginate($perPage);
            // $projects = collect([]); // Empty collection for debugging
            
            // // Transform data for frontend
            // // Temporarily disabled for debugging
            // // $transformedProjects = $projects->map(function($project) {
            // //     return [
            // //         'id' => 1, // Temporarily disabled for debugging
            // //         'name' => 'Project Name', // Temporarily disabled for debugging
            // //         'description' => 'Project Description', // Temporarily disabled for debugging
            // //         'status' => 'active', // Temporarily disabled for debugging
            // //         'priority' => 'medium', // Temporarily disabled for debugging
            // //         'team' => 'No Team', // Temporarily disabled for debugging
            // //         'progress' => 0, // Temporarily disabled for debugging
            // //         'tasks_completed' => 0, // Default to 0 since tasks table might not exist
            // //         'total_tasks' => 0, // Default to 0 since tasks table might not exist
            // //         'due_date' => 'No due date', // Temporarily disabled for debugging
            // //         'members_count' => 0, // Default to 0 since relationship table doesn't exist
            // //         'created_at' => '2024-01-01', // Temporarily disabled for debugging
            // //         'updated_at' => '2024-01-01', // Temporarily disabled for debugging
            // //     ];
            // // });
            
            // $transformedProjects = []; // Empty array for debugging
            
            // return response()->json([
            //     'status' => 'success',
            //     'data' => [
            //         'projects' => $transformedProjects,
            //         'pagination' => [
            //             'current_page' => $projects->currentPage(),
            //             'last_page' => $projects->lastPage(),
            //             'per_page' => $projects->perPage(),
            //             'total' => $projects->total(),
            //             'from' => $projects->firstItem(),
            //             'to' => $projects->lastItem(),
            //         ]
            //     ]
            // ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch projects: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create a new project
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'nullable|in:planning,active,on_hold,completed,cancelled',
            'budget_total' => 'nullable|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $projectData = $validator->validated();
            $projectData['tenant_id'] = Auth::user()->tenant_id ?? '01k5kzpfwd618xmwdwq3rej3jz';
            $projectData['code'] = 'PRJ-' . strtoupper(uniqid());
            
            $project = Project::create($projectData);
            
            // Log audit trail for project creation
            $this->logProjectAudit('project_created', $project->id, Auth::user()->id, [
                'project_data' => $project->toArray()
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project created successfully',
                'data' => [
                    'project' => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'description' => $project->description,
                        'status' => $project->status,
                        'progress' => $project->progress,
                        'code' => $project->code,
                    ]
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create project: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get a specific project
     */
    public function show($id): JsonResponse
    {
        try {
            $project = Project::findOrFail($id);
            
            // Check tenant access
            if (Auth::check() && Auth::user()->tenant_id && $project->tenant_id !== Auth::user()->tenant_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied'
                ], 403);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'project' => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'description' => $project->description,
                        'status' => $project->status,
                        'progress' => $project->progress,
                        'start_date' => $project->start_date,
                        'end_date' => $project->end_date,
                        'budget_total' => $project->budget_total,
                        'code' => $project->code,
                        'tasks_count' => 0, // Default to 0 since tasks table might not exist
                        'members_count' => 0, // Default to 0 since relationship table doesn't exist
                        'created_at' => $project->created_at,
                        'updated_at' => $project->updated_at,
                    ]
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch project: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update a project
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'sometimes|in:planning,active,on_hold,completed,cancelled',
            'budget_total' => 'nullable|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $project = Project::findOrFail($id);
            
            // Check tenant access
            if (Auth::check() && Auth::user()->tenant_id && $project->tenant_id !== Auth::user()->tenant_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied'
                ], 403);
            }
            
            // Store original data for audit
            $originalData = $project->toArray();
            
            $project->update($validator->validated());
            
            // Log audit trail for important changes
            $this->logProjectAudit('project_updated', $project->id, Auth::user()->id, [
                'original_data' => $originalData,
                'updated_data' => $project->toArray(),
                'changes' => $validator->validated()
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project updated successfully',
                'data' => [
                    'project' => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'description' => $project->description,
                        'status' => $project->status,
                        'progress' => $project->progress,
                    ]
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update project: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a project
     */
    public function destroy($id): JsonResponse
    {
        try {
            $project = Project::findOrFail($id);
            
            // Check tenant access
            if (Auth::check() && Auth::user()->tenant_id && $project->tenant_id !== Auth::user()->tenant_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied'
                ], 403);
            }
            
            // Check if project has tasks (skip for now since tasks table might not exist)
            // if ($project->tasks()->count() > 0) {
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'Cannot delete project with existing tasks'
            //     ], 400);
            // }
            
            // Store project data for audit before deletion
            $projectData = $project->toArray();
            
            $project->delete();
            
            // Log audit trail for project deletion
            $this->logProjectAudit('project_deleted', $id, Auth::user()->id, [
                'deleted_project_data' => $projectData
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project deleted successfully'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete project: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get project documents
     */
    public function documents($id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => "Project {$id} Documents - Coming Soon",
            'data' => [
                'documents' => []
            ]
        ]);
    }
    
    /**
     * Get project history
     */
    public function history($id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => "Project {$id} History - Coming Soon",
            'data' => [
                'history' => []
            ]
        ]);
    }
    
    /**
     * Get project design
     */
    public function design($id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => "Project {$id} Design - Coming Soon",
            'data' => [
                'design' => []
            ]
        ]);
    }
    
    /**
     * Get project construction
     */
    public function construction($id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => "Project {$id} Construction - Coming Soon",
            'data' => [
                'construction' => []
            ]
        ]);
    }
    
    /**
     * Helper method to get priority from status
     */
    private function getPriorityFromStatus($status): string
    {
        $priorityMap = [
            'active' => 'high',
            'planning' => 'medium',
            'on_hold' => 'low',
            'completed' => 'low',
            'cancelled' => 'low',
        ];
        
        return $priorityMap[$status] ?? 'medium';
    }
    
    /**
     * Helper method to get team name
     */
    private function getTeamName($project): string
    {
        $teamCount = $project->teams()->count();
        if ($teamCount > 0) {
            return $project->teams()->first()->name ?? 'Team';
        }
        return 'No Team';
    }


    public function metrics(Request $request)
    {
        // Mock user for testing
        $tenantId = '01k5kzpfwd618xmwdwq3rej3jz'; // Default tenant ID
        
        // Calculate KPIs
        $totalProjects = Project::where('tenant_id', $tenantId)->count();
        $activeProjects = Project::where('tenant_id', $tenantId)
            ->whereIn('status', ['planning', 'active'])
            ->count();
        
        // Calculate on-time rate
        $totalActiveProjects = Project::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('end_date')
            ->count();
        
        $onTimeProjects = Project::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('end_date')
            ->where('end_date', '>=', now())
            ->count();
        
        $onTimeRate = $totalActiveProjects > 0 ? round(($onTimeProjects / $totalActiveProjects) * 100) : 0;
        $overdueProjects = $totalActiveProjects - $onTimeProjects;
        
        // Calculate budget usage
        $totalBudget = Project::where('tenant_id', $tenantId)->sum('budget_total');
        $usedBudget = Project::where('tenant_id', $tenantId)->sum('budget_total') * 0.75; // Mock 75% usage
        $overBudgetProjects = Project::where('tenant_id', $tenantId)
            ->whereRaw('budget_total * 0.75 > budget_total')
            ->count();
        
        // Calculate health snapshot
        $goodProjects = Project::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $atRiskProjects = Project::where('tenant_id', $tenantId)->where('status', 'on_hold')->count();
        $criticalProjects = Project::where('tenant_id', $tenantId)->where('status', 'planning')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'totalProjects' => $totalProjects,
                'activeProjects' => $activeProjects,
                'onTimeRate' => $onTimeRate,
                'overdueProjects' => $overdueProjects,
                'budgetUsage' => '$' . number_format($usedBudget/1000) . 'K / $' . number_format($totalBudget/1000) . 'K',
                'overBudgetProjects' => $overBudgetProjects,
                'healthSnapshot' => $goodProjects . ' / ' . $atRiskProjects . ' / ' . $criticalProjects,
                'atRiskProjects' => $atRiskProjects,
                'criticalProjects' => $criticalProjects
            ]
        ]);
    }

    public function alerts(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $severity = $request->input('severity', 'high|critical');
        $limit = $request->input('limit', 3);

        // Mock alerts based on project data
        $alerts = [];
        
        $overdueCount = Project::where('tenant_id', $user->tenant_id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where('end_date', '<', now())
            ->count();
        
        if ($overdueCount > 0) {
            $alerts[] = [
                'id' => 1,
                'message' => $overdueCount . ' projects are overdue',
                'action' => 'View',
                'severity' => 'critical'
            ];
        }

        $overBudgetCount = Project::where('tenant_id', $user->tenant_id)
            ->whereRaw('budget_total * 0.75 > budget_total')
            ->count();
        
        if ($overBudgetCount > 0) {
            $alerts[] = [
                'id' => 2,
                'message' => $overBudgetCount . ' project(s) are over budget',
                'action' => 'Resolve',
                'severity' => 'high'
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => array_slice($alerts, 0, $limit)
        ]);
    }

    public function nowPanel(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        // Mock now panel actions based on project data
        $actions = [];
        
        $noPMCount = Project::where('tenant_id', $user->tenant_id)
            ->whereNull('pm_id')
            ->count();
        
        if ($noPMCount > 0) {
            $actions[] = [
                'id' => 1,
                'title' => 'Assign PM',
                'description' => $noPMCount . ' projects need PM',
                'icon' => 'fas fa-user-plus',
                'action' => 'assign_pm'
            ];
        }

        $atRiskCount = Project::where('tenant_id', $user->tenant_id)
            ->where('status', 'on_hold')
            ->count();
        
        if ($atRiskCount > 0) {
            $actions[] = [
                'id' => 2,
                'title' => 'Update Health',
                'description' => $atRiskCount . ' projects at risk',
                'icon' => 'fas fa-heartbeat',
                'action' => 'update_health'
            ];
        }

        $overdueCount = Project::where('tenant_id', $user->tenant_id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where('end_date', '<', now())
            ->count();
        
        if ($overdueCount > 0) {
            $actions[] = [
                'id' => 3,
                'title' => 'Resolve Overdue',
                'description' => $overdueCount . ' overdue projects',
                'icon' => 'fas fa-clock',
                'action' => 'resolve_overdue'
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $actions
        ]);
    }

    public function filters(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $tenantId = $user->tenant_id;

        // Get filter options
        $statuses = Project::where('tenant_id', $tenantId)
            ->distinct()
            ->pluck('status')
            ->filter()
            ->values();

        $clients = Project::where('tenant_id', $tenantId)
            ->whereNotNull('client_id')
            ->distinct()
            ->pluck('client_id')
            ->filter()
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'statuses' => $statuses,
                'clients' => $clients,
                'priorities' => ['high', 'medium', 'low'],
                'health' => ['good', 'at_risk', 'critical']
            ]
        ]);
    }

    public function insights(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $range = $request->input('range', '30d');
        
        // Mock insights data
        return response()->json([
            'status' => 'success',
            'data' => [
                'overdueTrend' => [
                    'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    'data' => [2, 3, 1, 2]
                ],
                'throughput' => [
                    'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    'completed' => [1, 2, 1, 3],
                    'started' => [2, 1, 3, 2]
                ],
                'budgetVariance' => [
                    'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    'data' => [5, -2, 8, -1]
                ],
                'healthDistribution' => [
                    'good' => 12,
                    'at_risk' => 5,
                    'critical' => 2
                ]
            ]
        ]);
    }

    public function activity(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $limit = $request->input('limit', 10);

        // Mock activity data
        $activities = [
            [
                'id' => 1,
                'user' => 'John Doe',
                'action' => 'updated status',
                'target' => 'Website Redesign',
                'timestamp' => now()->subMinutes(5)->toISOString(),
                'type' => 'status_change'
            ],
            [
                'id' => 2,
                'user' => 'Jane Smith',
                'action' => 'added document',
                'target' => 'Mobile App Development',
                'timestamp' => now()->subMinutes(15)->toISOString(),
                'type' => 'document_added'
            ],
            [
                'id' => 3,
                'user' => 'Mike Johnson',
                'action' => 'assigned PM',
                'target' => 'Marketing Campaign',
                'timestamp' => now()->subMinutes(30)->toISOString(),
                'type' => 'pm_assigned'
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => array_slice($activities, 0, $limit)
        ]);
    }
    
    /**
     * Log project audit trail
     */
    private function logProjectAudit($action, $projectId, $userId, $data = [])
    {
        Log::info('Project Audit', [
            'action' => $action,
            'project_id' => $projectId,
            'user_id' => $userId,
            'tenant_id' => Auth::user()->tenant_id ?? null,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}
