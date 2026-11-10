<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reminders\MonthEndReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * GAP-120 (Gate 2 R-1) — month-end reminders. Scheduled daily at 08:00 Asia/Ho_Chi_Minh (Console\Kernel, behind
 * ENABLE_SCHEDULER). --dry-run lists who would get what without writing; --date runs for another day.
 */
class MonthEndRemindersCommand extends Command
{
    protected $signature = 'reminders:month-end
        {--dry-run : List the reminders without writing them}
        {--date= : Run as if today were this date (YYYY-MM-DD)}';

    protected $description = 'Send month-end reminders (time review, payroll close, overhead close) as in-app notifications';

    public function handle(MonthEndReminderService $reminders): int
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

        $plan = $reminders->plan($today);
        $this->table(
            ['Tenant', 'Recipient', 'Type', 'Title', 'Body'],
            array_map(static fn (array $r): array => [$r['tenant_id'], $r['user_name'], $r['type'], $r['title'], $r['body']], $plan),
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run: ' . count($plan) . ' reminder(s) planned for ' . $today->toDateString() . ', nothing written.');

            return self::SUCCESS;
        }

        $written = $reminders->send($today);
        $this->info($written . ' reminder(s) written for ' . $today->toDateString() . ' (' . (count($plan) - $written) . ' already sent today).');

        return self::SUCCESS;
    }
}
