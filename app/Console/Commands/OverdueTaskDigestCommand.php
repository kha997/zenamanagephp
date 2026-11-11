<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reminders\OverdueTaskDigestService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * GAP-124 (Gate 2 O-1) — daily overdue-task digest (in-app to each project's PM, one summary to the company's Slack
 * channel). Scheduled daily at 08:00 Asia/Ho_Chi_Minh (Console\Kernel, behind ENABLE_SCHEDULER). --dry-run lists the
 * projects without writing or posting; --date runs for another day.
 */
class OverdueTaskDigestCommand extends Command
{
    protected $signature = 'reminders:overdue-tasks
        {--dry-run : List the overdue tasks per project without writing or posting}
        {--date= : Run as if today were this date (YYYY-MM-DD)}';

    protected $description = 'Daily overdue-task digest: in-app notification to each project PM and one summary to the company Slack channel';

    public function handle(OverdueTaskDigestService $digest): int
    {
        $dateOption = $this->option('date');
        if (is_string($dateOption) && $dateOption !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateOption);
            if ($parsed === false || $parsed->format('Y-m-d') !== $dateOption) {
                $this->error('--date must be YYYY-MM-DD.');

                return self::INVALID;
            }
            $today = Carbon::instance($parsed);
        } else {
            $today = Carbon::now('Asia/Ho_Chi_Minh')->startOfDay();
        }

        $plan = $digest->plan($today);
        foreach ($plan as $project) {
            $this->line("{$project['project_name']}: {$project['count']}" . ($project['pm_id'] === null ? ' (no PM)' : ''));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: ' . count($plan) . ' project(s) with overdue tasks on ' . $today->toDateString() . ', nothing written or posted.');

            return self::SUCCESS;
        }

        $result = $digest->send($today);
        $this->info("{$result['notifications']} notification(s) written, {$result['slack_messages']} Slack message(s) posted for " . $today->toDateString() . '.');

        return self::SUCCESS;
    }
}
