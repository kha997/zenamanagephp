<?php

namespace Tests\Unit\GAP055;

use App\Services\LaunchChecklistService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * GAP-055: pre-launch actions (also run by GET launch-report) must not flush
 * the shared cache store. Artisan is mocked so no command really runs.
 */
final class PreLaunchActionsNoCacheFlushTest extends TestCase
{
    public function test_pre_launch_actions_never_call_cache_clear(): void
    {
        $called = [];
        Artisan::shouldReceive('call')->andReturnUsing(function (string $command) use (&$called) {
            $called[] = $command;

            return 0;
        });

        app(LaunchChecklistService::class)->executePreLaunchActions();

        $this->assertNotContains('cache:clear', $called);
        $this->assertSame(
            ['config:clear', 'route:clear', 'optimize', 'config:cache', 'route:cache', 'migrate'],
            $called
        );
    }
}
