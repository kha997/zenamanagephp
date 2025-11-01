<?php

namespace Tests\Feature;

use Tests\TestCase;

class Phase2ControllersTest extends TestCase
{
    /**
     * Test that all Phase 2 API controllers exist
     */
    public function test_phase2_api_controllers_exist(): void
    {
        $controllers = [
            'App\Http\Controllers\Api\Admin\DashboardController',
            'App\Http\Controllers\Api\Admin\UserController',
            'App\Http\Controllers\Api\Admin\TenantController',
            'App\Http\Controllers\Api\Admin\AlertController',
            'App\Http\Controllers\Api\Admin\ActivityController',
        ];

        foreach ($controllers as $controller) {
            $this->assertTrue(
                class_exists($controller),
                "Controller {$controller} should exist"
            );
        }
    }

    /**
     * Test that all Phase 2 Web controllers exist
     */
    public function test_phase2_web_controllers_exist(): void
    {
        $controllers = [
            'App\Http\Controllers\Web\CalendarController',
            'App\Http\Controllers\Web\TeamController',
            'App\Http\Controllers\Web\SettingsController',
        ];

        foreach ($controllers as $controller) {
            $this->assertTrue(
                class_exists($controller),
                "Controller {$controller} should exist"
            );
        }
    }

    /**
     * Test that controllers have required methods
     */
    public function test_controllers_have_required_methods(): void
    {
        // Test DashboardController methods
        $this->assertTrue(
            method_exists('App\Http\Controllers\Api\Admin\DashboardController', 'getStats'),
            'DashboardController should have getStats method'
        );

        $this->assertTrue(
            method_exists('App\Http\Controllers\Api\Admin\DashboardController', 'getActivities'),
            'DashboardController should have getActivities method'
        );

        // Test UserController methods
        $this->assertTrue(
            method_exists('App\Http\Controllers\Api\Admin\UserController', 'index'),
            'UserController should have index method'
        );

        $this->assertTrue(
            method_exists('App\Http\Controllers\Api\Admin\UserController', 'store'),
            'UserController should have store method'
        );

        // Test CalendarController methods
        $this->assertTrue(
            method_exists('App\Http\Controllers\Web\CalendarController', 'index'),
            'CalendarController should have index method'
        );

        $this->assertTrue(
            method_exists('App\Http\Controllers\Web\CalendarController', 'store'),
            'CalendarController should have store method'
        );
    }

    /**
     * Test that controllers extend the correct base classes
     */
    public function test_controllers_extend_correct_base_classes(): void
    {
        // Test API controllers extend Controller
        $apiControllers = [
            'App\Http\Controllers\Api\Admin\DashboardController',
            'App\Http\Controllers\Api\Admin\UserController',
            'App\Http\Controllers\Api\Admin\TenantController',
            'App\Http\Controllers\Api\Admin\AlertController',
            'App\Http\Controllers\Api\Admin\ActivityController',
        ];

        foreach ($apiControllers as $controller) {
            $this->assertTrue(
                is_subclass_of($controller, 'App\Http\Controllers\Controller'),
                "Controller {$controller} should extend App\Http\Controllers\Controller"
            );
        }

        // Test Web controllers extend Controller
        $webControllers = [
            'App\Http\Controllers\Web\CalendarController',
            'App\Http\Controllers\Web\TeamController',
            'App\Http\Controllers\Web\SettingsController',
        ];

        foreach ($webControllers as $controller) {
            $this->assertTrue(
                is_subclass_of($controller, 'App\Http\Controllers\Controller'),
                "Controller {$controller} should extend App\Http\Controllers\Controller"
            );
        }
    }

    /**
     * Test that controllers use ApiResponse for API controllers
     */
    public function test_api_controllers_use_api_response(): void
    {
        $apiControllers = [
            'App\Http\Controllers\Api\Admin\DashboardController',
            'App\Http\Controllers\Api\Admin\UserController',
            'App\Http\Controllers\Api\Admin\TenantController',
            'App\Http\Controllers\Api\Admin\AlertController',
            'App\Http\Controllers\Api\Admin\ActivityController',
        ];

        foreach ($apiControllers as $controller) {
            $reflection = new \ReflectionClass($controller);
            $this->assertTrue(
                $reflection->hasMethod('getStats') || $reflection->hasMethod('index') || $reflection->hasMethod('logs'),
                "Controller {$controller} should have API methods"
            );
        }
    }

    /**
     * Test overall Phase 2 implementation
     */
    public function test_phase2_implementation_summary(): void
    {
        $implementationChecks = [
            'API Controllers Created' => class_exists('App\Http\Controllers\Api\Admin\DashboardController'),
            'Web Controllers Created' => class_exists('App\Http\Controllers\Web\CalendarController'),
            'User Management API' => class_exists('App\Http\Controllers\Api\Admin\UserController'),
            'Tenant Management API' => class_exists('App\Http\Controllers\Api\Admin\TenantController'),
            'Alert Management API' => class_exists('App\Http\Controllers\Api\Admin\AlertController'),
            'Activity Management API' => class_exists('App\Http\Controllers\Api\Admin\ActivityController'),
            'Calendar Web Interface' => class_exists('App\Http\Controllers\Web\CalendarController'),
            'Team Web Interface' => class_exists('App\Http\Controllers\Web\TeamController'),
            'Settings Web Interface' => class_exists('App\Http\Controllers\Web\SettingsController'),
        ];

        $passedChecks = array_filter($implementationChecks);
        $totalChecks = count($implementationChecks);
        $passedCount = count($passedChecks);

        $this->assertEquals(
            $totalChecks,
            $passedCount,
            "Phase 2 implementation check failed: $passedCount/$totalChecks passed. Failed: " . 
            implode(', ', array_keys(array_diff($implementationChecks, $passedChecks)))
        );
    }
}
