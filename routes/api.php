<?php declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserControllerV2;
use Src\InteractionLogs\Controllers\InteractionLogController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ComponentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskAssignmentController;
use Src\CoreProject\Controllers\WorkTemplateController;
use Src\CoreProject\Controllers\BaselineController;
use Src\WorkTemplate\Controllers\TemplateController;
use Src\RBAC\Controllers\AuthController;
use App\Http\Controllers\Api\SSOController;
use App\Http\Controllers\Api\BulkOperationsController;
use App\Http\Controllers\Api\SecurityDashboardController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Auth\PasswordResetController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

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

// Simple login endpoint (no CSRF)
Route::post('/login', function(Request $request) {
    $email = $request->input('email');
    $password = $request->input('password');
    
    // Demo users
    $demoUsers = [
        'superadmin@zena.com' => ['name' => 'Super Admin', 'role' => 'super_admin'],
        'pm@zena.com' => ['name' => 'Project Manager', 'role' => 'project_manager'],
        'designer@zena.com' => ['name' => 'Designer', 'role' => 'designer'],
        'site@zena.com' => ['name' => 'Site Engineer', 'role' => 'site_engineer'],
        'qc@zena.com' => ['name' => 'QC Engineer', 'role' => 'qc_engineer'],
        'procurement@zena.com' => ['name' => 'Procurement', 'role' => 'procurement'],
        'finance@zena.com' => ['name' => 'Finance', 'role' => 'finance'],
        'client@zena.com' => ['name' => 'Client', 'role' => 'client'],
    ];
    
    if ($password === 'zena1234' && isset($demoUsers[$email])) {
        $userData = $demoUsers[$email];
        
        // Create a simple user object for session
        $user = new \stdClass();
        $user->id = rand(1000, 9999);
        $user->name = $userData['name'];
        $user->email = $email;
        $user->role = $userData['role'];
        
        // Store user data in session
        session(['user' => $user]);
        
        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công!',
            'redirect' => '/dashboard',
            'user' => $user
        ]);
    }
    
    return response()->json([
        'success' => false,
        'message' => 'Email hoặc mật khẩu không đúng'
    ], 401);
});

Route::get('/v1/health', function () {
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

Route::get('/status', function () {
    return response()->json([
        'status' => 'running',
        'message' => 'ZENA Manage API is running',
        'timestamp' => now()->toISOString()
    ]);
});

// API information endpoint
Route::get('/info', function () {
    return response()->json([
        'status' => 'success',
        'data' => [
            'api_version' => 'v1',
            'service' => 'Z.E.N.A Project Management API',
            'environment' => app()->environment(),
            'timestamp' => now()->toISOString(),
            'features' => [
                'authentication' => 'JWT',
                'response_format' => 'JSend',
                'pagination' => 'cursor_based',
                'localization' => 'vi'
            ]
        ]
    ]);
});

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
*/

// API v1 Routes (prefix removed - already handled by RouteServiceProvider)
Route::group([], function () {
    // Simple documents endpoint without any middleware
    Route::get('/documents-simple', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Documents endpoint working',
            'data' => []
        ]);
    });

    // Projects endpoint for form dropdown
    Route::get('/projects-simple', function () {
    try {
        // Load real projects from database using unified Project model
        $projects = \App\Models\Project::select('id', 'name', 'code', 'description', 'status')
            ->where('status', '!=', 'archived') // Exclude archived projects
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'code' => $project->code,
                    'description' => $project->description ?: 'No description',
                    'status' => $project->status
                ];
            });
        
        // If no projects exist, return demo projects
        if ($projects->isEmpty()) {
            $projects = collect([
                ['id' => 1, 'name' => 'Project Alpha', 'description' => 'First project'],
                ['id' => 2, 'name' => 'Project Beta', 'description' => 'Second project'],
                ['id' => 3, 'name' => 'Project Gamma', 'description' => 'Third project'],
                ['id' => 4, 'name' => 'Project Delta', 'description' => 'Fourth project'],
                ['id' => 5, 'name' => 'Project Epsilon', 'description' => 'Fifth project']
            ]);
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Projects loaded successfully',
            'data' => $projects
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to load projects: ' . $e->getMessage()
        ], 500);
    }
});

Route::post('/v1/upload-document', function (Request $request) {
    try {
        // Debug: Log all request data
        \Log::info('Upload request data:', [
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'project_id' => $request->input('project_id'),
            'document_type' => $request->input('document_type'),
            'version' => $request->input('version'),
            'has_file' => $request->hasFile('file'),
            'file_info' => $request->file('file') ? [
                'name' => $request->file('file')->getClientOriginalName(),
                'size' => $request->file('file')->getSize(),
                'mime' => $request->file('file')->getMimeType(),
                'is_valid' => $request->file('file')->isValid(),
                'error' => $request->file('file')->getError()
            ] : null
        ]);
        
        // Simple upload without validation
        $title = $request->input('title', 'Untitled Document');
        $description = $request->input('description', '');
        $projectId = $request->input('project_id', null);
        $documentType = $request->input('document_type', 'other');
        $version = $request->input('version', '1.0');
        $file = $request->file('file');
        
        // Enhanced file validation
        if (!$request->hasFile('file')) {
            return response()->json([
                'status' => 'error',
                'message' => 'No file uploaded - hasFile() returned false'
            ], 400);
        }
        
        if (!$file) {
            return response()->json([
                'status' => 'error',
                'message' => 'No file uploaded - file() returned null'
            ], 400);
        }
        
        if (!$file->isValid()) {
            return response()->json([
                'status' => 'error',
                'message' => 'File upload failed - isValid() returned false. Error: ' . $file->getError()
            ], 400);
        }
        
        // Get file information safely
        $fileName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $fileMimeType = $file->getMimeType();
        
        // Enhanced file name validation
        if (empty($fileName)) {
            // Try to get file name from other sources
            $fileName = $file->getFilename();
            if (empty($fileName)) {
                $fileName = 'uploaded_file_' . time();
            }
        }
        
        // Additional validation for file name
        if (empty($fileName) || $fileName === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'File name is empty or invalid'
            ], 400);
        }
        
        // Store file (simple storage)
        $storedPath = $file->store('documents', 'public');
        
        return response()->json([
            'status' => 'success',
            'message' => 'Document uploaded successfully',
            'data' => [
                'id' => rand(1000, 9999),
                'title' => $title,
                'description' => $description,
                'project_id' => $projectId,
                'document_type' => $documentType,
                'version' => $version,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'file_mime_type' => $fileMimeType,
                'stored_path' => $storedPath,
                'uploaded_at' => now()->toISOString()
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Upload failed: ' . $e->getMessage()
        ], 500);
    }
});

// Simple test route
Route::get('test-simple', function () {
    return response()->json(['status' => 'success', 'message' => 'Simple test working']);
});

Route::group([], function () {
    
    /*
    |--------------------------------------------------------------------------
    | Authentication Routes (Public)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);
        
        // SSO routes
        Route::prefix('oidc')->group(function () {
            Route::get('providers', [SSOController::class, 'getOIDCProviders']);
            Route::get('{provider}/initiate', [SSOController::class, 'initiateOIDC']);
            Route::post('{provider}/callback', [SSOController::class, 'handleOIDCCallback']);
        });
        
        Route::prefix('saml')->group(function () {
            Route::get('providers', [SSOController::class, 'getSAMLProviders']);
            Route::get('{provider}/initiate', [SSOController::class, 'initiateSAML']);
            Route::post('{provider}/callback', [SSOController::class, 'handleSAMLCallback']);
            Route::post('{provider}/logout', [SSOController::class, 'initiateSAMLLogout']);
        });
        
        // Generic SSO routes
        Route::get('sso/providers', [SSOController::class, 'getSSOProviders']);
        Route::get('sso/config', [SSOController::class, 'getSSOConfig']);
        Route::post('sso/test', [SSOController::class, 'testSSOConnection']);
        
        // Bulk operations routes
        Route::prefix('bulk')->group(function () {
            // User bulk operations
            Route::post('users/create', [BulkOperationsController::class, 'bulkCreateUsers']);
            Route::post('users/update', [BulkOperationsController::class, 'bulkUpdateUsers']);
            Route::post('users/delete', [BulkOperationsController::class, 'bulkDeleteUsers']);
            
            // Project bulk operations
            Route::post('projects/create', [BulkOperationsController::class, 'bulkCreateProjects']);
            Route::post('projects/update', [BulkOperationsController::class, 'bulkUpdateProjects']);
            
            // Task bulk operations
            Route::post('tasks/create', [BulkOperationsController::class, 'bulkCreateTasks']);
            Route::post('tasks/update-status', [BulkOperationsController::class, 'bulkUpdateTaskStatus']);
            
            // Generic bulk operations
            Route::post('assign-users-to-projects', [BulkOperationsController::class, 'bulkAssignUsersToProjects']);
            
            // Import/Export operations
            Route::get('export/users', [BulkOperationsController::class, 'exportUsers']);
            Route::get('export/projects', [BulkOperationsController::class, 'exportProjects']);
            Route::get('export/tasks', [BulkOperationsController::class, 'exportTasks']);
            
            Route::post('import/users', [BulkOperationsController::class, 'importUsers']);
            Route::post('import/projects', [BulkOperationsController::class, 'importProjects']);
            Route::post('import/tasks', [BulkOperationsController::class, 'importTasks']);
            
            Route::get('template/{type}', [BulkOperationsController::class, 'getImportTemplate']);
            Route::get('download/{filename}', [BulkOperationsController::class, 'downloadFile']);
            
        // Queue operations
        Route::post('queue', [BulkOperationsController::class, 'queueBulkOperation']);
        Route::get('status/{operation_id}', [BulkOperationsController::class, 'getBulkOperationStatus']);
        });
        
        // Security dashboard routes
        Route::prefix('security')->group(function () {
            Route::get('overview', [SecurityDashboardController::class, 'getSecurityOverview']);
            Route::get('events/timeline', [SecurityDashboardController::class, 'getSecurityEventsTimeline']);
            Route::get('failed-logins', [SecurityDashboardController::class, 'getFailedLoginAttempts']);
            Route::get('suspicious-activities', [SecurityDashboardController::class, 'getSuspiciousActivities']);
            Route::get('user-status', [SecurityDashboardController::class, 'getUserSecurityStatus']);
            Route::get('recommendations', [SecurityDashboardController::class, 'getSecurityRecommendations']);
            Route::get('alerts', [SecurityDashboardController::class, 'getSecurityAlerts']);
            Route::get('metrics', [SecurityDashboardController::class, 'getSecurityMetrics']);
            Route::get('export-report', [SecurityDashboardController::class, 'exportSecurityReport']);
        });
        
        // Protected auth routes
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::post('check-permission', [AuthController::class, 'checkPermission']);
    });
    
    /*
    |--------------------------------------------------------------------------
    | Protected API Routes
    |--------------------------------------------------------------------------
    */
    Route::group(['middleware' => ['auth:sanctum', 'tenant.isolation', 'rbac']], function () {
        
        // Test endpoint without authentication
        Route::get('test', function () {
            return response()->json([
                'status' => 'success',
                'message' => 'API is working',
                'timestamp' => now()
            ]);
        });
        
        // Simple documents endpoint without authentication
        Route::get('documents-simple', function () {
            return response()->json([
                'status' => 'success',
                'message' => 'Documents endpoint working',
                'data' => []
            ]);
        });

        // Simple documents endpoint without authentication (main)
        Route::get('documents', function () {
            return response()->json([
                'status' => 'success',
                'message' => 'Documents endpoint working',
                'data' => []
            ]);
        });
        
        // Simple documents POST endpoint without authentication
        Route::post('documents', function (Request $request) {
            try {
                $title = $request->input('title');
                $description = $request->input('description');
                $projectId = $request->input('project_id');
                $documentType = $request->input('document_type');
                $version = $request->input('version');
                $file = $request->file('file');
                
                // Validate required fields
                if (!$title) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Title is required'
                    ], 400);
                }
                
                if (!$documentType) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Document type is required'
                    ], 400);
                }
                
                if (!$file) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'File is required'
                    ], 400);
                }
                
                // Process file upload
                $fileName = $file->getClientOriginalName();
                $fileSize = $file->getSize();
                $fileMimeType = $file->getMimeType();
                
                // Store file (simple storage)
                $storedPath = $file->store('documents', 'public');
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Document uploaded successfully',
                    'data' => [
                        'id' => rand(1000, 9999),
                        'title' => $title,
                        'description' => $description,
                        'project_id' => $projectId,
                        'document_type' => $documentType,
                        'version' => $version ?: '1.0',
                        'file_name' => $fileName,
                        'file_size' => $fileSize,
                        'file_mime_type' => $fileMimeType,
                        'stored_path' => $storedPath,
                        'uploaded_at' => now()->toISOString()
                    ]
                ]);
                
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Upload failed: ' . $e->getMessage()
                ], 500);
            }
        });
        
        // Simple Document Controller Routes
        Route::apiResource('documents-simple', App\Http\Controllers\Api\DocumentController::class);
        
        /*
        |--------------------------------------------------------------------------
        | User Management Routes
        |--------------------------------------------------------------------------
        */
        Route::apiResource('users', UserController::class);
        Route::prefix('users')->group(function () {
            Route::get('profile', [UserController::class, 'profile']);
            Route::put('profile', [UserController::class, 'updateProfile']);
        });

        /*
        |--------------------------------------------------------------------------
        | Project Management Routes
        |--------------------------------------------------------------------------
        */
        // Unified Project Management Routes
        Route::apiResource('projects', \App\Http\Controllers\Api\ProjectController::class);
        Route::prefix('projects')->group(function () {
            Route::post('{project}/status', [\App\Http\Controllers\Api\ProjectController::class, 'updateStatus']);
            Route::get('statistics', [\App\Http\Controllers\Api\ProjectController::class, 'statistics']);
            Route::get('dropdown', [\App\Http\Controllers\Api\ProjectController::class, 'dropdown']);
            
            // Project Milestones Routes
            Route::apiResource('milestones', \App\Http\Controllers\Api\ProjectMilestoneController::class);
            Route::prefix('milestones')->group(function () {
                Route::post('{milestone}/mark-completed', [\App\Http\Controllers\Api\ProjectMilestoneController::class, 'markCompleted']);
                Route::post('{milestone}/mark-cancelled', [\App\Http\Controllers\Api\ProjectMilestoneController::class, 'markCancelled']);
                Route::post('reorder', [\App\Http\Controllers\Api\ProjectMilestoneController::class, 'reorder']);
            });
        });

        // Project Templates Routes
        Route::apiResource('project-templates', \App\Http\Controllers\Api\ProjectTemplateController::class);
        Route::prefix('project-templates')->group(function () {
            Route::post('{template}/create-project', [\App\Http\Controllers\Api\ProjectTemplateController::class, 'createProject']);
            Route::post('{template}/duplicate', [\App\Http\Controllers\Api\ProjectTemplateController::class, 'duplicate']);
            Route::get('categories', [\App\Http\Controllers\Api\ProjectTemplateController::class, 'categories']);
        });

        // Project Analytics Routes
        Route::prefix('analytics')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\Api\ProjectAnalyticsController::class, 'dashboardAnalytics']);
            Route::get('projects/{project}', [\App\Http\Controllers\Api\ProjectAnalyticsController::class, 'projectAnalytics']);
            Route::post('search', [\App\Http\Controllers\Api\ProjectAnalyticsController::class, 'advancedSearch']);
            Route::get('search-suggestions', [\App\Http\Controllers\Api\ProjectAnalyticsController::class, 'searchSuggestions']);
            Route::get('filter-options', [\App\Http\Controllers\Api\ProjectAnalyticsController::class, 'filterOptions']);
        });

        // Real-time Collaboration Routes
        Route::prefix('realtime')->group(function () {
            Route::get('projects/{project}/activity-feed', [\App\Http\Controllers\Api\RealTimeController::class, 'getProjectActivityFeed']);
            Route::get('projects/{project}/notification-history', [\App\Http\Controllers\Api\RealTimeController::class, 'getNotificationHistory']);
            Route::get('projects/{project}/activity-statistics', [\App\Http\Controllers\Api\RealTimeController::class, 'getActivityStatistics']);
            Route::get('user/activities', [\App\Http\Controllers\Api\RealTimeController::class, 'getUserRecentActivities']);
            Route::get('user/notification-preferences', [\App\Http\Controllers\Api\RealTimeController::class, 'getNotificationPreferences']);
            Route::put('user/notification-preferences', [\App\Http\Controllers\Api\RealTimeController::class, 'updateNotificationPreferences']);
            Route::post('send-notification', [\App\Http\Controllers\Api\RealTimeController::class, 'sendCustomNotification']);
            Route::post('websocket-auth', [\App\Http\Controllers\Api\RealTimeController::class, 'getWebSocketAuth']);
            Route::get('connection-status', [\App\Http\Controllers\Api\RealTimeController::class, 'getConnectionStatus']);
        });

        // Advanced Integrations Routes
        Route::prefix('integrations')->group(function () {
            // Calendar Integration Routes
            Route::prefix('calendar')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\CalendarIntegrationController::class, 'index']);
                Route::post('google/connect', [\App\Http\Controllers\Api\CalendarIntegrationController::class, 'connectGoogle']);
                Route::post('outlook/connect', [\App\Http\Controllers\Api\CalendarIntegrationController::class, 'connectOutlook']);
                Route::post('{integration}/sync', [\App\Http\Controllers\Api\CalendarIntegrationController::class, 'syncCalendar']);
                Route::delete('{integration}', [\App\Http\Controllers\Api\CalendarIntegrationController::class, 'disconnect']);
                Route::get('overview', [\App\Http\Controllers\Api\CalendarIntegrationController::class, 'getOverview']);
            });

            // Cloud Storage Routes
            Route::prefix('storage')->group(function () {
                Route::post('upload/s3', [\App\Http\Controllers\Api\CloudStorageController::class, 'uploadToS3']);
                Route::post('upload/gcs', [\App\Http\Controllers\Api\CloudStorageController::class, 'uploadToGCS']);
                Route::post('upload/multiple', [\App\Http\Controllers\Api\CloudStorageController::class, 'uploadToMultiple']);
                Route::delete('s3/{path}', [\App\Http\Controllers\Api\CloudStorageController::class, 'deleteFromS3']);
                Route::delete('gcs/{path}', [\App\Http\Controllers\Api\CloudStorageController::class, 'deleteFromGCS']);
                Route::get('s3/url/{path}', [\App\Http\Controllers\Api\CloudStorageController::class, 'getS3Url']);
                Route::get('gcs/url/{path}', [\App\Http\Controllers\Api\CloudStorageController::class, 'getGCSUrl']);
                Route::get('stats/{provider}', [\App\Http\Controllers\Api\CloudStorageController::class, 'getStats']);
            });

            // Third-party API Routes
            Route::prefix('third-party')->group(function () {
                Route::post('slack/notify', [\App\Http\Controllers\Api\ThirdPartyController::class, 'sendSlackNotification']);
                Route::post('teams/notify', [\App\Http\Controllers\Api\ThirdPartyController::class, 'sendTeamsNotification']);
                Route::post('discord/notify', [\App\Http\Controllers\Api\ThirdPartyController::class, 'sendDiscordNotification']);
                Route::post('github/create-issue', [\App\Http\Controllers\Api\ThirdPartyController::class, 'createGitHubIssue']);
                Route::post('jira/create-issue', [\App\Http\Controllers\Api\ThirdPartyController::class, 'createJiraIssue']);
                Route::post('trello/create-card', [\App\Http\Controllers\Api\ThirdPartyController::class, 'createTrelloCard']);
                Route::post('asana/create-task', [\App\Http\Controllers\Api\ThirdPartyController::class, 'createAsanaTask']);
                Route::post('webhook/send', [\App\Http\Controllers\Api\ThirdPartyController::class, 'sendWebhook']);
                Route::post('webhook/test', [\App\Http\Controllers\Api\ThirdPartyController::class, 'testWebhook']);
                Route::get('weather/{city}', [\App\Http\Controllers\Api\ThirdPartyController::class, 'getWeather']);
                Route::get('currency/rates', [\App\Http\Controllers\Api\ThirdPartyController::class, 'getCurrencyRates']);
            });
        });

        // Performance Optimization Routes
        Route::prefix('performance')->group(function () {
            // Cache Management Routes
            Route::prefix('cache')->group(function () {
                Route::get('stats', [\App\Http\Controllers\Api\PerformanceController::class, 'getCacheStats']);
                Route::post('flush', [\App\Http\Controllers\Api\PerformanceController::class, 'flushCache']);
                Route::post('flush/{tag}', [\App\Http\Controllers\Api\PerformanceController::class, 'flushCacheByTag']);
                Route::get('keys', [\App\Http\Controllers\Api\PerformanceController::class, 'getCacheKeys']);
                Route::delete('key/{key}', [\App\Http\Controllers\Api\PerformanceController::class, 'deleteCacheKey']);
            });

            // Database Optimization Routes
            Route::prefix('database')->group(function () {
                Route::post('optimize', [\App\Http\Controllers\Api\PerformanceController::class, 'optimizeDatabase']);
                Route::post('optimize/{table}', [\App\Http\Controllers\Api\PerformanceController::class, 'optimizeTable']);
                Route::get('stats', [\App\Http\Controllers\Api\PerformanceController::class, 'getDatabaseStats']);
                Route::get('slow-queries', [\App\Http\Controllers\Api\PerformanceController::class, 'getSlowQueries']);
                Route::post('create-indexes', [\App\Http\Controllers\Api\PerformanceController::class, 'createMissingIndexes']);
                Route::get('fragmentation', [\App\Http\Controllers\Api\PerformanceController::class, 'getTableFragmentation']);
            });

            // Performance Monitoring Routes
            Route::prefix('monitoring')->group(function () {
                Route::get('metrics', [\App\Http\Controllers\Api\PerformanceController::class, 'getSystemMetrics']);
                Route::get('trends', [\App\Http\Controllers\Api\PerformanceController::class, 'getPerformanceTrends']);
                Route::get('alerts', [\App\Http\Controllers\Api\PerformanceController::class, 'getPerformanceAlerts']);
                Route::get('health', [\App\Http\Controllers\Api\PerformanceController::class, 'getHealthStatus']);
            });

            // Rate Limiting Routes
            Route::prefix('rate-limit')->group(function () {
                Route::get('status', [\App\Http\Controllers\Api\PerformanceController::class, 'getRateLimitStatus']);
                Route::post('reset', [\App\Http\Controllers\Api\PerformanceController::class, 'resetRateLimit']);
                Route::get('stats', [\App\Http\Controllers\Api\PerformanceController::class, 'getRateLimitStats']);
            });

            // CDN Integration Routes
            Route::prefix('cdn')->group(function () {
                Route::post('purge', [\App\Http\Controllers\Api\PerformanceController::class, 'purgeCDNCache']);
                Route::post('purge/{provider}', [\App\Http\Controllers\Api\PerformanceController::class, 'purgeCDNCacheByProvider']);
                Route::post('upload', [\App\Http\Controllers\Api\PerformanceController::class, 'uploadToCDN']);
                Route::get('stats/{provider}', [\App\Http\Controllers\Api\PerformanceController::class, 'getCDNStats']);
                Route::get('health/{provider}', [\App\Http\Controllers\Api\PerformanceController::class, 'checkCDNHealth']);
                Route::get('config', [\App\Http\Controllers\Api\PerformanceController::class, 'getCDNConfig']);
                Route::get('url/{path}', [\App\Http\Controllers\Api\PerformanceController::class, 'generateCDNUrl']);
            });
        });

        // Mobile & Reporting Routes
        // Mobile App Routes - TODO: Create MobileController
        /*
        Route::prefix('mobile')->group(function () {
            // Mobile API Routes
            Route::get('dashboard', [\App\Http\Controllers\Api\MobileController::class, 'getDashboard']);
            Route::get('offline-data', [\App\Http\Controllers\Api\MobileController::class, 'getOfflineData']);
            Route::post('push-notification', [\App\Http\Controllers\Api\MobileController::class, 'sendPushNotification']);
            Route::get('search', [\App\Http\Controllers\Api\MobileController::class, 'search']);
            Route::get('analytics', [\App\Http\Controllers\Api\MobileController::class, 'getAnalytics']);
            Route::get('optimized-data', [\App\Http\Controllers\Api\MobileController::class, 'getOptimizedData']);
        });
        */

        // Advanced Reporting Routes - TODO: Create ReportingController
        /*
        Route::prefix('reports')->group(function () {
            Route::post('project-summary', [\App\Http\Controllers\Api\ReportingController::class, 'generateProjectSummary']);
            Route::post('task-analysis', [\App\Http\Controllers\Api\ReportingController::class, 'generateTaskAnalysis']);
            Route::post('user-productivity', [\App\Http\Controllers\Api\ReportingController::class, 'generateUserProductivity']);
            Route::post('financial', [\App\Http\Controllers\Api\ReportingController::class, 'generateFinancial']);
            Route::post('custom', [\App\Http\Controllers\Api\ReportingController::class, 'generateCustom']);
            Route::post('export', [\App\Http\Controllers\Api\ReportingController::class, 'exportData']);
            Route::get('templates', [\App\Http\Controllers\Api\ReportingController::class, 'getTemplates']);
            Route::get('history', [\App\Http\Controllers\Api\ReportingController::class, 'getHistory']);
            Route::delete('cleanup', [\App\Http\Controllers\Api\ReportingController::class, 'cleanupExpired']);
            Route::get('download/{filename}', [\App\Http\Controllers\Api\ReportingController::class, 'downloadReport']);
        });
        */


        // Custom Integrations Routes - DISABLED (Controller not implemented)
        /*
        Route::prefix('integrations')->group(function () {
            Route::post('custom', [\App\Http\Controllers\Api\CustomIntegrationController::class, 'createIntegration']);
            Route::put('custom/{id}', [\App\Http\Controllers\Api\CustomIntegrationController::class, 'updateIntegration']);
            Route::delete('custom/{id}', [\App\Http\Controllers\Api\CustomIntegrationController::class, 'deleteIntegration']);
            Route::get('custom', [\App\Http\Controllers\Api\CustomIntegrationController::class, 'getAllIntegrations']);
            Route::get('custom/{id}', [\App\Http\Controllers\Api\CustomIntegrationController::class, 'getIntegration']);
            Route::post('custom/{id}/test', [\App\Http\Controllers\Api\CustomIntegrationController::class, 'testIntegration']);
            Route::post('custom/{id}/execute', [\App\Http\Controllers\Api\CustomIntegrationController::class, 'executeIntegration']);
            Route::post('custom/{id}/webhook', [\App\Http\Controllers\Api\CustomIntegrationController::class, 'handleWebhook']);
            Route::get('custom/{id}/logs', [\App\Http\Controllers\Api\CustomIntegrationController::class, 'getLogs']);
            Route::get('custom/{id}/statistics', [\App\Http\Controllers\Api\CustomIntegrationController::class, 'getStatistics']);
        });
        */

        // API Documentation Routes - DISABLED (Controller not implemented)
        /*
        Route::prefix('docs')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\DocumentationController::class, 'getDocumentation']);
            Route::get('openapi', [\App\Http\Controllers\Api\DocumentationController::class, 'getOpenAPISpec']);
            Route::get('postman', [\App\Http\Controllers\Api\DocumentationController::class, 'getPostmanCollection']);
            Route::get('endpoints', [\App\Http\Controllers\Api\DocumentationController::class, 'getEndpointsByCategory']);
            Route::get('endpoint/{method}/{path}', [\App\Http\Controllers\Api\DocumentationController::class, 'getEndpointDocumentation']);
            Route::get('statistics', [\App\Http\Controllers\Api\DocumentationController::class, 'getAPIStatistics']);
            Route::get('schema', [\App\Http\Controllers\Api\DocumentationController::class, 'getSchemas']);
        });
        */
        
        Route::prefix('projects/{projectId}')->group(function () {
            Route::get('tasks', [TaskController::class, 'index']);
            Route::post('recalculate-progress', [ProjectController::class, 'recalculateProgress']);
            Route::post('recalculate-actual-cost', [ProjectController::class, 'recalculateActualCost']);
            
            // Project-specific baselines
            Route::apiResource('baselines', BaselineController::class)
                ->except(['index'])
                ->names([
                    'show' => 'project.baselines.show',
                    'store' => 'project.baselines.store', 
                    'update' => 'project.baselines.update',
                    'destroy' => 'project.baselines.destroy'
                ]);
            Route::get('baselines', [BaselineController::class, 'index'])->name('project.baselines.index');
            Route::post('baselines/from-current', [BaselineController::class, 'createFromCurrent'])->name('project.baselines.from-current');
            Route::get('baselines/report', [BaselineController::class, 'report'])->name('project.baselines.report');
            
            // Project-specific interaction logs
            Route::get('interaction-logs', [InteractionLogController::class, 'indexByProject']);
            Route::get('interaction-logs/client-visible', [InteractionLogController::class, 'clientVisible']);
        });
        
        /*
        |--------------------------------------------------------------------------
        | Component Management Routes
        |--------------------------------------------------------------------------
        */
        Route::apiResource('components', ComponentController::class);
        
        /*
        |--------------------------------------------------------------------------
        | Document Management Routes
        |--------------------------------------------------------------------------
        */
        Route::apiResource('documents', \App\Http\Controllers\Api\DocumentController::class);
        Route::prefix('documents')->group(function () {
            Route::get('{document}/download', [\App\Http\Controllers\Api\DocumentController::class, 'download']);
            Route::post('{document}/versions', [\App\Http\Controllers\Api\DocumentController::class, 'createVersion']);
            Route::get('{document}/versions', [\App\Http\Controllers\Api\DocumentController::class, 'getVersions']);
        });
        
        /*
        |--------------------------------------------------------------------------
        | Drawing Management Routes
        |--------------------------------------------------------------------------
        */
        Route::apiResource('drawings', \App\Http\Controllers\Api\DrawingController::class);
        Route::prefix('drawings')->group(function () {
            Route::get('{drawing}/download', [\App\Http\Controllers\Api\DrawingController::class, 'download']);
            Route::post('{drawing}/approve', [\App\Http\Controllers\Api\DrawingController::class, 'approve']);
            Route::post('{drawing}/reject', [\App\Http\Controllers\Api\DrawingController::class, 'reject']);
            Route::get('statistics', [\App\Http\Controllers\Api\DrawingController::class, 'statistics']);
        });
        
        /*
        |--------------------------------------------------------------------------
        | Super Admin Dashboard Routes
        |--------------------------------------------------------------------------
        */
        // Admin Dashboard Routes - DISABLED (Controller not implemented)
        /*
        Route::prefix('admin')->group(function () {
            Route::get('dashboard/stats', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getStats']);
            Route::get('dashboard/activities', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getActivities']);
            Route::get('dashboard/alerts', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getAlerts']);
            Route::get('dashboard/performance', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getPerformanceMetrics']);
        });
        */

    // Tenant Admin Dashboard - DISABLED (Controller has duplicate class issue)
    /*
    Route::prefix('tenant-admin')->group(function () {
        Route::get('dashboard/stats', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getStats']);
        Route::get('dashboard/user-activity', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getUserActivitySummary']);
    });
    */
        
        /*
        |--------------------------------------------------------------------------
        | Team Management Routes
        |--------------------------------------------------------------------------
        */
        Route::apiResource('teams', \App\Http\Controllers\Api\TeamController::class);
        Route::prefix('teams')->group(function () {
            Route::post('{team}/members', [\App\Http\Controllers\Api\TeamController::class, 'addMember']);
            Route::delete('{team}/members', [\App\Http\Controllers\Api\TeamController::class, 'removeMember']);
            Route::patch('{team}/members/role', [\App\Http\Controllers\Api\TeamController::class, 'updateMemberRole']);
            Route::get('{team}/members', [\App\Http\Controllers\Api\TeamController::class, 'getMembers']);
            Route::get('{team}/statistics', [\App\Http\Controllers\Api\TeamController::class, 'getStatistics']);
            Route::post('{team}/archive', [\App\Http\Controllers\Api\TeamController::class, 'archive']);
            Route::post('{team}/restore', [\App\Http\Controllers\Api\TeamController::class, 'restore']);
        });

        // Test Error Envelope
        Route::get('test-error', function () {
            return response()->json(['error' => 'Test error'], 400);
        });

        /*
        |--------------------------------------------------------------------------
        | Project Manager Dashboard Routes
        |--------------------------------------------------------------------------
        */
        Route::prefix('project-manager')->group(function () {
            Route::get('dashboard/stats', [\App\Http\Controllers\Api\ProjectManagerController::class, 'getStats']);
            Route::get('dashboard/timeline', [\App\Http\Controllers\Api\ProjectManagerController::class, 'getProjectTimeline']);
        });

        /*
        |--------------------------------------------------------------------------
        | Task Management Routes
        |--------------------------------------------------------------------------
        */
        Route::apiResource('tasks', TaskController::class);
        Route::apiResource('task-assignments', TaskAssignmentController::class);
        
        // Task-specific routes
        Route::prefix('tasks')->group(function () {
            Route::patch('{task}/status', [TaskController::class, 'updateStatus']);
            Route::post('{task}/assign', [TaskController::class, 'assignUser']);
            Route::post('{task}/assign-team', [TaskController::class, 'assignTeam']);
            Route::post('{task}/dependencies', [TaskController::class, 'updateDependencies']);
            Route::get('{task}/dependencies', [TaskController::class, 'getDependencies']);
            Route::post('{task}/dependencies/{dependencyId}', [TaskController::class, 'addDependency']);
            Route::delete('{task}/dependencies/{dependencyId}', [TaskController::class, 'removeDependency']);
            Route::get('{task}/watchers', [TaskController::class, 'getWatchers']);
            Route::post('{task}/watchers', [TaskController::class, 'addWatcher']);
            Route::delete('{task}/watchers', [TaskController::class, 'removeWatcher']);
            Route::get('statistics', [TaskController::class, 'statistics']);
        });
        
        Route::prefix('tasks/{taskId}')->group(function () {
            Route::get('interaction-logs', [InteractionLogController::class, 'indexByTask']);
        });
        
        /*
        |--------------------------------------------------------------------------
        | Work Template Routes
        |--------------------------------------------------------------------------
        */
        Route::apiResource('work-templates', WorkTemplateController::class);
        Route::prefix('work-templates')->group(function () {
            Route::post('{templateId}/apply', [WorkTemplateController::class, 'applyToProject']);
            Route::get('meta/categories', [WorkTemplateController::class, 'categories']);
            Route::get('meta/conditional-tags', [WorkTemplateController::class, 'conditionalTags']);
            Route::post('{templateId}/duplicate', [WorkTemplateController::class, 'duplicate']);
        });
        
        /*
        |--------------------------------------------------------------------------
        | Baseline Management Routes
        |--------------------------------------------------------------------------
        */
        Route::apiResource('baselines', BaselineController::class)->only(['show', 'update', 'destroy']);
        Route::get('baselines/{id1}/compare/{id2}', [BaselineController::class, 'compare'])->name('baselines.compare');
        
        /*
        |--------------------------------------------------------------------------
        | Template Routes
        |--------------------------------------------------------------------------
        */
        Route::apiResource('templates', TemplateController::class)
            ->parameters(['templates' => 'id']);
        Route::prefix('templates')->group(function () {
            Route::post('{id}/apply', [TemplateController::class, 'apply']);
            Route::get('{id}/versions', [TemplateController::class, 'versions']);
        });
        
        /*
        |--------------------------------------------------------------------------
        | Interaction Log Routes
        |--------------------------------------------------------------------------
        */
        Route::apiResource('interaction-logs', InteractionLogController::class);
        Route::prefix('interaction-logs')->group(function () {
            Route::patch('{id}/approve', [InteractionLogController::class, 'approve']);
            Route::get('export', [InteractionLogController::class, 'export']);
        });
        
        /*
        |--------------------------------------------------------------------------
        | Dashboard Routes
        |--------------------------------------------------------------------------
        */
// Project Template Routes
Route::prefix('templates')->group(function () {
    Route::get('/', [App\Http\Controllers\ProjectTemplateController::class, 'index']);
    Route::get('/category/{category}', [App\Http\Controllers\ProjectTemplateController::class, 'getByCategory']);
    Route::get('/{template}', [App\Http\Controllers\ProjectTemplateController::class, 'show']);
    Route::post('/apply', [App\Http\Controllers\ProjectTemplateController::class, 'apply']);
    Route::post('/', [App\Http\Controllers\ProjectTemplateController::class, 'store']);
    Route::put('/{template}', [App\Http\Controllers\ProjectTemplateController::class, 'update']);
    Route::delete('/{template}', [App\Http\Controllers\ProjectTemplateController::class, 'destroy']);
});

Route::prefix('dashboard')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\DashboardController::class, 'getUserDashboard']);
    Route::get('/template', [App\Http\Controllers\Api\DashboardController::class, 'getDashboardTemplate']);
    Route::post('/reset', [App\Http\Controllers\Api\DashboardController::class, 'resetDashboard']);
    
    // Widgets
    Route::get('/widgets', [App\Http\Controllers\Api\DashboardController::class, 'getAvailableWidgets']);
    Route::get('/widgets/{widgetId}/data', [App\Http\Controllers\Api\DashboardController::class, 'getWidgetData']);
    Route::post('/widgets', [App\Http\Controllers\Api\DashboardController::class, 'addWidget']);
    Route::delete('/widgets/{widgetId}', [App\Http\Controllers\Api\DashboardController::class, 'removeWidget']);
    Route::put('/widgets/{widgetId}/config', [App\Http\Controllers\Api\DashboardController::class, 'updateWidgetConfig']);
    
    // Layout
    Route::put('/layout', [App\Http\Controllers\Api\DashboardController::class, 'updateDashboardLayout']);
    
    // Alerts
    Route::get('/alerts', [App\Http\Controllers\Api\DashboardController::class, 'getUserAlerts']);
    Route::put('/alerts/{alertId}/read', [App\Http\Controllers\Api\DashboardController::class, 'markAlertAsRead']);
    Route::put('/alerts/read-all', [App\Http\Controllers\Api\DashboardController::class, 'markAllAlertsAsRead']);
    
    // Metrics
    Route::get('/metrics', [App\Http\Controllers\Api\DashboardController::class, 'getDashboardMetrics']);
    
    // Stats
    Route::get('/stats', [App\Http\Controllers\Api\DashboardController::class, 'getStats']);
    
    // Preferences
    Route::post('/preferences', [App\Http\Controllers\Api\DashboardController::class, 'saveUserPreferences']);
    
    // Real-time Updates
    Route::get('/sse', [App\Http\Controllers\Api\DashboardSSEController::class, 'stream']);
    Route::post('/broadcast', [App\Http\Controllers\Api\DashboardSSEController::class, 'broadcastToUser']);
    
    // Customization - DISABLED (Service not implemented)
    /*
    Route::prefix('customization')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'getCustomizableDashboard']);
        Route::get('/widgets', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'getAvailableWidgets']);
        Route::get('/templates', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'getLayoutTemplates']);
        Route::get('/options', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'getCustomizationOptions']);
        
        // Widget Management
        Route::post('/widgets', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'addWidget']);
        Route::delete('/widgets/{widgetInstanceId}', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'removeWidget']);
        Route::put('/widgets/{widgetInstanceId}/config', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'updateWidgetConfig']);
        Route::post('/widgets/{widgetInstanceId}/duplicate', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'duplicateWidget']);
        
        // Layout Management
        Route::put('/layout', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'updateLayout']);
        Route::post('/apply-template', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'applyTemplate']);
        
        // Preferences
        Route::post('/preferences', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'savePreferences']);
        
        // Import/Export
        Route::get('/export', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'exportDashboard']);
        Route::post('/import', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'importDashboard']);
        
        // Reset
        Route::post('/reset', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'resetDashboard']);
    });
    */
    
    // Role-based Logic - DISABLED (Service not implemented)
    /*
    Route::prefix('role-based')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getRoleBasedDashboard']);
        Route::get('/widgets', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getRoleWidgets']);
        Route::get('/metrics', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getRoleMetrics']);
        Route::get('/alerts', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getRoleAlerts']);
        Route::get('/permissions', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getRolePermissions']);
        Route::get('/role-config', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getRoleConfiguration']);
        Route::get('/projects', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getAvailableProjects']);
        Route::get('/summary', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getDashboardSummary']);
        Route::get('/project-context', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getProjectContext']);
        Route::post('/switch-project', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'switchProjectContext']);
    });
    */

    /*
    |--------------------------------------------------------------------------
    | Simple User Management Routes (With Authentication)
    |--------------------------------------------------------------------------
    */
    Route::prefix('simple')->middleware(['production.security'])->as('dashboard.simple.')->group(function () {
        Route::apiResource('users', UserController::class);
    });

            /*
            |--------------------------------------------------------------------------
            | Simple User Management V2 Routes (With SimpleJwtAuth)
            |--------------------------------------------------------------------------
            */
            Route::prefix('users-v2')->middleware(['production.security'])->group(function () {
                Route::get('/', [UserControllerV2::class, 'index']);
                Route::post('/', [UserControllerV2::class, 'store']);
                Route::get('/profile', [UserControllerV2::class, 'profile']);
                Route::get('/{id}', [UserControllerV2::class, 'show']);
                Route::put('/{id}', [UserControllerV2::class, 'update']);
                Route::delete('/{id}', [UserControllerV2::class, 'destroy']);
            });

            /*
            |--------------------------------------------------------------------------
            | Task Assignment Routes
            |--------------------------------------------------------------------------
            */
            Route::prefix('tasks')->group(function () {
                Route::get('/{taskId}/assignments', [TaskAssignmentController::class, 'index']);
                Route::post('/{taskId}/assignments', [TaskAssignmentController::class, 'store']);
            });

            Route::prefix('assignments')->group(function () {
                Route::put('/{assignmentId}', [TaskAssignmentController::class, 'update']);
                Route::delete('/{assignmentId}', [TaskAssignmentController::class, 'destroy']);
            });

            Route::prefix('users')->group(function () {
                Route::get('/{userId}/assignments', [TaskAssignmentController::class, 'getUserAssignments']);
                Route::get('/{userId}/assignments/stats', [TaskAssignmentController::class, 'getUserStats']);
            });

});

// Sidebar Config Management Routes
Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    Route::prefix('sidebar-configs')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\SidebarConfigController::class, 'index'])->name('api.sidebar-configs.index');
        Route::post('/', [App\Http\Controllers\Api\SidebarConfigController::class, 'store'])->name('api.sidebar-configs.store');
        Route::get('/{sidebarConfig}', [App\Http\Controllers\Api\SidebarConfigController::class, 'show'])->name('api.sidebar-configs.show');
        Route::put('/{sidebarConfig}', [App\Http\Controllers\Api\SidebarConfigController::class, 'update'])->name('api.sidebar-configs.update');
        Route::delete('/{sidebarConfig}', [App\Http\Controllers\Api\SidebarConfigController::class, 'destroy'])->name('api.sidebar-configs.destroy');
        
        // Custom routes
        Route::get('/role/{role}', [App\Http\Controllers\Api\SidebarConfigController::class, 'getForRole'])->name('api.sidebar-configs.get-for-role');
        Route::post('/clone', [App\Http\Controllers\Api\SidebarConfigController::class, 'clone'])->name('api.sidebar-configs.clone');
        Route::put('/role/{role}/reset', [App\Http\Controllers\Api\SidebarConfigController::class, 'reset'])->name('api.sidebar-configs.reset');
        Route::get('/default/{role}', [App\Http\Controllers\Api\SidebarConfigController::class, 'getDefault'])->name('api.sidebar-configs.get-default');
    });
});

        // Badge Management Routes
        Route::prefix('badges')->middleware(['auth:sanctum'])->group(function () {
            Route::get('/{itemId}', [App\Http\Controllers\Api\BadgeController::class, 'getBadgeCount'])->name('api.badges.get-count');
            Route::post('/counts', [App\Http\Controllers\Api\BadgeController::class, 'getBadgeCounts'])->name('api.badges.get-counts');
            Route::put('/{itemId}', [App\Http\Controllers\Api\BadgeController::class, 'updateBadgeCount'])->name('api.badges.update-count');
            Route::post('/update', [App\Http\Controllers\Api\BadgeController::class, 'updateBadgeCounts'])->name('api.badges.update-counts');
            Route::delete('/{itemId}/cache', [App\Http\Controllers\Api\BadgeController::class, 'clearBadgeCache'])->name('api.badges.clear-cache');
            Route::delete('/cache', [App\Http\Controllers\Api\BadgeController::class, 'clearUserBadgeCache'])->name('api.badges.clear-user-cache');
            Route::post('/config', [App\Http\Controllers\Api\BadgeController::class, 'getBadgeConfig'])->name('api.badges.get-config');
            Route::post('/batch-update', [App\Http\Controllers\Api\BadgeController::class, 'batchUpdateBadges'])->name('api.badges.batch-update');
        });

        // User Preferences Routes
        Route::prefix('user-preferences')->middleware(['auth:sanctum'])->group(function () {
            Route::get('/', [App\Http\Controllers\Api\UserPreferenceController::class, 'getPreferences'])->name('api.user-preferences.get');
            Route::put('/', [App\Http\Controllers\Api\UserPreferenceController::class, 'updatePreferences'])->name('api.user-preferences.update');
            Route::post('/pin', [App\Http\Controllers\Api\UserPreferenceController::class, 'pinItem'])->name('api.user-preferences.pin');
            Route::post('/unpin', [App\Http\Controllers\Api\UserPreferenceController::class, 'unpinItem'])->name('api.user-preferences.unpin');
            Route::post('/hide', [App\Http\Controllers\Api\UserPreferenceController::class, 'hideItem'])->name('api.user-preferences.hide');
            Route::post('/show', [App\Http\Controllers\Api\UserPreferenceController::class, 'showItem'])->name('api.user-preferences.show');
            Route::post('/custom-order', [App\Http\Controllers\Api\UserPreferenceController::class, 'setCustomOrder'])->name('api.user-preferences.custom-order');
            Route::post('/theme', [App\Http\Controllers\Api\UserPreferenceController::class, 'setTheme'])->name('api.user-preferences.theme');
            Route::post('/toggle-compact', [App\Http\Controllers\Api\UserPreferenceController::class, 'toggleCompactMode'])->name('api.user-preferences.toggle-compact');
            Route::post('/toggle-badges', [App\Http\Controllers\Api\UserPreferenceController::class, 'toggleBadges'])->name('api.user-preferences.toggle-badges');
            Route::post('/toggle-auto-expand', [App\Http\Controllers\Api\UserPreferenceController::class, 'toggleAutoExpandGroups'])->name('api.user-preferences.toggle-auto-expand');
            Route::post('/reset', [App\Http\Controllers\Api\UserPreferenceController::class, 'resetPreferences'])->name('api.user-preferences.reset');
            Route::get('/stats', [App\Http\Controllers\Api\UserPreferenceController::class, 'getStats'])->name('api.user-preferences.stats');
            Route::post('/bulk-update', [App\Http\Controllers\Api\UserPreferenceController::class, 'bulkUpdate'])->name('api.user-preferences.bulk-update');
        });

    }); // Close v1 prefix group

// Include Z.E.N.A API routes
require __DIR__.'/api_zena.php';

// Test route to verify inclusion works
Route::get('/zena-test', function () {
    return response()->json(['message' => 'Z.E.N.A routes are working!']);
});

// Export routes
Route::post('/tasks/bulk/export', [ExportController::class, 'exportTasks']);
Route::post('/projects/bulk/export', [ExportController::class, 'exportProjects']);

// Password reset routes
Route::prefix('auth')->group(function () {
    Route::post('/password/reset', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::post('/password/reset/confirm', [PasswordResetController::class, 'reset'])->name('password.update');
    Route::post('/password/reset/check-token', [PasswordResetController::class, 'checkToken'])->name('password.check-token');
});

// Analytics routes
Route::get('/analytics/tasks', [AnalyticsController::class, 'getTasksAnalytics']);
Route::get('/analytics/projects', [AnalyticsController::class, 'getProjectsAnalytics']);
Route::get('/analytics/dashboard', [AnalyticsController::class, 'getDashboardAnalytics']);

}); // Close the main Route::group() at line 303

}); // Close the Route::group() at line 151

// Admin Dashboard API Routes (no middleware for testing)
Route::prefix('api/admin/dashboard')->group(function () {
    Route::get('/stats', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getStats']);
    Route::get('/activities', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getActivities']);
    Route::get('/alerts', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getAlerts']);
    Route::get('/metrics', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getMetrics']);
});

// Test route
Route::get('test', function () {
    return response()->json(['message' => 'Test route working']);
});

// Test controller route
Route::get('test-controller', [\App\Http\Controllers\Api\DashboardController::class, 'getCsrfToken']);

// Authentication Routes (public) with rate limiting
Route::prefix('auth')->middleware([\App\Http\Middleware\EnhancedRateLimitMiddleware::class . ':auth'])->group(function () {
    Route::post('login', [\App\Http\Controllers\Api\AuthenticationController::class, 'login']);
    Route::post('logout', [\App\Http\Controllers\Api\AuthenticationController::class, 'logout']);
    Route::post('refresh', [\App\Http\Controllers\Api\AuthenticationController::class, 'refresh']);
    Route::get('validate', [\App\Http\Controllers\Api\AuthenticationController::class, 'validateToken']);
});

// CSRF Token endpoint (public - no authentication required)
Route::get('csrf-token', [\App\Http\Controllers\Api\DashboardController::class, 'getCsrfToken']);

// Authenticated Routes (temporarily without middleware for testing)
Route::group([], function () {
    // User info and permissions
    Route::prefix('auth')->group(function () {
        Route::get('me', [\App\Http\Controllers\Api\AuthenticationController::class, 'me']);
        Route::get('permissions', [\App\Http\Controllers\Api\AuthenticationController::class, 'permissions']);
    });
    
    // Dashboard API Routes (temporarily with simple responses for testing)
    Route::prefix('dashboard')->group(function () {
        Route::get('data', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'kpis' => [
                        'totalProjects' => 12,
                        'activeProjects' => 8,
                        'onTimeRate' => 85,
                        'overdueProjects' => 2,
                        'budgetUsage' => 75,
                        'overBudgetProjects' => 1,
                        'healthSnapshot' => 90,
                        'atRiskProjects' => 1,
                        'activeTasks' => 25,
                        'completedToday' => 5,
                        'teamMembers' => 8,
                        'projects' => 12
                    ],
                    'alerts' => [
                        [
                            'id' => 'overdue_tasks',
                            'type' => 'warning',
                            'title' => 'Overdue Tasks',
                            'message' => '3 tasks are overdue',
                            'action_url' => '/app/tasks?filter=overdue'
                        ],
                        [
                            'id' => 'urgent_projects',
                            'type' => 'error',
                            'title' => 'Urgent Projects',
                            'message' => '2 projects due within 2 days',
                            'action_url' => '/app/projects?filter=urgent'
                        ]
                    ],
                    'quickActions' => [
                        [
                            'id' => 1,
                            'label' => 'New Project',
                            'icon' => 'fas fa-plus',
                            'action' => 'create_project',
                            'url' => '/app/projects/create'
                        ],
                        [
                            'id' => 2,
                            'label' => 'Add Task',
                            'icon' => 'fas fa-tasks',
                            'action' => 'add_task',
                            'url' => '/app/tasks/create'
                        ],
                        [
                            'id' => 3,
                            'label' => 'Invite Team',
                            'icon' => 'fas fa-user-plus',
                            'action' => 'invite_team',
                            'url' => '/app/team/invite'
                        ],
                        [
                            'id' => 4,
                            'label' => 'Upload File',
                            'icon' => 'fas fa-upload',
                            'action' => 'upload_file',
                            'url' => '/app/documents/upload'
                        ]
                    ],
                    'notifications' => [
                        [
                            'id' => 1,
                            'title' => 'Task Completed',
                            'message' => 'John Doe completed "Design Review" task',
                            'icon' => 'fas fa-check-circle',
                            'read' => false,
                            'created_at' => '1 hour ago',
                            'type' => 'success'
                        ],
                        [
                            'id' => 2,
                            'title' => 'New Comment',
                            'message' => 'Jane Smith commented on Project Alpha',
                            'icon' => 'fas fa-comment',
                            'read' => false,
                            'created_at' => '3 hours ago',
                            'type' => 'info'
                        ],
                        [
                            'id' => 3,
                            'title' => 'Document Uploaded',
                            'message' => 'New document uploaded to Project Beta',
                            'icon' => 'fas fa-file-alt',
                            'read' => true,
                            'created_at' => '5 hours ago',
                            'type' => 'info'
                        ]
                    ],
                    'stats' => [
                        'totalTasks' => 25,
                        'completedTasks' => 5,
                        'teamMembers' => 8,
                        'totalProjects' => 12,
                        'activeTasksGrowth' => 12,
                        'completionRate' => '85%',
                        'activeMembers' => 3,
                        'onTimeRate' => '78%',
                        'budgetUsage' => '75%',
                        'totalBudget' => 50000,
                        'healthScore' => '90%',
                        'atRiskProjects' => 1,
                        'overdueItems' => 2,
                        'overdueProjects' => 1,
                        'documents' => 24,
                        'pendingReviews' => 3
                    ],
                    'recentActivity' => [
                        ['id' => 1, 'description' => 'Task completed', 'user' => 'John Doe', 'time' => '2 hours ago'],
                        ['id' => 2, 'description' => 'Project created', 'user' => 'Jane Smith', 'time' => '4 hours ago'],
                        ['id' => 3, 'description' => 'Document uploaded', 'user' => 'Mike Johnson', 'time' => '6 hours ago']
                    ],
                    'generated_at' => now()->toISOString()
                ]
            ]);
        });
        Route::get('widget/{widget}', function ($widget) {
            return response()->json([
                'success' => true,
                'data' => ['widget' => $widget, 'data' => []]
            ]);
        });
        Route::get('analytics', function () {
            return response()->json([
                'success' => true,
                'data' => ['analytics' => 'mock_data']
            ]);
        });
        Route::get('notifications', function () {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        });
        Route::get('preferences', function () {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        });
        Route::get('statistics', function () {
            return response()->json([
                'success' => true,
                'data' => ['statistics' => 'mock_data']
            ]);
        });
    });

    // Cache Management Routes
    Route::prefix('cache')->group(function () {
        Route::get('stats', [\App\Http\Controllers\Api\CacheController::class, 'getStats']);
        Route::get('config', [\App\Http\Controllers\Api\CacheController::class, 'getConfig']);
        Route::post('invalidate/key', [\App\Http\Controllers\Api\CacheController::class, 'invalidateKey']);
        Route::post('invalidate/tags', [\App\Http\Controllers\Api\CacheController::class, 'invalidateTags']);
        Route::post('invalidate/pattern', [\App\Http\Controllers\Api\CacheController::class, 'invalidatePattern']);
        Route::post('warmup', [\App\Http\Controllers\Api\CacheController::class, 'warmUp']);
        Route::post('clear', [\App\Http\Controllers\Api\CacheController::class, 'clearAll']);
    });

// WebSocket Management Routes (temporarily without middleware for testing)
Route::prefix('websocket')->group(function () {
    Route::get('info', [\App\Http\Controllers\Api\WebSocketController::class, 'getConnectionInfo']);
    Route::get('stats', [\App\Http\Controllers\Api\WebSocketController::class, 'getStats']);
    Route::get('channels', [\App\Http\Controllers\Api\WebSocketController::class, 'getChannels']);
    Route::get('test', [\App\Http\Controllers\Api\WebSocketController::class, 'testConnection']);
    Route::post('online', [\App\Http\Controllers\Api\WebSocketController::class, 'markOnline']);
    Route::post('offline', [\App\Http\Controllers\Api\WebSocketController::class, 'markOffline']);
    Route::post('activity', [\App\Http\Controllers\Api\WebSocketController::class, 'updateActivity']);
    Route::post('broadcast', [\App\Http\Controllers\Api\WebSocketController::class, 'broadcast']);
    Route::post('notification', [\App\Http\Controllers\Api\WebSocketController::class, 'sendNotification']);
});

    // Dashboard preferences update (no caching)
    Route::put('dashboard/preferences', [\App\Http\Controllers\Api\DashboardController::class, 'updatePreferences']);
    
    // Project CRUD Operations (moved from web routes) - REMOVED: Already handled by apiResource above
    // Route::prefix('projects')->group(function () {
    //     Route::post('/', [\App\Http\Controllers\Api\ProjectController::class, 'store']);
    //     Route::put('/{project}', [\App\Http\Controllers\Api\ProjectController::class, 'update']);
    //     Route::delete('/{project}', [\App\Http\Controllers\Api\ProjectController::class, 'destroy']);
    // });
    
    // Task CRUD Operations (moved from web routes) - REMOVED: Already handled by apiResource above
    // Route::prefix('tasks')->group(function () {
    //     Route::post('/', [\App\Http\Controllers\Api\TaskController::class, 'store']);
    //     Route::put('/{task}', [\App\Http\Controllers\Api\TaskController::class, 'update']);
    //     Route::delete('/{task}', [\App\Http\Controllers\Api\TaskController::class, 'destroy']);
    //     Route::post('/{task}/documents', [\App\Http\Controllers\Api\TaskController::class, 'storeDocument']);
    // });
    
    // Invitation Operations (moved from web routes)
    Route::prefix('invitations')->group(function () {
        Route::post('/accept/{token}', [\App\Http\Controllers\Api\InvitationController::class, 'processAcceptance']);
    });
});

// Admin Dashboard API Routes (temporarily without middleware for testing)
Route::prefix('api/admin/dashboard')->group(function () {
    Route::get('/stats', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getStats']);
    Route::get('/activities', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getActivities']);
    Route::get('/alerts', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getAlerts']);
    Route::get('/metrics', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getMetrics']);
});

// Legacy Route Monitoring Routes (temporarily without middleware for testing)
Route::prefix('legacy-routes')->group(function () {
    Route::get('/usage', [App\Http\Controllers\Api\LegacyRouteMonitoringController::class, 'getUsageStats']);
    Route::get('/migration-phase', [App\Http\Controllers\Api\LegacyRouteMonitoringController::class, 'getMigrationPhaseStats']);
    Route::get('/report', [App\Http\Controllers\Api\LegacyRouteMonitoringController::class, 'generateReport']);
    Route::post('/record-usage', [App\Http\Controllers\Api\LegacyRouteMonitoringController::class, 'recordUsage']);
    Route::post('/cleanup', [App\Http\Controllers\Api\LegacyRouteMonitoringController::class, 'cleanup']);
});
