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
use App\Http\Controllers\Api\SupportDocumentationController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Api\Public\SystemHealthController;

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

Route::get('/health/detailed', [SystemHealthController::class, 'detailed']);
Route::get('/health/performance', [SystemHealthController::class, 'performance']);

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
    $version = 'v1';
    $serviceName = 'Z.E.N.A Project Management API';

    return response()->json([
        'status' => [
            'version' => $version,
            'name' => $serviceName,
            'environment' => app()->environment(),
            'timestamp' => now()->toISOString(),
        ],
        'data' => [
            'name' => $serviceName,
            'version' => $version,
            'description' => $serviceName,
            'features' => [
                'authentication' => 'JWT',
                'response_format' => 'JSend',
                'pagination' => 'cursor_based',
                'localization' => 'vi'
            ]
        ]
    ]);
});

Route::middleware(['auth:sanctum', 'tenant.isolation'])->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/tasks', [TaskController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
*/

// API v1 Routes (prefix removed - already handled by RouteServiceProvider)
Route::group([], function () {
// Simple test route
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
        
        // Simple Document Controller Routes
        Route::apiResource('documents-simple', App\Http\Controllers\Api\DocumentController::class);

        // Support system helpers
        Route::prefix('support')->as('api.support.')->middleware(['input.sanitization', 'error.envelope'])->group(function () {
            Route::post('tickets', [SupportTicketController::class, 'store'])->name('tickets.store');
            Route::get('tickets/{ticket}', [SupportTicketController::class, 'show'])->name('tickets.show');
            Route::post('tickets/{ticket}/messages', [SupportTicketController::class, 'addMessage'])->name('tickets.messages.store');
            Route::put('tickets/{ticket}', [SupportTicketController::class, 'update'])->name('tickets.update');

            Route::post('documentation', [SupportDocumentationController::class, 'store'])->name('documentation.store');
            Route::get('documentation/search', [SupportDocumentationController::class, 'search'])->name('documentation.search');
            Route::get('documentation/{documentation}', [SupportDocumentationController::class, 'show'])->name('documentation.show');
        });
        
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
        Route::get('teams', [\App\Http\Controllers\Api\TeamController::class, 'index'])->name('teams.index')->middleware('rbac:team.view');
        Route::post('teams', [\App\Http\Controllers\Api\TeamController::class, 'store'])->name('teams.store')->middleware('rbac:team.create');
        Route::get('teams/{team}', [\App\Http\Controllers\Api\TeamController::class, 'show'])->name('teams.show')->middleware('rbac:team.view');
        Route::put('teams/{team}', [\App\Http\Controllers\Api\TeamController::class, 'update'])->name('teams.update')->middleware('rbac:team.update');
        Route::patch('teams/{team}', [\App\Http\Controllers\Api\TeamController::class, 'update'])->middleware('rbac:team.update');
        Route::delete('teams/{team}', [\App\Http\Controllers\Api\TeamController::class, 'destroy'])->name('teams.destroy')->middleware('rbac:team.delete');
        Route::prefix('teams')->group(function () {
            Route::post('{team}/members', [\App\Http\Controllers\Api\TeamController::class, 'addMember'])->middleware('rbac:team.member.add');
            Route::delete('{team}/members', [\App\Http\Controllers\Api\TeamController::class, 'removeMember'])->middleware('rbac:team.member.remove');
            Route::patch('{team}/members/role', [\App\Http\Controllers\Api\TeamController::class, 'updateMemberRole'])->middleware('rbac:team.member.update-role');
            Route::get('{team}/members', [\App\Http\Controllers\Api\TeamController::class, 'getMembers'])->middleware('rbac:team.member.view');
            Route::get('{team}/statistics', [\App\Http\Controllers\Api\TeamController::class, 'getStatistics'])->middleware('rbac:team.view');
            Route::post('{team}/archive', [\App\Http\Controllers\Api\TeamController::class, 'archive'])->middleware('rbac:team.archive');
            Route::post('{team}/restore', [\App\Http\Controllers\Api\TeamController::class, 'restore'])->middleware('rbac:team.restore');

            Route::get('{team}/invitations', [\App\Http\Controllers\Api\InvitationController::class, 'index'])->middleware('rbac:invitation.view');
            Route::post('{team}/invitations', [\App\Http\Controllers\Api\InvitationController::class, 'store'])->middleware('rbac:invitation.create');
            Route::delete('{team}/invitations/{invitation}', [\App\Http\Controllers\Api\InvitationController::class, 'revoke'])->middleware('rbac:invitation.revoke');
            Route::post('{team}/invitations/{token}/accept', [\App\Http\Controllers\Api\InvitationController::class, 'accept'])
                ->middleware(['throttle:invitation-accept', 'rbac:invitation.accept']);
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
        Route::prefix('v1/project-manager')->as('api.v1.project_manager.')->middleware(['input.sanitization', 'error.envelope'])->group(function () {
            Route::get('dashboard/stats', [\App\Http\Controllers\Api\ProjectManagerController::class, 'getStats'])->name('dashboard.stats');
            Route::get('dashboard/timeline', [\App\Http\Controllers\Api\ProjectManagerController::class, 'getProjectTimeline'])->name('dashboard.timeline');
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
        | Interaction Log + Change Request APIs (v1)
        |--------------------------------------------------------------------------
        */
        Route::prefix('v1')->middleware(['auth:sanctum', 'tenant.isolation', 'rbac'])->group(function () {




            Route::apiResource('interaction-logs', InteractionLogController::class);
            Route::prefix('interaction-logs')->group(function () {
                Route::patch('{id}/approve', [InteractionLogController::class, 'approve']);
                Route::get('export', [InteractionLogController::class, 'export']);
            });

            require base_path('src/ChangeRequest/routes/api.php');
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
    Route::post('/widgets', [App\Http\Controllers\Api\DashboardController::class, 'addWidget'])->middleware('input.sanitization');
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

Route::prefix('v1')->as('api.v1.')->middleware(['auth:sanctum', 'tenant.isolation', 'rbac'])->group(function () {
    Route::prefix('settings')->as('settings.')->group(function () {
        Route::get('/general', [App\Http\Controllers\Api\App\SettingsController::class, 'general'])
            ->middleware('rbac:settings.general.read')
            ->name('general');
        Route::patch('/general', [App\Http\Controllers\Api\App\SettingsController::class, 'updateGeneral'])
            ->middleware('rbac:settings.general.update')
            ->name('general.update');
        Route::get('/security', [App\Http\Controllers\Api\App\SettingsController::class, 'security'])
            ->middleware('rbac:settings.security.read')
            ->name('security');
        Route::patch('/security', [App\Http\Controllers\Api\App\SettingsController::class, 'updateSecurity'])
            ->middleware('rbac:settings.security.update')
            ->name('security.update');
        Route::get('/notifications', [App\Http\Controllers\Api\App\SettingsController::class, 'notifications'])
            ->middleware('rbac:notification.read')
            ->name('notifications');
        Route::patch('/notifications', [App\Http\Controllers\Api\App\SettingsController::class, 'updateNotifications'])
            ->middleware('rbac:notification.manage_rules')
            ->name('notifications.update');
    });

    Route::prefix('projects/{project}/contracts')->as('projects.contracts.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\ContractController::class, 'index'])
            ->middleware('rbac:contract.view')
            ->name('index');
        Route::post('/', [App\Http\Controllers\Api\ContractController::class, 'store'])
            ->middleware('rbac:contract.create')
            ->name('store');
        Route::get('/{contract}', [App\Http\Controllers\Api\ContractController::class, 'show'])
            ->middleware('rbac:contract.view')
            ->name('show');
        Route::put('/{contract}', [App\Http\Controllers\Api\ContractController::class, 'update'])
            ->middleware('rbac:contract.update')
            ->name('update');
        Route::delete('/{contract}', [App\Http\Controllers\Api\ContractController::class, 'destroy'])
            ->middleware('rbac:contract.delete')
            ->name('destroy');
    });

    Route::prefix('contracts/{contract}/payments')->as('contracts.payments.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\ContractPaymentController::class, 'index'])
            ->middleware('rbac:contract.payment.view')
            ->name('index');
        Route::post('/', [App\Http\Controllers\Api\ContractPaymentController::class, 'store'])
            ->middleware('rbac:contract.payment.create')
            ->name('store');
        Route::put('/{payment}', [App\Http\Controllers\Api\ContractPaymentController::class, 'update'])
            ->middleware('rbac:contract.payment.update')
            ->name('update');
        Route::delete('/{payment}', [App\Http\Controllers\Api\ContractPaymentController::class, 'destroy'])
            ->middleware('rbac:contract.payment.delete')
            ->name('destroy');
    });

    Route::prefix('dashboard')->as('dashboard.')->middleware(['input.sanitization', 'error.envelope'])->group(function () {
        Route::get('/', [App\Http\Controllers\Api\DashboardController::class, 'getUserDashboard'])->name('index');
        Route::get('/template', [App\Http\Controllers\Api\DashboardController::class, 'getDashboardTemplate'])->name('template');
        Route::post('/reset', [App\Http\Controllers\Api\DashboardController::class, 'resetDashboard'])->name('reset');
        
        // Widgets
        Route::get('/widgets', [App\Http\Controllers\Api\DashboardController::class, 'getAvailableWidgets'])->name('widgets.index');
        Route::get('/widgets/{widgetId}/data', [App\Http\Controllers\Api\DashboardController::class, 'getWidgetData'])->name('widgets.data');
        Route::post('/widgets', [App\Http\Controllers\Api\DashboardController::class, 'addWidget'])->middleware('input.sanitization')->name('widgets.store');
        Route::delete('/widgets/{widgetId}', [App\Http\Controllers\Api\DashboardController::class, 'removeWidget'])->name('widgets.destroy');
        Route::put('/widgets/{widgetId}/config', [App\Http\Controllers\Api\DashboardController::class, 'updateWidgetConfig'])->name('widgets.config.update');
        
        // Layout
        Route::put('/layout', [App\Http\Controllers\Api\DashboardController::class, 'updateDashboardLayout'])->name('layout.update');
        
        // Alerts
        Route::get('/alerts', [App\Http\Controllers\Api\DashboardController::class, 'getUserAlerts'])->name('alerts.index');
        Route::put('/alerts/{alertId}/read', [App\Http\Controllers\Api\DashboardController::class, 'markAlertAsRead'])->name('alerts.read');
        Route::put('/alerts/read-all', [App\Http\Controllers\Api\DashboardController::class, 'markAllAlertsAsRead'])->name('alerts.read_all');
        
        // Metrics
        Route::get('/metrics', [App\Http\Controllers\Api\DashboardController::class, 'getDashboardMetrics'])->name('metrics');
        
        // Stats
        Route::get('/stats', [App\Http\Controllers\Api\DashboardController::class, 'getStats'])->name('stats');
        
        // Preferences
        Route::post('/preferences', [App\Http\Controllers\Api\DashboardController::class, 'saveUserPreferences'])->name('preferences.store');
        
        // Real-time Updates
        Route::get('/sse', [App\Http\Controllers\Api\DashboardSSEController::class, 'stream'])->name('sse');
        Route::post('/broadcast', [App\Http\Controllers\Api\DashboardSSEController::class, 'broadcastToUser'])->name('broadcast');
        
        Route::prefix('customization')->as('customization.')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'getCustomizableDashboard'])->name('index');
            Route::get('/widgets', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'getAvailableWidgets'])->name('widgets.index');
            Route::get('/templates', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'getLayoutTemplates'])->name('templates.index');
            Route::get('/options', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'getCustomizationOptions'])->name('options.index');
            
            // Widget Management
            Route::post('/widgets', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'addWidget'])->name('widgets.store');
            Route::delete('/widgets/{widgetInstanceId}', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'removeWidget'])->name('widgets.destroy');
            Route::put('/widgets/{widgetInstanceId}/config', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'updateWidgetConfig'])->name('widgets.config.update');
            Route::post('/widgets/{widgetInstanceId}/duplicate', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'duplicateWidget'])->name('widgets.duplicate');
            
            // Layout Management
            Route::put('/layout', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'updateLayout'])->name('layout.update');
            Route::post('/apply-template', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'applyTemplate'])->name('templates.apply');
            
            // Preferences
            Route::post('/preferences', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'savePreferences'])->name('preferences.store');
            
            // Import/Export
            Route::get('/export', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'exportDashboard'])->name('export');
            Route::post('/import', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'importDashboard'])->name('import');
            
            // Reset
            Route::post('/reset', [App\Http\Controllers\Api\DashboardCustomizationController::class, 'resetDashboard'])->name('reset');
        });
        
        Route::prefix('role-based')->as('role_based.')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getRoleBasedDashboard'])->name('index');
            Route::get('/widgets', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getRoleWidgets'])->name('widgets');
            Route::get('/metrics', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getRoleMetrics'])->name('metrics');
            Route::get('/alerts', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getRoleAlerts'])->name('alerts');
            Route::get('/permissions', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getRolePermissions'])->name('permissions');
            Route::get('/role-config', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getRoleConfiguration'])->name('config');
            Route::get('/projects', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getAvailableProjects'])->name('projects');
            Route::get('/summary', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getDashboardSummary'])->name('summary');
            Route::get('/project-context', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'getProjectContext'])->name('project_context');
            Route::post('/switch-project', [App\Http\Controllers\Api\DashboardRoleBasedController::class, 'switchProjectContext'])->name('switch_project');
        });
        
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

Route::middleware(['auth:sanctum', 'tenant.isolation', 'rbac'])->group(function () {
    // Export routes
    Route::post('/tasks/bulk/export', [ExportController::class, 'exportTasks']);
    Route::post('/projects/bulk/export', [ExportController::class, 'exportProjects']);
});

Route::middleware(['auth:sanctum', 'tenant.isolation', 'rbac:task.update'])->post('/tasks/bulk/status-change', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'Bulk status change processed',
    ]);
});

}); // Close the main Route::group() at line 303

}); // Close the Route::group() at line 151

// == Module routes mounted explicitly (providers disabled) ==
require base_path('src/RBAC/routes/api.php');
require base_path('src/DocumentManagement/routes/api.php');
require base_path('src/Compensation/routes/api.php');
require base_path('src/CoreProject/routes/api.php');

// Password reset routes
Route::prefix('auth')->group(function () {
    Route::post('/password/reset', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::post('/password/reset/confirm', [PasswordResetController::class, 'reset'])->name('password.update');
    Route::post('/password/reset/check-token', [PasswordResetController::class, 'checkToken'])->name('password.check-token');
});

Route::middleware(['auth:sanctum', 'tenant.isolation', 'rbac'])->group(function () {
    // Analytics routes
    Route::get('/analytics/tasks', [AnalyticsController::class, 'getTasksAnalytics']);
    Route::get('/analytics/projects', [AnalyticsController::class, 'getProjectsAnalytics']);
    Route::get('/analytics/dashboard', [AnalyticsController::class, 'getDashboardAnalytics']);
});

// Admin Dashboard API Routes (protected)
Route::prefix('admin/dashboard')->middleware(['auth:sanctum', 'tenant.isolation', 'rbac:admin'])->group(function () {
    Route::get('/stats', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getStats'])->name('api.v1.admin.dashboard.stats');
    Route::get('/activities', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getActivities'])->name('api.v1.admin.dashboard.activities');
    Route::get('/alerts', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getAlerts'])->name('api.v1.admin.dashboard.alerts');
    Route::get('/metrics', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getMetrics'])->name('api.v1.admin.dashboard.metrics');
});

if (app()->environment(['local', 'testing'])) {
    Route::get('test', function () {
        return response()->json(['message' => 'Test route working']);
    });

    Route::get('test-controller', [\App\Http\Controllers\Api\DashboardController::class, 'getCsrfToken']);
}

// Authentication Routes (public) with rate limiting
Route::prefix('auth')->middleware([\App\Http\Middleware\EnhancedRateLimitMiddleware::class . ':auth'])->group(function () {
    Route::post('login', [\App\Http\Controllers\Api\AuthenticationController::class, 'login']);
    Route::post('logout', [\App\Http\Controllers\Api\AuthenticationController::class, 'logout']);
    Route::post('refresh', [\App\Http\Controllers\Api\AuthenticationController::class, 'refresh']);
    Route::get('validate', [\App\Http\Controllers\Api\AuthenticationController::class, 'validateToken']);
});

// CSRF Token endpoint (public - no authentication required)
Route::get('csrf-token', [\App\Http\Controllers\Api\DashboardController::class, 'getCsrfToken']);

// Authenticated support routes
Route::middleware(['auth:sanctum', 'tenant.isolation', 'rate.limit:api'])->group(function () {
    // User info and permissions
    Route::prefix('auth')->group(function () {
        Route::get('me', [\App\Http\Controllers\Api\AuthenticationController::class, 'me']);
        Route::get('permissions', [\App\Http\Controllers\Api\AuthenticationController::class, 'permissions']);
    });

    Route::middleware(['rbac'])->group(function () {
        Route::get('dashboard/data', [\App\Http\Controllers\Api\DashboardController::class, 'getDashboardData']);
        Route::get('dashboard/analytics', [\App\Http\Controllers\Api\DashboardController::class, 'getAnalytics']);
        Route::get('dashboard/notifications', [\App\Http\Controllers\Api\DashboardController::class, 'getNotifications']);
        Route::get('dashboard/preferences', [\App\Http\Controllers\Api\DashboardController::class, 'getPreferences']);
    });

    // Cache Management Routes
    Route::prefix('cache')->middleware(['rbac:admin'])->group(function () {
        Route::get('stats', [\App\Http\Controllers\Api\CacheController::class, 'getStats']);
        Route::get('config', [\App\Http\Controllers\Api\CacheController::class, 'getConfig']);
        Route::post('invalidate/key', [\App\Http\Controllers\Api\CacheController::class, 'invalidateKey']);
        Route::post('invalidate/tags', [\App\Http\Controllers\Api\CacheController::class, 'invalidateTags']);
        Route::post('invalidate/pattern', [\App\Http\Controllers\Api\CacheController::class, 'invalidatePattern']);
        Route::post('warmup', [\App\Http\Controllers\Api\CacheController::class, 'warmUp']);
        Route::post('clear', [\App\Http\Controllers\Api\CacheController::class, 'clearAll']);
    });

    // WebSocket Management Routes
    Route::prefix('websocket')->group(function () {
        Route::middleware(['rbac:admin'])->group(function () {
            Route::get('info', [\App\Http\Controllers\Api\WebSocketController::class, 'getConnectionInfo']);
            Route::get('stats', [\App\Http\Controllers\Api\WebSocketController::class, 'getStats']);
            Route::get('channels', [\App\Http\Controllers\Api\WebSocketController::class, 'getChannels']);
            Route::get('test', [\App\Http\Controllers\Api\WebSocketController::class, 'testConnection']);
            Route::post('broadcast', [\App\Http\Controllers\Api\WebSocketController::class, 'broadcast']);
            Route::post('notification', [\App\Http\Controllers\Api\WebSocketController::class, 'sendNotification']);
        });

        Route::post('online', [\App\Http\Controllers\Api\WebSocketController::class, 'markOnline']);
        Route::post('offline', [\App\Http\Controllers\Api\WebSocketController::class, 'markOffline']);
        Route::post('activity', [\App\Http\Controllers\Api\WebSocketController::class, 'updateActivity']);
    });

    // Legacy Route Monitoring Routes (protected)
    Route::prefix('legacy-routes')->middleware(['rbac:admin'])->group(function () {
        Route::get('/usage', [App\Http\Controllers\Api\LegacyRouteMonitoringController::class, 'getUsageStats']);
        Route::get('/migration-phase', [App\Http\Controllers\Api\LegacyRouteMonitoringController::class, 'getMigrationPhaseStats']);
        Route::get('/report', [App\Http\Controllers\Api\LegacyRouteMonitoringController::class, 'generateReport']);
        Route::post('/record-usage', [App\Http\Controllers\Api\LegacyRouteMonitoringController::class, 'recordUsage']);
        Route::post('/cleanup', [App\Http\Controllers\Api\LegacyRouteMonitoringController::class, 'cleanup']);
    });

    // Dashboard preferences update (no caching)
    Route::middleware(['rbac'])->group(function () {
        Route::put('dashboard/preferences', [\App\Http\Controllers\Api\DashboardController::class, 'updatePreferences']);
    });
});
