<?php declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class WorkTemplateRouteMountingLawTest extends TestCase
{
    public function test_routes_api_explicitly_mounts_work_template_module_routes(): void
    {
        $routesApi = file_get_contents(dirname(__DIR__, 2) . '/routes/api.php');

        $this->assertIsString($routesApi);
        $this->assertStringContainsString(
            "require base_path('src/WorkTemplate/routes/api.php');",
            $routesApi,
            'WorkTemplate module routes must be mounted explicitly from routes/api.php.'
        );
    }

    public function test_work_template_service_provider_does_not_auto_mount_routes(): void
    {
        $provider = file_get_contents(dirname(__DIR__, 2) . '/src/WorkTemplate/Providers/WorkTemplateServiceProvider.php');

        $this->assertIsString($provider);
        $this->assertStringNotContainsString(
            'group(base_path(\'src/WorkTemplate/routes/api.php\'))',
            $provider,
            'WorkTemplateServiceProvider must not auto-mount routes.'
        );
        $this->assertStringNotContainsString(
            'loadRoutesFrom(',
            $provider,
            'WorkTemplateServiceProvider must not auto-mount routes.'
        );
    }
}
