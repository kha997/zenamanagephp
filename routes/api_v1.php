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
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\V1\App\CostOutstandingController;
use App\Http\Controllers\Api\V1\App\CostSnapshotController;
use App\Http\Controllers\Api\V1\App\DocumentSubscriptionController;
use App\Http\Controllers\Api\V1\App\InspectionController;
use App\Http\Controllers\Api\V1\App\InspectionItemController;
use App\Http\Controllers\Api\V1\App\InspectionObservationController;
use App\Http\Controllers\Api\V1\App\InspectionQuotationController;
use App\Http\Controllers\Api\V1\App\InspectionQuotationLineController;
use App\Http\Controllers\Api\V1\App\InspectionQuotationAreaController;
use App\Http\Controllers\Api\V1\App\InspectionActualCostController;
use App\Http\Controllers\Api\V1\App\InspectionCostAttachmentController;
use App\Http\Controllers\Api\V1\App\InspectionPaymentAttachmentController;
use App\Http\Controllers\Api\V1\App\InspectionPaymentController;
use App\Http\Controllers\Api\V1\App\InspectionPaymentScheduleController;
use App\Http\Controllers\Api\V1\App\InspectionPaymentSummaryController;
use App\Http\Controllers\Api\V1\App\PaymentRegisterController;
use App\Http\Controllers\Api\V1\App\InspectionCompletionController;
use App\Http\Controllers\Api\V1\App\InspectionDossierArtifactController;
use App\Http\Controllers\Api\V1\App\MyActivityController;
use App\Http\Controllers\Api\V1\App\MyApprovalsController;
use App\Http\Controllers\Api\V1\App\MyHomeController;
use App\Http\Controllers\Api\V1\App\MyNotificationsController;
use App\Http\Controllers\Api\V1\App\MyRiskController;
use App\Http\Controllers\Api\V1\App\ProjectActivityController;
use App\Http\Controllers\Api\V1\App\ProjectSubscriptionController;
use App\Http\Controllers\Api\V1\App\ProjectHealthController;
use App\Http\Controllers\Api\V1\App\ProjectsController;
use App\Http\Controllers\Api\V1\App\ProjectHealthSettingsController;
use App\Http\Controllers\Api\V1\App\FinanceAlertsSettingsController;
use App\Http\Controllers\Api\V1\App\TaskCommentController;
use App\Http\Controllers\Api\V1\App\TaskSubscriptionController;
use App\Http\Controllers\Api\V1\App\TaskAssignmentsController;
use App\Http\Controllers\Api\V1\Admin\SystemUsersController;
use App\Http\Controllers\Admin\TenantMembersController;
use App\Http\Controllers\Api\HealthController;

// API v1 Routes - Simplified
Route::prefix('v1')->group(function () {
    
    // Health check endpoint (for OpenAPI spec generation)
    Route::get('/health', [HealthController::class, 'index'])
        ->middleware(['auth:sanctum', 'ability:admin', 'throttle:10,1']);
    
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
        
        // System users list (new contract)
        Route::get('/users', [SystemUsersController::class, 'index'])
            ->name('admin.users.list')
            ->middleware(['can:users.manage']);
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

    // Admin Tenant Members - Org Admin tenants
    Route::prefix('admin')->middleware([
        'api.stateful',
        'auth:sanctum',
        'ability:tenant',
        'tenant.permission:admin.members.manage',
        'tenant.scope',
    ])->group(function () {
        Route::get('/members', [TenantMembersController::class, 'index'])
            ->name('admin.members.api.index');
    });
    
    // App API Routes - Tenant-scoped
    $tenantAppMiddleware = ['api.stateful', 'debug.auth', 'auth:sanctum', 'tenant.scope'];

    $tenantAppRoutes = function () {

        // Global search - Round 261
        Route::get('/search', [App\Http\Controllers\Api\V1\App\GlobalSearchController::class, 'index'])
            ->name('app.search.index');

        // Projects API - using existing ProjectManagementController
        Route::get('/projects', [App\Http\Controllers\Unified\ProjectManagementController::class, 'getProjects']);
        Route::post('/projects', [App\Http\Controllers\Unified\ProjectManagementController::class, 'createProject']);
        Route::get('/projects/{project}', [App\Http\Controllers\Unified\ProjectManagementController::class, 'getProject'])
            ->withoutMiddleware('bindings');
        Route::post('/projects/{proj}/follow', [ProjectSubscriptionController::class, 'follow']);
        Route::delete('/projects/{proj}/follow', [ProjectSubscriptionController::class, 'unfollow']);
        Route::put('/projects/{project}', [App\Http\Controllers\Unified\ProjectManagementController::class, 'updateProject'])
            ->withoutMiddleware('bindings');
        Route::delete('/projects/{project}', [App\Http\Controllers\Unified\ProjectManagementController::class, 'deleteProject'])
            ->withoutMiddleware('bindings');
        Route::get('/projects/{project}/documents', [App\Http\Controllers\Unified\ProjectManagementController::class, 'documents']);
        Route::post('/projects/{project}/documents', [App\Http\Controllers\Unified\ProjectManagementController::class, 'storeDocument']);
        Route::patch('/projects/{proj}/documents/{doc}', [App\Http\Controllers\Unified\ProjectManagementController::class, 'updateDocument'])
            ->name('app.projects.documents.update')
            ->withoutMiddleware('bindings');
        Route::delete('/projects/{proj}/documents/{doc}', [App\Http\Controllers\Unified\ProjectManagementController::class, 'destroyDocument'])
            ->name('app.projects.documents.destroy')
            ->withoutMiddleware('bindings');
        Route::post('/projects/{proj}/documents/{doc}/follow', [DocumentSubscriptionController::class, 'follow'])
            ->withoutMiddleware('bindings');
        Route::delete('/projects/{proj}/documents/{doc}/follow', [DocumentSubscriptionController::class, 'unfollow'])
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
        Route::post('/projects/{proj}/mark-activity-read', [ProjectActivityController::class, 'markRead'])
            ->name('app.projects.mark-activity-read')
            ->withoutMiddleware('bindings');
        
        // Project Tasks API - Round 202 (must come before /tasks to avoid route conflicts)
        // Round 206: Added update, complete, incomplete endpoints
        // Round 210: Added reorder endpoint
        Route::prefix('projects/{proj}/tasks')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\App\ProjectTaskController::class, 'index']);
            Route::post('/reorder', [App\Http\Controllers\Api\V1\App\ProjectTaskController::class, 'reorder'])
                ->name('app.projects.tasks.reorder')
                ->withoutMiddleware('bindings');
            Route::get('/{task}/activities', [App\Http\Controllers\Api\V1\App\ActivityFeedController::class, 'taskActivities'])
                ->name('app.projects.tasks.activities')
                ->withoutMiddleware([\Illuminate\Routing\Middleware\SubstituteBindings::class]);
            Route::get('/{task}/comments', [TaskCommentController::class, 'index'])
                ->name('app.projects.tasks.comments.index')
                ->withoutMiddleware([\Illuminate\Routing\Middleware\SubstituteBindings::class]);
            Route::post('/{task}/comments', [TaskCommentController::class, 'store'])
                ->name('app.projects.tasks.comments.store')
                ->withoutMiddleware([\Illuminate\Routing\Middleware\SubstituteBindings::class]);
            Route::post('/{task}/follow', [TaskSubscriptionController::class, 'follow'])
                ->withoutMiddleware('bindings');
            Route::delete('/{task}/follow', [TaskSubscriptionController::class, 'unfollow'])
                ->withoutMiddleware('bindings');
            Route::patch('/{proj_task}', [App\Http\Controllers\Api\V1\App\ProjectTaskController::class, 'update'])
                ->name('app.projects.tasks.update')
                ->withoutMiddleware('bindings');
            Route::post('/{proj_task}/complete', [App\Http\Controllers\Api\V1\App\ProjectTaskController::class, 'complete'])
                ->name('app.projects.tasks.complete')
                ->withoutMiddleware('bindings');
            Route::post('/{proj_task}/mark-read', [App\Http\Controllers\Api\V1\App\ProjectTaskController::class, 'markRead'])
                ->name('app.projects.tasks.mark-read')
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
        Route::middleware(['tenant.permission:tenant.view_reports'])->group(function () {
            Route::get('/project-health/summary', [ProjectHealthController::class, 'summary']);
            Route::get('/project-health/risk-trends', [ProjectHealthController::class, 'riskTrends']);
            Route::get('/project-health', [ProjectHealthController::class, 'index']);
            Route::get('/projects/{proj}/health', [ProjectHealthController::class, 'show']);
            Route::get('/projects/{proj}/health/history', [ProjectHealthController::class, 'history']);
            Route::post('/projects/{proj}/health/snapshot', [ProjectsController::class, 'snapshotHealth']);
        });

        Route::middleware(['tenant.permission:tenant.manage_settings'])->group(function () {
        Route::get('/settings/project-health', [ProjectHealthSettingsController::class, 'show']);
        Route::put('/settings/project-health', [ProjectHealthSettingsController::class, 'update']);
        Route::get('/settings/finance-alerts', [FinanceAlertsSettingsController::class, 'show']);
        Route::put('/settings/finance-alerts', [FinanceAlertsSettingsController::class, 'update']);
    });
        
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

        // Project Inspections API - Round 300
        Route::prefix('projects/{proj}/inspections')->group(function () {
            Route::get('/', [InspectionController::class, 'index'])
                ->name('app.projects.inspections.index')
                ->withoutMiddleware('bindings');
            Route::post('/', [InspectionController::class, 'store'])
                ->name('app.projects.inspections.store')
                ->withoutMiddleware('bindings');
        });

        Route::get('/inspections/{insp}', [InspectionController::class, 'show'])
            ->name('app.inspections.show')
            ->withoutMiddleware('bindings');
        Route::put('/inspections/{insp}', [InspectionController::class, 'update'])
            ->name('app.inspections.update')
            ->withoutMiddleware('bindings');
        Route::delete('/inspections/{insp}', [InspectionController::class, 'destroy'])
            ->name('app.inspections.destroy')
            ->withoutMiddleware('bindings');

        Route::get('/inspections/{insp}/quotation', [InspectionQuotationController::class, 'index'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/quotation', [InspectionQuotationController::class, 'store'])
            ->withoutMiddleware('bindings');
        Route::put('/inspections/{insp}/quotation/{inspectionQuotation}', [InspectionQuotationController::class, 'update'])
            ->withoutMiddleware('bindings');
        Route::delete('/inspections/{insp}/quotation/{inspectionQuotation}', [InspectionQuotationController::class, 'destroy'])
            ->withoutMiddleware('bindings');

        Route::get('/inspections/{insp}/actual-costs', [InspectionActualCostController::class, 'index'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/actual-costs', [InspectionActualCostController::class, 'store'])
            ->withoutMiddleware('bindings');
        Route::put('/inspections/actual-costs/{cost}', [InspectionActualCostController::class, 'update'])
            ->withoutMiddleware('bindings');
        Route::delete('/inspections/actual-costs/{cost}', [InspectionActualCostController::class, 'destroy'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/actual-costs/{cost}/submit', [InspectionActualCostController::class, 'submit'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/actual-costs/{cost}/approve', [InspectionActualCostController::class, 'approve'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/actual-costs/{cost}/reject', [InspectionActualCostController::class, 'reject'])
            ->withoutMiddleware('bindings');

        Route::post('/inspection-actual-costs/{cost}/attachments', [InspectionCostAttachmentController::class, 'store'])
            ->withoutMiddleware('bindings');
        Route::delete('/inspection-cost-attachments/{attachment}', [InspectionCostAttachmentController::class, 'destroy'])
            ->withoutMiddleware('bindings');

        Route::get('/inspections/{insp}/payment-schedules', [InspectionPaymentScheduleController::class, 'index'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/payment-schedules', [InspectionPaymentScheduleController::class, 'store'])
            ->withoutMiddleware('bindings');
        Route::put('/inspections/{insp}/payment-schedules/{schedule}', [InspectionPaymentScheduleController::class, 'update'])
            ->withoutMiddleware('bindings');
        Route::delete('/inspections/{insp}/payment-schedules/{schedule}', [InspectionPaymentScheduleController::class, 'destroy'])
            ->withoutMiddleware('bindings');

        Route::get('/inspections/{insp}/payments', [InspectionPaymentController::class, 'index'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/payments', [InspectionPaymentController::class, 'store'])
            ->withoutMiddleware('bindings');
        Route::patch('/inspections/{insp}/payments/{payment}', [InspectionPaymentController::class, 'update'])
            ->withoutMiddleware('bindings');
        Route::delete('/inspections/{insp}/payments/{payment}', [InspectionPaymentController::class, 'destroy'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/payments/{payment}/submit', [InspectionPaymentController::class, 'submit'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/payments/{payment}/approve', [InspectionPaymentController::class, 'approve'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/payments/{payment}/reject', [InspectionPaymentController::class, 'reject'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/payments/{payment}/mark-paid', [InspectionPaymentController::class, 'markPaid'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/payments/{payment}/close', [InspectionPaymentController::class, 'close'])
            ->withoutMiddleware('bindings');

        Route::post('/inspection-payments/{payment}/attachments', [InspectionPaymentAttachmentController::class, 'store'])
            ->withoutMiddleware('bindings');
        Route::delete('/inspection-payment-attachments/{attachment}', [InspectionPaymentAttachmentController::class, 'destroy'])
            ->withoutMiddleware('bindings');

        Route::get('/inspections/{insp}/payment-summary', [InspectionPaymentSummaryController::class, 'show'])
            ->withoutMiddleware('bindings');

        Route::get('/costs/outstanding', [CostOutstandingController::class, 'index'])
            ->name('app.costs.outstanding')
            ->withoutMiddleware('bindings');
        Route::get('/costs/snapshot', [CostSnapshotController::class, 'index'])
            ->name('app.costs.snapshot')
            ->withoutMiddleware('bindings');
        Route::get('/payments/register', [PaymentRegisterController::class, 'export'])
            ->name('app.payments.register')
            ->withoutMiddleware('bindings');

        Route::get('/inspections/{insp}/compliance-timeline', [InspectionController::class, 'complianceTimeline'])
            ->name('app.inspections.compliance-timeline')
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/export', [InspectionController::class, 'export'])
            ->withoutMiddleware('bindings');
        Route::get('/inspections/{insp}/dossier-artifacts', [InspectionDossierArtifactController::class, 'index'])
            ->name('app.inspections.dossier-artifacts.index')
            ->withoutMiddleware('bindings');
        Route::get('/inspection-dossier-artifacts/{artifact}/download', [InspectionDossierArtifactController::class, 'download'])
            ->name('app.inspection-dossier-artifacts.download')
            ->withoutMiddleware('bindings');
        Route::post('/inspection-dossier-artifacts/{artifact}/verify', [InspectionDossierArtifactController::class, 'verify'])
            ->name('app.inspection-dossier-artifacts.verify')
            ->withoutMiddleware('bindings');
        Route::delete('/inspection-dossier-artifacts/{artifact}', [InspectionDossierArtifactController::class, 'destroy'])
            ->name('app.inspection-dossier-artifacts.destroy')
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/sign', [InspectionController::class, 'sign'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/accept', [InspectionController::class, 'accept'])
            ->withoutMiddleware('bindings');
        Route::get('/inspections/{insp}/acceptance-artifacts', [InspectionController::class, 'listAcceptanceArtifacts'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/acceptance-artifacts', [InspectionController::class, 'storeAcceptanceArtifact'])
            ->withoutMiddleware('bindings');
        Route::delete('/inspections/{insp}/acceptance-artifacts/{artifact}', [InspectionController::class, 'deleteAcceptanceArtifact'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/accept/external', [InspectionController::class, 'acceptExternal'])
            ->withoutMiddleware('bindings');

        Route::get('/inspections/{insp}/completion-checklist', [InspectionCompletionController::class, 'checklist'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/complete', [InspectionCompletionController::class, 'complete'])
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/complete-override', [InspectionCompletionController::class, 'override'])
            ->withoutMiddleware('bindings');

        Route::get('/inspections/{insp}/cost-variance', [InspectionActualCostController::class, 'variance'])
            ->withoutMiddleware('bindings');

        Route::get('/inspections/{insp}/observations', [InspectionObservationController::class, 'index'])
            ->name('app.inspections.observations.index')
            ->withoutMiddleware('bindings');
        Route::post('/inspections/{insp}/observations', [InspectionObservationController::class, 'store'])
            ->name('app.inspections.observations.store')
            ->withoutMiddleware('bindings');

        Route::get('/inspections/{insp}/items', [InspectionItemController::class, 'index'])
            ->name('app.inspections.items.index')
            ->withoutMiddleware('bindings');

        Route::post('/quotation/{inspectionQuotation}/lines', [InspectionQuotationLineController::class, 'store'])
            ->withoutMiddleware('bindings');
        Route::put('/quotation/lines/{line}', [InspectionQuotationLineController::class, 'update'])
            ->withoutMiddleware('bindings');
        Route::delete('/quotation/lines/{line}', [InspectionQuotationLineController::class, 'destroy'])
            ->withoutMiddleware('bindings');

        Route::post('/quotation/lines/{line}/areas', [InspectionQuotationAreaController::class, 'store'])
            ->withoutMiddleware('bindings');
        Route::put('/quotation/areas/{area}', [InspectionQuotationAreaController::class, 'update'])
            ->withoutMiddleware('bindings');
        Route::delete('/quotation/areas/{area}', [InspectionQuotationAreaController::class, 'destroy'])
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
            Route::get('/summary', [App\Http\Controllers\Api\V1\App\MyTasksController::class, 'summary'])
                ->name('app.my.tasks.summary');
        });
        
        // Activity Feed API - Round 248
        Route::get('/activity-feed', [App\Http\Controllers\Api\V1\App\ActivityFeedController::class, 'index'])
            ->name('app.activity-feed.index');
        
        // My Notifications & Activity (Round 276)
        Route::prefix('my')->group(function () {
            Route::get('/home', [MyHomeController::class, 'show'])
                ->name('app.my.home');
            Route::get('/risk', [MyRiskController::class, 'index'])
                ->name('app.my.risk');
            Route::prefix('notifications')->group(function () {
                Route::get('/', [MyNotificationsController::class, 'index'])
                    ->name('app.my.notifications.index');
                Route::post('/{notification}/mark-read', [MyNotificationsController::class, 'markRead'])
                    ->name('app.my.notifications.mark-read');
                Route::post('/mark-all-read', [MyNotificationsController::class, 'markAllRead'])
                    ->name('app.my.notifications.mark-all-read');
                Route::get('/summary', [MyNotificationsController::class, 'summary'])
                    ->name('app.my.notifications.summary');
            });

            Route::get('/approvals', [MyApprovalsController::class, 'index'])
                ->name('app.my.approvals.index');
            Route::get('/approvals/summary', [MyApprovalsController::class, 'summary'])
                ->name('app.my.approvals.summary');

            Route::get('/activity', [MyActivityController::class, 'index'])
                ->name('app.my.activity.index');
        });

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
        Route::prefix('tasks/{task}/assignments')->group(function () {
            Route::post('/users', [TaskAssignmentsController::class, 'assignUsers']);
            Route::delete('/users/{user}', [TaskAssignmentsController::class, 'removeUser']);
            Route::get('/users', [TaskAssignmentsController::class, 'getUsers']);
            Route::post('/teams', [TaskAssignmentsController::class, 'assignTeams']);
            Route::delete('/teams/{team}', [TaskAssignmentsController::class, 'removeTeam']);
            Route::get('/teams', [TaskAssignmentsController::class, 'getTeams']);
            Route::get('/', [TaskAssignmentsController::class, 'index']);
        });
        
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
        Route::prefix('templates')->middleware([
            'feature.flag:features.tasks.enable_wbs_templates,Task Templates feature is not enabled'
        ])->group(function () {
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
        
        // Legacy Template Set API (WBS templates)
        Route::prefix('template-sets')->middleware([
            'feature.flag:features.tasks.enable_wbs_templates,Task Templates feature is not enabled'
        ])->group(function () {
            Route::get('/', [TemplateController::class, 'index']);
            Route::post('/preview', [TemplateController::class, 'preview']);
        });
        Route::post('/projects/{project}/apply-template', [TemplateController::class, 'apply']);
        Route::get('/projects/{project}/template-history', [TemplateController::class, 'history']);
        
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
    };

    Route::middleware($tenantAppMiddleware)->group($tenantAppRoutes);
    Route::prefix('app')->middleware($tenantAppMiddleware)->group($tenantAppRoutes);

    
    // Signed download route (outside auth middleware, protected by signed URL)
    Route::get('/app/projects/documents/{doc}/file', [App\Http\Controllers\Unified\ProjectManagementController::class, 'downloadDocumentSigned'])
        ->name('app.projects.documents.signed-download')
        ->middleware('signed')
        ->withoutMiddleware('bindings');
});
