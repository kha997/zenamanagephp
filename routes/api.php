<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use Src\CoreProject\Controllers\ProjectController;
use Src\CoreProject\Controllers\ComponentController;
use Src\CoreProject\Controllers\TaskController;
use Src\CoreProject\Controllers\TaskAssignmentController;
use Src\CoreProject\Controllers\WorkTemplateController;
use Src\CoreProject\Controllers\BaselineController;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Core Project API Routes (v1)
|--------------------------------------------------------------------------
|
| RESTful API routes cho module CoreProject với RBAC middleware
| Tất cả routes được prefix với /api/v1/ theo chuẩn JSend
|
*/

Route::prefix('v1')->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | User Management Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('users', UserController::class);
    
    // Profile routes
    Route::prefix('users')->group(function () {
        Route::get('profile', [UserController::class, 'profile']);
        Route::put('profile', [UserController::class, 'updateProfile']);
    });
    
    /*
    |--------------------------------------------------------------------------
    | Project Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('projects', ProjectController::class);
    
    // Additional project routes
    Route::prefix('projects/{projectId}')->group(function () {
        Route::post('recalculate-progress', [ProjectController::class, 'recalculateProgress']);
        Route::post('recalculate-actual-cost', [ProjectController::class, 'recalculateActualCost']);
    });
    
    /*
    |--------------------------------------------------------------------------
    | Component Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('components', ComponentController::class);
    
    /*
    |--------------------------------------------------------------------------
    | Task Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('tasks', TaskController::class);
    
    /*
    |--------------------------------------------------------------------------
    | Task Assignment Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('task-assignments', TaskAssignmentController::class);
    
    /*
    |--------------------------------------------------------------------------
    | Work Template Routes
    |--------------------------------------------------------------------------
    */
    // Work Templates routes
    Route::apiResource('work-templates', WorkTemplateController::class);
    Route::post('work-templates/{templateId}/apply', [WorkTemplateController::class, 'applyToProject']);
    Route::get('work-templates/meta/categories', [WorkTemplateController::class, 'categories']);
    Route::get('work-templates/meta/conditional-tags', [WorkTemplateController::class, 'conditionalTags']);
    Route::post('work-templates/{templateId}/duplicate', [WorkTemplateController::class, 'duplicate']);
    
    /*
    |--------------------------------------------------------------------------
    | Baseline Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('projects/{projectId}')->group(function () {
        Route::apiResource('baselines', BaselineController::class)->except(['index']);
        Route::get('baselines', [BaselineController::class, 'index']);
        Route::post('baselines/from-current', [BaselineController::class, 'createFromCurrent']);
        Route::get('baselines/report', [BaselineController::class, 'report']);
    });
    
    // Standalone baseline routes
    Route::apiResource('baselines', BaselineController::class)->only(['show', 'update', 'destroy']);
    Route::get('baselines/{id1}/compare/{id2}', [BaselineController::class, 'compare']);
    
    /*
    |--------------------------------------------------------------------------
    | API Documentation & Health Check Routes
    |--------------------------------------------------------------------------
    */
    Route::get('health', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'service' => 'Z.E.N.A Project Management API',
                'version' => '1.0.0',
                'timestamp' => now()->toISOString(),
                'environment' => app()->environment()
            ]
        ]);
    });
    
    Route::get('api-info', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'api_version' => 'v1',
                'modules' => [
                    'users' => 'User Management with RBAC',
                    'projects' => 'Core Project Management',
                    'components' => 'Project Components',
                    'tasks' => 'Task Management with Dependencies',
                    'assignments' => 'Task Assignments',
                    'work_templates' => 'Work Templates'
                ],
                'authentication' => 'JWT with RBAC',
                'response_format' => 'JSend Specification'
            ]
        ]);
    });
});

// File management routes
Route::middleware(['auth:api'])->group(function () {
    Route::prefix('files')->group(function () {
        Route::post('/upload', [\Src\Foundation\Controllers\FileController::class, 'upload']);
        Route::delete('/delete', [\Src\Foundation\Controllers\FileController::class, 'delete']);
        Route::get('/info', [\Src\Foundation\Controllers\FileController::class, 'info']);
        Route::get('/download/{disk}/{path}', [\Src\Foundation\Controllers\FileController::class, 'download'])
            ->name('files.download');
    });
});
