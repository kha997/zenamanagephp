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
use Src\WorkTemplate\Controllers\TemplateController;
use Src\RBAC\Controllers\AuthController;

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

/*
|--------------------------------------------------------------------------
| Public Test Routes
|--------------------------------------------------------------------------
*/

// Basic API health check
Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Laravel API is working!',
        'timestamp' => now()->toISOString(),
        'version' => app()->version()
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
            'timestamp' => now()->toISOString()
        ]
    ]);
});

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Authentication Routes (Public)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);
        
        // Protected auth routes
        Route::middleware('auth:api')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('check-permission', [AuthController::class, 'checkPermission']);
        });
    });
    
    /*
    |--------------------------------------------------------------------------
    | JWT Test Routes
    |--------------------------------------------------------------------------
    */
    
    // Test JWT authentication
    Route::middleware('auth:api')->get('jwt-test', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'message' => 'JWT Authentication working!',
            'data' => [
                'user_id' => $request->user('api')->id,
                'user_name' => $request->user('api')->name,
                'user_email' => $request->user('api')->email,
                'tenant_id' => $request->user('api')->tenant_id,
                'authenticated_at' => now()->toISOString()
            ]
        ]);
    });
    
    // Test JWT with user details
    Route::middleware('auth:api')->get('user-profile', function (Request $request) {
        $user = $request->user('api');
        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ],
                'tenant' => $user->tenant ? [
                    'id' => $user->tenant->id,
                    'name' => $user->tenant->name,
                    'domain' => $user->tenant->domain
                ] : null,
                'request_time' => now()->toISOString()
            ]
        ]);
    });
    
    /*
    |--------------------------------------------------------------------------
    | Health Check Routes
    |--------------------------------------------------------------------------
    */
    Route::get('health', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'service' => 'Z.E.N.A Project Management API',
                'version' => '1.0.0',
                'timestamp' => now()->toISOString(),
                'environment' => app()->environment(),
                'laravel_version' => app()->version()
            ]
        ]);
    });
    
    /*
    |--------------------------------------------------------------------------
    | User Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:api')->group(function () {
        Route::apiResource('users', UserController::class);
        
        // Profile routes
        Route::prefix('users')->group(function () {
            Route::get('profile', [UserController::class, 'profile']);
            Route::put('profile', [UserController::class, 'updateProfile']);
        });
    });
    
    /*
    |--------------------------------------------------------------------------
    | Project Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:api')->group(function () {
        Route::apiResource('projects', ProjectController::class);
        
        // Project-specific routes
        Route::prefix('projects/{projectId}')->group(function () {
            Route::get('tasks', [TaskController::class, 'index']);
            Route::post('recalculate-progress', [ProjectController::class, 'recalculateProgress']);
            Route::post('recalculate-actual-cost', [ProjectController::class, 'recalculateActualCost']);
        });
    });
    
    /*
    |--------------------------------------------------------------------------
    | Component Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:api')->group(function () {
        Route::apiResource('components', ComponentController::class);
    });
    
    /*
    |--------------------------------------------------------------------------
    | Task Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:api')->group(function () {
        Route::apiResource('tasks', TaskController::class);
        Route::apiResource('task-assignments', TaskAssignmentController::class);
    });
    
    /*
    |--------------------------------------------------------------------------
    | Work Template Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:api')->group(function () {
        Route::apiResource('work-templates', WorkTemplateController::class);
        Route::post('work-templates/{templateId}/apply', [WorkTemplateController::class, 'applyToProject']);
        Route::get('work-templates/meta/categories', [WorkTemplateController::class, 'categories']);
        Route::get('work-templates/meta/conditional-tags', [WorkTemplateController::class, 'conditionalTags']);
        Route::post('work-templates/{templateId}/duplicate', [WorkTemplateController::class, 'duplicate']);
    });
    
    /*
    |--------------------------------------------------------------------------
    | Baseline Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:api')->group(function () {
        Route::prefix('projects/{projectId}')->group(function () {
            Route::apiResource('baselines', BaselineController::class)->except(['index']);
            Route::get('baselines', [BaselineController::class, 'index']);
            Route::post('baselines/from-current', [BaselineController::class, 'createFromCurrent']);
            Route::get('baselines/report', [BaselineController::class, 'report']);
        });
        
        // Standalone baseline routes
        Route::apiResource('baselines', BaselineController::class)->only(['show', 'update', 'destroy']);
        Route::get('baselines/{id1}/compare/{id2}', [BaselineController::class, 'compare']);
    });
    
    /*
    |--------------------------------------------------------------------------
    | Template Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:api')->group(function () {
        Route::apiResource('templates', TemplateController::class)
            ->parameters(['templates' => 'id']);
        Route::post('templates/{id}/apply', [TemplateController::class, 'apply']);
        Route::get('templates/{id}/versions', [TemplateController::class, 'versions']);
    });
});
