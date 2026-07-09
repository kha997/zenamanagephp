<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| Z.E.N.A API Routes
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'zena', 'as' => 'api.zena.'], function () {

    // Main API info route
    Route::get('/', function () {
        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Z.E.N.A API is running',
            'data' => [
                'version' => '1.0.0',
                'endpoints' => [
                    'auth' => [
                        'POST /api/zena/auth/login' => 'User login',
                        'POST /api/zena/auth/logout' => 'User logout',
                        'GET /api/zena/auth/me' => 'Get user profile',
                        'POST /api/zena/auth/refresh' => 'Refresh token',
                        'GET /api/zena/auth/dashboard-url' => 'Get dashboard URL',
                        'POST /api/zena/auth/check-permission' => 'Check user permission',
                        'GET /api/zena/auth/notifications' => 'Get user notifications',
                        'POST /api/zena/auth/notifications/{id}/read' => 'Mark notification as read',
                    ],
                    'dashboard' => [
                        'GET /api/zena/dashboard' => 'Get dashboard overview',
                        'GET /api/zena/dashboard/widgets' => 'Get dashboard widgets',
                        'GET /api/zena/dashboard/metrics' => 'Get dashboard metrics',
                        'GET /api/zena/dashboard/alerts' => 'Get dashboard alerts',
                    ],
                    'role-specific' => [
                        'GET /api/zena/pm/dashboard' => 'PM Dashboard',
                        'GET /api/zena/designer/dashboard' => 'Designer Dashboard',
                        'GET /api/zena/site-engineer/dashboard' => 'Site Engineer Dashboard',
                    ]
                ],
                'timestamp' => now(),
            ],
        ]);
    })->name('api.info');

    // Public auth routes
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:zena-login')->name('auth.login');
    });

    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Z.E.N.A API is running',
            'data' => [
                'timestamp' => now(),
            ],
        ]);
    })->name('api.health');

    Route::middleware(['auth:sanctum', 'tenant.isolation', 'input.sanitization', 'error.envelope'])->group(function () {

        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->middleware('rbac:auth.logout')->name('auth.logout');
            Route::get('/me', [AuthController::class, 'me'])->middleware('rbac:auth.me')->name('auth.me');
            Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('rbac:auth.refresh')->name('auth.refresh');
            Route::post('/check-permission', [AuthController::class, 'checkPermission'])->middleware('rbac:auth.check-permission')->name('auth.check-permission');
            Route::get('/dashboard-url', [AuthController::class, 'getDashboardUrl'])->middleware('rbac:auth.dashboard-url')->name('auth.dashboard-url');
            Route::get('/notifications', [AuthController::class, 'getNotifications'])->middleware('rbac:auth.notifications.view')->name('auth.notifications');
            Route::post('/notifications/{id}/read', [AuthController::class, 'markNotificationAsRead'])->middleware('rbac:auth.notifications.read')->name('auth.notifications.read');
        });

        Route::get('/simple-test', function () {
            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Simple test working',
                'data' => [
                    'timestamp' => now(),
                ],
            ]);
        })->middleware('rbac:auth.test.simple');

        Route::get('/minimal-auth-test', function () {
            try {
                $user = auth()->user();
                return response()->json([
                    'success' => true,
                    'status' => 'success',
                    'message' => 'Minimal auth test working',
                    'data' => [
                        'user' => $user ? $user->id : null,
                        'timestamp' => now(),
                    ],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Auth error: ' . $e->getMessage(),
                    'timestamp' => now(),
                ]);
            }
        })->middleware('rbac:auth.test.minimal');

        Route::get('/sanctum-auth-test', function () {
            try {
                $user = auth('sanctum')->user();
                return response()->json([
                    'success' => true,
                    'status' => 'success',
                    'message' => 'Sanctum auth test working',
                    'data' => [
                        'user' => $user ? $user->id : null,
                        'timestamp' => now(),
                    ],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sanctum auth error: ' . $e->getMessage(),
                    'timestamp' => now(),
                ]);
            }
        })->middleware('rbac:auth.test.sanctum');

        Route::get('/me-test', function () {
            try {
                $user = auth('sanctum')->user();
                if (!$user) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized',
                    ], 401);
                }

                $roles = $user->roles()->pluck('name')->toArray();
                $permissions = $user->roles()->with('permissions')->get()
                    ->pluck('permissions')
                    ->flatten()
                    ->pluck('name')
                    ->unique()
                    ->toArray();

                return response()->json([
                    'success' => true,
                    'status' => 'success',
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'avatar' => $user->avatar,
                            'roles' => $roles,
                            'permissions' => $permissions,
                            'last_login_at' => $user->last_login_at,
                        ]
                    ]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: ' . $e->getMessage(),
                    'timestamp' => now(),
                ]);
            }
        })->middleware('rbac:auth.test.me');

        Route::get('/auth-test', function () {
            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Auth test working',
                'data' => [
                    'user' => auth()->user(),
                    'timestamp' => now(),
                ],
            ]);
        })->middleware('rbac:auth.test.auth');

        // Role-specific dashboard routes
        Route::group(['prefix' => 'pm'], function () {
            Route::get('/dashboard', [\App\Http\Controllers\Api\PmDashboardController::class, 'getOverview'])->middleware('rbac:pm.dashboard')->name('pm.dashboard');
            Route::get('/progress', [\App\Http\Controllers\Api\PmDashboardController::class, 'getProjectProgress'])->middleware('rbac:pm.progress')->name('pm.progress');
            Route::get('/risks', [\App\Http\Controllers\Api\PmDashboardController::class, 'getRiskAssessment'])->middleware('rbac:pm.risks')->name('pm.risks');
            Route::get('/weekly-report', [\App\Http\Controllers\Api\PmDashboardController::class, 'getWeeklyReport'])->middleware('rbac:pm.weekly-report')->name('pm.weekly-report');
        });

        Route::group(['prefix' => 'designer'], function () {
            Route::get('/dashboard', [\App\Http\Controllers\Api\DesignerDashboardController::class, 'getOverview'])->middleware('rbac:designer.dashboard')->name('designer.dashboard');
            Route::get('/tasks', [\App\Http\Controllers\Api\DesignerDashboardController::class, 'getDesignTasks'])->middleware('rbac:designer.tasks')->name('designer.tasks');
            Route::get('/drawings', [\App\Http\Controllers\Api\DesignerDashboardController::class, 'getDrawingsStatus'])->middleware('rbac:designer.drawings')->name('designer.drawings');
            Route::get('/rfis', [\App\Http\Controllers\Api\DesignerDashboardController::class, 'getRfisToAnswer'])->middleware('rbac:designer.rfis')->name('designer.rfis');
            Route::get('/submittals', [\App\Http\Controllers\Api\DesignerDashboardController::class, 'getSubmittalsStatus'])->middleware('rbac:designer.submittals')->name('designer.submittals');
            Route::get('/workload', [\App\Http\Controllers\Api\DesignerDashboardController::class, 'getDesignWorkload'])->middleware('rbac:designer.workload')->name('designer.workload');
        });

        Route::group(['prefix' => 'site-engineer'], function () {
            Route::get('/dashboard', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getOverview'])->middleware('rbac:site-engineer.dashboard')->name('site-engineer.dashboard');
            Route::get('/tasks', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getSiteTasks'])->middleware('rbac:site-engineer.tasks')->name('site-engineer.tasks');
            Route::get('/material-requests', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getMaterialRequests'])->middleware('rbac:site-engineer.material-requests')->name('site-engineer.material-requests');
            Route::get('/rfis', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getSiteRfis'])->middleware('rbac:site-engineer.rfis')->name('site-engineer.rfis');
            Route::get('/inspections', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getQcInspections'])->middleware('rbac:site-engineer.inspections')->name('site-engineer.inspections');
            Route::get('/safety', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getSiteSafetyStatus'])->middleware('rbac:site-engineer.safety')->name('site-engineer.safety');
            Route::get('/daily-report', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getDailySiteReport'])->middleware('rbac:site-engineer.daily-report')->name('site-engineer.daily-report');
        });
    });

    Route::middleware(['auth:sanctum', 'tenant.isolation', 'input.sanitization', 'error.envelope'])->group(function () {
        // Project Management routes
        Route::group(['prefix' => 'projects'], function () {
            Route::get('/', [\App\Http\Controllers\Api\ProjectController::class, 'index'])->middleware('rbac:project.view')->name('projects.index');
            Route::post('/', [\App\Http\Controllers\Api\ProjectController::class, 'store'])->middleware('rbac:project.create')->name('projects.store');
            Route::get('/{project}/work-instances', [\App\Http\Controllers\Api\WorkInstanceController::class, 'listByProject'])->middleware('rbac:work.view')->name('projects.work-instances.index');
            Route::get('/{id}', [\App\Http\Controllers\Api\ProjectController::class, 'show'])->middleware('rbac:project.view')->name('projects.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\ProjectController::class, 'update'])->middleware('rbac:project.update')->name('projects.update');
            Route::delete('/{id}', [\App\Http\Controllers\Api\ProjectController::class, 'destroy'])->middleware('rbac:project.delete')->name('projects.destroy');
            Route::post('/{id}/apply-template', [\App\Http\Controllers\Api\WorkTemplateController::class, 'applyToProject'])->middleware('rbac:template.apply')->name('projects.apply-template');

            Route::group(['prefix' => '/{project}/contracts', 'as' => 'projects.contracts.'], function () {
                Route::get('/', [\App\Http\Controllers\Api\ContractController::class, 'index'])->middleware('rbac:contract.view')->name('index');
                Route::post('/', [\App\Http\Controllers\Api\ContractController::class, 'store'])->middleware('rbac:contract.create')->name('store');
                Route::get('/{contract}', [\App\Http\Controllers\Api\ContractController::class, 'show'])->middleware('rbac:contract.view')->name('show');
                Route::put('/{contract}', [\App\Http\Controllers\Api\ContractController::class, 'update'])->middleware('rbac:contract.update')->name('update');
                Route::delete('/{contract}', [\App\Http\Controllers\Api\ContractController::class, 'destroy'])->middleware('rbac:contract.delete')->name('destroy');
                Route::get('/{contract}/material-receipts', [\App\Http\Controllers\Api\ContractController::class, 'materialReceipts'])->middleware('rbac:contract.view')->name('material-receipts.index');
                Route::get('/{contract}/cost-summary', [\App\Http\Controllers\Api\ContractController::class, 'costSummary'])->middleware('rbac:contract.view')->name('cost-summary.show');
            });
        });

        Route::group(['prefix' => 'components'], function () {
            Route::post('/{id}/apply-template', [\App\Http\Controllers\Api\WorkTemplateController::class, 'applyToComponent'])->middleware('rbac:template.apply')->name('components.apply-template');
        });

        // Work template routes
        Route::group(['prefix' => 'work-templates'], function () {
            Route::get('/', [\App\Http\Controllers\Api\WorkTemplateController::class, 'index'])->middleware('rbac:template.view')->name('work-templates.index');
            Route::post('/', [\App\Http\Controllers\Api\WorkTemplateController::class, 'store'])->middleware('rbac:template.edit_draft')->name('work-templates.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\WorkTemplateController::class, 'show'])->middleware('rbac:template.view')->name('work-templates.show');
            Route::post('/{id}/preview', [\App\Http\Controllers\Api\WorkTemplateController::class, 'preview'])->middleware('rbac:template.view')->name('work-templates.preview');
            Route::put('/{id}', [\App\Http\Controllers\Api\WorkTemplateController::class, 'update'])->middleware('rbac:template.edit_draft')->name('work-templates.update');
            Route::post('/{id}/publish', [\App\Http\Controllers\Api\WorkTemplateController::class, 'publish'])->middleware('rbac:template.publish')->name('work-templates.publish');
        });

        // Deliverable template routes
        Route::group(['prefix' => 'deliverable-templates'], function () {
            Route::get('/', [\App\Http\Controllers\Api\DeliverableTemplateController::class, 'index'])->middleware('rbac:template.view')->name('deliverable-templates.index');
            Route::post('/', [\App\Http\Controllers\Api\DeliverableTemplateController::class, 'store'])->middleware('rbac:template.edit_draft')->name('deliverable-templates.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\DeliverableTemplateController::class, 'show'])->middleware('rbac:template.view')->name('deliverable-templates.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\DeliverableTemplateController::class, 'update'])->middleware('rbac:template.edit_draft')->name('deliverable-templates.update');
            Route::post('/{id}/upload-version', [\App\Http\Controllers\Api\DeliverableTemplateController::class, 'uploadVersion'])->middleware('rbac:template.edit_draft')->name('deliverable-templates.upload-version');
            Route::post('/{id}/publish-version', [\App\Http\Controllers\Api\DeliverableTemplateController::class, 'publishVersion'])->middleware('rbac:template.publish')->name('deliverable-templates.publish-version');
            Route::get('/{id}/versions', [\App\Http\Controllers\Api\DeliverableTemplateController::class, 'versions'])->middleware('rbac:template.view')->name('deliverable-templates.versions');
        });

        // Tasks Management routes
        Route::group(['prefix' => 'tasks'], function () {
            Route::get('/', [\App\Http\Controllers\Api\TaskController::class, 'index'])->middleware('rbac:task.view')->name('tasks.index');
            Route::post('/', [\App\Http\Controllers\Api\TaskController::class, 'store'])->middleware('rbac:task.create')->name('tasks.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\TaskController::class, 'show'])->middleware('rbac:task.view')->name('tasks.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\TaskController::class, 'update'])->middleware('rbac:task.update')->name('tasks.update');
            Route::delete('/{id}', [\App\Http\Controllers\Api\TaskController::class, 'destroy'])->middleware('rbac:task.delete')->name('tasks.destroy');
            Route::patch('/{id}/status', [\App\Http\Controllers\Api\TaskController::class, 'updateStatus'])->middleware('rbac:task.update-status')->name('tasks.update-status');
            Route::get('/{id}/dependencies', [\App\Http\Controllers\Api\TaskController::class, 'getDependencies'])->middleware('rbac:task.dependencies.view')->name('tasks.dependencies');
            Route::post('/{id}/dependencies', [\App\Http\Controllers\Api\TaskController::class, 'addDependency'])->middleware('rbac:task.dependencies.add')->name('tasks.add-dependency');
            Route::delete('/{id}/dependencies/{dependencyId}', [\App\Http\Controllers\Api\TaskController::class, 'removeDependency'])->middleware('rbac:task.dependencies.remove')->name('tasks.remove-dependency');
            Route::post('/{id}/escalate-overdue', [\App\Http\Controllers\Api\TaskController::class, 'escalateOverdue'])->middleware('rbac:task.escalate-overdue')->name('tasks.escalate-overdue');
        });

        // RFI (Request for Information) routes
        Route::group(['prefix' => 'rfis'], function () {
            Route::get('/', [\App\Http\Controllers\Api\RfiController::class, 'index'])->middleware('rbac:rfi.view')->name('rfis.index');
            Route::post('/', [\App\Http\Controllers\Api\RfiController::class, 'store'])->middleware('rbac:rfi.create')->name('rfis.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\RfiController::class, 'show'])->middleware('rbac:rfi.view')->name('rfis.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\RfiController::class, 'update'])->middleware('rbac:rfi.edit')->name('rfis.update');
            Route::delete('/{id}', [\App\Http\Controllers\Api\RfiController::class, 'destroy'])->middleware('rbac:rfi.delete')->name('rfis.destroy');
            Route::post('/{id}/assign', [\App\Http\Controllers\Api\RfiController::class, 'assign'])->middleware('rbac:rfi.assign')->name('rfis.assign');
            Route::post('/{id}/respond', [\App\Http\Controllers\Api\RfiController::class, 'respond'])->middleware('rbac:rfi.respond')->name('rfis.respond');
            Route::post('/{id}/close', [\App\Http\Controllers\Api\RfiController::class, 'close'])->middleware('rbac:rfi.close')->name('rfis.close');
            Route::post('/{id}/escalate', [\App\Http\Controllers\Api\RfiController::class, 'escalate'])->middleware('rbac:rfi.escalate')->name('rfis.escalate');
        });

        // Submittals routes
        Route::group(['prefix' => 'submittals'], function () {
            Route::get('/', [\App\Http\Controllers\Api\SubmittalController::class, 'index'])->middleware('rbac:submittal.view')->name('submittals.index');
            Route::post('/', [\App\Http\Controllers\Api\SubmittalController::class, 'store'])->middleware('rbac:submittal.create')->name('submittals.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\SubmittalController::class, 'show'])->middleware('rbac:submittal.view')->name('submittals.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\SubmittalController::class, 'update'])->middleware('rbac:submittal.edit')->name('submittals.update');
            Route::delete('/{id}', [\App\Http\Controllers\Api\SubmittalController::class, 'destroy'])->middleware('rbac:submittal.delete')->name('submittals.destroy');
            Route::post('/{id}/submit', [\App\Http\Controllers\Api\SubmittalController::class, 'submit'])->middleware('rbac:submittal.submit')->name('submittals.submit');
            Route::post('/{id}/review', [\App\Http\Controllers\Api\SubmittalController::class, 'review'])->middleware('rbac:submittal.review')->name('submittals.review');
            Route::post('/{id}/approve', [\App\Http\Controllers\Api\SubmittalController::class, 'approve'])->middleware('rbac:submittal.approve')->name('submittals.approve');
            Route::post('/{id}/reject', [\App\Http\Controllers\Api\SubmittalController::class, 'reject'])->middleware('rbac:submittal.reject')->name('submittals.reject');
        });

        Route::group(['prefix' => 'materials'], function () {
            Route::get('/', [\App\Http\Controllers\Api\MaterialController::class, 'index'])->middleware('rbac:material.view')->name('materials.index');
            Route::post('/', [\App\Http\Controllers\Api\MaterialController::class, 'store'])->middleware('rbac:material.create')->name('materials.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\MaterialController::class, 'show'])->middleware('rbac:material.view')->name('materials.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\MaterialController::class, 'update'])->middleware('rbac:material.update')->name('materials.update');
            Route::delete('/{id}', [\App\Http\Controllers\Api\MaterialController::class, 'destroy'])->middleware('rbac:material.delete')->name('materials.destroy');
        });

        Route::group(['prefix' => 'vendors'], function () {
            Route::get('/', [\App\Http\Controllers\Api\VendorController::class, 'index'])->middleware('rbac:vendor.view')->name('vendors.index');
            Route::post('/', [\App\Http\Controllers\Api\VendorController::class, 'store'])->middleware('rbac:vendor.create')->name('vendors.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\VendorController::class, 'show'])->middleware('rbac:vendor.view')->name('vendors.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\VendorController::class, 'update'])->middleware('rbac:vendor.update')->name('vendors.update');
            Route::delete('/{id}', [\App\Http\Controllers\Api\VendorController::class, 'destroy'])->middleware('rbac:vendor.delete')->name('vendors.destroy');
        });

        Route::group(['prefix' => 'boqs'], function () {
            Route::get('/', [\App\Http\Controllers\Api\BoqController::class, 'index'])->middleware('rbac:boq.view')->name('boqs.index');
            Route::post('/', [\App\Http\Controllers\Api\BoqController::class, 'store'])->middleware('rbac:boq.create')->name('boqs.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\BoqController::class, 'show'])->middleware('rbac:boq.view')->name('boqs.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\BoqController::class, 'update'])->middleware('rbac:boq.update')->name('boqs.update');
            Route::delete('/{id}', [\App\Http\Controllers\Api\BoqController::class, 'destroy'])->middleware('rbac:boq.delete')->name('boqs.destroy');

            Route::get('/{boq}/line-items', [\App\Http\Controllers\Api\BoqLineItemController::class, 'index'])->middleware('rbac:boq.view')->name('boqs.line-items.index');
            Route::post('/{boq}/line-items', [\App\Http\Controllers\Api\BoqLineItemController::class, 'store'])->middleware('rbac:boq.create')->name('boqs.line-items.store');
            Route::get('/{boq}/line-items/{lineItem}', [\App\Http\Controllers\Api\BoqLineItemController::class, 'show'])->middleware('rbac:boq.view')->name('boqs.line-items.show');
            Route::put('/{boq}/line-items/{lineItem}', [\App\Http\Controllers\Api\BoqLineItemController::class, 'update'])->middleware('rbac:boq.update')->name('boqs.line-items.update');
            Route::delete('/{boq}/line-items/{lineItem}', [\App\Http\Controllers\Api\BoqLineItemController::class, 'destroy'])->middleware('rbac:boq.delete')->name('boqs.line-items.destroy');
        });

        // Material Requests (procurement workflow)
        Route::group(['prefix' => 'material-requests'], function () {
            Route::get('/', [\App\Http\Controllers\Api\MaterialRequestController::class, 'index'])->middleware('rbac:material.read')->name('material-requests.index');
            Route::post('/', [\App\Http\Controllers\Api\MaterialRequestController::class, 'store'])->middleware('rbac:material.request')->name('material-requests.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\MaterialRequestController::class, 'show'])->middleware('rbac:material.read')->name('material-requests.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\MaterialRequestController::class, 'update'])->middleware('rbac:material.request')->name('material-requests.update');
            Route::get('/{id}/receipts', [\App\Http\Controllers\Api\MaterialRequestController::class, 'receipts'])->middleware('rbac:material.read')->name('material-requests.receipts');
            Route::post('/{id}/submit', [\App\Http\Controllers\Api\MaterialRequestController::class, 'submit'])->middleware('rbac:material.request')->name('material-requests.submit');
            Route::post('/{id}/approve', [\App\Http\Controllers\Api\MaterialRequestController::class, 'approve'])->middleware('rbac:material.approve')->name('material-requests.approve');
            Route::post('/{id}/reject', [\App\Http\Controllers\Api\MaterialRequestController::class, 'reject'])->middleware('rbac:material.approve')->name('material-requests.reject');
            Route::post('/{id}/fulfill', [\App\Http\Controllers\Api\MaterialRequestController::class, 'fulfill'])->middleware('rbac:material.receive')->name('material-requests.fulfill');
        });

        // Design Item (design-item kanban — spec zena-ops-roadmap Phase 1)
        Route::group(['prefix' => 'design-items'], function () {
            Route::get('/', [\App\Http\Controllers\Api\DesignItemController::class, 'index'])->middleware('rbac:design-item.view')->name('design-items.index');
            Route::post('/', [\App\Http\Controllers\Api\DesignItemController::class, 'store'])->middleware('rbac:design-item.manage')->name('design-items.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\DesignItemController::class, 'show'])->middleware('rbac:design-item.view')->name('design-items.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\DesignItemController::class, 'update'])->middleware('rbac:design-item.manage')->name('design-items.update');
        });

        // CRM (lead inbox → account/opportunity → project; spec crm-zena)
        Route::group(['prefix' => 'crm'], function () {
            Route::get('/leads', [\App\Http\Controllers\Api\LeadController::class, 'index'])->middleware('rbac:crm.view')->name('crm.leads.index');
            Route::post('/leads', [\App\Http\Controllers\Api\LeadController::class, 'store'])->middleware('rbac:crm.manage')->name('crm.leads.store');
            Route::post('/leads/{id}/convert', [\App\Http\Controllers\Api\LeadController::class, 'convert'])->middleware('rbac:crm.manage')->name('crm.leads.convert');
            Route::post('/leads/{id}/discard', [\App\Http\Controllers\Api\LeadController::class, 'discard'])->middleware('rbac:crm.manage')->name('crm.leads.discard');

            Route::get('/accounts', [\App\Http\Controllers\Api\AccountController::class, 'index'])->middleware('rbac:crm.view')->name('crm.accounts.index');
            Route::post('/accounts', [\App\Http\Controllers\Api\AccountController::class, 'store'])->middleware('rbac:crm.manage')->name('crm.accounts.store');
            Route::get('/accounts/{id}', [\App\Http\Controllers\Api\AccountController::class, 'show'])->middleware('rbac:crm.view')->name('crm.accounts.show');
            Route::put('/accounts/{id}', [\App\Http\Controllers\Api\AccountController::class, 'update'])->middleware('rbac:crm.manage')->name('crm.accounts.update');

            Route::get('/opportunities', [\App\Http\Controllers\Api\OpportunityController::class, 'index'])->middleware('rbac:crm.view')->name('crm.opportunities.index');
            Route::post('/opportunities', [\App\Http\Controllers\Api\OpportunityController::class, 'store'])->middleware('rbac:crm.manage')->name('crm.opportunities.store');
            Route::get('/opportunities/{id}', [\App\Http\Controllers\Api\OpportunityController::class, 'show'])->middleware('rbac:crm.view')->name('crm.opportunities.show');
            Route::put('/opportunities/{id}', [\App\Http\Controllers\Api\OpportunityController::class, 'update'])->middleware('rbac:crm.manage')->name('crm.opportunities.update');
            Route::post('/opportunities/{id}/stage', [\App\Http\Controllers\Api\OpportunityController::class, 'updateStage'])->middleware('rbac:crm.manage')->name('crm.opportunities.stage');
            Route::post('/opportunities/{id}/convert', [\App\Http\Controllers\Api\OpportunityController::class, 'convert'])->middleware('rbac:crm.convert')->name('crm.opportunities.convert');
        });

        // Site Diaries (daily site logs)
        Route::group(['prefix' => 'site-diaries'], function () {
            Route::get('/', [\App\Http\Controllers\Api\SiteDiaryController::class, 'index'])->middleware('rbac:site_diary.view')->name('site-diaries.index');
            Route::post('/', [\App\Http\Controllers\Api\SiteDiaryController::class, 'store'])->middleware('rbac:site_diary.create')->name('site-diaries.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\SiteDiaryController::class, 'show'])->middleware('rbac:site_diary.view')->name('site-diaries.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\SiteDiaryController::class, 'update'])->middleware('rbac:site_diary.create')->name('site-diaries.update');
            Route::post('/{id}/submit', [\App\Http\Controllers\Api\SiteDiaryController::class, 'submit'])->middleware('rbac:site_diary.create')->name('site-diaries.submit');
            Route::post('/{id}/approve', [\App\Http\Controllers\Api\SiteDiaryController::class, 'approve'])->middleware('rbac:site_diary.approve')->name('site-diaries.approve');
        });

        // Material Receipts (delivery + acceptance checklist)
        Route::group(['prefix' => 'material-receipts'], function () {
            Route::get('/', [\App\Http\Controllers\Api\MaterialReceiptController::class, 'index'])->middleware('rbac:material-receipt.view')->name('material-receipts.index');
            Route::post('/', [\App\Http\Controllers\Api\MaterialReceiptController::class, 'store'])->middleware('rbac:material-receipt.create')->name('material-receipts.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\MaterialReceiptController::class, 'show'])->middleware('rbac:material-receipt.view')->name('material-receipts.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\MaterialReceiptController::class, 'update'])->middleware('rbac:material-receipt.create')->name('material-receipts.update');
            Route::get('/{id}/material-request', [\App\Http\Controllers\Api\MaterialReceiptController::class, 'materialRequest'])->middleware('rbac:material-receipt.view')->name('material-receipts.material-request');

            Route::group(['prefix' => '/{receipt}/checklists'], function () {
                Route::post('/', [\App\Http\Controllers\Api\MaterialReceiptChecklistController::class, 'store'])->middleware('rbac:material-receipt-checklist.create')->name('material-receipts.checklists.store');
                Route::get('/{checklist}', [\App\Http\Controllers\Api\MaterialReceiptChecklistController::class, 'show'])->middleware('rbac:material-receipt-checklist.view')->name('material-receipts.checklists.show');
            });

            Route::group(['prefix' => '/{receipt}/lines'], function () {
                Route::get('/', [\App\Http\Controllers\Api\MaterialReceiptLineController::class, 'index'])->middleware('rbac:material-receipt-line.view')->name('material-receipts.lines.index');
                Route::post('/', [\App\Http\Controllers\Api\MaterialReceiptLineController::class, 'store'])->middleware('rbac:material-receipt-line.create')->name('material-receipts.lines.store');
                Route::get('/{line}', [\App\Http\Controllers\Api\MaterialReceiptLineController::class, 'show'])->middleware('rbac:material-receipt-line.view')->name('material-receipts.lines.show');
                Route::put('/{line}', [\App\Http\Controllers\Api\MaterialReceiptLineController::class, 'update'])->middleware('rbac:material-receipt-line.create')->name('material-receipts.lines.update');
            });
        });

        // Change Requests routes
        Route::group(['prefix' => 'change-requests'], function () {
            Route::get('/', [\App\Http\Controllers\Api\ChangeRequestController::class, 'index'])->middleware('rbac:change-request.view')->name('change-requests.index');
            Route::post('/', [\App\Http\Controllers\Api\ChangeRequestController::class, 'store'])->middleware('rbac:change-request.create')->name('change-requests.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\ChangeRequestController::class, 'show'])->middleware('rbac:change-request.view')->name('change-requests.show');
            Route::post('/{id}/links', [\App\Http\Controllers\Api\ChangeRequestController::class, 'attachLink'])->middleware('rbac:change-request.update')->name('change-requests.links.attach');
            Route::delete('/{id}/links', [\App\Http\Controllers\Api\ChangeRequestController::class, 'detachLink'])->middleware('rbac:change-request.update')->name('change-requests.links.detach');
            Route::get('/{id}/timeline', [\App\Http\Controllers\Api\ChangeRequestController::class, 'timeline'])->middleware('rbac:change-request.view')->name('change-requests.timeline');
            Route::put('/{id}', [\App\Http\Controllers\Api\ChangeRequestController::class, 'update'])->middleware('rbac:change-request.update')->name('change-requests.update');
            Route::delete('/{id}', [\App\Http\Controllers\Api\ChangeRequestController::class, 'destroy'])->middleware('rbac:change-request.delete')->name('change-requests.destroy');
            Route::post('/{id}/submit', [\App\Http\Controllers\Api\ChangeRequestController::class, 'submit'])->middleware('rbac:change-request.submit')->name('change-requests.submit');
            Route::post('/{id}/approve', [\App\Http\Controllers\Api\ChangeRequestController::class, 'approve'])->middleware('rbac:change-request.approve')->name('change-requests.approve');
            Route::post('/{id}/reject', [\App\Http\Controllers\Api\ChangeRequestController::class, 'reject'])->middleware('rbac:change-request.reject')->name('change-requests.reject');
            Route::post('/{id}/apply', [\App\Http\Controllers\Api\ChangeRequestController::class, 'apply'])->middleware('rbac:change-request.apply')->name('change-requests.apply');
        });

        // Inspections routes
        Route::group(['prefix' => 'inspections'], function () {
            Route::get('/', [\App\Http\Controllers\Api\InspectionController::class, 'index'])->middleware('rbac:inspection.view')->name('inspections.index');
            Route::post('/', [\App\Http\Controllers\Api\InspectionController::class, 'store'])->middleware('rbac:inspection.create')->name('inspections.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\InspectionController::class, 'show'])->middleware('rbac:inspection.view')->name('inspections.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\InspectionController::class, 'update'])->middleware('rbac:inspection.edit')->name('inspections.update');
            Route::delete('/{id}', [\App\Http\Controllers\Api\InspectionController::class, 'destroy'])->middleware('rbac:inspection.delete')->name('inspections.destroy');
            Route::post('/{id}/schedule', [\App\Http\Controllers\Api\InspectionController::class, 'schedule'])->middleware('rbac:inspection.schedule')->name('inspections.schedule');
            Route::post('/{id}/conduct', [\App\Http\Controllers\Api\InspectionController::class, 'conduct'])->middleware('rbac:inspection.conduct')->name('inspections.conduct');
            Route::post('/{id}/complete', [\App\Http\Controllers\Api\InspectionController::class, 'complete'])->middleware('rbac:inspection.complete')->name('inspections.complete');
            Route::get('/{inspection}/ncrs', [\App\Http\Controllers\Api\InspectionController::class, 'listNcrs'])->middleware('rbac:inspection.view')->name('inspections.ncrs.index');
            Route::post('/{inspection}/ncrs', [\App\Http\Controllers\Api\InspectionController::class, 'storeNcr'])->middleware('rbac:inspection.create')->name('inspections.ncrs.store');
            Route::get('/{inspection}/ncrs/{ncr}', [\App\Http\Controllers\Api\InspectionController::class, 'showNcr'])->middleware('rbac:inspection.view')->name('inspections.ncrs.show');
            Route::patch('/{inspection}/ncrs/{ncr}/status', [\App\Http\Controllers\Api\InspectionController::class, 'updateNcrStatus'])->middleware('rbac:inspection.edit')->name('inspections.ncrs.update-status');
        });

        // Safety Incidents routes - DISABLED (Controller not implemented)
        /*
        Route::group(['prefix' => 'safety-incidents'], function () {
            Route::get('/', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'index'])->name('safety-incidents.index');
            Route::post('/', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'store'])->name('safety-incidents.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'show'])->name('safety-incidents.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'update'])->name('safety-incidents.update');
            Route::delete('/{id}', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'destroy'])->name('safety-incidents.destroy');
            Route::post('/{id}/report', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'report'])->name('safety-incidents.report');
            Route::post('/{id}/investigate', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'investigate'])->name('safety-incidents.investigate');
            Route::post('/{id}/resolve', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'resolve'])->name('safety-incidents.resolve');
        });
        */

        // Site Diary routes - DISABLED (Controller not implemented)
        /*
        Route::group(['prefix' => 'site-diary'], function () {
            Route::get('/', [\App\Http\Controllers\Api\SiteDiaryController::class, 'index'])->name('site-diary.index');
            Route::post('/', [\App\Http\Controllers\Api\SiteDiaryController::class, 'store'])->name('site-diary.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\SiteDiaryController::class, 'show'])->name('site-diary.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\SiteDiaryController::class, 'update'])->name('site-diary.update');
            Route::delete('/{id}', [\App\Http\Controllers\Api\SiteDiaryController::class, 'destroy'])->name('site-diary.destroy');
            Route::get('/daily/{date}', [\App\Http\Controllers\Api\SiteDiaryController::class, 'getDailyReport'])->name('site-diary.daily');
            Route::get('/weekly/{week}', [\App\Http\Controllers\Api\SiteDiaryController::class, 'getWeeklyReport'])->name('site-diary.weekly');
        });
        */

        // Document Management routes (using SimpleDocumentController)
        Route::group(['prefix' => 'documents'], function () {
            Route::get('/', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'index'])->middleware('rbac:document.view')->name('documents.index');
            Route::post('/', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'store'])->middleware('rbac:document.create')->name('documents.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'show'])->middleware('rbac:document.view')->name('documents.show');
            Route::post('/{id}/link', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'attachLink'])->middleware('rbac:document.update')->name('documents.link.attach');
            Route::delete('/{id}/link', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'detachLink'])->middleware('rbac:document.update')->name('documents.link.detach');
            Route::get('/{id}/versions', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'getVersions'])->middleware('rbac:document.view')->name('documents.versions.index');
            Route::post('/{id}/versions', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'createVersion'])->middleware('rbac:document.update')->name('documents.versions.store');
            Route::post('/{id}/submit', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'submit'])->middleware('rbac:document.update')->name('documents.submit');
            Route::post('/{id}/decision', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'decision'])->middleware('rbac:document.update')->name('documents.decision');
            Route::put('/{id}', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'update'])->middleware('rbac:document.update')->name('documents.update');
            Route::delete('/{id}', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'destroy'])->middleware('rbac:document.delete')->name('documents.destroy');
        });

        /*
        |--------------------------------------------------------------------------
        | Notification Routes
        |--------------------------------------------------------------------------
        */
        Route::prefix('notifications')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'index'])->middleware('rbac:notification.view')->name('notifications.index');
            Route::post('/', [\App\Http\Controllers\Api\NotificationController::class, 'store'])->middleware('rbac:notification.create')->name('notifications.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'show'])->middleware('rbac:notification.view')->name('notifications.show');
            Route::put('/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead'])->middleware('rbac:notification.read')->name('notifications.mark-read');
            Route::put('/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead'])->middleware('rbac:notification.mark-all-read')->name('notifications.mark-all-read');
            Route::delete('/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy'])->middleware('rbac:notification.delete')->name('notifications.destroy');
            Route::get('/stats/count', [\App\Http\Controllers\Api\NotificationController::class, 'getUnreadCount'])->middleware('rbac:notification.stats')->name('notifications.unread-count');
            Route::get('/stats/summary', [\App\Http\Controllers\Api\NotificationController::class, 'getStats'])->middleware('rbac:notification.stats')->name('notifications.stats');
        });

        // Work instance execution routes
        Route::group(['prefix' => 'work-instances'], function () {
            Route::get('/', [\App\Http\Controllers\Api\WorkInstanceController::class, 'index'])->middleware('rbac:work.view')->name('work-instances.index');
            Route::get('/metrics', [\App\Http\Controllers\Api\WorkInstanceController::class, 'metrics'])->middleware('rbac:work.view')->name('work-instances.metrics');
            Route::patch('/{id}/steps/{stepId}', [\App\Http\Controllers\Api\WorkInstanceController::class, 'updateStep'])->middleware('rbac:work.update')->name('work-instances.steps.update');
            Route::post('/{id}/steps/{stepId}/approve', [\App\Http\Controllers\Api\WorkInstanceController::class, 'approveStep'])->middleware('rbac:work.approve')->name('work-instances.steps.approve');
            Route::get('/{id}/steps/{stepId}/attachments', [\App\Http\Controllers\Api\WorkInstanceController::class, 'listStepAttachments'])->middleware('rbac:work.view')->name('work-instances.steps.attachments.index');
            Route::post('/{id}/steps/{stepId}/attachments', [\App\Http\Controllers\Api\WorkInstanceController::class, 'uploadStepAttachment'])->middleware('rbac:work.update')->name('work-instances.steps.attachments.store');
            Route::delete('/{id}/steps/{stepId}/attachments/{attachmentId}', [\App\Http\Controllers\Api\WorkInstanceController::class, 'deleteStepAttachment'])->middleware('rbac:work.update')->name('work-instances.steps.attachments.destroy');
            Route::post('/{id}/export-bundle', [\App\Http\Controllers\Api\WorkInstanceController::class, 'exportBundle'])->middleware('rbac:work.export')->name('work-instances.export-bundle');
            Route::post('/{id}/export', [\App\Http\Controllers\Api\WorkInstanceController::class, 'exportDeliverable'])->middleware('rbac:work.export')->name('work-instances.export');
        });

        // Template package import/export routes
        Route::get('/export-template-package/{wtId}', [\App\Http\Controllers\Api\WorkTemplateController::class, 'exportTemplatePackage'])->middleware('rbac:template.view')->name('work-templates.package.export');
        Route::post('/import-template-package', [\App\Http\Controllers\Api\WorkTemplateController::class, 'importTemplatePackage'])->middleware('rbac:template.edit_draft')->name('work-templates.package.import');

        // Alert taxonomy routes (S6.2)
        Route::prefix('alerts')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\AlertController::class, 'index'])->middleware('rbac:alert.view')->name('alerts.index');
            Route::get('/{id}', [\App\Http\Controllers\Api\AlertController::class, 'show'])->middleware('rbac:alert.view')->name('alerts.show');
            Route::put('/{id}/read', [\App\Http\Controllers\Api\AlertController::class, 'markAsRead'])->middleware('rbac:alert.read')->name('alerts.mark-read');
        });

        // Event record outbox routes (S6.3)
        Route::prefix('event-records')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\EventRecordController::class, 'index'])->middleware('rbac:event-record.view')->name('event-records.index');
            Route::get('/{id}', [\App\Http\Controllers\Api\EventRecordController::class, 'show'])->middleware('rbac:event-record.view')->name('event-records.show');
        });
    });
});
