<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\TestCase;

class DeploymentProcessConfigInvariantTest extends TestCase
{
    public function test_supervisor_does_not_start_horizon_without_horizon_package(): void
    {
        $composer = json_decode($this->readProjectFile('composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $requires = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);
        $supervisor = $this->readProjectFile('docker/supervisor/supervisord.conf');

        $usesHorizon = str_contains($supervisor, 'artisan horizon');

        $this->assertFalse(
            $usesHorizon && !array_key_exists('laravel/horizon', $requires),
            'Supervisor must not start php artisan horizon unless composer.json requires laravel/horizon.'
        );
    }

    public function test_production_queue_processes_use_queue_work_without_horizon(): void
    {
        $supervisor = $this->readProjectFile('docker/supervisor/supervisord.conf');
        $compose = $this->readProjectFile('docker-compose.prod.yml');

        $this->assertStringContainsString('artisan queue:work --sleep=3 --tries=3 --max-time=3600', $supervisor);
        $this->assertStringContainsString('php artisan queue:work --sleep=3 --tries=3 --max-time=3600', $compose);
        $this->assertStringNotContainsString('artisan horizon', $supervisor);
    }

    private function readProjectFile(string $path): string
    {
        $absolutePath = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.$path;

        $this->assertFileExists($absolutePath);

        return (string) file_get_contents($absolutePath);
    }
}
