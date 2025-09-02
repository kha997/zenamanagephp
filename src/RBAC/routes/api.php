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
Route::prefix('v1/auth')->group(function () {
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

// RBAC management routes (chỉ cần rbac middleware)
Route::prefix('v1/rbac')->group(function () {
    // Role management
    Route::get('roles', [RoleController::class, 'index'])->middleware('rbac:role.view');
    Route::post('roles', [RoleController::class, 'store'])->middleware('rbac:role.create');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('rbac:role.view');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('rbac:role.edit');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('rbac:role.delete');
    Route::post('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('rbac:role.edit');
    Route::get('roles/by-scope', [RBACController::class, 'getRolesByScope'])->middleware('rbac:role.view');
    
    // Permission management
    Route::get('permissions', [PermissionController::class, 'index'])->middleware('rbac:permission.view');
    Route::post('permissions', [PermissionController::class, 'store'])->middleware('rbac:permission.create');
    Route::get('permissions/{permission}', [PermissionController::class, 'show'])->middleware('rbac:permission.view');
    Route::put('permissions/{permission}', [PermissionController::class, 'update'])->middleware('rbac:permission.edit');
    Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('rbac:permission.delete');
    Route::get('permissions/hierarchy', [RBACController::class, 'getPermissionHierarchy'])->middleware('rbac:permission.view');
    
    // Permission Matrix CSV import/export
    Route::prefix('permission-matrix')->group(function () {
        Route::get('export', [PermissionMatrixController::class, 'export'])->middleware('rbac:permission.export');
        Route::post('import', [PermissionMatrixController::class, 'import'])->middleware('rbac:permission.import');
        Route::post('validate', [PermissionMatrixController::class, 'validateCsv'])->middleware('rbac:permission.import');
        Route::get('template', [PermissionMatrixController::class, 'getTemplate'])->middleware('rbac:permission.view');
    });
    
    // User effective permissions
    Route::get('users/{user}/effective-permissions', [RBACController::class, 'getUserEffectivePermissions'])->middleware('rbac:user.view');
    Route::post('users/{user}/check-permission', [RBACController::class, 'checkUserPermission'])->middleware('rbac:user.view');
    
    // Bulk operations
    Route::post('bulk-assign-roles', [RBACController::class, 'bulkAssignRoles'])->middleware('rbac:role.assign');
    
    // Audit log
    Route::get('audit-log', [RBACController::class, 'getAuditLog'])->middleware('rbac:audit.view');
    
    // Assignment management
    Route::prefix('assignments')->group(function () {
        Route::get('users/{user}/roles', [AssignmentController::class, 'getUserRoles'])->middleware('rbac:user.view');
        Route::post('users/{user}/roles', [AssignmentController::class, 'assignUserRoles'])->middleware('rbac:role.assign');
        Route::delete('users/{user}/roles/{role}', [AssignmentController::class, 'removeUserRole'])->middleware('rbac:role.assign');
        
        Route::get('projects/{project}/users', [AssignmentController::class, 'getProjectUsers'])->middleware('rbac:project.view');
        Route::post('projects/{project}/users/{user}/roles', [AssignmentController::class, 'assignProjectRole'])->middleware('rbac:role.assign');
        Route::delete('projects/{project}/users/{user}/roles/{role}', [AssignmentController::class, 'removeProjectRole'])->middleware('rbac:role.assign');
    });
    
    // User-roles routes for test compatibility
    Route::post('user-roles', [AssignmentController::class, 'assignUserRoles'])->middleware('rbac:role.assign');
    Route::delete('user-roles/{user}/{role}', [AssignmentController::class, 'removeUserRole'])->middleware('rbac:role.assign');
});