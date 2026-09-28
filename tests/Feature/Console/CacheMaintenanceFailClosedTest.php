<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CacheMaintenanceFailClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_task_refuses_and_mutates_nothing(): void
    {
        Cache::put('gap054:probe', 'kept', 600);
        RateLimiter::hit('gap054-login-probe', 60);

        $exit = Artisan::call('maintenance:run', ['--task' => 'cache']);

        $this->assertNotSame(0, $exit);
        $this->assertSame('kept', Cache::get('gap054:probe'));
        $this->assertSame(1, RateLimiter::attempts('gap054-login-probe'));
        $this->assertStringContainsString('no maintenance-owned cache namespace', Artisan::output());
        $this->assertDatabaseMissing('maintenance_tasks', ['task' => 'Clear application cache']);
    }

    public function test_all_task_does_not_touch_the_cache(): void
    {
        Cache::put('gap054:probe', 'kept', 600);

        Artisan::call('maintenance:run', ['--task' => 'all']);

        $this->assertSame('kept', Cache::get('gap054:probe'));
        $this->assertDatabaseMissing('maintenance_tasks', ['task' => 'Clear application cache']);
    }
}
