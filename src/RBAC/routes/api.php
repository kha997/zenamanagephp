<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\RBAC\Controllers\AuthController;
use Src\RBAC\Controllers\RoleController;
use Src\RBAC\Controllers\PermissionController;
use Src\RBAC\Controllers\AssignmentController;
use Src\RBAC\Controllers\PermissionMatrixController;
use Src\RBAC\Controllers\RBACController;

/*
|--------------------------------------------------------------------------
| RBAC API Routes
|--------------------------------------------------------------------------
*/

// Authentication routes (không cần middleware)
Route::prefix('api/v1/auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    
    // Routes cần authentication
    Route::middleware(['auth:api'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('check-permission', [AuthController::class, 'checkPermission']);
    });
});

// RBAC management routes (cần authentication và permissions)
Route::middleware(['auth:api', 'rbac'])->prefix('api/v1/rbac')->group(function () {
    // Role management
    Route::apiResource('roles', RoleController::class);
    Route::post('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
    Route::get('roles/by-scope', [RBACController::class, 'getRolesByScope']);
    
    // Permission management
    Route::apiResource('permissions', PermissionController::class);
    Route::get('permissions/hierarchy', [RBACController::class, 'getPermissionHierarchy']);
    
    // Permission Matrix CSV import/export
    Route::prefix('permission-matrix')->group(function () {
        Route::get('export', [PermissionMatrixController::class, 'export']);
        Route::post('import', [PermissionMatrixController::class, 'import']);
        Route::post('validate', [PermissionMatrixController::class, 'validateCsv']);
        Route::get('template', [PermissionMatrixController::class, 'getTemplate']);
    });
    
    // User effective permissions
    Route::get('users/{user}/effective-permissions', [RBACController::class, 'getUserEffectivePermissions']);
    Route::post('users/{user}/check-permission', [RBACController::class, 'checkUserPermission']);
    
    // Bulk operations
    Route::post('bulk-assign-roles', [RBACController::class, 'bulkAssignRoles']);
    
    // Audit log
    Route::get('audit-log', [RBACController::class, 'getAuditLog']);
    
    // Assignment management
    Route::prefix('assignments')->group(function () {
        Route::get('users/{user}/roles', [AssignmentController::class, 'getUserRoles']);
        Route::post('users/{user}/roles', [AssignmentController::class, 'assignUserRoles']);
        Route::delete('users/{user}/roles/{role}', [AssignmentController::class, 'removeUserRole']);
        
        Route::get('projects/{project}/users', [AssignmentController::class, 'getProjectUsers']);
        Route::post('projects/{project}/users/{user}/roles', [AssignmentController::class, 'assignProjectRole']);
        Route::delete('projects/{project}/users/{user}/roles/{role}', [AssignmentController::class, 'removeProjectRole']);
    });
});