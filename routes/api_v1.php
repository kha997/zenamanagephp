<?php

/*
|--------------------------------------------------------------------------
| API v1 Routes (Simplified for Testing)
|--------------------------------------------------------------------------
|
| Simplified API v1 routes for testing architecture fixes
|
*/

use Illuminate\Support\Facades\Route;

// API v1 Routes - Simplified
Route::prefix('v1')->group(function () {
    
    // Admin API Routes - System-wide admin functions
    Route::prefix('admin')->middleware(['api.stateful', 'auth:sanctum', 'ability:admin'])->group(function () {
        // Roles & Permissions Management - Round 233
        Route::get('/permissions', [App\Http\Controllers\Api\V1\Admin\RolePermissionController::class, 'permissions'])
            ->name('admin.permissions.index');
        Route::put('/roles/{role}/permissions', [App\Http\Controllers\Api\V1\Admin\RolePermissionController::class, 'updatePermissions'])
            ->name('admin.roles.permissions.update')
            ->withoutMiddleware('bindings');
        
        // Role Management CRUD - Round 234
        Route::get('/roles', [App\Http\Controllers\Api\V1\Admin\RoleManagementController::class, 'index'])
            ->name('admin.roles.list');
        Route::post('/roles', [App\Http\Controllers\Api\V1\Admin\RoleManagementController::class, 'store'])
            ->name('admin.roles.store');
        Route::put('/roles/{role}', [App\Http\Controllers\Api\V1\Admin\RoleManagementController::class, 'update'])
            ->name('admin.roles.update')
            ->withoutMiddleware('bindings');
        Route::delete('/roles/{role}', [App\Http\Controllers\Api\V1\Admin\RoleManagementController::class, 'destroy'])
            ->name('admin.roles.destroy')
            ->withoutMiddleware('bindings');
        
        // User-Role Assignment - Round 234
        Route::get('/users', [App\Http\Controllers\Api\V1\Admin\UserRoleController::class, 'index'])
            ->name('admin.users.list');
        // Round 246: Changed {user} to {usr} to avoid route-model-binding issues (consistent with assign-profile route)
        Route::put('/users/{usr}/roles', [App\Http\Controllers\Api\V1\Admin\UserRoleController::class, 'updateRoles'])
            ->name('admin.users.roles.update')
            ->withoutMiddleware('bindings');
        
        // Role Profiles - Round 244
        // Note: assign-profile route must come before role-profiles routes to avoid route conflicts
        // Round 246: Changed {user} to {usr} to avoid route-model-binding issues
        Route::put('/users/{usr}/assign-profile', [App\Http\Controllers\Api\V1\Admin\RoleProfileController::class, 'assignProfileToUser'])
            ->name('admin.users.assign-profile')
            ->withoutMiddleware('bindings');
        Route::get('/role-profiles', [App\Http\Controllers\Api\V1\Admin\RoleProfileController::class, 'index'])
            ->name('admin.role-profiles.list');
        Route::get('/role-profiles/{profile}', [App\Http\Controllers\Api\V1\Admin\RoleProfileController::class, 'show'])
            ->name('admin.role-profiles.show')
            ->withoutMiddleware('bindings');
        Route::post('/role-profiles', [App\Http\Controllers\Api\V1\Admin\RoleProfileController::class, 'store'])
            ->name('admin.role-profiles.store');
        Route::put('/role-profiles/{profile}', [App\Http\Controllers\Api\V1\Admin\RoleProfileController::class, 'update'])
            ->name('admin.role-profiles.update')
            ->withoutMiddleware('bindings');
        Route::delete('/role-profiles/{profile}', [App\Http\Controllers\Api\V1\Admin\RoleProfileController::class, 'destroy'])
            ->name('admin.role-profiles.destroy')
            ->withoutMiddleware('bindings');
        
        // Audit Logs - Round 235
        Route::get('/audit-logs', [App\Http\Controllers\Api\V1\Admin\AuditLogController::class, 'index'])
            ->name('admin.audit-logs.index');
        
        // Permission Inspector - Round 236
        Route::get('/permissions/inspect', [App\Http\Controllers\Api\V1\Admin\PermissionInspectorController::class, 'inspect'])
            ->name('admin.permissions.inspect');
        
        // Cost Approval Policy - Round 239
        Route::get('/cost-approval-policy', [App\Http\Controllers\Api\V1\Admin\CostApprovalPolicyController::class, 'index'])
            ->name('admin.cost-approval-policy.index');
        Route::put('/cost-approval-policy', [App\Http\Controllers\Api\V1\Admin\CostApprovalPolicyController::class, 'update'])
            ->name('admin.cost-approval-policy.update');
        
        // Cost Governance Overview - Round 243
        Route::get('/cost-governance-overview', [App\Http\Controllers\Api\V1\Admin\CostGovernanceOverviewController::class, 'index'])
            ->name('admin.cost-governance-overview.index');
    });
    
    // App API Routes - Tenant-scoped
    // Use api.stateful middleware group for session-based SPA authentication
    Route::prefix('app')->middleware(['api.stateful', 'debug.auth', 'auth:sanctum'])->group(function () {
        // Global search - Round 261
        Route::get('/search', [App\Http\Controllers\Api\V1\App\GlobalSearchController::class, 'index'])
            ->name('app.search.index');

        // Projects API - using existing ProjectManagementController
        Route::get('/projects', [App\Http\Controllers\Unified\ProjectManagementController::class, 'getProjects']);
        Route::post('/projects', [App\Http\Controllers\Unified\ProjectManagementController::class, 'createProject']);
        Route::get('/projects/{project}', [App\Http\Controllers\Unified\ProjectManagementController::class, 'getProject']);
        Route::put('/projects/{project}', [App\Http\Controllers\Unified\ProjectManagementController::class, 'updateProject']);
        Route::delete('/projects/{project}', [App\Http\Controllers\Unified\ProjectManagementController::class, 'deleteProject']);
        Route::get('/projects/{project}/documents', [App\Http\Controllers\Unified\ProjectManagementController::class, 'documents']);
        Route::post('/projects/{project}/documents', [App\Http\Controllers\Unified\ProjectManagementController::class, 'storeDocument']);
        Route::patch('/projects/{proj}/documents/{doc}', [App\Http\Controllers\Unified\ProjectManagementController::class, 'updateDocument'])
            ->name('app.projects.documents.update')
            ->withoutMiddleware('bindings');
        Route::delete('/projects/{proj}/documents/{doc}', [App\Http\Controllers\Unified\ProjectManagementController::class, 'destroyDocument'])
            ->name('app.projects.documents.destroy')
            ->withoutMiddleware('bindings');
        Route::get('/projects/{proj}/documents/{doc}/download', [App\Http\Controllers\Unified\ProjectManagementController::class, 'downloadDocument'])
            ->name('app.projects.documents.download')
            ->withoutMiddleware('bindings');
        Route::get('/projects/{proj}/documents/{doc}/versions', [App\Http\Controllers\Unified\ProjectManagementController::class, 'listDocumentVersions'])
            ->name('app.projects.documents.versions.index')
            ->withoutMiddleware('bindings');
        Route::post('/projects/{proj}/documents/{doc}/versions', [App\Http\Controllers\Unified\ProjectManagementController::class, 'storeDocumentVersion'])
            ->name('app.projects.documents.versions.store')
            ->withoutMiddleware('bindings');
        Route::get('/projects/{proj}/documents/{doc}/versions/{version}/download', [App\Http\Controllers\Unified\ProjectManagementController::class, 'downloadDocumentVersion'])
            ->name('app.projects.documents.versions.download')
            ->withoutMiddleware('bindings');
        Route::post('/projects/{proj}/documents/{doc}/versions/{version}/restore', [App\Http\Controllers\Unified\ProjectManagementController::class, 'restoreDocumentVersion'])
            ->name('app.projects.documents.versions.restore')
            ->withoutMiddleware('bindings');
        Route::get('/projects/{project}/history', [App\Http\Controllers\Unified\ProjectManagementController::class, 'history']);
        
        // Project Tasks API - Round 202 (must come before /tasks to avoid route conflicts)
        // Round 206: Added update, complete, incomplete endpoints
        // Round 210: Added reorder endpoint
        Route::prefix('projects/{proj}/tasks')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\App\ProjectTaskController::class, 'index']);
            Route::post('/reorder', [App\Http\Controllers\Api\V1\App\ProjectTaskController::class, 'reorder'])
                ->name('app.projects.tasks.reorder')
                ->withoutMiddleware('bindings');
            Route::patch('/{proj_task}', [App\Http\Controllers\Api\V1\App\ProjectTaskController::class, 'update'])
                ->name('app.projects.tasks.update')
                ->withoutMiddleware('bindings');
            Route::post('/{proj_task}/complete', [App\Http\Controllers\Api\V1\App\ProjectTaskController::class, 'complete'])
                ->name('app.projects.tasks.complete')
                ->withoutMiddleware('bindings');
            Route::post('/{proj_task}/incomplete', [App\Http\Controllers\Api\V1\App\ProjectTaskController::class, 'incomplete'])
                ->name('app.projects.tasks.incomplete')
                ->withoutMiddleware('bindings');
        });
        
        // Project Budget Lines API - Round 219
        Route::prefix('projects/{proj}/budget-lines')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\App\ProjectBudgetController::class, 'index'])
                ->name('app.projects.budget-lines.index')
                ->withoutMiddleware('bindings');
            Route::post('/', [App\Http\Controllers\Api\V1\App\ProjectBudgetController::class, 'store'])
                ->name('app.projects.budget-lines.store')
                ->withoutMiddleware('bindings');
            Route::patch('/{budget_line}', [App\Http\Controllers\Api\V1\App\ProjectBudgetController::class, 'update'])
                ->name('app.projects.budget-lines.update')
                ->withoutMiddleware('bindings');
            Route::delete('/{budget_line}', [App\Http\Controllers\Api\V1\App\ProjectBudgetController::class, 'destroy'])
                ->name('app.projects.budget-lines.destroy')
                ->withoutMiddleware('bindings');
        });
        
        // Project Cost Summary API - Round 222
        Route::get('/projects/{proj}/cost-summary', [App\Http\Controllers\Api\V1\App\ProjectCostSummaryController::class, 'show'])
            ->name('app.projects.cost-summary.show')
            ->withoutMiddleware('bindings');
        
        // Project Cost Dashboard API - Round 223
        Route::get('/projects/{proj}/cost-dashboard', [App\Http\Controllers\Api\V1\App\ProjectCostDashboardController::class, 'show'])
            ->name('app.projects.cost-dashboard.show')
            ->withoutMiddleware('bindings');
        
        // Project Cost Health API - Round 226
        Route::get('/projects/{proj}/cost-health', [App\Http\Controllers\Api\V1\App\ProjectCostHealthController::class, 'show'])
            ->name('app.projects.cost-health.show')
            ->withoutMiddleware('bindings');
        
        // Project Cost Alerts API - Round 227
        Route::get('/projects/{proj}/cost-alerts', [App\Http\Controllers\Api\V1\App\ProjectCostAlertsController::class, 'show'])
            ->name('app.projects.cost-alerts.show')
            ->withoutMiddleware('bindings');
        
        // Project Cost Flow Status API - Round 232
        Route::get('/projects/{proj}/cost-flow-status', [App\Http\Controllers\Api\V1\App\ProjectCostFlowStatusController::class, 'show'])
            ->name('app.projects.cost-flow-status.show')
            ->withoutMiddleware('bindings');
        
        // Project Contracts API - Round 219
        Route::prefix('projects/{proj}/contracts')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\App\ContractController::class, 'index'])
                ->name('app.projects.contracts.index')
                ->withoutMiddleware('bindings');
            Route::post('/', [App\Http\Controllers\Api\V1\App\ContractController::class, 'store'])
                ->name('app.projects.contracts.store')
                ->withoutMiddleware('bindings');
            Route::get('/{contract}', [App\Http\Controllers\Api\V1\App\ContractController::class, 'show'])
                ->name('app.projects.contracts.show')
                ->withoutMiddleware('bindings');
            Route::patch('/{contract}', [App\Http\Controllers\Api\V1\App\ContractController::class, 'update'])
                ->name('app.projects.contracts.update')
                ->withoutMiddleware('bindings');
            Route::delete('/{contract}', [App\Http\Controllers\Api\V1\App\ContractController::class, 'destroy'])
                ->name('app.projects.contracts.destroy')
                ->withoutMiddleware('bindings');
            
            // Change Orders API - Round 220
            Route::prefix('{contract}/change-orders')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\App\ChangeOrderController::class, 'index'])
                    ->name('app.projects.contracts.change-orders.index')
                    ->withoutMiddleware('bindings');
                Route::post('/', [App\Http\Controllers\Api\V1\App\ChangeOrderController::class, 'store'])
                    ->name('app.projects.contracts.change-orders.store')
                    ->withoutMiddleware('bindings');
                // PDF Export - Round 228 (must come before {change_order} route)
                Route::get('/{co}/export/pdf', [App\Http\Controllers\Api\V1\App\ChangeOrderPdfExportController::class, 'export'])
                    ->name('app.projects.contracts.change-orders.export.pdf')
                    ->withoutMiddleware('bindings');
                Route::get('/{change_order}', [App\Http\Controllers\Api\V1\App\ChangeOrderController::class, 'show'])
                    ->name('app.projects.contracts.change-orders.show')
                    ->withoutMiddleware('bindings');
                Route::patch('/{change_order}', [App\Http\Controllers\Api\V1\App\ChangeOrderController::class, 'update'])
                    ->name('app.projects.contracts.change-orders.update')
                    ->withoutMiddleware('bindings');
                Route::delete('/{change_order}', [App\Http\Controllers\Api\V1\App\ChangeOrderController::class, 'destroy'])
                    ->name('app.projects.contracts.change-orders.destroy')
                    ->withoutMiddleware('bindings');
                // Workflow endpoints - Round 230
                Route::post('/{co}/propose', [App\Http\Controllers\Api\V1\App\ChangeOrderController::class, 'propose'])
                    ->name('app.projects.contracts.change-orders.propose')
                    ->withoutMiddleware('bindings');
                Route::post('/{co}/approve', [App\Http\Controllers\Api\V1\App\ChangeOrderController::class, 'approve'])
                    ->name('app.projects.contracts.change-orders.approve')
                    ->withoutMiddleware('bindings');
                Route::post('/{co}/reject', [App\Http\Controllers\Api\V1\App\ChangeOrderController::class, 'reject'])
                    ->name('app.projects.contracts.change-orders.reject')
                    ->withoutMiddleware('bindings');
            });
            
            // Payment Certificates API - Round 221
            Route::prefix('{contract}/payment-certificates')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\App\ContractPaymentCertificateController::class, 'index'])
                    ->name('app.projects.contracts.payment-certificates.index')
                    ->withoutMiddleware('bindings');
                Route::post('/', [App\Http\Controllers\Api\V1\App\ContractPaymentCertificateController::class, 'store'])
                    ->name('app.projects.contracts.payment-certificates.store')
                    ->withoutMiddleware('bindings');
                // PDF Export - Round 228 (must come before {certificate} route)
                Route::get('/{certificate}/export/pdf', [App\Http\Controllers\Api\V1\App\PaymentCertificatePdfExportController::class, 'export'])
                    ->name('app.projects.contracts.payment-certificates.export.pdf')
                    ->withoutMiddleware('bindings');
                Route::get('/{certificate}', [App\Http\Controllers\Api\V1\App\ContractPaymentCertificateController::class, 'show'])
                    ->name('app.projects.contracts.payment-certificates.show')
                    ->withoutMiddleware('bindings');
                Route::patch('/{certificate}', [App\Http\Controllers\Api\V1\App\ContractPaymentCertificateController::class, 'update'])
                    ->name('app.projects.contracts.payment-certificates.update')
                    ->withoutMiddleware('bindings');
                Route::delete('/{certificate}', [App\Http\Controllers\Api\V1\App\ContractPaymentCertificateController::class, 'destroy'])
                    ->name('app.projects.contracts.payment-certificates.destroy')
                    ->withoutMiddleware('bindings');
                // Workflow endpoints - Round 230
                Route::post('/{certificate}/submit', [App\Http\Controllers\Api\V1\App\ContractPaymentCertificateController::class, 'submit'])
                    ->name('app.projects.contracts.payment-certificates.submit')
                    ->withoutMiddleware('bindings');
                Route::post('/{certificate}/approve', [App\Http\Controllers\Api\V1\App\ContractPaymentCertificateController::class, 'approve'])
                    ->name('app.projects.contracts.payment-certificates.approve')
                    ->withoutMiddleware('bindings');
            });
            
            // Payments API - Round 221
            Route::prefix('{contract}/payments')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\App\ContractPaymentController::class, 'index'])
                    ->name('app.projects.contracts.payments.index')
                    ->withoutMiddleware('bindings');
                Route::post('/', [App\Http\Controllers\Api\V1\App\ContractPaymentController::class, 'store'])
                    ->name('app.projects.contracts.payments.store')
                    ->withoutMiddleware('bindings');
                Route::get('/{payment}', [App\Http\Controllers\Api\V1\App\ContractPaymentController::class, 'show'])
                    ->name('app.projects.contracts.payments.show')
                    ->withoutMiddleware('bindings');
                Route::patch('/{payment}', [App\Http\Controllers\Api\V1\App\ContractPaymentController::class, 'update'])
                    ->name('app.projects.contracts.payments.update')
                    ->withoutMiddleware('bindings');
                Route::delete('/{payment}', [App\Http\Controllers\Api\V1\App\ContractPaymentController::class, 'destroy'])
                    ->name('app.projects.contracts.payments.destroy')
                    ->withoutMiddleware('bindings');
                // Workflow endpoints - Round 230
                Route::post('/{payment}/mark-paid', [App\Http\Controllers\Api\V1\App\ContractPaymentController::class, 'markPaid'])
                    ->name('app.projects.contracts.payments.mark-paid')
                    ->withoutMiddleware('bindings');
            });
            
            // PDF Export Routes - Round 228
            Route::get('/{contract}/export/pdf', [App\Http\Controllers\Api\V1\App\ContractPdfExportController::class, 'export'])
                ->name('app.projects.contracts.export.pdf')
                ->withoutMiddleware('bindings');
        });
        
        // My Tasks API - Round 213
        Route::prefix('my/tasks')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\App\MyTasksController::class, 'index'])
                ->name('app.my.tasks.index');
        });
        
        // Activity Feed API - Round 248
        Route::get('/activity-feed', [App\Http\Controllers\Api\V1\App\ActivityFeedController::class, 'index'])
            ->name('app.activity-feed.index');
        
        // Notifications API - Round 251
        Route::get('/notifications', [App\Http\Controllers\Api\V1\App\NotificationController::class, 'index'])
            ->name('app.notifications.index');
        Route::put('/notifications/{id}/read', [App\Http\Controllers\Api\V1\App\NotificationController::class, 'markRead'])
            ->name('app.notifications.mark-read')
            ->withoutMiddleware('bindings');
        Route::put('/notifications/read-all', [App\Http\Controllers\Api\V1\App\NotificationController::class, 'markAllRead'])
            ->name('app.notifications.mark-all-read');
        
        // Notification Preferences API - Round 255
        Route::get('/notification-preferences', [App\Http\Controllers\Api\V1\App\NotificationPreferenceController::class, 'index'])
            ->name('app.notification-preferences.index');
        Route::put('/notification-preferences', [App\Http\Controllers\Api\V1\App\NotificationPreferenceController::class, 'update'])
            ->name('app.notification-preferences.update');
        
        // Tasks API
        Route::apiResource('tasks', App\Http\Controllers\Api\TasksController::class);
        Route::post('/tasks/{task}/assign', [App\Http\Controllers\Api\TasksController::class, 'assign']);
        Route::post('/tasks/{task}/unassign', [App\Http\Controllers\Api\TasksController::class, 'unassign']);
        Route::post('/tasks/{task}/progress', [App\Http\Controllers\Api\TasksController::class, 'updateProgress']);
        
        // Clients API
        Route::apiResource('clients', App\Http\Controllers\Api\ClientsController::class);
        Route::patch('/clients/{client}/lifecycle-stage', [App\Http\Controllers\Api\ClientsController::class, 'updateLifecycleStage']);
        
        // Quotes API
        Route::apiResource('quotes', App\Http\Controllers\Api\QuotesController::class);
        Route::post('/quotes/{quote}/send', [App\Http\Controllers\Api\QuotesController::class, 'send']);
        Route::post('/quotes/{quote}/accept', [App\Http\Controllers\Api\QuotesController::class, 'accept']);
        Route::post('/quotes/{quote}/reject', [App\Http\Controllers\Api\QuotesController::class, 'reject']);
        
        // Documents API
        Route::apiResource('documents', App\Http\Controllers\Api\DocumentsController::class);
        Route::get('/documents/approvals', [App\Http\Controllers\Api\DocumentsController::class, 'approvals']);
        
        // Templates API - MVP (Round 192)
        Route::prefix('templates')->group(function () {
            // Task Templates API - Round 200 (must come before /{tpl} routes to avoid route conflicts)
            Route::prefix('{tpl}/task-templates')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\App\TaskTemplateController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Api\V1\App\TaskTemplateController::class, 'store']);
                Route::patch('/{task_template}', [App\Http\Controllers\Api\V1\App\TaskTemplateController::class, 'update']);
                Route::delete('/{task_template}', [App\Http\Controllers\Api\V1\App\TaskTemplateController::class, 'destroy']);
            });
            
            // Template routes (must come after nested routes)
            Route::post('{tpl}/projects', [App\Http\Controllers\Api\V1\App\TemplateProjectController::class, 'store']);
            Route::get('/', [App\Http\Controllers\Api\V1\App\TemplateController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\V1\App\TemplateController::class, 'store']);
            Route::get('/{tpl}', [App\Http\Controllers\Api\V1\App\TemplateController::class, 'show']);
            Route::patch('/{tpl}', [App\Http\Controllers\Api\V1\App\TemplateController::class, 'update']);
            Route::delete('/{tpl}', [App\Http\Controllers\Api\V1\App\TemplateController::class, 'destroy']);
        });
        
        // Legacy Templates API (WBS templates)
        Route::apiResource('template-sets', App\Http\Controllers\Api\TemplatesController::class);
        Route::get('/template-sets/library', [App\Http\Controllers\Api\TemplatesController::class, 'library']);
        Route::get('/template-sets/builder', [App\Http\Controllers\Api\TemplatesController::class, 'builder']);
        
        // Dashboard API - using proper middleware
        Route::middleware(['ability:tenant'])->prefix('dashboard')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'index']);
            Route::get('/stats', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getStats']);
            Route::get('/recent-projects', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getRecentProjects']);
            Route::get('/recent-tasks', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getRecentTasks']);
            Route::get('/recent-activity', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getRecentActivity']);
            Route::get('/metrics', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getMetrics']);
            Route::get('/team-status', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getTeamStatus']);
            Route::get('/charts/{type}', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getChartData']);
            Route::get('/alerts', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getAlerts']);
            Route::put('/alerts/{id}/read', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'markAlertAsRead']);
            Route::put('/alerts/read-all', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'markAllAlertsAsRead']);
            Route::get('/widgets', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getAvailableWidgets']);
            Route::get('/widgets/{id}/data', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getWidgetData']);
            Route::post('/widgets', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'addWidget']);
            Route::delete('/widgets/{id}', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'removeWidget']);
            Route::put('/widgets/{id}', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'updateWidgetConfig']);
            Route::put('/layout', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'updateLayout']);
            Route::post('/preferences', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'saveUserPreferences']);
            Route::post('/reset', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'resetToDefault']);
        });
    });
    
    // Signed download route (outside auth middleware, protected by signed URL)
    Route::get('/app/projects/documents/{doc}/file', [App\Http\Controllers\Unified\ProjectManagementController::class, 'downloadDocumentSigned'])
        ->name('app.projects.documents.signed-download')
        ->middleware('signed')
        ->withoutMiddleware('bindings');
});
