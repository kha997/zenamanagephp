<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Src\WorkTemplate\Controllers\ProjectTaskController;
use Tests\TestCase;

class TasksContractParityAuditInvariantTest extends TestCase
{
    public function test_task_route_surfaces_keep_their_current_owner_counts_and_method_sets(): void
    {
        $zenaRoutes = $this->routesByPrefix('api/zena/tasks');
        $v1CrudRoutes = $this->routesByPrefix('api/v1/tasks');

        $this->assertCount(9, $zenaRoutes);
        $this->assertSame([
            'DELETE api/zena/tasks/{id}',
            'DELETE api/zena/tasks/{id}/dependencies/{dependencyId}',
            'GET|HEAD api/zena/tasks',
            'GET|HEAD api/zena/tasks/{id}',
            'GET|HEAD api/zena/tasks/{id}/dependencies',
            'PATCH api/zena/tasks/{id}/status',
            'POST api/zena/tasks',
            'POST api/zena/tasks/{id}/dependencies',
            'PUT api/zena/tasks/{id}',
        ], $this->methodUriPairs($zenaRoutes));

        $this->assertCount(6, array_values(array_filter(
            $v1CrudRoutes,
            static fn (RoutingRoute $route): bool => str_starts_with($route->getActionName(), 'Src\\CoreProject\\Controllers\\TaskController@')
        )));
        $this->assertSame([
            'DELETE api/v1/tasks/{task}',
            'GET|HEAD api/v1/tasks',
            'GET|HEAD api/v1/tasks/{task}',
            'PATCH api/v1/tasks/{task}',
            'POST api/v1/tasks',
            'PUT api/v1/tasks/{task}',
        ], $this->methodUriPairs(array_values(array_filter(
            $v1CrudRoutes,
            static fn (RoutingRoute $route): bool => str_starts_with($route->getActionName(), 'Src\\CoreProject\\Controllers\\TaskController@')
        ))));

        $apiRoutesSource = file_get_contents(base_path('routes/api.php'));

        $this->assertIsString($apiRoutesSource);
        $this->assertStringNotContainsString("Route::patch('{task}/status', [TaskController::class, 'updateStatus']);", $apiRoutesSource);
        $this->assertStringNotContainsString("Route::get('{task}/dependencies', [TaskController::class, 'getDependencies']);", $apiRoutesSource);
        $this->assertStringNotContainsString("Route::get('{task}/watchers', [TaskController::class, 'getWatchers']);", $apiRoutesSource);
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskAssignmentController@index',
            $this->routeByUri('api/v1/task-assignments')?->getActionName()
        );
    }

    public function test_task_route_middleware_and_guard_drift_stays_explicit(): void
    {
        $zenaIndex = Route::getRoutes()->getByName('api.zena.tasks.index');
        $v1Index = $this->routeByUri('api/v1/tasks');

        $this->assertNotNull($zenaIndex);
        $this->assertNotNull($v1Index);

        $this->assertContains('auth:sanctum', $zenaIndex->middleware());
        $this->assertContains('input.sanitization', $zenaIndex->middleware());
        $this->assertContains('error.envelope', $zenaIndex->middleware());
        $this->assertContains('rbac:task.view', $zenaIndex->middleware());

        $this->assertContains('auth:api', $v1Index->middleware());
        $this->assertContains('rbac:task.view', $v1Index->middleware());
        $this->assertNotContains('input.sanitization', $v1Index->middleware());
        $this->assertNotContains('error.envelope', $v1Index->middleware());
    }

    public function test_v1_task_controller_signatures_stay_mounted_and_reconciled_in_source(): void
    {
        $controller = new \ReflectionClass(\Src\CoreProject\Controllers\TaskController::class);

        $index = $controller->getMethod('index');
        $show = $controller->getMethod('show');
        $update = $controller->getMethod('update');
        $destroy = $controller->getMethod('destroy');

        $this->assertSame(['request'], array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $index->getParameters()
        ));
        $this->assertSame(['task'], array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $show->getParameters()
        ));
        $this->assertSame(['request', 'task'], array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $update->getParameters()
        ));
        $this->assertSame(['task'], array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $destroy->getParameters()
        ));

        $this->assertSame('Src\\CoreProject\\Controllers\\TaskController@index', $this->routeByUri('api/v1/tasks')?->getActionName());
        $this->assertSame('Src\\CoreProject\\Controllers\\TaskController@show', $this->routeByUri('api/v1/tasks/{task}')?->getActionName());
    }

    public function test_work_template_projection_shrink_state_remains_documented(): void
    {
        $this->assertTrue(class_exists('Src\\WorkTemplate\\Requests\\UpdateTaskRequest'));
        $this->assertFalse(class_exists('Src\\WorkTemplate\\Resources\\ProjectTaskCollection'));

        $this->assertFalse(method_exists(ProjectTaskController::class, 'store'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'destroy'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'updateStatus'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'getConditionalTags'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'getPhases'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'reorderPhase'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'getConditionalTagStats'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'toggleConditionalTag'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'bulkToggleConditionalTags'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'partialSync'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'getTemplateDiff'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'applyTemplateDiff'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'searchTasks'));

        $this->assertSame(
            'Src\\WorkTemplate\\Controllers\\ProjectTaskController@index',
            $this->routeByUri('api/v1/work-template/projects/{projectId}/tasks')?->getActionName()
        );
        $this->assertSame(
            'Src\\WorkTemplate\\Controllers\\ProjectTaskController@conditionalTasks',
            $this->routeByUri('api/v1/work-template/projects/{projectId}/tasks/conditional')?->getActionName()
        );
        $this->assertSame(
            'Src\\WorkTemplate\\Controllers\\ProjectTaskController@toggleConditional',
            $this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/tasks/{taskId}/toggle-conditional', 'POST')?->getActionName()
        );
        $this->assertSame(
            'Src\\WorkTemplate\\Controllers\\ProjectTaskController@updateProgress',
            $this->routeByUriAndMethod('api/v1/work-template/projects/{projectId}/tasks/{taskId}/progress', 'PUT')?->getActionName()
        );
        $this->assertTrue(method_exists(ProjectTaskController::class, 'updateProgress'));
        $this->assertFalse(method_exists(ProjectTaskController::class, 'errorResponse'));

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

    private function routesByPrefix(string $prefix): array
    {
        $matches = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), $prefix)) {
                $matches[] = $route;
            }
        }

        usort(
            $matches,
            static fn (RoutingRoute $left, RoutingRoute $right): int => strcmp(
                $left->methods()[0] . ' ' . $left->uri(),
                $right->methods()[0] . ' ' . $right->uri()
            )
        );

        return $matches;
    }

    private function methodUriPairs(array $routes): array
    {
        return array_map(
            static function (RoutingRoute $route): string {
                $methods = $route->methods();
                if (in_array('GET', $methods, true)) {
                    $methods = array_values(array_filter($methods, static fn (string $method): bool => $method !== 'HEAD'));
                    return implode('|', array_merge($methods, ['HEAD'])) . ' ' . $route->uri();
                }

                return implode('|', $methods) . ' ' . $route->uri();
            },
            $routes
        );
    }

    private function routeByUri(string $uri): ?RoutingRoute
    {
        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri) {
                return $route;
            }
        }

        return null;
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
}
