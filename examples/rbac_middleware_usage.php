<?php declare(strict_types=1);

/**
 * Ví dụ sử dụng RBAC Middleware trong Laravel Routes
 * 
 * File này minh họa cách áp dụng các middleware RBAC
 * vào các API endpoints khác nhau
 */

// Trong file routes/api.php của Laravel:

use Illuminate\Support\Facades\Route;
use zenamanage\RBAC\Middleware\RBACMiddleware;
use zenamanage\RBAC\Middleware\ProjectContextMiddleware;
use zenamanage\RBAC\Middleware\AdminOnlyMiddleware;
use zenamanage\RBAC\Middleware\TenantIsolationMiddleware;

// Group với tenant isolation cho tất cả API
Route::middleware([TenantIsolationMiddleware::class])->prefix('api/v1')->group(function () {
    
    // System Admin only endpoints
    Route::middleware([AdminOnlyMiddleware::class])->prefix('admin')->group(function () {
        Route::get('/users', 'AdminController@listAllUsers');
        Route::post('/tenants', 'AdminController@createTenant');
        Route::get('/system-stats', 'AdminController@getSystemStats');
    });
    
    // User management endpoints
    Route::middleware([RBACMiddleware::class . ':user.view'])->group(function () {
        Route::get('/users', 'UserController@index');
        Route::get('/users/{user_id}', 'UserController@show');
    });
    
    Route::middleware([RBACMiddleware::class . ':user.create'])->group(function () {
        Route::post('/users', 'UserController@store');
    });
    
    Route::middleware([RBACMiddleware::class . ':user.update'])->group(function () {
        Route::put('/users/{user_id}', 'UserController@update');
    });
    
    Route::middleware([RBACMiddleware::class . ':user.delete'])->group(function () {
        Route::delete('/users/{user_id}', 'UserController@destroy');
    });
    
    // Project-specific endpoints
    Route::prefix('projects/{project_id}')->middleware([
        ProjectContextMiddleware::class . ':project_id'
    ])->group(function () {
        
        // Project view
        Route::middleware([RBACMiddleware::class . ':project.view,project_id'])
            ->get('/', 'ProjectController@show');
        
        // Project update
        Route::middleware([RBACMiddleware::class . ':project.update,project_id'])
            ->put('/', 'ProjectController@update');
        
        // Task management
        Route::middleware([RBACMiddleware::class . ':task.view,project_id'])
            ->get('/tasks', 'TaskController@index');
        
        Route::middleware([RBACMiddleware::class . ':task.create,project_id'])
            ->post('/tasks', 'TaskController@store');
        
        Route::middleware([RBACMiddleware::class . ':task.update,project_id'])
            ->put('/tasks/{task_id}', 'TaskController@update');
        
        Route::middleware([RBACMiddleware::class . ':task.delete,project_id'])
            ->delete('/tasks/{task_id}', 'TaskController@destroy');
        
        // Component management
        Route::middleware([RBACMiddleware::class . ':component.view,project_id'])
            ->get('/components', 'ComponentController@index');
        
        Route::middleware([RBACMiddleware::class . ':component.create,project_id'])
            ->post('/components', 'ComponentController@store');
        
        // Document management
        Route::middleware([RBACMiddleware::class . ':document.view,project_id'])
            ->get('/documents', 'DocumentController@index');
        
        Route::middleware([RBACMiddleware::class . ':document.upload,project_id'])
            ->post('/documents', 'DocumentController@upload');
        
        // Change Request management
        Route::middleware([RBACMiddleware::class . ':cr.view,project_id'])
            ->get('/change-requests', 'ChangeRequestController@index');
        
        Route::middleware([RBACMiddleware::class . ':cr.create,project_id'])
            ->post('/change-requests', 'ChangeRequestController@store');
        
        Route::middleware([RBACMiddleware::class . ':cr.approve,project_id'])
            ->post('/change-requests/{cr_id}/approve', 'ChangeRequestController@approve');
    });
    
    // RBAC management endpoints
    Route::prefix('rbac')->group(function () {
        
        // Role management
        Route::middleware([RBACMiddleware::class . ':rbac.role.view'])
            ->get('/roles', 'RoleController@index');
        
        Route::middleware([RBACMiddleware::class . ':rbac.role.create'])
            ->post('/roles', 'RoleController@store');
        
        Route::middleware([RBACMiddleware::class . ':rbac.role.update'])
            ->put('/roles/{role_id}', 'RoleController@update');
        
        // Permission management
        Route::middleware([RBACMiddleware::class . ':rbac.permission.view'])
            ->get('/permissions', 'PermissionController@index');
        
        // Assignment management
        Route::middleware([RBACMiddleware::class . ':rbac.assignment.manage'])
            ->post('/assignments/system', 'AssignmentController@assignSystemRole');
        
        Route::middleware([RBACMiddleware::class . ':rbac.assignment.manage,project_id'])
            ->post('/assignments/project', 'AssignmentController@assignProjectRole');
        
        // Permission Matrix
        Route::middleware([RBACMiddleware::class . ':rbac.matrix.export'])
            ->get('/matrix/export', 'PermissionMatrixController@export');
        
        Route::middleware([RBACMiddleware::class . ':rbac.matrix.import'])
            ->post('/matrix/import', 'PermissionMatrixController@import');
    });
});

/**
 * Ví dụ sử dụng trong Controller
 */
class TaskController
{
    use \zenamanage\RBAC\Traits\HasRBACContext;
    
    public function store(Request $request)
    {
        // Get current user and project context
        $userId = $this->getCurrentUserId($request);
        $projectId = $this->getCurrentProjectId($request);
        $tenantId = $this->getCurrentTenantId($request);
        
        // Validate request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date'
        ]);
        
        // Create task with context
        $task = [
            'id' => \Ulid\Ulid::generate(),
            'project_id' => $projectId,
            'tenant_id' => $tenantId,
            'created_by' => $userId,
            'updated_by' => $userId,
            ...$validated,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString()
        ];
        
        // Save to database...
        
        return response()->json([
            'status' => 'success',
            'data' => $task
        ], 201);
    }
    
    public function update(Request $request, string $taskId)
    {
        // Check if user can update this specific task
        if (!$this->hasPermission($request, 'task.update.own')) {
            // If not own task, check general update permission
            if (!$this->hasPermission($request, 'task.update')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions'
                ], 403);
            }
        }
        
        // Continue with update logic...
    }
}