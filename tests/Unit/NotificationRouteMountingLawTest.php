<?php declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NotificationRouteMountingLawTest extends TestCase
{
    public function test_routes_api_explicitly_mounts_notification_module_routes(): void
    {
        $routesApi = file_get_contents(dirname(__DIR__, 2) . '/routes/api.php');

        $this->assertIsString($routesApi);
        $this->assertStringContainsString(
            "require base_path('src/Notification/routes/api.php');",
            $routesApi,
            'Notification module routes must be mounted explicitly from routes/api.php.'
        );
    }

    public function test_notification_service_provider_does_not_auto_mount_routes(): void
    {
        $provider = file_get_contents(dirname(__DIR__, 2) . '/src/Notification/Providers/NotificationServiceProvider.php');

        $this->assertIsString($provider);
        $this->assertStringNotContainsString(
            'loadRoutesFrom(',
            $provider,
            'NotificationServiceProvider must not auto-mount routes.'
        );
    }
}
