<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
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

        // Cache Maintenance
        $schedule->command('maintenance:run --task=cache')
            ->dailyAt('02:00')
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

        // Queue Health Check
        $schedule->command('queue:monitor')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Queue Restart
        $schedule->command('queue:restart')
            ->hourly()
            ->withoutOverlapping();

        // Session Cleanup
        $schedule->command('session:gc')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        // Cache Optimization
        $schedule->command('cache:optimize')
            ->dailyAt('09:00')
            ->withoutOverlapping();

        // Route Cache
        $schedule->command('route:cache')
            ->dailyAt('10:00')
            ->withoutOverlapping();

        // View Cache
        $schedule->command('view:cache')
            ->dailyAt('11:00')
            ->withoutOverlapping();

        // Config Cache
        $schedule->command('config:cache')
            ->dailyAt('12:00')
            ->withoutOverlapping();
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
