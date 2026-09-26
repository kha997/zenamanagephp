<?php

namespace App\Console;

use App\Console\Commands\BackfillInvitationTokenHash;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * @var array<int, class-string>
     */
    protected $commands = [
        BackfillInvitationTokenHash::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        if (!config('app.enable_scheduler', false)) {
            return;
        }

        // System Health Monitoring
        $schedule->command('maintenance:run --task=metrics')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Database Optimization
        $schedule->command('maintenance:run --task=database')
            ->weekly()
            ->sundays()
            ->at('03:00')
            ->withoutOverlapping();

        // Log Cleanup
        $schedule->command('maintenance:run --task=logs')
            ->dailyAt('04:00')
            ->withoutOverlapping();

        // System Backup
        $schedule->command('backup:run --type=all')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Database Backup
        $schedule->command('backup:run --type=database')
            ->everySixHours()
            ->withoutOverlapping()
            ->runInBackground();

        // Queue Restart
        $schedule->command('queue:restart')
            ->hourly()
            ->withoutOverlapping();

        // Compiled config/route/view caches are built by the deploy step
        // (.github/workflows/production.yml), never on a schedule. The
        // application cache store is never flushed on a schedule: it holds
        // rate-limit counters, OIDC state and these overlap locks (GAP-054).
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
