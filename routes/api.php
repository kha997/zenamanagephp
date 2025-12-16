<?php declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes (Architecture Compliant)
|--------------------------------------------------------------------------
|
| All business logic operations are handled via API endpoints.
| Web routes only render views and call these API endpoints.
|
*/

// E2E readiness endpoint - simple 200 OK response for Playwright webServer
// Must be at the top to avoid session middleware that requires APP_KEY
// Using plain response() to avoid any service dependencies
Route::get('/_e2e/ready', function() {
    return new \Illuminate\Http\Response('ok', 200, ['Content-Type' => 'text/plain']);
});

// Health check endpoints
Route::get('/health', function () {
    return response()->json([
        'status' => 'success',
        'data' => [
            'service' => 'Z.E.N.A Project Management API',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'laravel_version' => app()->version(),
            'database' => 'connected',
            'services' => [
                'database' => 'ok',
                'cache' => 'ok',
                'queue' => 'ok'
            ]
        ]
    ]);
});

// Metrics endpoints
Route::prefix('metrics')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\MetricsController::class, 'index']);
    Route::get('/prometheus', [App\Http\Controllers\Api\MetricsController::class, 'prometheus']);
    Route::get('/health', [App\Http\Controllers\Api\MetricsController::class, 'health']);
    Route::get('/{metric}', [App\Http\Controllers\Api\MetricsController::class, 'show']);
});

Route::get('/health/performance', function () {
    return response()->json([
        'memory' => [
            'used' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        ],
        'cpu' => [
            'load' => sys_getloadavg()[0] ?? 0,
            'cores' => 4 // Default assumption
        ],
        'php' => [
            'version' => PHP_VERSION,
            'extensions' => get_loaded_extensions()
        ],
        'application' => [
            'name' => 'Z.E.N.A Project Management',
            'version' => '1.0.0',
            'environment' => app()->environment(),
            'uptime' => time() - $_SERVER['REQUEST_TIME']
        ],
        'database' => [
            'connections' => DB::getConnections(),
            'queries' => DB::getQueryLog()
        ]
    ]);
});

// REMOVED: Dangerous unprotected login endpoint
// Use AuthenticationController@login with proper middleware instead

// Simple user endpoint (protected)
Route::get('/user', function() {
    return response()->json([
        'success' => true,
        'data' => [
            'message' => 'API is working',
            'timestamp' => now()->toISOString()
        ]
    ]);
})->middleware('auth:sanctum');

// Simple dashboard endpoint (protected)
Route::get('/dashboard', function() {
    return response()->json([
        'success' => true,
        'data' => [
            'message' => 'Dashboard API is working',
            'timestamp' => now()->toISOString()
        ]
    ]);
})->middleware('auth:sanctum');

// Debug endpoints (development only)
Route::get('/debug/ping', function() {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString()
    ]);
})->middleware(['auth:sanctum']);

Route::get('/debug/info', function() {
    return response()->json([
        'status' => 'ok',
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
        'environment' => app()->environment(),
        'timestamp' => now()->toISOString()
    ]);
})->middleware(['auth:sanctum']);

// AI endpoints (protected)
Route::prefix('ai')->middleware('auth:sanctum')->group(function () {
    Route::get('/project-insights', [\App\Http\Controllers\Api\AIController::class, 'getProjectInsights']);
    Route::get('/task-recommendations', [\App\Http\Controllers\Api\AIController::class, 'getTaskRecommendations']);
    Route::post('/generate-report', [\App\Http\Controllers\Api\AIController::class, 'generateReport']);
    Route::get('/predictive-analytics', [\App\Http\Controllers\Api\AIController::class, 'getPredictiveAnalytics']);
    Route::post('/process-query', [\App\Http\Controllers\Api\AIController::class, 'processQuery']);
});

// ========================================
// PUBLIC AUTHENTICATION API ENDPOINTS
// ========================================

Route::prefix('public/auth')->group(function () {
    Route::post('/register', [\App\Http\Controllers\Api\Auth\RegistrationController::class, 'store']);
    Route::get('/test', function () {
        return response()->json(['message' => 'API test working']);
    });
    Route::post('/test', function () {
        return response()->json(['message' => 'POST test working', 'data' => request()->all()]);
    });
    Route::post('/register-simple', function () {
        return response()->json(['message' => 'Simple registration test', 'data' => request()->all()]);
    });
    Route::post('/register-test', function () {
        try {
            return \App\Support\ApiResponse::created([
                'message' => 'Registration test successful',
                'data' => request()->all()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });
    Route::post('/register-controller-test', function () {
        try {
            $controller = new \App\Http\Controllers\Api\Auth\RegistrationController(
                new \App\Services\TenantProvisioningService(
                    new \App\Services\EmailVerificationService()
                )
            );
            return $controller->store(request());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    });
});

Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'logout'])
        ->middleware(['auth:sanctum', 'security', 'validation']);
});

// ========================================

Route::prefix('auth')->group(function () {
    // Login moved to web routes for session support
    // Route::post('/login', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'login'])
    //     ->middleware(['web', 'throttle:5,1']);
    
    // Add compatibility route for login form
    // Round 158: Add debug.auth middleware for E2E auth tracing
    Route::post('/login', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'login'])
        ->middleware(['web', 'debug.auth', 'brute.force.protection', 'input.validation']);
    
    Route::post('/logout', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'logout'])
        ->middleware(['auth:sanctum', 'security', 'validation']);
    
    // Password management
    Route::post('/password/forgot', [\App\Http\Controllers\Api\Auth\PasswordController::class, 'forgot'])
        ->middleware(['security', 'validation', 'rate.limit:sliding,3,1']);
    Route::post('/password/reset', [\App\Http\Controllers\Api\Auth\PasswordController::class, 'reset'])
        ->middleware(['security', 'validation', 'rate.limit:sliding,3,1']);
    
    // Password reset with new controller
    Route::post('/password/reset/send', [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'sendResetLink'])
        ->middleware(['input.validation', 'rate.limit:sliding,3,1']);
    
    Route::post('/password/reset/confirm', [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'resetPassword'])
        ->middleware(['input.validation']);
    
    Route::post('/password/reset/verify', [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'verifyToken'])
        ->middleware(['input.validation']);
    
    // Token management
    Route::post('/refresh', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'refresh'])
        ->middleware(['auth:sanctum', 'security', 'validation']);
    Route::get('/validate', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'validateToken'])
        ->middleware(['auth:sanctum', 'security', 'validation']);
    
    // User context
    // Use api.stateful middleware group for session-based SPA authentication
    Route::middleware('api.stateful')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'me'])
            ->middleware(['auth:sanctum', 'ability:tenant']);
        Route::get('/permissions', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'permissions'])
            ->middleware(['auth:sanctum', 'ability:tenant']);
    });
});

// User context endpoints (canonical /api/v1/me for frontend compatibility)
// Round 157: Moved OUTSIDE auth:sanctum group so api.stateful can set up session first
// Use api.stateful middleware group for session-based SPA authentication
Route::middleware(['api.stateful', 'debug.auth'])->group(function () {
    Route::get('/v1/me', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'me'])
        ->middleware(['auth:sanctum', 'ability:tenant']);
    Route::get('/v1/permissions', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'permissions'])
        ->middleware(['auth:sanctum', 'ability:tenant']);
});

// Protected routes - All business logic operations
Route::middleware(['auth:sanctum'])->group(function () {
    // Dashboard endpoints - REMOVED (Trùng lặp với routes khác)

    // Rewards API endpoints (tenant-scoped)
    Route::prefix('v1/app/rewards')
        ->middleware(['ability:tenant'])
        ->controller(\App\Http\Controllers\RewardsController::class)
        ->group(function () {
            Route::get('status', 'status');
            Route::post('toggle', 'toggle');
            Route::post('trigger-task-completion', 'triggerTaskCompletion');
            Route::get('messages', 'messages');
        });

    // Notifications API endpoints (tenant-scoped)
    Route::prefix('v1')
        ->middleware(['ability:tenant'])
        ->controller(\App\Http\Controllers\Api\App\NotificationController::class)
        ->group(function () {
            Route::get('/notifications', 'index');
            Route::put('/notifications/{id}/read', 'markAsRead');
            Route::put('/notifications/read-all', 'markAllAsRead');
        });

    // AI endpoints
    Route::prefix('ai')->group(function () {
        Route::get('/project-insights', [\App\Http\Controllers\Api\AIController::class, 'getProjectInsights']);
        Route::get('/task-recommendations', [\App\Http\Controllers\Api\AIController::class, 'getTaskRecommendations']);
        Route::post('/generate-report', [\App\Http\Controllers\Api\AIController::class, 'generateReport']);
        Route::get('/predictive-analytics', [\App\Http\Controllers\Api\AIController::class, 'getPredictiveAnalytics']);
        Route::post('/process-query', [\App\Http\Controllers\Api\AIController::class, 'processQuery']);
    });

    // ========================================
    // WIDGETS API ENDPOINTS
    // ========================================
    Route::prefix('widgets')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\WidgetController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\WidgetController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\WidgetController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\WidgetController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\WidgetController::class, 'destroy']);
    });

    // ========================================
    // SUPPORT TICKETS API ENDPOINTS
    // ========================================
    Route::prefix('support')->group(function () {
        Route::prefix('tickets')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\SupportTicketController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\SupportTicketController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\SupportTicketController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\SupportTicketController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\SupportTicketController::class, 'destroy']);
            Route::post('/{id}/messages', [\App\Http\Controllers\Api\SupportTicketController::class, 'addMessage']);
        });
    });

    // ========================================
    // DASHBOARD API ENDPOINTS
    // ========================================
    Route::prefix('dashboard')->group(function () {
        Route::get('/kpis', function () {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
            }
            
            // Get real project data
            $projects = \App\Models\Project::where('tenant_id', $user->tenant_id)->get();
            $activeProjects = $projects->where('status', 'active')->count();
            $completedProjects = $projects->where('status', 'completed')->count();
            $totalProjects = $projects->count();
            
            // Get real user data
            $users = \App\Models\User::where('tenant_id', $user->tenant_id)->get();
            $activeUsers = $users->where('is_active', true)->count();
            $totalUsers = $users->count();
            
            // Calculate overall progress
            $overallProgress = $projects->avg('progress') ?? 0;
            
            // Calculate budget metrics
            $totalBudget = $projects->sum('budget_total');
            $totalSpent = $projects->sum('budget_actual');
            $budgetUtilization = $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100) : 0;
            
            // Calculate change metrics (compare with previous month)
            $lastMonth = now()->subMonth();
            $lastMonthProjects = \App\Models\Project::where('tenant_id', $user->tenant_id)
                ->where('created_at', '<=', $lastMonth->endOfMonth())
                ->get();
            $lastMonthCount = $lastMonthProjects->count();
            $projectChange = $lastMonthCount > 0 ? round((($totalProjects - $lastMonthCount) / $lastMonthCount) * 100) : 0;
            
            $lastMonthUsers = \App\Models\User::where('tenant_id', $user->tenant_id)
                ->where('created_at', '<=', $lastMonth->endOfMonth())
                ->get();
            $lastMonthUserCount = $lastMonthUsers->count();
            $userChange = $lastMonthUserCount > 0 ? round((($totalUsers - $lastMonthUserCount) / $lastMonthUserCount) * 100) : 0;
            
            $lastMonthProgress = $lastMonthProjects->avg('progress') ?? 0;
            $progressChange = $lastMonthProgress > 0 ? round($overallProgress - $lastMonthProgress) : 0;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'projects' => [
                        'total' => $totalProjects,
                        'active' => $activeProjects,
                        'completed' => $completedProjects,
                        'change' => $projectChange
                    ],
                    'tasks' => [
                        'total' => 45, // Mock data for now
                        'pending' => 20,
                        'in_progress' => 15,
                        'completed' => 10,
                        'change' => -5
                    ],
                    'users' => [
                        'total' => $totalUsers,
                        'active' => $activeUsers,
                        'inactive' => $totalUsers - $activeUsers,
                        'change' => $userChange
                    ],
                    'progress' => [
                        'overall' => round($overallProgress),
                        'this_month' => 85, // Mock data for now
                        'last_month' => 72,
                        'change' => $progressChange
                    ],
                    'budget' => [
                        'total' => $totalBudget,
                        'spent' => $totalSpent,
                        'remaining' => $totalBudget - $totalSpent,
                        'utilization' => $budgetUtilization,
                        'change' => 8 // Mock change for now
                    ]
                ]
            ]);
        });
        
        Route::get('/charts', function () {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
            }
            
            // Get real project data for charts
            $projects = \App\Models\Project::where('tenant_id', $user->tenant_id)->get();
            
            // Project Status Distribution (Real Data)
            $statusCounts = $projects->groupBy('status')->map->count();
            $projectProgressData = [
                'labels' => ['Planning', 'Active', 'On Hold', 'Completed', 'Cancelled'],
                'datasets' => [[
                    'label' => 'Projects',
                    'data' => [
                        $statusCounts->get('planning', 0),
                        $statusCounts->get('active', 0),
                        $statusCounts->get('on_hold', 0),
                        $statusCounts->get('completed', 0),
                        $statusCounts->get('cancelled', 0)
                    ],
                    'backgroundColor' => ['#F59E0B', '#10B981', '#EF4444', '#3B82F6', '#6B7280']
                ]]
            ];
            
            // Project Progress Over Time (Last 6 months)
            $sixMonthsAgo = now()->subMonths(6);
            $monthlyProgress = [];
            $monthlyLabels = [];
            
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $monthlyLabels[] = $month->format('M');
                
                $monthProjects = $projects->filter(function($project) use ($month) {
                    return $project->created_at->format('Y-m') === $month->format('Y-m');
                });
                
                $monthlyProgress[] = $monthProjects->avg('progress') ?? 0;
            }
            
            $taskDistributionData = [
                'labels' => $monthlyLabels,
                'datasets' => [[
                    'label' => 'Average Progress %',
                    'data' => $monthlyProgress,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderWidth' => 2,
                    'fill' => true
                ]]
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'project_progress' => $projectProgressData,
                    'task_distribution' => $taskDistributionData
                ]
            ]);
        });
        
        Route::get('/recent-activity', function () {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
            }
            
            // Get recent projects
            $recentProjects = \App\Models\Project::where('tenant_id', $user->tenant_id)
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get();
            
            $activities = [];
            foreach ($recentProjects as $project) {
                $activities[] = [
                    'id' => $project->id,
                    'type' => 'project',
                    'action' => 'updated',
                    'description' => "Project \"{$project->name}\" was updated",
                    'timestamp' => $project->updated_at->toISOString(),
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name
                    ]
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $activities
            ]);
        });
    });

    // ========================================
    // PROJECTS API ENDPOINTS (Unified)
    // ========================================
    Route::prefix('projects')->group(function () {
        Route::get('/', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'getProjects']);
        Route::post('/', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'createProject']);
        Route::get('/stats', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'getProjectStats']);
        Route::get('/timeline/{id}', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'getProjectTimeline']);
        Route::get('/search', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'searchProjects']);
        Route::get('/recent', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'getRecentProjects']);
        Route::get('/dashboard', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'getProjectDashboardData']);
        Route::get('/{id}', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'getProject']);
        Route::put('/{id}', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'updateProject']);
        Route::delete('/{id}', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'deleteProject']);
        Route::post('/bulk-delete', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'bulkDeleteProjects']);
        Route::post('/bulk-archive', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'bulkArchiveProjects']);
        Route::post('/bulk-export', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'bulkExportProjects']);
        Route::put('/{id}/status', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'updateProjectStatus']);
        Route::put('/{id}/progress', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'updateProjectProgress']);
        Route::put('/{id}/assign', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'assignProject']);
        Route::post('/{id}/restore', [\App\Http\Controllers\Unified\ProjectManagementController::class, 'restoreProject']);
        
        // Project-specific task routes
        Route::prefix('{projectId}/tasks')->group(function () {
            Route::get('/', [\App\Http\Controllers\Unified\TaskManagementController::class, 'getTasksForProject']);
            Route::post('/', [\App\Http\Controllers\Unified\TaskManagementController::class, 'createTask']);
            Route::get('/{id}', [\App\Http\Controllers\Unified\TaskManagementController::class, 'getTask']);
            Route::put('/{id}', [\App\Http\Controllers\Unified\TaskManagementController::class, 'updateTask']);
            Route::patch('/{id}', [\App\Http\Controllers\Unified\TaskManagementController::class, 'updateTask']);
            Route::delete('/{id}', [\App\Http\Controllers\Unified\TaskManagementController::class, 'deleteTask']);
        });
    });

    // ========================================
    // TASKS API ENDPOINTS (Unified)
    // ========================================
    Route::prefix('tasks')->group(function () {
        Route::get('/', [\App\Http\Controllers\Unified\TaskManagementController::class, 'getTasks']);
        Route::post('/', [\App\Http\Controllers\Unified\TaskManagementController::class, 'createTask']);
        Route::get('/stats', [\App\Http\Controllers\Unified\TaskManagementController::class, 'getTaskStatistics']);
        Route::get('/project/{projectId}', [\App\Http\Controllers\Unified\TaskManagementController::class, 'getTasksForProject']);
        Route::get('/{id}', [\App\Http\Controllers\Unified\TaskManagementController::class, 'getTask']);
        Route::put('/{id}', [\App\Http\Controllers\Unified\TaskManagementController::class, 'updateTask']);
        Route::delete('/{id}', [\App\Http\Controllers\Unified\TaskManagementController::class, 'deleteTask']);
        Route::put('/{id}/progress', [\App\Http\Controllers\Unified\TaskManagementController::class, 'updateTaskProgress']);
        Route::post('/bulk-delete', [\App\Http\Controllers\Unified\TaskManagementController::class, 'bulkDeleteTasks']);
        Route::post('/bulk-status', [\App\Http\Controllers\Unified\TaskManagementController::class, 'bulkUpdateStatus']);
        Route::post('/bulk-assign', [\App\Http\Controllers\Unified\TaskManagementController::class, 'bulkAssignTasks']);
    });

    // ========================================
    // SUBTASKS - API ENDPOINTS
    // ========================================
    Route::prefix('subtasks')->group(function () {
        Route::get('/task/{taskId}', [\App\Http\Controllers\Unified\SubtaskManagementController::class, 'getSubtasksForTask']);
        Route::post('/', [\App\Http\Controllers\Unified\SubtaskManagementController::class, 'createSubtask']);
        Route::get('/{id}', [\App\Http\Controllers\Unified\SubtaskManagementController::class, 'getSubtask']);
        Route::put('/{id}', [\App\Http\Controllers\Unified\SubtaskManagementController::class, 'updateSubtask']);
        Route::delete('/{id}', [\App\Http\Controllers\Unified\SubtaskManagementController::class, 'deleteSubtask']);
        Route::put('/{id}/progress', [\App\Http\Controllers\Unified\SubtaskManagementController::class, 'updateSubtaskProgress']);
        Route::get('/task/{taskId}/stats', [\App\Http\Controllers\Unified\SubtaskManagementController::class, 'getSubtaskStatistics']);
        Route::post('/bulk-delete', [\App\Http\Controllers\Unified\SubtaskManagementController::class, 'bulkDeleteSubtasks']);
        Route::post('/bulk-status', [\App\Http\Controllers\Unified\SubtaskManagementController::class, 'bulkUpdateStatus']);
        Route::post('/bulk-assign', [\App\Http\Controllers\Unified\SubtaskManagementController::class, 'bulkAssignSubtasks']);
        Route::post('/reorder', [\App\Http\Controllers\Unified\SubtaskManagementController::class, 'reorderSubtasks']);
    });

    // ========================================
    // TASK COMMENTS - API ENDPOINTS
    // ========================================
    Route::prefix('task-comments')->middleware(['ability:tenant'])->group(function () {
        Route::get('/task/{taskId}', [\App\Http\Controllers\Unified\TaskCommentManagementController::class, 'getCommentsForTask']);
        Route::post('/', [\App\Http\Controllers\Unified\TaskCommentManagementController::class, 'createComment']);
        Route::get('/{id}', [\App\Http\Controllers\Unified\TaskCommentManagementController::class, 'getComment']);
        Route::put('/{id}', [\App\Http\Controllers\Unified\TaskCommentManagementController::class, 'updateComment']);
        Route::delete('/{id}', [\App\Http\Controllers\Unified\TaskCommentManagementController::class, 'deleteComment']);
        Route::patch('/{id}/pin', [\App\Http\Controllers\Unified\TaskCommentManagementController::class, 'togglePinComment']);
        Route::get('/task/{taskId}/stats', [\App\Http\Controllers\Unified\TaskCommentManagementController::class, 'getCommentStatistics']);
    });

    // ========================================
    // TASK ATTACHMENTS - API ENDPOINTS
    // ========================================
    Route::prefix('task-attachments')->middleware(['ability:tenant'])->group(function () {
        Route::get('/task/{taskId}', [\App\Http\Controllers\Unified\TaskAttachmentManagementController::class, 'getAttachmentsForTask']);
        Route::post('/', [\App\Http\Controllers\Unified\TaskAttachmentManagementController::class, 'uploadAttachment']);
        Route::get('/{id}', [\App\Http\Controllers\Unified\TaskAttachmentManagementController::class, 'getAttachment']);
        Route::put('/{id}', [\App\Http\Controllers\Unified\TaskAttachmentManagementController::class, 'updateAttachment']);
        Route::delete('/{id}', [\App\Http\Controllers\Unified\TaskAttachmentManagementController::class, 'deleteAttachment']);
        Route::get('/{id}/download', [\App\Http\Controllers\Unified\TaskAttachmentManagementController::class, 'downloadAttachment']);
        Route::get('/{id}/preview', [\App\Http\Controllers\Unified\TaskAttachmentManagementController::class, 'previewAttachment']);
        Route::post('/{id}/versions', [\App\Http\Controllers\Unified\TaskAttachmentManagementController::class, 'uploadVersion']);
        Route::get('/task/{taskId}/stats', [\App\Http\Controllers\Unified\TaskAttachmentManagementController::class, 'getAttachmentStatistics']);
    });

    // ========================================
    Route::prefix('clients')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ClientsController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\ClientsController::class, 'store']);
        Route::get('/{client}', [\App\Http\Controllers\Api\ClientsController::class, 'show']);
        Route::put('/{client}', [\App\Http\Controllers\Api\ClientsController::class, 'update']);
        Route::delete('/{client}', [\App\Http\Controllers\Api\ClientsController::class, 'destroy']);
        Route::patch('/{client}/lifecycle-stage', [\App\Http\Controllers\Api\ClientsController::class, 'updateLifecycleStage']);
    });

    // ========================================
    // QUOTES API ENDPOINTS
    // ========================================
    Route::prefix('quotes')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\QuotesController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\QuotesController::class, 'store']);
        Route::get('/{quote}', [\App\Http\Controllers\Api\QuotesController::class, 'show']);
        Route::put('/{quote}', [\App\Http\Controllers\Api\QuotesController::class, 'update']);
        Route::delete('/{quote}', [\App\Http\Controllers\Api\QuotesController::class, 'destroy']);
        Route::post('/{quote}/send', [\App\Http\Controllers\Api\QuotesController::class, 'send']);
        Route::post('/{quote}/accept', [\App\Http\Controllers\Api\QuotesController::class, 'accept']);
        Route::post('/{quote}/reject', [\App\Http\Controllers\Api\QuotesController::class, 'reject']);
    });

    // ========================================
    // DOCUMENTS API ENDPOINTS
    // ========================================
    Route::prefix('documents')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\DocumentsController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\DocumentsController::class, 'store']);
        Route::get('/{document}', [\App\Http\Controllers\Api\DocumentsController::class, 'show']);
        Route::put('/{document}', [\App\Http\Controllers\Api\DocumentsController::class, 'update']);
        Route::delete('/{document}', [\App\Http\Controllers\Api\DocumentsController::class, 'destroy']);
        Route::get('/approvals', [\App\Http\Controllers\Api\DocumentsController::class, 'approvals']);
    });

    // ========================================
    // TEMPLATES API ENDPOINTS
    // ========================================
    Route::prefix('templates')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\TemplatesController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\TemplatesController::class, 'store']);
        Route::get('/{template}', [\App\Http\Controllers\Api\TemplatesController::class, 'show']);
        Route::put('/{template}', [\App\Http\Controllers\Api\TemplatesController::class, 'update']);
        Route::delete('/{template}', [\App\Http\Controllers\Api\TemplatesController::class, 'destroy']);
        Route::get('/library', [\App\Http\Controllers\Api\TemplatesController::class, 'library']);
        Route::get('/builder', [\App\Http\Controllers\Api\TemplatesController::class, 'builder']);
    });

    // ========================================
    // USER MANAGEMENT API ENDPOINTS (TENANT-SCOPED)
    // ========================================
    Route::prefix('app/users')->middleware(['ability:tenant', 'tenant.scope'])->group(function () {
        // User management routes - using existing UserController
        Route::get('/', [\App\Http\Controllers\UserController::class, 'index'])
            ->middleware('can:users.view');
        Route::post('/', [\App\Http\Controllers\UserController::class, 'store'])
            ->middleware('can:users.create');
        Route::get('/{user}', [\App\Http\Controllers\UserController::class, 'show'])
            ->middleware('can:users.view');
        Route::put('/{user}', [\App\Http\Controllers\UserController::class, 'update'])
            ->middleware('can:users.update');
        Route::delete('/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])
            ->middleware('can:users.delete');
    });

    // ========================================
    // ADMIN USER MANAGEMENT API ENDPOINTS (CROSS-TENANT)
    // ========================================
    Route::prefix('admin/users')->middleware(['ability:admin'])->group(function () {
        // Admin user management routes - using existing UserController
        Route::get('/', [\App\Http\Controllers\UserController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\UserController::class, 'store']);
        Route::get('/{user}', [\App\Http\Controllers\UserController::class, 'show']);
        Route::put('/{user}', [\App\Http\Controllers\UserController::class, 'update']);
        Route::delete('/{user}', [\App\Http\Controllers\UserController::class, 'destroy']);
    });

    // ========================================
    // ADMIN DASHBOARD API ENDPOINTS
    // ========================================
    Route::prefix('admin/dashboard')->middleware(['ability:admin'])->group(function () {
        Route::get('/summary', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_users' => 0,
                    'total_projects' => 0,
                    'total_tasks' => 0,
                    'active_sessions' => 0
                ]
            ]);
        });
        Route::get('/charts', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'chart_data' => []
                ]
            ]);
        });
        Route::get('/activity', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'activities' => []
                ]
            ]);
        });
        Route::get('/export', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'export_url' => '/admin/dashboard/export/download'
                ]
            ]);
        });
    });

    // ========================================
    // BADGES API ENDPOINTS
    // ========================================
    Route::prefix('badges')->group(function () {
        Route::get('/{id}', function ($id) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'name' => 'Sample Badge',
                    'description' => 'This is a sample badge',
                    'icon' => 'fas fa-star',
                    'color' => 'gold',
                    'earned' => true,
                    'earned_at' => now()->toISOString()
                ]
            ]);
        });
        
        Route::post('/{id}/toggle', function ($id) {
            return response()->json([
                'success' => true,
                'message' => 'Badge toggled successfully',
                'data' => [
                    'id' => $id,
                    'status' => 'toggled'
                ]
            ]);
        });
    });

    // ========================================
    // USER PREFERENCES API ENDPOINTS
    // ========================================
    Route::prefix('user-preferences')->group(function () {
        Route::post('/pin', function () {
            return response()->json([
                'success' => true,
                'message' => 'Preference updated successfully',
                'data' => [
                    'pinned' => true,
                    'updated_at' => now()->toISOString()
                ]
            ]);
        });
        
        Route::get('/dashboard', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'layout' => 'default',
                    'widgets' => [
                        'recent_projects' => true,
                        'recent_tasks' => true,
                        'activity_feed' => true,
                        'quick_stats' => true
                    ],
                    'theme' => 'light',
                    'density' => 'normal'
                ]
            ]);
        });
        
        Route::put('/dashboard', function () {
            return response()->json([
                'success' => true,
                'message' => 'Dashboard preferences updated successfully',
                'data' => [
                    'updated_at' => now()->toISOString()
                ]
            ]);
        });
    });
});

// ========================================
// USERS API ENDPOINTS (Unified)
// ========================================
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('users')->group(function () {
        Route::get('/', [\App\Http\Controllers\Unified\UserManagementController::class, 'getUsers']);
        Route::post('/', [\App\Http\Controllers\Unified\UserManagementController::class, 'createUser']);
        Route::get('/stats', [\App\Http\Controllers\Unified\UserManagementController::class, 'getUserStats']);
        Route::get('/search', [\App\Http\Controllers\Unified\UserManagementController::class, 'searchUsers']);
        Route::post('/bulk-delete', [\App\Http\Controllers\Unified\UserManagementController::class, 'bulkDeleteUsers']);
        Route::get('/{id}', [\App\Http\Controllers\Unified\UserManagementController::class, 'getUser']);
        Route::put('/{id}', [\App\Http\Controllers\Unified\UserManagementController::class, 'updateUser']);
        Route::delete('/{id}', [\App\Http\Controllers\Unified\UserManagementController::class, 'deleteUser']);
        Route::put('/{id}/status', [\App\Http\Controllers\Unified\UserManagementController::class, 'toggleUserStatus']);
        Route::put('/{id}/role', [\App\Http\Controllers\Unified\UserManagementController::class, 'updateUserRole']);
        Route::get('/{id}/preferences', [\App\Http\Controllers\Unified\UserManagementController::class, 'getUserPreferences']);
        Route::put('/{id}/preferences', [\App\Http\Controllers\Unified\UserManagementController::class, 'updateUserPreferences']);
    });
});

// ========================================
// USER PREFERENCES API ENDPOINTS
// ========================================
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('user/preferences')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\UserPreferencesController::class, 'getPreferences']);
        Route::post('/theme', [App\Http\Controllers\Api\UserPreferencesController::class, 'updateTheme']);
        Route::put('/', [App\Http\Controllers\Api\UserPreferencesController::class, 'updatePreferences']);
    });

    // Dashboard Analytics API
    Route::prefix('dashboard-analytics')->group(function () {
        Route::get('/analytics', [App\Http\Controllers\Api\DashboardAnalyticsController::class, 'analytics']);
        Route::get('/metrics', [App\Http\Controllers\Api\DashboardAnalyticsController::class, 'metrics']);
    });

    // Dashboard API v1 - REMOVED (Trùng lặp với routes/api_v1.php)

    // Document Management API
    Route::prefix('v1/documents')->group(function () {
        Route::post('/', [App\Http\Controllers\Api\DocumentController::class, 'upload']);
        Route::get('/', [App\Http\Controllers\Api\DocumentController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Api\DocumentController::class, 'show']);
        Route::get('/{id}/download', [App\Http\Controllers\Api\DocumentController::class, 'download']);
        Route::post('/{id}/versions', [App\Http\Controllers\Api\DocumentController::class, 'uploadVersion']);
        Route::post('/{id}/revert', [App\Http\Controllers\Api\DocumentController::class, 'revertVersion']);
        Route::get('/analytics', [App\Http\Controllers\Api\DocumentController::class, 'analytics']);
    });

// CSV Import/Export routes - Admin only
Route::prefix('admin/csv')->middleware(['auth:sanctum', \App\Http\Middleware\AdminOnlyMiddleware::class])->group(function () {
    Route::get('/export/users', [\App\Http\Controllers\Admin\CsvController::class, 'exportUsers'])->name('admin.csv.export.users');
    Route::get('/export/projects', [\App\Http\Controllers\Admin\CsvController::class, 'exportProjects'])->name('admin.csv.export.projects');
    Route::post('/import/users', [\App\Http\Controllers\Admin\CsvController::class, 'importUsers'])->name('admin.csv.import.users');
    Route::post('/import/projects', [\App\Http\Controllers\Admin\CsvController::class, 'importProjects'])->name('admin.csv.import.projects');
    Route::get('/template', [\App\Http\Controllers\Admin\CsvController::class, 'getImportTemplate'])->name('admin.csv.template');
    Route::post('/validate', [\App\Http\Controllers\Admin\CsvController::class, 'validateCsv'])->name('admin.csv.validate');
});


// Queue management routes - Admin only
Route::prefix('admin/queue')->middleware(['auth:sanctum', \App\Http\Middleware\AdminOnlyMiddleware::class])->group(function () {
    Route::get('/stats', [\App\Http\Controllers\QueueController::class, 'getStats'])->name('admin.queue.stats');
    Route::get('/metrics', [\App\Http\Controllers\QueueController::class, 'getMetrics'])->name('admin.queue.metrics');
    Route::get('/workers', [\App\Http\Controllers\QueueController::class, 'getWorkers'])->name('admin.queue.workers');
    Route::post('/retry', [\App\Http\Controllers\QueueController::class, 'retryFailedJobs'])->name('admin.queue.retry');
    Route::post('/clear', [\App\Http\Controllers\QueueController::class, 'clearFailedJobs'])->name('admin.queue.clear');
});

// Performance monitoring routes - Admin only
Route::prefix('admin/performance')->middleware(['auth:sanctum', \App\Http\Middleware\AdminOnlyMiddleware::class])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\PerformanceController::class, 'getDashboard'])->name('admin.performance.dashboard');
    Route::get('/stats', [\App\Http\Controllers\PerformanceController::class, 'getPerformanceStats'])->name('admin.performance.stats');
    Route::get('/memory', [\App\Http\Controllers\PerformanceController::class, 'getMemoryStats'])->name('admin.performance.memory');
    Route::get('/network', [\App\Http\Controllers\PerformanceController::class, 'getNetworkStats'])->name('admin.performance.network');
    Route::get('/recommendations', [\App\Http\Controllers\PerformanceController::class, 'getRecommendations'])->name('admin.performance.recommendations');
    Route::get('/thresholds', [\App\Http\Controllers\PerformanceController::class, 'getThresholds'])->name('admin.performance.thresholds');
    Route::post('/thresholds', [\App\Http\Controllers\PerformanceController::class, 'setThresholds'])->name('admin.performance.set.thresholds');
    Route::post('/page-load', [\App\Http\Controllers\PerformanceController::class, 'recordPageLoadTime'])->name('admin.performance.page.load');
    Route::post('/api-response', [\App\Http\Controllers\PerformanceController::class, 'recordApiResponseTime'])->name('admin.performance.api.response');
    Route::post('/memory', [\App\Http\Controllers\PerformanceController::class, 'recordMemoryUsage'])->name('admin.performance.memory.record');
    Route::post('/network-monitor', [\App\Http\Controllers\PerformanceController::class, 'monitorNetworkEndpoint'])->name('admin.performance.network.monitor');
    Route::get('/realtime', [\App\Http\Controllers\PerformanceController::class, 'getRealTimeMetrics'])->name('admin.performance.realtime');
    Route::post('/clear', [\App\Http\Controllers\PerformanceController::class, 'clearData'])->name('admin.performance.clear');
    Route::get('/export', [\App\Http\Controllers\PerformanceController::class, 'exportData'])->name('admin.performance.export');
    Route::post('/gc', [\App\Http\Controllers\PerformanceController::class, 'forceGarbageCollection'])->name('admin.performance.gc');
    Route::post('/test-connectivity', [\App\Http\Controllers\PerformanceController::class, 'testConnectivity'])->name('admin.performance.test.connectivity');
    Route::get('/network-health', [\App\Http\Controllers\PerformanceController::class, 'getNetworkHealthStatus'])->name('admin.performance.network.health');
});

// I18n routes (public)
Route::prefix('i18n')->group(function () {
    Route::get('/config', [\App\Http\Controllers\Api\I18nController::class, 'getConfiguration'])->name('i18n.config');
    Route::post('/language', [\App\Http\Controllers\Api\I18nController::class, 'setLanguage'])->name('i18n.language');
    Route::post('/timezone', [\App\Http\Controllers\Api\I18nController::class, 'setTimezone'])->name('i18n.timezone');
    Route::post('/format/date', [\App\Http\Controllers\Api\I18nController::class, 'formatDate'])->name('i18n.format.date');
    Route::post('/format/time', [\App\Http\Controllers\Api\I18nController::class, 'formatTime'])->name('i18n.format.time');
    Route::post('/format/datetime', [\App\Http\Controllers\Api\I18nController::class, 'formatDateTime'])->name('i18n.format.datetime');
    Route::post('/format/number', [\App\Http\Controllers\Api\I18nController::class, 'formatNumber'])->name('i18n.format.number');
    Route::post('/format/currency', [\App\Http\Controllers\Api\I18nController::class, 'formatCurrency'])->name('i18n.format.currency');
    Route::get('/locale', [\App\Http\Controllers\Api\I18nController::class, 'getCurrentLocale'])->name('i18n.locale');
});

// Close the auth:sanctum group
});