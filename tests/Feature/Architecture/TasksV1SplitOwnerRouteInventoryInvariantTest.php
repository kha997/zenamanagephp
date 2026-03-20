<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Http\Controllers\Api\TaskAssignmentController as AppTaskAssignmentController;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionParameter;
use Src\CoreProject\Controllers\TaskAssignmentController as CoreTaskAssignmentController;
use Src\CoreProject\Controllers\TaskController as CoreTaskController;
use Src\WorkTemplate\Controllers\ProjectTaskController;
use Tests\TestCase;

class TasksV1SplitOwnerRouteInventoryInvariantTest extends TestCase
{
    public function test_mounted_v1_task_related_owner_map_stays_explicit(): void
    {
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskController@index',
            $this->routeByUriAndMethod('api/v1/tasks', 'GET')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskController@store',
            $this->routeByUriAndMethod('api/v1/tasks', 'POST')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskController@show',
            $this->routeByUriAndMethod('api/v1/tasks/{task}', 'GET')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskAssignmentController@index',
            $this->routeByUriAndMethod('api/v1/task-assignments', 'GET')?->getActionName()
        );
        $this->assertSame(
            'App\\Http\\Controllers\\Api\\TaskAssignmentController@getTaskAssignments',
            $this->routeByUriAndMethod('api/v1/dashboard/tasks/{taskId}/assignments', 'GET')?->getActionName()
        );
        $this->assertSame(
            'App\\Http\\Controllers\\Api\\TaskAssignmentController@getUserStats',
            $this->routeByUriAndMethod('api/v1/dashboard/users/{userId}/assignments/stats', 'GET')?->getActionName()
        );
        $this->assertSame(
            'Src\\WorkTemplate\\Controllers\\ProjectTaskController@index',
            $this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/tasks', 'GET')?->getActionName()
        );

        // Present in routes/api.php source, but absent from the mounted route collection.
        $this->assertNull($this->routeByUriAndMethod('api/v1/tasks/{task}/status', 'PATCH'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/tasks/{task}/assign', 'POST'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/tasks/{task}/assign-team', 'POST'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/tasks/{task}/dependencies', 'GET'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/tasks/{task}/dependencies', 'POST'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/tasks/{task}/dependencies/{dependencyId}', 'POST'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/tasks/{task}/dependencies/{dependencyId}', 'DELETE'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/tasks/{task}/watchers', 'GET'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/tasks/{task}/watchers', 'POST'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/tasks/{task}/watchers', 'DELETE'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/tasks/statistics', 'GET'));
    }

    public function test_reconciled_task_and_assignment_signatures_match_flat_mounted_routes(): void
    {
        $this->assertSame(
            [],
            $this->nonRequestParameterNames(CoreTaskController::class, 'index')
        );
        $this->assertSame(
            [],
            $this->routeParameterNames('api/v1/tasks')
        );

        $this->assertSame(
            ['task'],
            $this->nonRequestParameterNames(CoreTaskController::class, 'show')
        );
        $this->assertSame(
            ['task'],
            $this->nonRequestParameterNames(CoreTaskController::class, 'update')
        );
        $this->assertSame(
            ['task'],
            $this->nonRequestParameterNames(CoreTaskController::class, 'destroy')
        );
        $this->assertSame(
            ['task'],
            $this->routeParameterNames('api/v1/tasks/{task}')
        );

        $this->assertSame(
            [],
            $this->nonRequestParameterNames(CoreTaskAssignmentController::class, 'index')
        );
        $this->assertSame(
            [],
            $this->routeParameterNames('api/v1/task-assignments')
        );

        $this->assertSame(
            ['taskAssignment'],
            $this->nonRequestParameterNames(CoreTaskAssignmentController::class, 'show')
        );
        $this->assertSame(
            ['taskAssignment'],
            $this->routeParameterNames('api/v1/task-assignments/{taskAssignment}')
        );

        $this->assertSame(
            ['taskAssignment'],
            $this->nonRequestParameterNames(CoreTaskAssignmentController::class, 'update')
        );
        $this->assertSame(
            ['taskAssignment'],
            $this->routeParameterNames('api/v1/task-assignments/{taskAssignment}')
        );

        $this->assertSame(
            ['taskAssignment'],
            $this->nonRequestParameterNames(CoreTaskAssignmentController::class, 'destroy')
        );
        $this->assertSame(
            ['taskAssignment'],
            $this->routeParameterNames('api/v1/task-assignments/{taskAssignment}')
        );

        $this->assertSame(
            ['taskId'],
            $this->nonRequestParameterNames(AppTaskAssignmentController::class, 'getTaskAssignments')
        );
        $this->assertSame(
            ['taskId'],
            $this->routeParameterNames('api/v1/dashboard/tasks/{taskId}/assignments')
        );
        $this->assertSame(
            IlluminateRequest::class,
            $this->requestParameterType(AppTaskAssignmentController::class, 'store')
        );
        $this->assertSame(
            ['taskId'],
            $this->nonRequestParameterNames(AppTaskAssignmentController::class, 'store')
        );

        $this->assertSame(
            IlluminateRequest::class,
            $this->requestParameterType(AppTaskAssignmentController::class, 'update')
        );
        $this->assertSame(
            ['assignmentId'],
            $this->nonRequestParameterNames(AppTaskAssignmentController::class, 'update')
        );
        $this->assertSame(
            ['assignmentId'],
            $this->routeParameterNames('api/v1/dashboard/assignments/{assignmentId}')
        );
        $this->assertSame(
            ['assignmentId'],
            $this->nonRequestParameterNames(AppTaskAssignmentController::class, 'destroy')
        );

        $this->assertTrue(method_exists(AppTaskAssignmentController::class, 'getUserAssignments'));
        $this->assertTrue(method_exists(AppTaskAssignmentController::class, 'getUserStats'));
        $this->assertSame(
            ['userId'],
            $this->nonRequestParameterNames(AppTaskAssignmentController::class, 'getUserStats')
        );
    }

    public function test_work_template_projection_boundary_and_removed_broken_declarations_stay_explicit(): void
    {
        $this->assertSame(
            ['projectId'],
            $this->nonRequestParameterNames(ProjectTaskController::class, 'index')
        );
        $this->assertSame(
            ['projectId', 'taskId'],
            $this->nonRequestParameterNames(ProjectTaskController::class, 'show')
        );
        $this->assertSame(
            ['projectId', 'taskId'],
            $this->nonRequestParameterNames(ProjectTaskController::class, 'update')
        );
        $this->assertSame(
            ['projectId', 'taskId'],
            $this->routeParameterNames('api/v1/work-template/projects/{projectId}/tasks/{taskId}')
        );

        $this->assertTrue(class_exists('Src\\WorkTemplate\\Requests\\UpdateTaskRequest'));
        $this->assertFalse(class_exists('Src\\WorkTemplate\\Resources\\ProjectTaskCollection'));

        $this->assertTrue(method_exists(ProjectTaskController::class, 'conditionalTasks'));
        $this->assertTrue(method_exists(ProjectTaskController::class, 'toggleConditional'));
        $this->assertTrue(method_exists(ProjectTaskController::class, 'updateProgress'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'errorResponse'));
        $this->assertSame(
            'Src\\WorkTemplate\\Controllers\\ProjectTaskController@conditionalTasks',
            $this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/tasks/conditional', 'GET')?->getActionName()
        );
        $this->assertSame(
            'Src\\WorkTemplate\\Controllers\\ProjectTaskController@toggleConditional',
            $this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/tasks/{taskId}/toggle-conditional', 'POST')?->getActionName()
        );
        $this->assertSame(
            'Src\\WorkTemplate\\Controllers\\ProjectTaskController@updateProgress',
            $this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/tasks/{taskId}/progress', 'PUT')?->getActionName()
        );

        $this->assertFalse(method_exists(ProjectTaskController::class, 'store'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'destroy'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'updateStatus'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'bulkUpdate'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'bulkToggleConditional'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'getConditionalTags'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'getConditionalTagStats'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'toggleConditionalTag'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'bulkToggleConditionalTags'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'getPhases'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'getPhaseTask'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'reorderPhase'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'getProgressReport'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'getTasksSummary'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'getConditionalUsageReport'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'getTemplateDiff'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'partialSync'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'applyTemplateDiff'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'searchTasks'));

        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/tasks', 'POST'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/tasks/{taskId}', 'DELETE'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/tasks/{taskId}/status', 'PUT'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/tasks/bulk-update', 'POST'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/tasks/bulk-toggle-conditional', 'POST'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/phases', 'GET'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/phases/{phaseId}/tasks', 'GET'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/phases/{phaseId}/reorder', 'PUT'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/conditional-tags', 'GET'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/conditional-tags/statistics', 'GET'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/conditional-tags/{tag}/toggle', 'POST'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/conditional-tags/bulk-toggle', 'POST'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/template-sync/partial', 'POST'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/template-sync/diff', 'GET'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/template-sync/apply-diff', 'POST'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/reports/progress', 'GET'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/reports/tasks-summary', 'GET'));
        $this->assertNull($this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/reports/conditional-usage', 'GET'));
    }

    private function routeByUriAndMethod(string $uri, string $method): ?RoutingRoute
    {
        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                return $route;
            }
        }

        return null;
    }

    private function routeParameterNames(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        return $matches[1] ?? [];
    }

    private function nonRequestParameterNames(string $class, string $method): array
    {
        $reflection = new ReflectionClass($class);
        $methodReflection = $reflection->getMethod($method);

        return array_values(array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName(),
            array_filter(
                $methodReflection->getParameters(),
                static fn (ReflectionParameter $parameter): bool => !self::isFrameworkRequestParameter($parameter)
            )
        ));
    }

    private static function isFrameworkRequestParameter(ReflectionParameter $parameter): bool
    {
        return $parameter->getName() === 'request';
    }

    private function requestParameterType(string $class, string $method): ?string
    {
        $reflection = new ReflectionClass($class);
        $methodReflection = $reflection->getMethod($method);

        foreach ($methodReflection->getParameters() as $parameter) {
            if ($parameter->getName() !== 'request') {
                continue;
            }

            return $parameter->getType()?->getName();
        }

        return null;
    }
}
