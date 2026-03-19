<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleOwnershipRouteInvariantTest extends TestCase
{
    public function test_zena_business_modules_stay_owned_by_app_api_controllers(): void
    {
        $expected = [
            'api.zena.projects.index' => 'App\\Http\\Controllers\\Api\\ProjectController',
            'api.zena.projects.show' => 'App\\Http\\Controllers\\Api\\ProjectController',
            'api.zena.tasks.index' => 'App\\Http\\Controllers\\Api\\TaskController',
            'api.zena.tasks.show' => 'App\\Http\\Controllers\\Api\\TaskController',
            'api.zena.documents.index' => 'App\\Http\\Controllers\\Api\\SimpleDocumentController',
            'api.zena.documents.show' => 'App\\Http\\Controllers\\Api\\SimpleDocumentController',
            'api.zena.change-requests.index' => 'App\\Http\\Controllers\\Api\\ChangeRequestController',
            'api.zena.change-requests.show' => 'App\\Http\\Controllers\\Api\\ChangeRequestController',
            'api.zena.notifications.index' => 'App\\Http\\Controllers\\Api\\NotificationController',
            'api.zena.notifications.show' => 'App\\Http\\Controllers\\Api\\NotificationController',
            'api.zena.rfis.index' => 'App\\Http\\Controllers\\Api\\RfiController',
            'api.zena.submittals.index' => 'App\\Http\\Controllers\\Api\\SubmittalController',
            'api.zena.inspections.index' => 'App\\Http\\Controllers\\Api\\InspectionController',
            'api.zena.work-templates.index' => 'App\\Http\\Controllers\\Api\\WorkTemplateController',
            'api.zena.work-instances.index' => 'App\\Http\\Controllers\\Api\\WorkInstanceController',
            'api.zena.deliverable-templates.index' => 'App\\Http\\Controllers\\Api\\DeliverableTemplateController',
        ];

        foreach ($expected as $routeName => $controller) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, sprintf('Expected route [%s] to exist.', $routeName));
            $this->assertSame(
                $controller,
                $this->controllerFromRoute($route),
                sprintf('Route [%s] drifted away from controller [%s].', $routeName, $controller)
            );
        }
    }

    public function test_v1_compatibility_modules_keep_their_current_runtime_owner_families(): void
    {
        $expectedByUri = [
            'api/v1/projects' => 'Src\\CoreProject\\Controllers\\ProjectController',
            'api/v1/tasks' => 'Src\\CoreProject\\Controllers\\TaskController',
            'api/v1/work-templates' => 'Src\\CoreProject\\Controllers\\WorkTemplateController',
            'api/v1/change-requests' => 'Src\\ChangeRequest\\Controllers\\ChangeRequestController',
            'api/v1/notifications' => 'Src\\Notification\\Controllers\\NotificationController',
            'api/v1/notification-rules' => 'Src\\Notification\\Controllers\\NotificationRuleController',
            'api/v1/projects/{project}/contracts' => 'App\\Http\\Controllers\\Api\\ContractController',
            'api/v1/contracts/{contract}/payments' => 'App\\Http\\Controllers\\Api\\ContractPaymentController',
            'api/v1/documents' => 'App\\Http\\Controllers\\Api\\SimpleDocumentController',
        ];

        foreach ($expectedByUri as $uri => $controller) {
            $route = $this->routeByUri($uri);

            $this->assertNotNull($route, sprintf('Expected route uri [%s] to exist.', $uri));
            $this->assertSame(
                $controller,
                $this->controllerFromRoute($route),
                sprintf('Compatibility route [%s] drifted away from controller [%s].', $uri, $controller)
            );
        }
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

    private function controllerFromRoute(RoutingRoute $route): string
    {
        $action = $route->getActionName();

        return explode('@', $action)[0];
    }
}
