<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use Tests\TestCase;

class ScheduleContractTest extends TestCase
{
    /** @return list<string> scheduled artisan command lines, e.g. "backup:run --type=all" */
    private function scheduledCommands(): array
    {
        config(['app.enable_scheduler' => true]);
        $schedule = new Schedule();
        $kernel = $this->app->make(ConsoleKernel::class);
        $method = new ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        return array_values(array_map(static function (Event $event): string {
            $command = (string) $event->command;
            $pos = strpos($command, 'artisan');
            $tail = $pos === false ? $command : substr($command, $pos + strlen('artisan'));

            return trim(str_replace(["'", '"'], '', $tail));
        }, $schedule->events()));
    }

    public function test_every_scheduled_command_is_registered(): void
    {
        $registered = array_keys(Artisan::all());

        foreach ($this->scheduledCommands() as $line) {
            $name = strtok($line, ' ');
            $this->assertContains($name, $registered, "scheduled command '{$name}' is not registered");
        }
    }

    public function test_schedule_contains_no_cache_flush_or_compiled_cache_rebuild(): void
    {
        $commands = $this->scheduledCommands();

        foreach (['maintenance:run --task=cache', 'route:cache', 'view:cache', 'config:cache', 'session:gc', 'cache:optimize'] as $forbidden) {
            $this->assertNotContains($forbidden, $commands, "schedule must not contain '{$forbidden}'");
        }
    }

    public function test_schedule_contains_no_argumentless_queue_monitor(): void
    {
        $this->assertNotContains('queue:monitor', $this->scheduledCommands());
    }

    public function test_approved_workloads_remain_scheduled(): void
    {
        $this->assertSame([
            'maintenance:run --task=metrics',
            'maintenance:run --task=database',
            'maintenance:run --task=logs',
            'backup:run --type=all',
            'backup:run --type=database',
            'queue:restart',
        ], $this->scheduledCommands());
    }

    public function test_scheduler_disabled_registers_nothing(): void
    {
        config(['app.enable_scheduler' => false]);
        $schedule = new Schedule();
        $kernel = $this->app->make(ConsoleKernel::class);
        $method = new ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $this->assertSame([], $schedule->events());
    }
}
