<?php

namespace Tests\Unit\GAP055;

use App\Services\LaunchChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * GAP-055: pre-launch work (also reached by GET launch-report) must not flush
 * the shared cache store. Since GAP-057 it is read-only readiness and runs no
 * Artisan command at all, which is strictly stronger. Artisan is mocked.
 */
final class PreLaunchActionsNoCacheFlushTest extends TestCase
{
    use RefreshDatabase;

    public function test_pre_launch_readiness_never_calls_cache_clear_or_any_command(): void
    {
        $called = [];
        Artisan::shouldReceive('call')->andReturnUsing(function (string $command) use (&$called) {
            $called[] = $command;

            return 0;
        });

        app(LaunchChecklistService::class)->getPreLaunchReadiness();

        $this->assertNotContains('cache:clear', $called);
        $this->assertSame([], $called);
    }
}
