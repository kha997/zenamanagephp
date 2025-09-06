<?php declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\InteractionLogController; // Thêm dòng này
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
| Public Health Check Routes
|--------------------------------------------------------------------------
*/

// Basic API health check
Route::get('/health', function () {
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
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::post('check-permission', [AuthController::class, 'checkPermission']);
        });
    });
    
    /*
    |--------------------------------------------------------------------------
    | Protected API Routes
    |--------------------------------------------------------------------------
    */
    // Thêm import ở đầu file
    use App\Http\Controllers\Api\InteractionLogController;
    
    Route::middleware('auth:api')->group(function () {
        
        // User Management Routes
        Route::apiResource('users', UserController::class);
        Route::prefix('users')->group(function () {
            Route::get('profile', [UserController::class, 'profile']);
            Route::put('profile', [UserController::class, 'updateProfile']);
        });
        
        // Project Routes
        Route::apiResource('projects', ProjectController::class);
        Route::prefix('projects/{projectId}')->group(function () {
            Route::get('tasks', [TaskController::class, 'index']);
            Route::post('recalculate-progress', [ProjectController::class, 'recalculateProgress']);
            Route::post('recalculate-actual-cost', [ProjectController::class, 'recalculateActualCost']);
        });
        
        // Component Routes
        Route::apiResource('components', ComponentController::class);
        
        // Task Routes
        Route::apiResource('tasks', TaskController::class);
        Route::apiResource('task-assignments', TaskAssignmentController::class);
        
        // Work Template Routes
        Route::apiResource('work-templates', WorkTemplateController::class);
        Route::prefix('work-templates')->group(function () {
            Route::post('{templateId}/apply', [WorkTemplateController::class, 'applyToProject']);
            Route::get('meta/categories', [WorkTemplateController::class, 'categories']);
            Route::get('meta/conditional-tags', [WorkTemplateController::class, 'conditionalTags']);
            Route::post('{templateId}/duplicate', [WorkTemplateController::class, 'duplicate']);
        });
        
        // Baseline Routes
        Route::prefix('projects/{projectId}')->group(function () {
            Route::apiResource('baselines', BaselineController::class)->except(['index']);
            Route::get('baselines', [BaselineController::class, 'index']);
            Route::post('baselines/from-current', [BaselineController::class, 'createFromCurrent']);
            Route::get('baselines/report', [BaselineController::class, 'report']);
        });
        
        // Standalone baseline routes
        Route::apiResource('baselines', BaselineController::class)->only(['show', 'update', 'destroy']);
        Route::get('baselines/{id1}/compare/{id2}', [BaselineController::class, 'compare']);
        
        // Template Routes
        Route::apiResource('templates', TemplateController::class)
            ->parameters(['templates' => 'id']);
        Route::prefix('templates')->group(function () {
            Route::post('{id}/apply', [TemplateController::class, 'apply']);
            Route::get('{id}/versions', [TemplateController::class, 'versions']);
        });
        
        // Protected routes với full middleware stack
        Route::middleware(['jwt.auth', 'tenant.isolation', 'api.rate.limit:general'])->group(function () {
        
        // User management routes
        Route::middleware(['rbac:user.view'])->get('/users', [UserController::class, 'index']);
        Route::middleware(['rbac:user.create'])->post('/users', [UserController::class, 'store']);
        
        // Project routes
        Route::middleware(['rbac:project.view'])->get('/projects', [ProjectController::class, 'index']);
        Route::middleware(['rbac:project.create'])->post('/projects', [ProjectController::class, 'store']);
        
        // Interaction logs với rate limiting đặc biệt
        Route::middleware(['api.rate.limit:general'])->group(function () {
            Route::middleware(['rbac:interaction_log.view'])->get('/interaction-logs', [InteractionLogController::class, 'index']);
            Route::middleware(['rbac:interaction_log.create'])->post('/interaction-logs', [InteractionLogController::class, 'store']);
            Route::middleware(['rbac:interaction_log.approve'])->patch('/interaction-logs/{id}/approve', [InteractionLogController::class, 'approve']);
        });
        
        // File upload với rate limiting nghiêm ngặt
        Route::middleware(['api.rate.limit:upload'])->group(function () {
            Route::middleware(['rbac:file.upload'])->post('/files/upload', [FileController::class, 'upload']);
        });
        
        // Export với rate limiting rất nghiêm ngặt
        Route::middleware(['api.rate.limit:export'])->group(function () {
            Route::middleware(['rbac:project.export'])->get('/projects/{id}/export', [ProjectController::class, 'export']);
            Route::middleware(['rbac:interaction_log.export'])->get('/interaction-logs/export', [InteractionLogController::class, 'export']);
        });
        });
        
        // Project-specific Interaction Log Routes
        Route::prefix('projects/{projectId}')->group(function () {
            Route::get('interaction-logs', [InteractionLogController::class, 'indexByProject']);
            Route::get('interaction-logs/client-visible', [InteractionLogController::class, 'clientVisible']);
        });
        
        // Task-specific Interaction Log Routes
        Route::prefix('tasks/{taskId}')->group(function () {
            Route::get('interaction-logs', [InteractionLogController::class, 'indexByTask']);
        });
    });
});
