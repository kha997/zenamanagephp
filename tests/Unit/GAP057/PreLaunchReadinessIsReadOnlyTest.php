<?php

namespace Tests\Unit\GAP057;

use App\Services\LaunchChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * GAP-057: pre-launch readiness is reported, never performed.
 */
final class PreLaunchReadinessIsReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_reports_state_without_running_artisan(): void
    {
        $called = [];
        Artisan::shouldReceive('call')->andReturnUsing(function (string $command) use (&$called) {
            $called[] = $command;

            return 0;
        });

        $readiness = app(LaunchChecklistService::class)->getPreLaunchReadiness();

        $this->assertSame([], $called);
        $this->assertSame(0, $readiness['pending_migrations']);
        $this->assertIsBool($readiness['config_cached']);
        $this->assertIsBool($readiness['routes_cached']);
        $this->assertStringContainsString('deploy:migrate', $readiness['performed_by']);
    }

    public function test_the_executing_pre_launch_method_no_longer_exists(): void
    {
        $this->assertFalse(method_exists(LaunchChecklistService::class, 'executePreLaunchActions'));
    }
}
