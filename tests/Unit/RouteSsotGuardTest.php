<?php declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RouteSsotGuardTest extends TestCase
{
    public function test_root_routes_api_v1_file_is_not_present(): void
    {
        $legacyRootFile = dirname(__DIR__, 2) . '/routes/api_v1.php';

        $this->assertFileDoesNotExist(
            $legacyRootFile,
            'routes/api_v1.php must stay quarantined to avoid SSOT drift.'
        );
    }

    public function test_route_service_provider_does_not_reference_api_v1_file(): void
    {
        $providerPath = dirname(__DIR__, 2) . '/app/Providers/RouteServiceProvider.php';
        $providerContent = file_get_contents($providerPath);

        $this->assertNotFalse($providerContent, 'Unable to read RouteServiceProvider.php');
        $this->assertStringNotContainsString(
            'api_v1.php',
            $providerContent,
            'RouteServiceProvider must not load routes/api_v1.php.'
        );
    }
}
